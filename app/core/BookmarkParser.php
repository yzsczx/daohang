<?php
// dh/app/core/BookmarkParser.php
declare(strict_types=1);

class BookmarkParser {

    public static function importToDatabase(string $htmlFilePath, int $userId, PDO $db, int $targetPageId, int $targetColumnId): array {
        // 读取文件内容
        $htmlContent = file_get_contents($htmlFilePath);
        if ($htmlContent === false) throw new Exception("无法读取书签文件内容。");

        error_log("BookmarkParser: 读取到书签文件内容，长度: " . strlen($htmlContent) . " 字节");

        // 保存原始内容的一部分到日志，用于调试
        $contentPreview = substr($htmlContent, 0, 500);
        error_log("BookmarkParser: 文件内容预览: " . $contentPreview);

        // 检查文件内容是否包含基本的书签结构
        if (stripos($htmlContent, '<dl') === false || stripos($htmlContent, '<dt') === false) {
            error_log("BookmarkParser: 警告 - 文件内容中未找到基本的书签结构 (DL/DT 标签)");

            // 尝试直接解析文本，查找链接
            if (stripos($htmlContent, '<a') !== false && stripos($htmlContent, 'href=') !== false) {
                error_log("BookmarkParser: 尝试直接解析文本中的链接");
                return self::parseLinksDirectly($htmlContent, $db, $targetColumnId, $userId);
            }
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true); // 抑制 HTML 解析错误

        // 确保有字符集声明，避免中文乱码
        if (stripos($htmlContent, 'charset=') === false) {
            $htmlContent = '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">' . $htmlContent;
            error_log("BookmarkParser: 添加了 UTF-8 字符集声明");
        }

        // 尝试修复常见的 HTML 实体错误
        $htmlContent = preg_replace('/&(?!#?[a-zA-Z0-9]+;)/', '&amp;', $htmlContent);
        error_log("BookmarkParser: 修复了可能的 HTML 实体错误");

        // 设置更宽松的解析选项
        $doc->recover = true;
        $doc->strictErrorChecking = false;

        // 尝试加载 HTML
        $loadResult = $doc->loadHTML($htmlContent, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_PARSEHUGE);
        if (!$loadResult) {
            error_log("BookmarkParser: 警告 - HTML 加载可能不成功");
        }

        // 检查并记录解析错误
        $errors = libxml_get_errors();
        if (count($errors) > 0) {
            error_log("BookmarkParser: HTML 解析产生了 " . count($errors) . " 个警告/错误，但我们将继续处理");
            // 只记录前 5 个错误，避免日志过大
            for ($i = 0; $i < min(5, count($errors)); $i++) {
                error_log("BookmarkParser: HTML 解析错误 #" . ($i + 1) . ": " . $errors[$i]->message);
            }
        }
        libxml_clear_errors();

        // 输出文档结构到日志
        error_log("BookmarkParser: 文档结构:");
        self::logDocumentStructure($doc);

        $xpath = new DOMXPath($doc);

        // 计数器
        $totalLinks = 0;
        $totalFolders = 0;

        // 获取区块顺序
        $maxBlockOrderStmt = $db->prepare("SELECT MAX(display_order) as max_order FROM blocks WHERE column_id = ?");
        $maxBlockOrderStmt->execute([$targetColumnId]);
        $globalBlockOrder = ($maxBlockOrderStmt->fetchColumn() ?? -1) + 1;

        error_log("BookmarkParser: 开始导入。目标列 ID: {$targetColumnId}, 初始区块顺序: {$globalBlockOrder}");

        // 查找所有链接元素，不管它们在哪里
        $allLinks = $xpath->query('//A[@HREF]');
        error_log("BookmarkParser: 文档中找到 " . $allLinks->length . " 个链接元素");

        // 如果找到链接但没有找到 DL/DT 结构，尝试直接导入所有链接
        if ($allLinks->length > 0 && (stripos($htmlContent, '<dl') === false || stripos($htmlContent, '<dt') === false)) {
            error_log("BookmarkParser: 找到链接但没有标准书签结构，尝试直接导入所有链接");

            // 创建一个默认区块
            $stmt_block = $db->prepare("INSERT INTO blocks (column_id, title, type, display_order) VALUES (?, ?, 'links', ?)");
            $stmt_block->execute([$targetColumnId, "导入的书签", $globalBlockOrder]);
            $blockId = (int)$db->lastInsertId();
            $totalFolders++;

            // 导入所有链接
            foreach ($allLinks as $linkIndex => $linkElement) {
                $url = $linkElement->getAttribute('href');
                $title = trim($linkElement->textContent);

                if (empty($title)) {
                    $title = "链接 #" . ($linkIndex + 1);
                }

                if (!empty($url)) {
                    $stmt_link = $db->prepare("INSERT INTO links (block_id, title, url, display_order) VALUES (?, ?, ?, ?)");
                    try {
                        $stmt_link->execute([$blockId, $title, $url, $linkIndex]);
                        $totalLinks++;
                        error_log("BookmarkParser: 直接导入链接 '{$title}' -> {$url}");
                    } catch (PDOException $e) {
                        error_log("BookmarkParser: 导入链接失败: " . $e->getMessage());
                    }
                }
            }

            if ($totalLinks > 0) {
                error_log("BookmarkParser: 直接导入了 {$totalLinks} 个链接到区块 {$blockId}");
                return ['folders' => $totalFolders, 'links' => $totalLinks];
            }
        }

        // 查找主 DL 节点 - 尝试多种方法
        $mainDLNode = null;

        // 方法 1: 查找 Bookmarks 标题后的 DL
        error_log("BookmarkParser: 尝试查找 Bookmarks 标题后的 DL");
        $h1Nodes = $xpath->query('//H1');
        error_log("BookmarkParser: 找到 " . $h1Nodes->length . " 个 H1 节点");

        foreach ($h1Nodes as $h1Candidate) {
            $h1Text = trim($h1Candidate->textContent);
            error_log("BookmarkParser: 检查 H1 节点: '{$h1Text}'");

            if (stripos($h1Text, "Bookmarks") !== false) {
                error_log("BookmarkParser: 找到 Bookmarks 标题: '{$h1Text}'");
                $sibling = $h1Candidate->nextSibling;
                while ($sibling) {
                    if ($sibling->nodeType === XML_ELEMENT_NODE && strtolower($sibling->nodeName) === 'dl') {
                        $mainDLNode = $sibling;
                        error_log("BookmarkParser: 在 Bookmarks 标题后找到 DL 节点");
                        break 2;
                    }
                    $sibling = $sibling->nextSibling;
                }
            }
        }

        // 方法 2: 查找任何 DL 节点
        if (!$mainDLNode) {
            $dlNodes = $xpath->query('//DL');
            error_log("BookmarkParser: 未找到 Bookmarks 标题后的 DL，尝试查找任何 DL 节点，找到 " . $dlNodes->length . " 个");

            if ($dlNodes->length > 0) {
                $mainDLNode = $dlNodes->item(0);
                error_log("BookmarkParser: 使用第一个 DL 节点作为主节点");
            }
        }

        // 方法 3: 尝试查找 BODY 下的第一个 DL
        if (!$mainDLNode) {
            $bodyDLs = $xpath->query('/HTML/BODY//DL');
            error_log("BookmarkParser: 尝试查找 BODY 下的 DL，找到 " . $bodyDLs->length . " 个");

            if ($bodyDLs->length > 0) {
                $mainDLNode = $bodyDLs->item(0);
                error_log("BookmarkParser: 使用 BODY 下的第一个 DL 节点作为主节点");
            }
        }

        if ($mainDLNode && $mainDLNode instanceof DOMElement) {
            $rootLooseLinksBlockId = null;
            $rootDefaultTitle = "书签栏"; // Default for root content
            $h1MainTitleNode = $xpath->query('/html/body/h1[1] | //h1[1]')->item(0);
            if ($h1MainTitleNode) {
                $h1Text = trim($h1MainTitleNode->textContent);
                if (!empty($h1Text) && strtolower($h1Text) !== "bookmarks") { $rootDefaultTitle = $h1Text; }
            }

            self::parseDlLevel($mainDLNode, $xpath, $db, $userId, $targetColumnId, $rootLooseLinksBlockId, $rootDefaultTitle, $globalBlockOrder, $totalLinks, $totalFolders, 0);
        } else {
            error_log("BookmarkParser: CRITICAL - No main <DL> element found.");
            // Fallback for flat structure could be added here if necessary
        }

        error_log("BookmarkParser: Import finished. Total Folders: {$totalFolders}, Total Links: {$totalLinks}");
        return ['folders' => $totalFolders, 'links' => $totalLinks];
    }

