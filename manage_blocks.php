<?php
// dh/manage_blocks.php
declare(strict_types=1);

require_once __DIR__ . '/app/core/config.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/functions.php';
require_once __DIR__ . '/app/core/admin_check.php'; // 添加管理员检查

$db = getDBConnection();

if (!isLoggedIn()) {
    redirect('index.php');
}
$current_user_id = getCurrentUserId($db);
if ($current_user_id === null) {
    redirect('index.php?action=logout');
}

$action = $_GET['action'] ?? $_POST['action_manage_block'] ?? 'list_placeholder'; // Default to a placeholder
$column_id_param = isset($_GET['column_id']) ? (int)$_GET['column_id'] : (isset($_POST['column_id']) ? (int)$_POST['column_id'] : null);
$block_id_param = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] :
                  (isset($_POST['block_id']) ? (int)$_POST['block_id'] :
                  (isset($_GET['delete_id']) ? (int)$_GET['delete_id'] : null));
$page_id_redirect = isset($_REQUEST['page_id']) ? (int)$_REQUEST['page_id'] : null; // For redirecting back

$block_to_edit = null;
$block_to_confirm_delete = null;
$target_column_data_for_block = null;

$form_error = $_SESSION['form_error'] ?? null;
$form_success = $_SESSION['form_success'] ?? null;
unset($_SESSION['form_error'], $_SESSION['form_success']);

$csrf_token = generateCsrfToken(); // From functions.php

// Fetch column data if column_id is present (for adding or context)
// Also fetch its page_id for redirecting
if ($column_id_param) {
    $stmtCol = $db->prepare(
        "SELECT c.id as column_id, c.page_id, p.title as page_title
         FROM columns c
         JOIN pages p ON c.page_id = p.id
         WHERE c.id = ? AND p.user_id = ?"
    );
    $stmtCol->execute([$column_id_param, $current_user_id]);
    $target_column_data_for_block = $stmtCol->fetch();
    if ($target_column_data_for_block) {
        if ($page_id_redirect === null) { // If not passed in request, get from fetched column data
            $page_id_redirect = (int)$target_column_data_for_block['page_id'];
        }
    } else {
        $_SESSION['form_error'] = "指定的列无效或不属于您 (ID: {$column_id_param})。";
        redirect($page_id_redirect ? 'index.php?page_id=' . $page_id_redirect : 'index.php');
    }
}


