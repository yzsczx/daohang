<?php
// dh/manage_links.php
declare(strict_types=1);

require_once __DIR__ . '/app/core/config.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/functions.php';
require_once __DIR__ . '/app/core/favicon_fetcher.php';
require_once __DIR__ . '/app/core/admin_check.php'; // 添加管理员检查

$db = getDBConnection();

if (!isLoggedIn()) {
    redirect('index.php');
}
$current_user_id = getCurrentUserId($db);
if ($current_user_id === null) {
    redirect('index.php?action=logout');
}

$action = $_GET['action'] ?? $_POST['action_manage_link'] ?? 'list_placeholder';
$block_id_param = isset($_REQUEST['block_id']) ? (int)$_REQUEST['block_id'] : null; // Links belong to a block
$link_id_param = isset($_REQUEST['edit_id']) ? (int)$_REQUEST['edit_id'] : (isset($_REQUEST['delete_id']) ? (int)$_REQUEST['delete_id'] : null);
$page_id_redirect = isset($_REQUEST['page_id']) ? (int)$_REQUEST['page_id'] : null;

$form_error = $_SESSION['form_error'] ?? null;
$form_success = $_SESSION['form_success'] ?? null;
unset($_SESSION['form_error'], $_SESSION['form_success']);

$csrf_token = generateCsrfToken();
$link_to_edit = null;
$parent_block_data = null;

// Verify block ownership and get its data
if ($block_id_param) {
    $stmtBlock = $db->prepare(
        "SELECT b.id as block_id, b.title as block_title, b.type as block_type, c.page_id
         FROM blocks b
         JOIN columns c ON b.column_id = c.id
         JOIN pages p ON c.page_id = p.id
         WHERE b.id = ? AND p.user_id = ?"
    );
    $stmtBlock->execute([$block_id_param, $current_user_id]);
    $parent_block_data = $stmtBlock->fetch();
    if (!$parent_block_data || $parent_block_data['block_type'] !== 'links') {
        $_SESSION['form_error'] = "指定的区块无效、不属于您或不是链接类型区块。";
        redirect($page_id_redirect ? 'index.php?page_id=' . $page_id_redirect : 'index.php');
    }
    if ($page_id_redirect === null) $page_id_redirect = (int)$parent_block_data['page_id'];
} else if ($action !== 'list_placeholder') { // block_id is required for add/edit/delete
    $_SESSION['form_error'] = "未指定区块ID。";
    redirect($page_id_redirect ? 'index.php?page_id=' . $page_id_redirect : 'index.php');
}