    private static function parseDlLevel(
        DOMElement $dlElement, DOMXPath $xpath, PDO $db, int $userId,
        int $targetColumnId, // Column where ALL blocks (folders) are created
        ?int &$blockIdForLooseLinksInThisDl, // Block ID for loose links in THIS DL. Passed by ref.
        string $defaultTitleForLooseLinksInThisDl, // Title if a block for loose links needs creation for THIS DL.
        int &$globalBlockOrderForNewTopLevelFolders, // Order for any new folder (block) created. Passed by ref.
        int &$totalLinksImported, int &$totalFoldersImported,
        int $depth
    ) {
        $indent = str_repeat("  ", $depth);
        error_log("{$indent}--- 进入 parseDlLevel 处理 DL: {$dlElement->getNodePath()} ---");
        error_log("{$indent}  上下文: 当前区块ID (引用): " . ($blockIdForLooseLinksInThisDl ?? 'NULL') . ", 默认标题: '{$defaultTitleForLooseLinksInThisDl}'");

        // 尝试多种方式查找 DT 节点
        $dtNodes = $xpath->query('./DT', $dlElement); // 获取直接子 DT 节点

        if ($dtNodes->length === 0) {
            // 如果没有找到直接子 DT 节点，尝试查找所有后代 DT 节点
            error_log("{$indent}  未找到直接子 DT 节点，尝试查找所有后代 DT 节点");
            $dtNodes = $xpath->query('.//DT', $dlElement);

            if ($dtNodes->length === 0) {
                // 最后尝试：查找所有 DT 节点，不管它们在哪里
                error_log("{$indent}  尝试查找文档中的所有 DT 节点");
                $dtNodes = $xpath->query('//DT');

                if ($dtNodes->length === 0) {
                    error_log("{$indent}  文档中没有找到任何 DT 节点。退出此级别。");
                    error_log("{$indent}--- 退出 parseDlLevel (没有 DT 节点) ---");
                    return;
                } else {
                    error_log("{$indent}  在整个文档中找到 {$dtNodes->length} 个 DT 节点。");
                }
            } else {
                error_log("{$indent}  在 DL 的后代中找到 {$dtNodes->length} 个 DT 节点。");
            }
        } else {
            error_log("{$indent}  在 DL 的直接子节点中找到 {$dtNodes->length} 个 DT 节点。");
        }

        // 此变量将保存此 DL 下直接链接的区块 ID。
        // 它要么是传入的（如果此 DL 是文件夹的内容），要么在此处为松散链接创建。
        // 变量 $blockIdForLooseLinksInThisDl（通过引用传递）用于为此 DL 创建一次区块。
        $currentContextBlockId = $blockIdForLooseLinksInThisDL;


        foreach ($dtNodes as $dtIndex => $dtNode) {
            error_log("{$indent}    处理 DT #" . ($dtIndex + 1) . " / " . $dtNodes->length . ": {$dtNode->getNodePath()}");

            // 尝试多种方式查找 H3 或 A 元素
            $itemElement = null;

            // 方法 1: 直接子节点
            foreach ($dtNode->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE && in_array(strtoupper($child->nodeName), ['H3', 'A'])) {
                    $itemElement = $child;
                    error_log("{$indent}      在 DT 的直接子节点中找到 " . $child->nodeName . " 元素");
                    break;
                }
            }

            // 方法 2: 如果没有找到，尝试查找所有后代节点
            if (!$itemElement) {
                error_log("{$indent}      在 DT 的直接子节点中未找到 H3 或 A 元素，尝试查找所有后代节点");
                $h3Nodes = $xpath->query('.//H3', $dtNode);
                $aNodes = $xpath->query('.//A', $dtNode);

                if ($h3Nodes->length > 0) {
                    $itemElement = $h3Nodes->item(0);
                    error_log("{$indent}      在 DT 的后代节点中找到 H3 元素");
                } elseif ($aNodes->length > 0) {
                    $itemElement = $aNodes->item(0);
                    error_log("{$indent}      在 DT 的后代节点中找到 A 元素");
                }
            }

            if (!$itemElement) {
                error_log("{$indent}      跳过此 DT: 未找到 H3 或 A 元素。");
                continue; // 继续处理下一个 DT
            }

            $itemType = strtoupper($itemElement->nodeName);
            $itemText = trim($itemElement->textContent);
            error_log("{$indent}      项目类型: {$itemType}, 文本: '" . mb_substr($itemText, 0, 40) . "'");

            if ($itemType === 'H3') { // FOLDER
                $totalFoldersImported++;
                $folderName = $itemText;
                if (empty($folderName)) $folderName = "未命名文件夹 {$totalFoldersImported}";

                $stmt_block = $db->prepare("INSERT INTO blocks (column_id, title, type, display_order) VALUES (?, ?, 'links', ?)");
                $stmt_block->execute([$targetColumnId, $folderName, $globalBlockOrderForNewTopLevelFolders]);
                $newFolderCreatedBlockId = (int)$db->lastInsertId(); // This is the block for the folder itself
                $globalBlockOrderForNewTopLevelFolders++;
                error_log("{$indent}        CREATED FOLDER Block: '{$folderName}', ID: {$newFolderCreatedBlockId}");

                // 尝试多种方式查找文件夹内容的 DL 元素
                error_log("{$indent}      尝试查找文件夹 '{$folderName}' 的内容 DL");
                $folderContentDlNode = null;

                // 方法 1: 作为 DT 的直接子节点 (标准书签格式)
                foreach ($dtNode->childNodes as $child) {
                    if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'dl') {
                        $folderContentDlNode = $child;
                        error_log("{$indent}      在 DT 的直接子节点中找到文件夹内容 DL");
                        break;
                    }
                }

                // 方法 2: 作为 DT 的兄弟节点 (替代格式)
                if (!$folderContentDlNode) {
                    error_log("{$indent}      在 DT 的直接子节点中未找到 DL，尝试查找兄弟节点");
                    $folderContentDlNode = $dtNode->nextSibling;
                    while ($folderContentDlNode && ($folderContentDlNode->nodeType !== XML_ELEMENT_NODE || strtolower($folderContentDlNode->nodeName) !== 'dl')) {
                        if ($folderContentDlNode->nodeType === XML_ELEMENT_NODE && strtolower($folderContentDlNode->nodeName) === 'dt') {
                            $folderContentDlNode = null;
                            break;
                        }
                        $folderContentDlNode = $folderContentDlNode->nextSibling;
                    }

                    if ($folderContentDlNode) {
                        error_log("{$indent}      在 DT 的兄弟节点中找到文件夹内容 DL");
                    }
                }

                // 方法 3: 查找 H3 后的第一个 DL (另一种替代格式)
                if (!$folderContentDlNode) {
                    error_log("{$indent}      在 DT 的子节点或兄弟节点中未找到 DL，尝试查找 H3 后的 DL");
                    $h3Sibling = $itemElement->nextSibling;
                    while ($h3Sibling) {
                        if ($h3Sibling->nodeType === XML_ELEMENT_NODE && strtolower($h3Sibling->nodeName) === 'dl') {
                            $folderContentDlNode = $h3Sibling;
                            error_log("{$indent}      在 H3 后找到文件夹内容 DL");
                            break;
                        }
                        $h3Sibling = $h3Sibling->nextSibling;
                    }
                }

                // 方法 4: 查找 DT 的父节点下的下一个 DL (最后尝试)
                if (!$folderContentDlNode && $dtNode->parentNode) {
                    error_log("{$indent}      尝试最后方法: 查找 DT 的父节点下的下一个 DL");
                    $dtSiblings = $dtNode->parentNode->childNodes;
                    $foundCurrentDt = false;

                    foreach ($dtSiblings as $sibling) {
                        if ($foundCurrentDt && $sibling->nodeType === XML_ELEMENT_NODE && strtolower($sibling->nodeName) === 'dl') {
                            $folderContentDlNode = $sibling;
                            error_log("{$indent}      在 DT 之后的父节点下找到 DL");
                            break;
                        }

                        if ($sibling === $dtNode) {
                            $foundCurrentDt = true;
                        }
                    }
                }

                if ($folderContentDlNode && $folderContentDlNode instanceof DOMElement) {
                    error_log("{$indent}        Recursing into DL for folder '{$folderName}'");
                    // For links/subfolders inside this folder's DL, the $currentContextBlockId will be $newFolderCreatedBlockId
                    // No, wait. Links inside this DL will go into $newFolderCreatedBlockId if loose.
                    // So, $newFolderCreatedBlockId is the $blockIdForLooseLinksInThisDL for the *next level*.
                    $looseLinksBlockForThisSubFolder = null; // Reset for the sub-DL
                    self::parseDlLevel($folderContentDlNode, $xpath, $db, $userId, $targetColumnId, $looseLinksBlockForThisSubFolder, $folderName, $globalBlockOrderForNewTopLevelFolders, $totalLinksImported, $totalFoldersImported, $depth + 1);
                } else {
                    error_log("{$indent}        WARNING: Folder '{$folderName}' has no content <DL>.");
                }

            } elseif ($itemType === 'A') { // LINK
                if ($currentContextBlockId === null) { // No block yet for loose links in THIS DL
                    $totalFoldersImported++; // Count this conceptual block
                    error_log("{$indent}        Creating default block '{$defaultTitleForLooseLinksInThisDL}' for loose links in this DL. Order: {$globalBlockOrderForNewTopLevelFolders}");
                    $stmt_default_block = $db->prepare("INSERT INTO blocks (column_id, title, type, display_order) VALUES (?, ?, 'links', ?)");
                    $stmt_default_block->execute([$targetColumnId, $defaultTitleForLooseLinksInThisDL, $globalBlockOrderForNewTopLevelFolders]);
                    $currentContextBlockId = (int)$db->lastInsertId();
                    // Update the passed-by-reference variable so the CALLER knows this DL now has a block for its loose links
                    $blockIdForLooseLinksInThisDL = $currentContextBlockId;
                    $globalBlockOrderForNewTopLevelFolders++;
                }
                // Now $currentContextBlockId is guaranteed to be set for this link

                // 获取链接属性
                $url = $itemElement->getAttribute('href');
                $title = $itemText;
                $icon = $itemElement->getAttribute('icon');

                // 如果没有找到图标，尝试查找其他可能的图标属性
                if (empty($icon)) {
                    $icon = $itemElement->getAttribute('ICON');
                    if (empty($icon)) {
                        $icon = $itemElement->getAttribute('icon_uri');
                    }
                }

                // 如果标题为空，使用 URL 作为标题
                if (empty($title) && !empty($url)) {
                    $title = $url;
                }

                // 如果标题仍然为空，使用默认标题
                if (empty($title)) {
                    $title = "未命名链接 #" . ($totalLinksImported + 1);
                }

                error_log("{$indent}        链接: '{$title}' --> 到区块 ID: {$currentContextBlockId}");
                error_log("{$indent}        URL: {$url}");

                // 验证 URL 并插入数据库
                // 放宽 URL 验证，接受更多类型的 URL
                if (!empty($url)) {
                    // 清理和修复 URL
                    $url = trim($url);

                    // 修复一些常见的 URL 问题
                    if (strpos($url, 'http') !== 0 && strpos($url, 'ftp') !== 0 && strpos($url, 'mailto:') !== 0) {
                        // 如果 URL 不以协议开头，尝试添加 http://
                        if (strpos($url, '//') === 0) {
                            // 如果以 // 开头，添加 http:
                            $url = 'http:' . $url;
                        } elseif (strpos($url, '/') !== 0) {
                            // 如果不以 / 开头，添加 http://
                            $url = 'http://' . $url;
                        }
                    }

                    // 清理标题
                    $title = trim($title);
                    if (empty($title)) {
                        $title = parse_url($url, PHP_URL_HOST) ?: "链接 #" . ($totalLinksImported + 1);
                    }

                    // 限制标题和 URL 长度，避免数据库错误
                    $title = mb_substr($title, 0, 255);
                    if (strlen($url) > 2000) {
                        $url = substr($url, 0, 2000);
                    }

                    // 插入链接
                    try {
                        $stmt_link = $db->prepare("INSERT INTO links (block_id, title, url, icon_url, display_order) VALUES (?, ?, ?, ?, ?)");
                        $maxLinkOrderStmt = $db->prepare("SELECT MAX(display_order) FROM links WHERE block_id = ?");
                        $maxLinkOrderStmt->execute([$currentContextBlockId]);
                        $newLinkOrder = ($maxLinkOrderStmt->fetchColumn() ?? -1) + 1;

                        $stmt_link->execute([$currentContextBlockId, $title, $url, $icon ?: null, $newLinkOrder]);
                        $totalLinksImported++;
                        error_log("{$indent}          成功插入链接 '{$title}' -> {$url}");
                    } catch (PDOException $linkEx) {
                        error_log("{$indent}          插入链接 '{$title}' 失败。错误: " . $linkEx->getMessage());

                        // 尝试不带图标插入
                        try {
                            $stmt_link->execute([$currentContextBlockId, $title, $url, null, $newLinkOrder]);
                            $totalLinksImported++;
                            error_log("{$indent}          成功插入链接 (无图标) '{$title}' -> {$url}");
                        } catch (PDOException $retryEx) {
                            error_log("{$indent}          第二次尝试插入链接失败: " . $retryEx->getMessage());
                        }
                    }
                } else {
                    error_log("{$indent}          跳过空 URL 的链接");
                }
            }
            error_log("{$indent}    完成处理 DT 项目 #" . ($dtIndex + 1));
        } // End foreach $dtNode
        error_log("{$indent}--- 退出 parseDlLevel 处理 DL: {$dlElement->getNodePath()}。处理了 {$dtNodes->length} 个 DT 节点 ---");
    }

    /**
     * 调试函数：输出 DOM 节点的基本信息
     */
    private static function debugNode(DOMNode $node, string $prefix = ''): string {
        $info = $prefix . "节点类型: ";

        switch ($node->nodeType) {
            case XML_ELEMENT_NODE:
                $info .= "元素节点 <" . $node->nodeName . ">";
                if ($node->hasAttributes()) {
                    $info .= " 属性: ";
                    foreach ($node->attributes as $attr) {
                        $info .= $attr->name . "=\"" . $attr->value . "\" ";
                    }
                }
                break;
            case XML_TEXT_NODE:
                $text = trim($node->textContent);
                $info .= "文本节点 \"" . (strlen($text) > 30 ? substr($text, 0, 27) . "..." : $text) . "\"";
                break;
            default:
                $info .= "其他节点类型 (" . $node->nodeType . ")";
        }

        return $info;
    }

    /**
     * 记录文档结构到日志
     */
    private static function logDocumentStructure(DOMDocument $doc, int $maxDepth = 3) {
        $root = $doc->documentElement;
        if (!$root) {
            error_log("BookmarkParser: 文档没有根元素");
            return;
        }

        self::logNodeStructure($root, 0, $maxDepth);
    }

    /**
     * 递归记录节点结构
     */
    private static function logNodeStructure(DOMNode $node, int $depth = 0, int $maxDepth = 3) {
        if ($depth > $maxDepth) {
            return; // 限制递归深度
        }

        $indent = str_repeat("  ", $depth);
        $nodeInfo = self::debugNode($node, $indent);
        error_log($nodeInfo);

        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    self::logNodeStructure($child, $depth + 1, $maxDepth);
                }
            }
        }
    }

    /**
     * 直接从 HTML 文本中解析链接
     */
    private static function parseLinksDirectly(string $htmlContent, PDO $db, int $targetColumnId, int $userId): array {
        error_log("BookmarkParser: 开始直接解析链接");

        $totalLinks = 0;
        $totalFolders = 1; // 我们将创建一个文件夹

        // 创建一个默认区块
        $maxBlockOrderStmt = $db->prepare("SELECT MAX(display_order) as max_order FROM blocks WHERE column_id = ?");
        $maxBlockOrderStmt->execute([$targetColumnId]);
        $blockOrder = ($maxBlockOrderStmt->fetchColumn() ?? -1) + 1;

        $stmt_block = $db->prepare("INSERT INTO blocks (column_id, title, type, display_order) VALUES (?, ?, 'links', ?)");
        $stmt_block->execute([$targetColumnId, "导入的书签", $blockOrder]);
        $blockId = (int)$db->lastInsertId();

        error_log("BookmarkParser: 创建了默认区块，ID: {$blockId}");

        // 使用正则表达式查找所有链接
        $pattern = '/<a\s+[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is';
        preg_match_all($pattern, $htmlContent, $matches, PREG_SET_ORDER);

        error_log("BookmarkParser: 正则表达式找到 " . count($matches) . " 个链接");

        foreach ($matches as $index => $match) {
            $url = $match[2];
            $title = strip_tags($match[3]);

            if (empty($title)) {
                $title = "链接 #" . ($index + 1);
            }

            if (!empty($url)) {
                $stmt_link = $db->prepare("INSERT INTO links (block_id, title, url, display_order) VALUES (?, ?, ?, ?)");
                try {
                    $stmt_link->execute([$blockId, $title, $url, $index]);
                    $totalLinks++;
                    error_log("BookmarkParser: 直接导入链接 '{$title}' -> {$url}");
                } catch (PDOException $e) {
                    error_log("BookmarkParser: 导入链接失败: " . $e->getMessage());
                }
            }
        }

        error_log("BookmarkParser: 直接解析完成，导入了 {$totalLinks} 个链接");
        return ['folders' => $totalFolders, 'links' => $totalLinks];
    }
} // End Class BookmarkParser