// --- 处理 POST 请求 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['form_error'] = '无效的请求或会话已过期。';
        redirect('manage_blocks.php?action=' . $action . ($column_id_param ? '&column_id='.$column_id_param : '') . ($block_id_param ? '&edit_id='.$block_id_param : '') . ($page_id_redirect ? '&page_id='.$page_id_redirect : ''));
    }

    if ($action === 'add_submit' && $column_id_param && $target_column_data_for_block) {
        $block_title = trim($_POST['block_title'] ?? '');
        $block_type = $_POST['block_type'] ?? '';

        if (empty($block_title)) {
            $_SESSION['form_error'] = '区块标题不能为空。';
        } elseif (empty($block_type) || !in_array($block_type, ['links', 'notes', 'weather', 'clock_calendar', 'search_box'])) {
            $_SESSION['form_error'] = '请选择一个有效的区块类型。';
        } else {
            try {
                $maxOrderStmt = $db->prepare("SELECT MAX(display_order) as max_order FROM blocks WHERE column_id = ?");
                $maxOrderStmt->execute([$column_id_param]);
                $maxOrder = $maxOrderStmt->fetchColumn();
                $newBlockOrder = ($maxOrder === null) ? 0 : (int)$maxOrder + 1;

                $config_json = null;
                if ($block_type === 'notes') {
                    $config_json = json_encode(['content' => '']);
                }
                // TODO: Default config for other types

                $stmt = $db->prepare("INSERT INTO blocks (column_id, title, type, display_order, config_json) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$column_id_param, $block_title, $block_type, $newBlockOrder, $config_json]);
                $_SESSION['form_success'] = '区块 "' . escape_html($block_title) . '" 添加成功！';
                redirect('index.php?page_id=' . $page_id_redirect);

            } catch (PDOException $e) {
                error_log("Error adding block: " . $e->getMessage());
                $_SESSION['form_error'] = '添加区块时发生数据库错误。';
            }
        }
        redirect('manage_blocks.php?action=add&column_id=' . $column_id_param . ($page_id_redirect ? '&page_id='.$page_id_redirect : ''));

    } elseif ($action === 'edit_submit' && $block_id_param) {
        $block_title = trim($_POST['block_title'] ?? '');
        $block_type = $_POST['block_type'] ?? ''; // Usually type is not editable, but title and config are
        $config_content = $_POST['config_content'] ?? null; // For notes, etc.

        if (empty($block_title)) {
            $_SESSION['form_error'] = '区块标题不能为空。';
        } else {
            try {
                // Verify block ownership
                $verifyStmt = $db->prepare("SELECT b.id, b.type, c.page_id FROM blocks b JOIN columns c ON b.column_id = c.id JOIN pages p ON c.page_id = p.id WHERE b.id = ? AND p.user_id = ?");
                $verifyStmt->execute([$block_id_param, $current_user_id]);
                $block_data_for_edit = $verifyStmt->fetch();

                if ($block_data_for_edit) {
                    $config_json_update = null;
                    if ($block_data_for_edit['type'] === 'notes' && $config_content !== null) {
                        $config_json_update = json_encode(['content' => $config_content]);
                    }
                    // TODO: Handle config for other widget types

                    if ($config_json_update !== null) {
                        $stmt = $db->prepare("UPDATE blocks SET title = ?, config_json = ? WHERE id = ?");
                        $stmt->execute([$block_title, $config_json_update, $block_id_param]);
                    } else {
                        $stmt = $db->prepare("UPDATE blocks SET title = ? WHERE id = ?");
                        $stmt->execute([$block_title, $block_id_param]);
                    }
                    $_SESSION['form_success'] = '区块 "' . escape_html($block_title) . '" 更新成功！';
                    if($page_id_redirect === null && $block_data_for_edit) $page_id_redirect = $block_data_for_edit['page_id']; // get page_id if not passed
                    redirect('index.php?page_id=' . $page_id_redirect);
                } else {
                    $_SESSION['form_error'] = '无法编辑此区块或区块不存在。';
                }
            } catch (PDOException $e) {
                error_log("Error editing block: " . $e->getMessage());
                $_SESSION['form_error'] = '编辑区块时发生数据库错误。';
            }
        }
        redirect('manage_blocks.php?action=edit&edit_id=' . $block_id_param . ($page_id_redirect ? '&page_id='.$page_id_redirect : ''));


    } elseif ($action === 'delete' && isset($_GET['confirm']) && $_GET['confirm'] === '1' && $block_id_param) { // Changed to GET for confirm link
         if (!isset($_GET['token']) || !verifyCsrfToken($_GET['token'])) { // CSRF for GET delete
            $_SESSION['form_error'] = '无效的删除请求。';
        } else {
            try {
                $verifyStmt = $db->prepare("SELECT b.title, c.page_id FROM blocks b JOIN columns c ON b.column_id = c.id JOIN pages p ON c.page_id = p.id WHERE b.id = ? AND p.user_id = ?");
                $verifyStmt->execute([$block_id_param, $current_user_id]);
                $block_to_delete_data = $verifyStmt->fetch();

                if ($block_to_delete_data) {
                    $stmt = $db->prepare("DELETE FROM blocks WHERE id = ?"); // Links will cascade delete
                    $stmt->execute([$block_id_param]);
                    $_SESSION['form_success'] = '区块 "' . escape_html($block_to_delete_data['title']) . '" 已删除。';
                    if($page_id_redirect === null) $page_id_redirect = $block_to_delete_data['page_id'];
                } else {
                    $_SESSION['form_error'] = '无法删除此区块或区块不存在。';
                }
            } catch (PDOException $e) {
                error_log("Error deleting block: " . $e->getMessage());
                $_SESSION['form_error'] = '删除区块时发生数据库错误。';
            }
        }
        redirect('index.php?page_id=' . $page_id_redirect);
    }
}


// --- Prepare data for 'edit' or 'delete' confirmation views ---
if ($action === 'edit' && $block_id_param) {
    $stmt = $db->prepare(
        "SELECT b.*, c.page_id
         FROM blocks b JOIN columns c ON b.column_id = c.id
         JOIN pages p ON c.page_id = p.id
         WHERE b.id = ? AND p.user_id = ?"
    );
    $stmt->execute([$block_id_param, $current_user_id]);
    $block_to_edit = $stmt->fetch();
    if ($block_to_edit) {
        if ($page_id_redirect === null) $page_id_redirect = $block_to_edit['page_id'];
        if ($column_id_param === null) $column_id_param = $block_to_edit['column_id']; // Set column_id for context
    } else {
        $_SESSION['form_error'] = '要编辑的区块未找到或不属于您。';
        redirect('index.php' . ($page_id_redirect ? '?page_id='.$page_id_redirect : ''));
    }
}