// --- POST Handler ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['form_error'] = '无效请求。';
        redirect("manage_links.php?action={$action}&block_id={$block_id_param}&page_id={$page_id_redirect}" . ($link_id_param ? '&edit_id='.$link_id_param : ''));
    }

    if ($action === 'add_submit' && $block_id_param && $parent_block_data) {
        $link_title = trim($_POST['link_title'] ?? '');
        $link_url = trim($_POST['link_url'] ?? '');
        $link_description = trim($_POST['link_description'] ?? '');
        $link_icon_url = trim($_POST['link_icon_url'] ?? ''); // Optional

        if (empty($link_title) || empty($link_url)) {
            $_SESSION['form_error'] = '链接标题和URL不能为空。';
        } elseif (!filter_var($link_url, FILTER_VALIDATE_URL) && stripos($link_url, 'javascript:') !== 0) { // Allow javascript bookmarklets
            $_SESSION['form_error'] = '请输入有效的URL。';
        } else {
            try {
                // 如果没有提供图标URL，尝试自动抓取
                if (empty($link_icon_url) && stripos($link_url, 'javascript:') !== 0) {
                    error_log("尝试为 {$link_url} 自动抓取图标");

                    // 提取域名
                    $domain = null;
                    $parsedUrl = parse_url($link_url);
                    if (isset($parsedUrl['host'])) {
                        $domain = $parsedUrl['host'];
                    } else {
                        // 尝试修复URL
                        $fixedUrl = $link_url;
                        if (strpos($fixedUrl, '//') === 0) {
                            $fixedUrl = 'http:' . $fixedUrl;
                        } elseif (strpos($fixedUrl, '/') !== 0) {
                            $fixedUrl = 'http://' . $fixedUrl;
                        }
                        $parsedUrl = parse_url($fixedUrl);
                        if (isset($parsedUrl['host'])) {
                            $domain = $parsedUrl['host'];
                        }
                    }

                    // 使用直接的favicon.ico路径
                    if ($domain) {
                        $link_icon_url = "https://{$domain}/favicon.ico";
                        error_log("使用直接favicon路径: {$link_icon_url}");
                    } else {
                        error_log("无法从URL提取域名，使用默认图标");
                        // 使用内嵌的默认图标（一个简单的灰色图标）
                        $link_icon_url = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAbwAAAG8B8aLcQwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAB9SURBVDiNY/z//z8DJYCJgUIwDAxgYWBgYHj06BHD7du3GWbMmMHAwMDA8P//f4bPnz8zPHnyhOHLly8M//79w6ojKiqKwcnJieHZs2cMJiYmDIKCggysrKwMjIyMDB8+fGD4/fs3AycnJ4OAgABWRfgA48iLTEqzcuQbAAAA//9A/Af/6nZM7wAAAABJRU5ErkJggg==";
                    }
                }

                $maxOrderStmt = $db->prepare("SELECT MAX(display_order) as max_order FROM links WHERE block_id = ?");
                $maxOrderStmt->execute([$block_id_param]);
                $maxOrder = $maxOrderStmt->fetchColumn();
                $newLinkOrder = ($maxOrder === null) ? 0 : (int)$maxOrder + 1;

                $stmt = $db->prepare("INSERT INTO links (block_id, title, url, description, icon_url, display_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$block_id_param, $link_title, $link_url, $link_description, $link_icon_url ?: null, $newLinkOrder]);
                $_SESSION['form_success'] = '链接 "' . escape_html($link_title) . '" 添加成功！' . ($link_icon_url ? ' 已自动抓取图标。' : '');
                redirect('index.php?page_id=' . $page_id_redirect . '#block-' . $block_id_param); // Redirect to page and try to anchor to block
            } catch (PDOException $e) {
                error_log("Error adding link: " . $e->getMessage());
                $_SESSION['form_error'] = '添加链接时发生数据库错误。';
            }
        }
        redirect("manage_links.php?action=add&block_id={$block_id_param}&page_id={$page_id_redirect}");

    } elseif ($action === 'edit_submit' && $link_id_param && $block_id_param && $parent_block_data) {
        $link_title = trim($_POST['link_title'] ?? '');
        $link_url = trim($_POST['link_url'] ?? '');
        $link_description = trim($_POST['link_description'] ?? '');
        $link_icon_url = trim($_POST['link_icon_url'] ?? '');

        if (empty($link_title) || empty($link_url)) {
            $_SESSION['form_error'] = '链接标题和URL不能为空。';
        } elseif (!filter_var($link_url, FILTER_VALIDATE_URL) && stripos($link_url, 'javascript:') !== 0) {
             $_SESSION['form_error'] = '请输入有效的URL。';
        } else {
            try {
                // 如果没有提供图标URL，尝试自动抓取
                if (empty($link_icon_url) && stripos($link_url, 'javascript:') !== 0) {
                    error_log("尝试为 {$link_url} 自动抓取图标");

                    // 提取域名
                    $domain = null;
                    $parsedUrl = parse_url($link_url);
                    if (isset($parsedUrl['host'])) {
                        $domain = $parsedUrl['host'];
                    } else {
                        // 尝试修复URL
                        $fixedUrl = $link_url;
                        if (strpos($fixedUrl, '//') === 0) {
                            $fixedUrl = 'http:' . $fixedUrl;
                        } elseif (strpos($fixedUrl, '/') !== 0) {
                            $fixedUrl = 'http://' . $fixedUrl;
                        }
                        $parsedUrl = parse_url($fixedUrl);
                        if (isset($parsedUrl['host'])) {
                            $domain = $parsedUrl['host'];
                        }
                    }

                    // 使用直接的favicon.ico路径
                    if ($domain) {
                        $link_icon_url = "https://{$domain}/favicon.ico";
                        error_log("使用直接favicon路径: {$link_icon_url}");
                    } else {
                        error_log("无法从URL提取域名，使用默认图标");
                        // 使用内嵌的默认图标（一个简单的灰色图标）
                        $link_icon_url = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAbwAAAG8B8aLcQwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAB9SURBVDiNY/z//z8DJYCJgUIwDAxgYWBgYHj06BHD7du3GWbMmMHAwMDA8P//f4bPnz8zPHnyhOHLly8M//79w6ojKiqKwcnJieHZs2cMJiYmDIKCggysrKwMjIyMDB8+fGD4/fs3AycnJ4OAgABWRfgA48iLTEqzcuQbAAAA//9A/Af/6nZM7wAAAABJRU5ErkJggg==";
                    }
                }

                // Verify link belongs to user (via block -> column -> page)
                $verifyLinkStmt = $db->prepare("SELECT l.id FROM links l WHERE l.id = ? AND l.block_id = ?");
                $verifyLinkStmt->execute([$link_id_param, $block_id_param]);
                if ($verifyLinkStmt->fetch()) {
                    $stmt = $db->prepare("UPDATE links SET title = ?, url = ?, description = ?, icon_url = ? WHERE id = ?");
                    $stmt->execute([$link_title, $link_url, $link_description, $link_icon_url ?: null, $link_id_param]);
                    $_SESSION['form_success'] = '链接 "' . escape_html($link_title) . '" 更新成功！' . ($link_icon_url ? ' 已自动抓取图标。' : '');
                    redirect('index.php?page_id=' . $page_id_redirect . '#link-' . $link_id_param);
                } else {
                    $_SESSION['form_error'] = '无法编辑此链接或链接不存在。';
                }
            } catch (PDOException $e) {
                error_log("Error editing link: " . $e->getMessage());
                $_SESSION['form_error'] = '编辑链接时发生数据库错误。';
            }
        }
        redirect("manage_links.php?action=edit&edit_id={$link_id_param}&block_id={$block_id_param}&page_id={$page_id_redirect}");
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'delete' && isset($_GET['confirm']) && $_GET['confirm'] === '1' && $link_id_param && $block_id_param && $parent_block_data) {
    if (!isset($_GET['token']) || !verifyCsrfToken($_GET['token'])) {
        $_SESSION['form_error'] = '无效的删除请求。';
    } else {
        try {
            // Verify link ownership
            $verifyLinkStmt = $db->prepare("SELECT l.title FROM links l WHERE l.id = ? AND l.block_id = ?");
            $verifyLinkStmt->execute([$link_id_param, $block_id_param]);
            $link_to_delete_data = $verifyLinkStmt->fetch();

            if ($link_to_delete_data) {
                $stmt = $db->prepare("DELETE FROM links WHERE id = ?");
                $stmt->execute([$link_id_param]);
                $_SESSION['form_success'] = '链接 "' . escape_html($link_to_delete_data['title']) . '" 已删除。';
            } else {
                $_SESSION['form_error'] = '无法删除此链接或链接不存在。';
            }
        } catch (PDOException $e) {
            error_log("Error deleting link: " . $e->getMessage());
            $_SESSION['form_error'] = '删除链接时发生数据库错误。';
        }
    }
    redirect('index.php?page_id=' . $page_id_redirect . '#block-' . $block_id_param);
}


// --- Prepare data for 'edit' view ---
if ($action === 'edit' && $link_id_param && $parent_block_data) {
    $stmt = $db->prepare("SELECT * FROM links WHERE id = ? AND block_id = ?");
    $stmt->execute([$link_id_param, $block_id_param]);
    $link_to_edit = $stmt->fetch();
    if (!$link_to_edit) {
        $_SESSION['form_error'] = '要编辑的链接未找到。';
        redirect('index.php?page_id=' . $page_id_redirect);
    }
}

$page_title_for_header = "管理链接";
if ($action === 'add' && $parent_block_data) {
    $page_title_for_header = "添加新链接到区块: \"" . escape_html($parent_block_data['block_title']) . "\"";
} elseif ($action === 'edit' && $link_to_edit) {
    $page_title_for_header = "编辑链接: \"" . escape_html($link_to_edit['title']) . "\"";
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title_for_header; ?> - <?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="manage-page-header">
        <h1><?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?> - <?php echo $page_title_for_header; ?></h1>
        <nav class="user-actions">
             <a href="index.php<?php echo $page_id_redirect ? '?page_id=' . $page_id_redirect : ''; ?>" class="btn btn-sm btn-secondary">返回页面</a>
            <a href="index.php?action=logout" class="btn btn-sm btn-secondary">登出</a>
        </nav>
    </header>

    <div class="container">
        <?php if ($form_error): ?>
            <div class="alert alert-danger"><?php echo $form_error; ?></div>
        <?php endif; ?>
        <?php if ($form_success): ?>
            <div class="alert alert-success"><?php echo escape_html($form_success); ?></div>
        <?php endif; ?>

        <?php if (($action === 'add' || ($action === 'edit' && $link_to_edit)) && $parent_block_data): ?>
            <form method="POST" action="manage_links.php">
                <input type="hidden" name="action_manage_link" value="<?php echo $action === 'add' ? 'add_submit' : 'edit_submit'; ?>">
                <input type="hidden" name="block_id" value="<?php echo (int)$block_id_param; ?>">
                <input type="hidden" name="page_id" value="<?php echo (int)$page_id_redirect; ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="edit_id" value="<?php echo (int)$link_to_edit['id']; ?>">
                <?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">

                <div class="form-group">
                    <label for="link_title">链接标题:</label>
                    <input type="text" id="link_title" name="link_title" value="<?php echo escape_html($link_to_edit['title'] ?? $_POST['link_title'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="link_url">链接 URL:</label>
                    <input type="url" id="link_url" name="link_url" value="<?php echo escape_html($link_to_edit['url'] ?? $_POST['link_url'] ?? ''); ?>" placeholder="例如：https://example.com" required>
                </div>
                <div class="form-group">
                    <label for="link_description">描述 (可选):</label>
                    <textarea id="link_description" name="link_description" rows="3" class="form-control"><?php echo escape_html($link_to_edit['description'] ?? $_POST['link_description'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label for="link_icon_url">图标 URL (可选):</label>
                    <div class="input-group">
                        <input type="url" id="link_icon_url" name="link_icon_url" value="<?php echo escape_html($link_to_edit['icon_url'] ?? $_POST['link_icon_url'] ?? ''); ?>" placeholder="例如：https://example.com/favicon.ico">
                        <button type="button" id="fetch-favicon-btn" class="btn btn-outline-secondary">抓取图标</button>
                    </div>
                    <small class="form-text">留空将自动尝试抓取网站图标。如需自定义图标，请输入完整URL。</small>
                    <div id="favicon-preview" class="mt-2" style="display:none;">
                        <strong>图标预览:</strong>
                        <img id="favicon-preview-img" src="" alt="图标预览" style="max-width:32px; max-height:32px; margin-left:10px; vertical-align:middle;">
                    </div>
                </div>
                 <?php // display_order is calculated automatically for new links ?>
                <button type="submit" class="btn btn-success"><?php echo $action === 'add' ? '添加链接' : '保存更改'; ?></button>
                <a href="index.php<?php echo $page_id_redirect ? '?page_id=' . $page_id_redirect . '#block-' . $block_id_param : ''; ?>" class="btn btn-secondary" style="margin-left:10px;">取消</a>
            </form>
        <?php else: ?>
            <p class="alert alert-info">请通过主页面的链接区块操作来管理链接。</p>
            <p><a href="index.php<?php echo $page_id_redirect ? '?page_id='.$page_id_redirect : ''; ?>" class="btn btn-primary">返回页面</a></p>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?></p>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // 图标预览功能
        const iconUrlInput = document.getElementById('link_icon_url');
        const faviconPreview = document.getElementById('favicon-preview');
        const faviconPreviewImg = document.getElementById('favicon-preview-img');
        const fetchFaviconBtn = document.getElementById('fetch-favicon-btn');
        const linkUrlInput = document.getElementById('link_url');

        // 当图标URL输入框内容变化时更新预览
        if (iconUrlInput) {
            iconUrlInput.addEventListener('input', updateFaviconPreview);
            // 初始加载时检查是否已有图标URL
            updateFaviconPreview();
        }

        // 抓取图标按钮点击事件
        if (fetchFaviconBtn && linkUrlInput) {
            fetchFaviconBtn.addEventListener('click', function() {
                const url = linkUrlInput.value.trim();
                if (!url) {
                    alert('请先输入链接URL');
                    return;
                }

                // 显示加载状态
                fetchFaviconBtn.innerHTML = '<span class="spinner"></span> 抓取中...';
                fetchFaviconBtn.disabled = true;

                // 使用本地图标抓取服务
                fetch(`local_favicon.php?url=${encodeURIComponent(url)}&t=${new Date().getTime()}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP错误: ${response.status}`);
                        }

                        return response.json().catch(err => {
                            console.error('解析JSON失败:', err);
                            // 使用本地备用方法
                            try {
                                const domain = new URL(url).hostname || url;
                                return {
                                    success: true,
                                    icon_url: `https://${domain}/favicon.ico`
                                };
                            } catch (urlError) {
                                console.error('解析URL失败:', urlError);
                                throw new Error('无法解析URL');
                            }
                        });
                    })
                    .then(data => {
                        if (data.error) {
                            console.error('抓取图标失败:', data.error);
                            // 使用直接的favicon.ico路径
                            try {
                                const domain = new URL(url).hostname || url;
                                const faviconUrl = `https://${domain}/favicon.ico`;
                                console.log('使用直接favicon路径:', faviconUrl);
                                iconUrlInput.value = faviconUrl;
                                updateFaviconPreview();
                            } catch (urlError) {
                                console.error('解析URL失败:', urlError);
                                alert('抓取图标失败: ' + data.error);
                            }
                            return;
                        }

                        if (data.success && data.icon_url) {
                            console.log('成功抓取图标:', data.icon_url);
                            // 更新图标URL输入框
                            iconUrlInput.value = data.icon_url;
                            // 更新预览
                            updateFaviconPreview();
                        } else {
                            console.error('抓取图标返回了意外的数据格式:', data);
                            // 使用直接的favicon.ico路径
                            try {
                                const domain = new URL(url).hostname || url;
                                const faviconUrl = `https://${domain}/favicon.ico`;
                                console.log('使用直接favicon路径:', faviconUrl);
                                iconUrlInput.value = faviconUrl;
                                updateFaviconPreview();
                            } catch (urlError) {
                                console.error('解析URL失败:', urlError);
                                alert('抓取图标失败: 服务器返回了意外的数据格式');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('抓取图标出错:', error);

                        // 使用直接的favicon.ico路径
                        try {
                            const domain = new URL(url).hostname || url;
                            const faviconUrl = `https://${domain}/favicon.ico`;
                            console.log('使用直接favicon路径:', faviconUrl);
                            iconUrlInput.value = faviconUrl;
                            updateFaviconPreview();
                        } catch (urlError) {
                            console.error('解析URL失败:', urlError);
                            alert('抓取图标时发生错误，请重试: ' + error.message);
                        }
                    })
                    .finally(() => {
                        // 恢复按钮状态
                        fetchFaviconBtn.innerHTML = '抓取图标';
                        fetchFaviconBtn.disabled = false;
                    });
            });
        }

        // 更新图标预览
        function updateFaviconPreview() {
            const iconUrl = iconUrlInput.value.trim();
            if (iconUrl) {
                faviconPreviewImg.src = iconUrl;
                faviconPreviewImg.onload = function() {
                    faviconPreview.style.display = 'block';
                };
                faviconPreviewImg.onerror = function() {
                    faviconPreview.style.display = 'none';
                };
            } else {
                faviconPreview.style.display = 'none';
            }
        }
    });
    </script>

    <style>
    .input-group {
        display: flex;
    }
    .input-group input {
        flex: 1;
        margin-right: 5px;
    }
    .spinner {
        display: inline-block;
        width: 12px;
        height: 12px;
        border: 2px solid rgba(0, 0, 0, 0.1);
        border-top-color: #333;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    </style>
</body>
</html>