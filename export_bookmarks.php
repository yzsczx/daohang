<?php
// dh/export_bookmarks.php
declare(strict_types=1);

require_once __DIR__ . '/app/core/config.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/functions.php'; // Includes getUserPages, getPageColumns, etc.

$db = getDBConnection();

if (!isLoggedIn()) {
    redirect('index.php');
}
$current_user_id = getCurrentUserId($db);
if ($current_user_id === null) {
    redirect('index.php?action=logout');
}

$user_pages = getUserPages($db, $current_user_id);
$export_error = null;

// --- 处理导出请求 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_action'])) {
    $selected_page_ids = $_POST['page_ids'] ?? []; // Array of page IDs to export
    $export_all = isset($_POST['export_all_pages']) && $_POST['export_all_pages'] === '1';

    if (!$export_all && empty($selected_page_ids)) {
        $export_error = "请至少选择一个页面进行导出，或选择导出所有页面。";
    } else {
        $pages_to_export = [];
        if ($export_all) {
            $pages_to_export = $user_pages; // Already fetched
        } else {
            foreach ($user_pages as $page) {
                if (in_array($page['id'], $selected_page_ids)) {
                    // Verify page belongs to user (already implicitly done by getUserPages)
                    $pages_to_export[] = $page;
                }
            }
        }

        if (empty($pages_to_export)) {
            $export_error = "没有找到可导出的有效页面。";
        } else {
            // --- 开始生成 HTML 书签内容 ---
            $output = "<!DOCTYPE NETSCAPE-Bookmark-file-1>\n";
            $output .= "<!-- This is an automatically generated file.\n";
            $output .= "     It will be read and overwritten.\n";
            $output .= "     DO NOT EDIT! -->\n";
            $output .= "<META HTTP-EQUIV=\"Content-Type\" CONTENT=\"text/html; charset=UTF-8\">\n";
            $output .= "<TITLE>Bookmarks</TITLE>\n";
            $output .= "<H1>Bookmarks</H1>\n\n";
            $output .= "<DL><p>\n";

            foreach ($pages_to_export as $page) {
                $page_add_date = strtotime($page['created_at']); // Convert to timestamp
                $output .= "    <DT><H3 ADD_DATE=\"{$page_add_date}\" LAST_MODIFIED=\"{$page_add_date}\">" . escape_html($page['title']) . "</H3></DT>\n";
                $output .= "    <DL><p>\n";

                $columns = getPageColumns($db, (int)$page['id']);
                foreach ($columns as $column) {
                    $blocks = getColumnBlocks($db, (int)$column['id']);
                    foreach ($blocks as $block) {
                        if ($block['type'] === 'links') {
                            $block_add_date = strtotime($block['created_at']);
                            // Treat block as a sub-folder
                            $output .= "        <DT><H3 ADD_DATE=\"{$block_add_date}\" LAST_MODIFIED=\"{$block_add_date}\">" . escape_html($block['title']) . "</H3></DT>\n";
                            $output .= "        <DL><p>\n";

                            $links = getBlockLinks($db, (int)$block['id']);
                            foreach ($links as $link) {
                                $link_add_date = strtotime($link['created_at']);
                                $icon_attr = !empty($link['icon_url']) ? " ICON=\"" . escape_html($link['icon_url']) . "\"" : "";
                                // Description can be added as a non-standard attribute or in comments,
                                // but most browsers don't use it. We'll skip it for simplicity here.
                                $output .= "            <DT><A HREF=\"" . escape_html($link['url']) . "\" ADD_DATE=\"{$link_add_date}\"{$icon_attr}>" . escape_html($link['title']) . "</A></DT>\n";
                            }
                            $output .= "        </DL><p></p>\n"; // Close block's DL
                        }
                        // TODO: Optionally handle other block types, e.g., notes as text files within a zip, or skip.
                    }
                }
                $output .= "    </DL><p></p>\n"; // Close page's DL
            }
            $output .= "</DL><p></p>\n"; // Close main DL

            // --- 发送文件给浏览器 ---
            $filename = "my_bookmarks_export_" . date('Y-m-d') . ".html";
            header('Content-Description: File Transfer');
            header('Content-Type: text/html; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . strlen($output)); // Use strlen for byte length
            ob_clean(); // Clean (erase) the output buffer
            flush();    // Flush system output buffer
            echo $output;
            exit;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>导出书签 - <?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Re-use .container, .page-header, .btn, .form-group, .alert from manage_pages styles */
        .container { max-width: 600px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .page-header h1 { margin-top:0; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="checkbox"] { width: auto; margin-right: 5px; vertical-align: middle;}
        .form-group .page-list label { font-weight: normal; margin-left: 5px;}
        .btn { display: inline-block; padding: 10px 15px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .btn:hover { background-color: #0056b3; }
        .alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        #page_selection_options { margin-top: 10px; padding-left: 20px; border-left: 2px solid #eee; }
    </style>
</head>
<body>
    <header>
        <h1><?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?> - 导出书签</h1>
        <nav class="user-actions">
            <a href="index.php">返回主页</a>
            <a href="import_bookmarks.php">导入书签</a>
            <a href="index.php?action=logout">登出</a>
        </nav>
    </header>

    <div class="container">
        <div class="page-header">
            <h2>选择要导出的导航页面</h2>
        </div>

        <?php if ($export_error): ?>
            <div class="alert alert-danger"><?php echo $export_error; ?></div>
        <?php endif; ?>

        <p>导出的文件将是 HTML 格式，可以被大多数浏览器重新导入。</p>

        <?php if (!empty($user_pages)): ?>
            <form action="export_bookmarks.php" method="POST">
                <div class="form-group">
                    <input type="checkbox" id="export_all_pages" name="export_all_pages" value="1" onchange="togglePageSelection(this.checked)">
                    <label for="export_all_pages"><strong>导出所有页面</strong></label>
                </div>

                <div id="page_selection_options">
                    <p>或者，选择特定页面导出：</p>
                    <div class="form-group page-list">
                        <?php foreach ($user_pages as $page): ?>
                            <div>
                                <input type="checkbox" id="page_<?php echo (int)$page['id']; ?>" name="page_ids[]" value="<?php echo (int)$page['id']; ?>">
                                <label for="page_<?php echo (int)$page['id']; ?>"><?php echo escape_html($page['title']); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" name="export_action" value="export" class="btn">导出选中书签</button>
            </form>
        <?php else: ?>
            <p>您还没有创建任何导航页面可供导出。</p>
        <?php endif; ?>
    </div>

    <script>
        function togglePageSelection(exportAllChecked) {
            var pageSelectionDiv = document.getElementById('page_selection_options');
            var pageCheckboxes = pageSelectionDiv.getElementsByTagName('input');
            if (exportAllChecked) {
                pageSelectionDiv.style.display = 'none';
                for (var i = 0; i < pageCheckboxes.length; i++) {
                    pageCheckboxes[i].checked = false; // Uncheck individual pages if "all" is selected
                    pageCheckboxes[i].disabled = true;
                }
            } else {
                pageSelectionDiv.style.display = 'block';
                 for (var i = 0; i < pageCheckboxes.length; i++) {
                    pageCheckboxes[i].disabled = false;
                }
            }
        }
        // Initialize on page load
        var exportAllCheckbox = document.getElementById('export_all_pages');
        if (exportAllCheckbox) { // Ensure element exists
             togglePageSelection(exportAllCheckbox.checked);
        }
    </script>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?></p>
    </footer>
</body>
</html>