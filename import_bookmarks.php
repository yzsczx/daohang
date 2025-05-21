<?php
// dh/import_bookmarks.php
declare(strict_types=1);

// 确保所有核心文件都被正确包含在最顶部
require_once __DIR__ . '/app/core/config.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/functions.php';
require_once __DIR__ . '/app/core/BookmarkParser.php'; // 原始解析器
require_once __DIR__ . '/app/core/SimpleBookmarkParser.php'; // 新的简单解析器

$db = getDBConnection();

if (!isLoggedIn()) { redirect('index.php'); }
$current_user_id = getCurrentUserId($db);
if ($current_user_id === null) { /* ... error_log and redirect ... */ redirect('index.php?action=logout'); }
try { /* ... user_id DB check ... */
    $userCheckStmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $userCheckStmt->execute([$current_user_id]);
    if (!$userCheckStmt->fetch()) { throw new Exception("当前用户数据异常。"); }
} catch (Exception $e) { /* ... error_log and redirect ... */ $_SESSION['import_error'] = $e->getMessage(); redirect('import_bookmarks.php');}


$user_pages = getUserPages($db, $current_user_id);
$import_message = $_SESSION['import_message'] ?? null;
$import_error = $_SESSION['import_error'] ?? null;
unset($_SESSION['import_message'], $_SESSION['import_error']);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bookmark_file'])) {
    if ($_FILES['bookmark_file']['error'] === UPLOAD_ERR_OK) {
        // ... (file validation: size, extension - same as before) ...
        if (!in_array(strtolower(pathinfo($_FILES['bookmark_file']['name'], PATHINFO_EXTENSION)), ['html', 'htm'])) { $_SESSION['import_error'] = '请上传 .html 文件。'; redirect('import_bookmarks.php'); }
        if ($_FILES['bookmark_file']['size'] > 10 * 1024 * 1024) { $_SESSION['import_error'] = '文件过大。'; redirect('import_bookmarks.php'); }


        $file_tmp_path = $_FILES['bookmark_file']['tmp_name'];
        $import_to_page_id_post = isset($_POST['import_to_page_id']) ? (int)$_POST['import_to_page_id'] : null;
        $new_page_title_post = trim($_POST['new_page_title'] ?? '');
        $create_new_page_post = isset($_POST['create_new_page']) && $_POST['create_new_page'] === '1';
        $target_page_id = null;

        try {
            $db->beginTransaction();
            // 1. Determine target Page ID (same as before)
            if ($create_new_page_post) { /* ... create page ... */
                $p_title = !empty($new_page_title_post) ? $new_page_title_post : "导入 (" . basename($_FILES['bookmark_file']['name'], ".".strtolower(pathinfo($_FILES['bookmark_file']['name'], PATHINFO_EXTENSION))) . ")";
                // ... (set $target_page_id)
                $stmt_page = $db->prepare("INSERT INTO pages (user_id, title, display_order) VALUES (?, ?, (SELECT IFNULL(MAX(display_order), -1) + 1 FROM pages p2 WHERE user_id = ?))");
                $stmt_page->execute([$current_user_id, $p_title, $current_user_id]);
                $target_page_id = (int)$db->lastInsertId();

            } elseif ($import_to_page_id_post) { /* ... verify page ... */
                $s_vp = $db->prepare("SELECT id FROM pages WHERE id = ? AND user_id = ?");
                $s_vp->execute([$import_to_page_id_post, $current_user_id]);
                if ($pr = $s_vp->fetch()) { $target_page_id = (int)$pr['id']; }
                else { throw new Exception("选择的页面无效。"); }
            } else { throw new Exception("未选择目标页面。"); }
            if (!$target_page_id) throw new Exception("无法确定目标页面。");


            // 2. Determine target Column ID (same as before)
            $target_column_id = null;
            $page_columns = getPageColumns($db, $target_page_id);
            if (!empty($page_columns)) { $target_column_id = (int)$page_columns[0]['id']; }
            else {
                $stmt_c = $db->prepare("INSERT INTO columns (page_id, width_percentage, display_order) VALUES (?, ?, 0)");
                $stmt_c->execute([$target_page_id, '100%']);
                $target_column_id = (int)$db->lastInsertId();
            }
            if (!$target_column_id) { throw new Exception("无法确定或创建目标列。"); }


            // 3. 尝试使用新的简单解析器
            error_log("Import Main: 尝试使用 SimpleBookmarkParser 导入文件: " . $_FILES['bookmark_file']['name']);
            try {
                $import_stats = SimpleBookmarkParser::importToDatabase($file_tmp_path, $current_user_id, $db, $target_page_id, $target_column_id);
                error_log("Import Main: SimpleBookmarkParser 成功导入，文件夹: " . $import_stats['folders'] . ", 链接: " . $import_stats['links']);
            } catch (Exception $simpleParserEx) {
                // 如果简单解析器失败，尝试使用原始解析器
                error_log("Import Main: SimpleBookmarkParser 失败: " . $simpleParserEx->getMessage() . "，尝试使用原始解析器");
                $import_stats = BookmarkParser::importToDatabase($file_tmp_path, $current_user_id, $db, $target_page_id, $target_column_id);
            }

            $total_folders_imported = $import_stats['folders'];
            $total_links_imported = $import_stats['links'];

            if ($total_links_imported == 0 && $total_folders_imported == 0) {
                 $_SESSION['import_error'] = '未能从文件中解析出任何书签或文件夹。请检查文件格式或错误日志。';
                 if($db->inTransaction()) $db->rollBack();
            } else {
                $db->commit();
                $_SESSION['import_message'] = "书签导入成功！创建了 {$total_folders_imported} 个文件夹 (区块) 并导入了 {$total_links_imported} 个链接。";
                redirect('index.php?page_id=' . $target_page_id);
            }

        } catch (Exception $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            $_SESSION['import_error'] = '导入书签时发生错误 (主流程): ' . escape_html($e->getMessage());
            error_log("Bookmark import Main Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
    } else {
        $_SESSION['import_error'] = '文件上传失败，错误代码: ' . $_FILES['bookmark_file']['error'];
    }
    redirect('import_bookmarks.php');
}

// --- HTML Form (保持与之前版本相同) ---
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>导入书签 - <?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Styles from previous version */
        .container { max-width: 600px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .page-header h1, .page-header h2 { margin-top:0; }
        /* ... (rest of the styles for form, alerts, header, footer from previous version) ... */
         .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        .form-group input[type="file"], .form-group input[type="text"], .form-group select {
            display: block; width: 100%; padding: 0.5rem 0.75rem; font-size: 1rem; line-height: 1.5;
            color: #495057; background-color: #fff; background-clip: padding-box;
            border: 1px solid #ced4da; border-radius: 0.25rem; box-sizing: border-box;
        }
        .form-group input[type="checkbox"] { width: auto; margin-right: 0.3rem; vertical-align: middle;}
        .form-text {font-size: 0.875em; color: #6c757d;}
        .btn { display: inline-block; padding: 10px 15px; font-size: 1rem; font-weight: 400; line-height: 1.5; text-align: center;
               text-decoration: none; vertical-align: middle; cursor: pointer; user-select: none;
               border: 1px solid transparent; border-radius: 0.25rem;
               color: white; background-color: #007bff; border-color: #007bff; }
        .btn:hover { background-color: #0056b3; border-color: #0056b3; }
        .alert { padding: 0.75rem 1.25rem; margin-bottom: 1rem; border: 1px solid transparent; border-radius: 0.25rem; }
        .alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        #page_selection_options { margin-top: 10px; padding-left: 20px; border-left: 2px solid #eee; }
        header { background-color: #2c3e50; color: #ecf0f1; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        header h1 { font-size: 1.6em; margin:0; font-weight: 600;}
        header .user-actions a { color: #ecf0f1; text-decoration: none; margin-left: 15px; font-size: 0.9em; padding: 5px 8px; border-radius: 3px; }
        header .user-actions a:hover { background-color: #4a6b8c; text-decoration: none; }
        footer { text-align: center; padding: 20px; margin-top: 40px; background-color: #343a40; color: #f8f9fa; font-size: 0.9em;}
    </style>
</head>
<body>
    <header>
         <h1><?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?> - 导入书签</h1>
        <nav class="user-actions">
            <a href="index.php" class="btn btn-sm btn-secondary">返回主页</a>
            <a href="manage_pages.php" class="btn btn-sm btn-secondary">管理页面</a>
            <a href="index.php?action=logout" class="btn btn-sm btn-secondary">登出</a>
        </nav>
    </header>

    <div class="container">
        <div class="page-header">
            <h2>从浏览器导入书签</h2>
        </div>

        <?php if ($import_error): ?>
            <div class="alert alert-danger"><?php echo $import_error; ?></div>
        <?php endif; ?>
        <?php if ($import_message): ?>
            <div class="alert alert-success"><?php echo escape_html($import_message); ?></div>
        <?php endif; ?>

        <p>您可以从大多数浏览器（如 Chrome, Firefox, Edge）导出书签为 HTML 文件，然后在此处上传。</p>
        <p>导入的文件夹将创建为新的链接区块，链接将添加到相应的区块中。</p>

        <form action="import_bookmarks.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="bookmark_file">选择书签HTML文件:</label>
                <input type="file" id="bookmark_file" name="bookmark_file" class="form-control-file" accept=".html,.htm" required>
            </div>

            <div class="form-group">
                <input type="checkbox" id="create_new_page" name="create_new_page" value="1" checked onchange="toggleImportOptions()">
                <label for="create_new_page" style="font-weight:normal; margin-left:5px;">为导入的书签创建一个新页面</label>
            </div>

            <div class="form-group" id="new_page_options">
                <label for="new_page_title">新页面标题 (可选):</label>
                <input type="text" id="new_page_title" name="new_page_title" class="form-control" placeholder="例如：导入的书签 (文件名)">
            </div>

            <div class="form-group" id="existing_page_options" style="display:none;">
                <label for="import_to_page_id">或，选择一个现有页面导入:</label>
                <select id="import_to_page_id" name="import_to_page_id" class="form-control">
                    <option value="">-- 选择页面 --</option>
                    <?php if (!empty($user_pages)): ?>
                        <?php foreach ($user_pages as $page_item_form_sel): ?>
                            <option value="<?php echo (int)$page_item_form_sel['id']; ?>"><?php echo escape_html($page_item_form_sel['title']); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                 <small class="form-text text-muted">书签将导入到此选定页面的一个新列或第一个现有列中。</small>
            </div>

            <button type="submit" class="btn btn-primary">开始导入</button>
        </form>
    </div>

    <script>
        function toggleImportOptions() { /* ... JS from previous version ... */
            var createNewPageCheckbox = document.getElementById('create_new_page');
            var newPageOptionsDiv = document.getElementById('new_page_options');
            var existingPageOptionsDiv = document.getElementById('existing_page_options');
            var selectPageDropdown = document.getElementById('import_to_page_id');
            var newPageTitleInput = document.getElementById('new_page_title');

            if (createNewPageCheckbox.checked) {
                newPageOptionsDiv.style.display = 'block';
                existingPageOptionsDiv.style.display = 'none';
                if(selectPageDropdown) selectPageDropdown.value = '';
                if(selectPageDropdown) selectPageDropdown.required = false;
                if(newPageTitleInput) newPageTitleInput.required = false;
            } else {
                newPageOptionsDiv.style.display = 'none';
                existingPageOptionsDiv.style.display = 'block';
                if(selectPageDropdown) selectPageDropdown.required = true;
                if(newPageTitleInput) newPageTitleInput.value = '';
            }
        }
        document.addEventListener('DOMContentLoaded', function() {
            var createPageChk = document.getElementById('create_new_page');
            if (createPageChk) { toggleImportOptions(); }
        });
    </script>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?></p>
    </footer>
</body>
</html>