// Note: Delete confirmation is now handled by JS confirm on index.php,
// and then direct GET link with confirm=1 and token. So no specific delete view here.

$page_title_for_header = "管理区块";
if ($action === 'add' && $target_column_data_for_block) {
    $page_title_for_header = "添加区块到页面: " . getPageTitleById($db, $page_id_redirect, $current_user_id);
} elseif ($action === 'edit' && $block_to_edit) {
    $page_title_for_header = "编辑区块: " . escape_html($block_to_edit['title']);
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
            <a href="manage_pages.php" class="btn btn-sm btn-secondary">管理页面</a>
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

        <?php if ($action === 'add' && $target_column_data_for_block): ?>
            <form method="POST" action="manage_blocks.php?page_id=<?php echo (int)$page_id_redirect; ?>">
                <input type="hidden" name="action_manage_block" value="add_submit">
                <input type="hidden" name="column_id" value="<?php echo (int)$column_id_param; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">

                <div class="form-group">
                    <label for="block_title">区块标题:</label>
                    <input type="text" id="block_title" name="block_title" value="<?php echo escape_html($_POST['block_title'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="block_type">区块类型:</label>
                    <select id="block_type" name="block_type" required>
                        <option value="">-- 选择类型 --</option>
                        <option value="links" <?php echo (($_POST['block_type'] ?? '') === 'links' ? 'selected' : ''); ?>>链接列表</option>
                        <option value="notes" <?php echo (($_POST['block_type'] ?? '') === 'notes' ? 'selected' : ''); ?>>笔记</option>
                        <option value="weather" <?php echo (($_POST['block_type'] ?? '') === 'weather' ? 'selected' : ''); ?>>天气</option>
                        <option value="clock_calendar" <?php echo (($_POST['block_type'] ?? '') === 'clock_calendar' ? 'selected' : ''); ?>>时钟日历</option>
                        <option value="search_box" <?php echo (($_POST['block_type'] ?? '') === 'search_box' ? 'selected' : ''); ?>>搜索框</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">添加区块</button>
                <a href="index.php<?php echo $page_id_redirect ? '?page_id=' . $page_id_redirect : ''; ?>" class="btn btn-secondary" style="margin-left:10px;">取消</a>
            </form>

        <?php elseif ($action === 'edit' && $block_to_edit): ?>
            <form method="POST" action="manage_blocks.php?page_id=<?php echo (int)$page_id_redirect; ?>">
                <input type="hidden" name="action_manage_block" value="edit_submit">
                <input type="hidden" name="block_id" value="<?php echo (int)$block_to_edit['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">

                <div class="form-group">
                    <label for="block_title_edit">区块标题:</label>
                    <input type="text" id="block_title_edit" name="block_title" value="<?php echo escape_html($block_to_edit['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label>区块类型:</label>
                    <p><strong><?php echo escape_html(ucfirst($block_to_edit['type'])); ?></strong> (类型不可更改)</p>
                    <input type="hidden" name="block_type" value="<?php echo escape_html($block_to_edit['type']); ?>">
                </div>

                <?php if ($block_to_edit['type'] === 'notes'): ?>
                    <div class="form-group">
                        <label for="config_content_note">笔记内容:</label>
                        <?php $note_config_edit = json_decode($block_to_edit['config_json'] ?: '{}', true); ?>
                        <textarea id="config_content_note" name="config_content" rows="8" class="form-control"><?php echo escape_html($note_config_edit['content'] ?? ''); ?></textarea>
                    </div>
                <?php endif; ?>
                <?php // TODO: Add configuration fields for other widget types (weather city, search engine list etc.) ?>

                <button type="submit" class="btn btn-success">保存更改</button>
                <a href="index.php<?php echo $page_id_redirect ? '?page_id=' . $page_id_redirect : ''; ?>" class="btn btn-secondary" style="margin-left:10px;">取消</a>
            </form>

        <?php else: ?>
            <p class="alert alert-info">请通过主页面的列操作来添加或编辑区块。</p>
            <p><a href="index.php" class="btn btn-primary">返回首页</a></p>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?></p>
    </footer>
</body>
</html>