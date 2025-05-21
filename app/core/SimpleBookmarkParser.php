<?php
// dh/app/core/SimpleBookmarkParser.php
declare(strict_types=1);

/**
 * 简单书签解析器
 * 使用正则表达式直接解析书签文件，不依赖 DOM 解析
 */
class SimpleBookmarkParser {
    
    /**
     * 导入书签到数据库
     */
    public static function importToDatabase(string $htmlFilePath, int $userId, PDO $db, int $targetPageId, int $targetColumnId): array {
        // 读取文件内容
        $htmlContent = file_get_contents($htmlFilePath);
        if ($htmlContent === false) throw new Exception("无法读取书签文件内容。");
        
        error_log("SimpleBookmarkParser: 读取到书签文件内容，长度: " . strlen($htmlContent) . " 字节");
        
        // 计数器
        $totalLinks = 0;
        $totalFolders = 0;
        
        // 获取区块顺序
        $maxBlockOrderStmt = $db->prepare("SELECT MAX(display_order) as max_order FROM blocks WHERE column_id = ?");
        $maxBlockOrderStmt->execute([$targetColumnId]);
        $globalBlockOrder = ($maxBlockOrderStmt->fetchColumn() ?? -1) + 1;
        
        // 创建一个默认区块
        $defaultBlockId = null;
        
        // 查找所有文件夹 (H3 标签)
        $folderPattern = '/<DT><H3[^>]*>(.*?)<\/H3>/is';
        preg_match_all($folderPattern, $htmlContent, $folderMatches, PREG_SET_ORDER);
        
        error_log("SimpleBookmarkParser: 找到 " . count($folderMatches) . " 个文件夹");
        
        // 处理每个文件夹
        foreach ($folderMatches as $folderIndex => $folderMatch) {
            $folderName = strip_tags($folderMatch[1]);
            $folderName = trim($folderName);
            
            if (empty($folderName)) {
                $folderName = "未命名文件夹 " . ($folderIndex + 1);
            }
            
            // 创建文件夹区块
            $stmt_block = $db->prepare("INSERT INTO blocks (column_id, title, type, display_order) VALUES (?, ?, 'links', ?)");
            $stmt_block->execute([$targetColumnId, $folderName, $globalBlockOrder]);
            $blockId = (int)$db->lastInsertId();
            $globalBlockOrder++;
            $totalFolders++;
            
            error_log("SimpleBookmarkParser: 创建文件夹区块 '{$folderName}', ID: {$blockId}");
            
            // 查找此文件夹后的 DL 标签内的链接
            $folderPos = strpos($htmlContent, $folderMatch[0]);
            if ($folderPos !== false) {
                $afterFolder = substr($htmlContent, $folderPos + strlen($folderMatch[0]));
                $dlStart = strpos($afterFolder, '<DL>');
                $dlEnd = strpos($afterFolder, '</DL>');
                
                if ($dlStart !== false && $dlEnd !== false && $dlStart < $dlEnd) {
                    $dlContent = substr($afterFolder, $dlStart, $dlEnd - $dlStart + 5);
                    
                    // 查找 DL 中的所有链接
                    $linkPattern = '/<DT><A[^>]*HREF="([^"]*)"[^>]*>(.*?)<\/A>/is';
                    preg_match_all($linkPattern, $dlContent, $linkMatches, PREG_SET_ORDER);
                    
                    error_log("SimpleBookmarkParser: 在文件夹 '{$folderName}' 中找到 " . count($linkMatches) . " 个链接");
                    
                    // 导入链接
                    foreach ($linkMatches as $linkIndex => $linkMatch) {
                        $url = $linkMatch[1];
                        $title = strip_tags($linkMatch[2]);
                        
                        if (empty($title)) {
                            $title = "链接 #" . ($linkIndex + 1);
                        }
                        
                        if (!empty($url)) {
                            try {
                                $stmt_link = $db->prepare("INSERT INTO links (block_id, title, url, display_order) VALUES (?, ?, ?, ?)");
                                $stmt_link->execute([$blockId, $title, $url, $linkIndex]);
                                $totalLinks++;
                                error_log("SimpleBookmarkParser: 导入链接 '{$title}' -> {$url} 到文件夹 '{$folderName}'");
                            } catch (PDOException $e) {
                                error_log("SimpleBookmarkParser: 导入链接失败: " . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }
        
        // 查找所有不在文件夹中的链接
        $linkPattern = '/<DT><A[^>]*HREF="([^"]*)"[^>]*>(.*?)<\/A>/is';
        preg_match_all($linkPattern, $htmlContent, $linkMatches, PREG_SET_ORDER);
        
        // 过滤掉已经处理过的链接
        $processedLinks = [];
        foreach ($folderMatches as $folderMatch) {
            $folderPos = strpos($htmlContent, $folderMatch[0]);
            if ($folderPos !== false) {
                $afterFolder = substr($htmlContent, $folderPos + strlen($folderMatch[0]));
                $dlStart = strpos($afterFolder, '<DL>');
                $dlEnd = strpos($afterFolder, '</DL>');
                
                if ($dlStart !== false && $dlEnd !== false && $dlStart < $dlEnd) {
                    $dlContent = substr($afterFolder, $dlStart, $dlEnd - $dlStart + 5);
                    $processedLinks[] = $dlContent;
                }
            }
        }
        
        $remainingLinks = [];
        foreach ($linkMatches as $linkMatch) {
            $linkHtml = $linkMatch[0];
            $isProcessed = false;
            
            foreach ($processedLinks as $processedContent) {
                if (strpos($processedContent, $linkHtml) !== false) {
                    $isProcessed = true;
                    break;
                }
            }
            
            if (!$isProcessed) {
                $remainingLinks[] = $linkMatch;
            }
        }
        
        error_log("SimpleBookmarkParser: 找到 " . count($remainingLinks) . " 个未分类链接");
        
        // 如果有未分类链接，创建一个默认区块
        if (count($remainingLinks) > 0) {
            $stmt_block = $db->prepare("INSERT INTO blocks (column_id, title, type, display_order) VALUES (?, ?, 'links', ?)");
            $stmt_block->execute([$targetColumnId, "未分类书签", $globalBlockOrder]);
            $defaultBlockId = (int)$db->lastInsertId();
            $globalBlockOrder++;
            $totalFolders++;
            
            error_log("SimpleBookmarkParser: 创建默认区块，ID: {$defaultBlockId}");
            
            // 导入未分类链接
            foreach ($remainingLinks as $linkIndex => $linkMatch) {
                $url = $linkMatch[1];
                $title = strip_tags($linkMatch[2]);
                
                if (empty($title)) {
                    $title = "链接 #" . ($linkIndex + 1);
                }
                
                if (!empty($url)) {
                    try {
                        $stmt_link = $db->prepare("INSERT INTO links (block_id, title, url, display_order) VALUES (?, ?, ?, ?)");
                        $stmt_link->execute([$defaultBlockId, $title, $url, $linkIndex]);
                        $totalLinks++;
                        error_log("SimpleBookmarkParser: 导入未分类链接 '{$title}' -> {$url}");
                    } catch (PDOException $e) {
                        error_log("SimpleBookmarkParser: 导入链接失败: " . $e->getMessage());
                    }
                }
            }
        }
        
        // 如果没有找到任何文件夹或链接，尝试使用更宽松的正则表达式
        if ($totalLinks == 0 && $totalFolders == 0) {
            error_log("SimpleBookmarkParser: 未找到任何文件夹或链接，尝试使用更宽松的正则表达式");
            return self::fallbackParsing($htmlContent, $db, $targetColumnId, $userId);
        }
        
        error_log("SimpleBookmarkParser: 导入完成，总共导入了 {$totalFolders} 个文件夹和 {$totalLinks} 个链接");
        return ['folders' => $totalFolders, 'links' => $totalLinks];
    }
    
    /**
     * 备用解析方法，使用更宽松的正则表达式
     */
    private static function fallbackParsing(string $htmlContent, PDO $db, int $targetColumnId, int $userId): array {
        $totalLinks = 0;
        $totalFolders = 1;
        
        // 创建一个默认区块
        $maxBlockOrderStmt = $db->prepare("SELECT MAX(display_order) as max_order FROM blocks WHERE column_id = ?");
        $maxBlockOrderStmt->execute([$targetColumnId]);
        $blockOrder = ($maxBlockOrderStmt->fetchColumn() ?? -1) + 1;
        
        $stmt_block = $db->prepare("INSERT INTO blocks (column_id, title, type, display_order) VALUES (?, ?, 'links', ?)");
        $stmt_block->execute([$targetColumnId, "导入的书签", $blockOrder]);
        $blockId = (int)$db->lastInsertId();
        
        error_log("SimpleBookmarkParser: 备用方法 - 创建了默认区块，ID: {$blockId}");
        
        // 使用更宽松的正则表达式查找所有链接
        $pattern = '/<a\s+[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is';
        preg_match_all($pattern, $htmlContent, $matches, PREG_SET_ORDER);
        
        error_log("SimpleBookmarkParser: 备用方法 - 找到 " . count($matches) . " 个链接");
        
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
                    error_log("SimpleBookmarkParser: 备用方法 - 导入链接 '{$title}' -> {$url}");
                } catch (PDOException $e) {
                    error_log("SimpleBookmarkParser: 备用方法 - 导入链接失败: " . $e->getMessage());
                }
            }
        }
        
        error_log("SimpleBookmarkParser: 备用方法 - 导入完成，总共导入了 {$totalFolders} 个文件夹和 {$totalLinks} 个链接");
        return ['folders' => $totalFolders, 'links' => $totalLinks];
    }
}
