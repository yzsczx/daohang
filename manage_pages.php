<?php
// dh/manage_pages.php
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

$action = $_GET['action'] ?? $_POST['action_manage_page'] ?? 'list';
$page_id_param = isset($_GET['edit_id']) ? (int)$_GET['edit_id'] : (isset($_POST['page_id']) ? (int)$_POST['page_id'] : (isset($_GET['delete_id']) ? (int)$_GET['delete_id'] : null));
$page_to_edit = null;
$page_confirm_delete = null;

$form_error = $_SESSION['form_error'] ?? null;
$form_success = $_SESSION['form_success'] ?? null;
unset($_SESSION['form_error'], $_SESSION['form_success']);

// Generate CSRF token if not exists
$csrf_token = generateCsrfToken();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['form_error'] = '无效的请求或会话已过期，请重试。';
        redirect('manage_pages.php?action=' . $action . ($page_id_param ? '&edit_id=' . $page_id_param : ''));
    }

    if ($action === 'add_submit' || $action === 'edit_submit') {
        $page_title = trim($_POST['page_title'] ?? '');
        $display_order = isset($_POST['display_order']) ? (int)$_POST['display_order'] : 0;

        if (empty($page_title)) {
            $_SESSION['form_error'] = '页面标题不能为空。';
        } else {
            try {
                if ($action === 'add_submit') {
                    $stmt = $db->prepare("INSERT INTO pages (user_id, title, display_order) VALUES (?, ?, ?)");
                    $stmt->execute([$current_user_id, $page_title, $display_order]);
                    $_SESSION['form_success'] = '页面 "' . escape_html($page_title) . '" 添加成功！';
                } elseif ($action === 'edit_submit' && $page_id_param) {
                    $verifyStmt = $db->prepare("SELECT id FROM pages WHERE id = ? AND user_id = ?");
                    $verifyStmt->execute([$page_id_param, $current_user_id]);
                    if ($verifyStmt->fetch()) {
                        $stmt = $db->prepare("UPDATE pages SET title = ?, display_order = ? WHERE id = ? AND user_id = ?");
                        $stmt->execute([$page_title, $display_order, $page_id_param, $current_user_id]);
                        $_SESSION['form_success'] = '页面 "' . escape_html($page_title) . '" 更新成功！';
                    } else {
                        $_SESSION['form_error'] = '无法编辑此页面或页面不存在。';
                    }
                }
                redirect('manage_pages.php');
            } catch (PDOException $e) {
                error_log("Error managing page (add/edit): " . $e->getMessage());
                $_SESSION['form_error'] = '操作页面时发生数据库错误。';
            }
        }
        // Redirect back to form on error
        $redirect_url = 'manage_pages.php?action=' . ($action === 'add_submit' ? 'add' : 'edit');
        if ($action === 'edit_submit' && $page_id_param) $redirect_url .= '&edit_id=' . $page_id_param;
        redirect($redirect_url);

    } elseif ($action === 'delete_confirm') {
        $page_id_to_delete_post = isset($_POST['page_id_to_delete']) ? (int)$_POST['page_id_to_delete'] : null;
        if ($page_id_to_delete_post) {
            try {
                $verifyStmt = $db->prepare("SELECT title FROM pages WHERE id = ? AND user_id = ?");
                $verifyStmt->execute([$page_id_to_delete_post, $current_user_id]);
                $page_to_delete_data = $verifyStmt->fetch();

                if ($page_to_delete_data) {
                    $userSettings = getUserSettings($db, $current_user_id);
                    if ($userSettings && isset($userSettings['default_page_id']) && $userSettings['default_page_id'] == $page_id_to_delete_post) {
                        $updateSettingsStmt = $db->prepare("UPDATE settings SET default_page_id = NULL WHERE user_id = ?");
                        $updateSettingsStmt->execute([$current_user_id]);
                    }
                    $stmt = $db->prepare("DELETE FROM pages WHERE id = ? AND user_id = ?");
                    $stmt->execute([$page_id_to_delete_post, $current_user_id]);
                    $_SESSION['form_success'] = '页面 "' . escape_html($page_to_delete_data['title']) . '" 已删除。';
                } else {
                    $_SESSION['form_error'] = '无法删除此页面或页面不存在。';
                }
            } catch (PDOException $e) {
                error_log("Error deleting page: " . $e->getMessage());
                $_SESSION['form_error'] = '删除页面时发生数据库错误。';
            }
        }
        redirect('manage_pages.php');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'set_default') {
    $page_id_to_set_default = isset($_GET['page_id']) ? (int)$_GET['page_id'] : null;
    // Simple GET CSRF check (can be improved with a real token in URL if needed)
    // if (!isset($_GET['token']) || !verifyCsrfToken($_GET['token'])) {
    // $_SESSION['form_error'] = '无效的请求或会话已过期，请重试。';
    //    redirect('manage_pages.php');
    // }

    if ($page_id_to_set_default) {
        try {
            $verifyPageStmt = $db->prepare("SELECT id, title FROM pages WHERE id = ? AND user_id = ?");
            $verifyPageStmt->execute([$page_id_to_set_default, $current_user_id]);
            $page_data = $verifyPageStmt->fetch();

            if ($page_data) {
                $userSettings = getUserSettings($db, $current_user_id);
                if (!$userSettings) {
                    $insertSettingsStmt = $db->prepare("INSERT INTO settings (user_id, default_page_id) VALUES (?, ?)");
                    $insertSettingsStmt->execute([$current_user_id, $page_id_to_set_default]);
                } else {
                    $updateSettingsStmt = $db->prepare("UPDATE settings SET default_page_id = ? WHERE user_id = ?");
                    $updateSettingsStmt->execute([$page_id_to_set_default, $current_user_id]);
                }
                $_SESSION['form_success'] = '页面 "' . escape_html($page_data['title']) . '" 已设为默认。';
            } else {
                $_SESSION['form_error'] = '无法将此页面设为默认或页面不存在。';
            }
        } catch (PDOException $e) {
            error_log("Error setting default page: " . $e->getMessage());
            $_SESSION['form_error'] = '设置默认页面时发生数据库错误。';
        }
    }
    redirect('manage_pages.php');
}

$pages_list = getUserPages($db, $current_user_id);
$current_default_page_id = null;
$user_settings_for_view = getUserSettings($db, $current_user_id);
if ($user_settings_for_view && isset($user_settings_for_view['default_page_id'])) {
    $current_default_page_id = (int)$user_settings_for_view['default_page_id'];
}

if ($action === 'edit' && $page_id_param) {
    $stmt = $db->prepare("SELECT * FROM pages WHERE id = ? AND user_id = ?");
    $stmt->execute([$page_id_param, $current_user_id]);
    $page_to_edit = $stmt->fetch();
    if (!$page_to_edit) {
        $_SESSION['form_error'] = '要编辑的页面未找到或不属于您。';
        redirect('manage_pages.php');
    }
}

if ($action === 'delete' && $page_id_param) {
    $stmt = $db->prepare("SELECT id, title FROM pages WHERE id = ? AND user_id = ?");
    $stmt->execute([$page_id_param, $current_user_id]);
    $page_confirm_delete = $stmt->fetch();
    if (!$page_confirm_delete) {
        $_SESSION['form_error'] = '要删除的页面未找到或不属于您。';
        redirect('manage_pages.php');
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理导航页面 - <?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="manage-page-header"> <?php // Added class for specific styling if needed ?>
        <h1><?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?> - 页面管理</h1>
        <nav class="user-actions">
            <a href="index.php" class="btn btn-sm btn-secondary">返回主页</a>
            <a href="settings.php" class="btn btn-sm btn-secondary">设置</a>
            <a href="index.php?action=logout" class="btn btn-sm btn-secondary">登出</a>
        </nav>
    </header>

    <div class="container">
        <div class="page-header">
            <h2>导航页面列表</h2>
            <?php if ($action === 'list'): ?>
            <a href="manage_pages.php?action=add" class="btn btn-primary">添加新页面</a>
            <?php endif; ?>
        </div>

        <?php if ($form_error): ?>
            <div class="alert alert-danger"><?php echo $form_error; /* May contain HTML */ ?></div>
        <?php endif; ?>
        <?php if ($form_success): ?>
            <div class="alert alert-success"><?php echo escape_html($form_success); ?></div>
        <?php endif; ?>


        <?php if ($action === 'add' || ($action === 'edit' && $page_to_edit)): ?>
            <h3><?php echo $action === 'add' ? '添加新页面' : '编辑页面: ' . escape_html($page_to_edit['title']); ?></h3>
            <form method="POST" action="manage_pages.php">
                <input type="hidden" name="action_manage_page" value="<?php echo $action === 'add' ? 'add_submit' : 'edit_submit'; ?>">
                <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="page_id" value="<?php echo (int)$page_to_edit['id']; ?>">
                <?php endif; ?>
                <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">

                <div class="form-group">
                    <label for="page_title">页面标题:</label>
                    <input type="text" id="page_title" name="page_title" value="<?php echo escape_html(isset($_POST['page_title']) ? $_POST['page_title'] : ($page_to_edit['title'] ?? '')); ?>" required>
                </div>
                <div class="form-group">
                    <label for="display_order">显示顺序:</label>
                    <input type="number" id="display_order" name="display_order" value="<?php echo escape_html((string)(isset($_POST['display_order']) ? $_POST['display_order'] : ($page_to_edit['display_order'] ?? 0))); ?>" min="0">
                    <small class="form-text">数字越小越靠前显示在导航标签中。</small>
                </div>
                <button type="submit" class="btn btn-success"><?php echo $action === 'add' ? '添加页面' : '保存更改'; ?></button>
                <a href="manage_pages.php" class="btn btn-secondary" style="margin-left:10px;">取消</a>
            </form>

        <?php elseif ($action === 'delete' && isset($page_confirm_delete)): ?>
            <h3>确认删除页面</h3>
            <p>您确定要删除页面 "<strong><?php echo escape_html($page_confirm_delete['title']); ?></strong>" 吗?</p>
            <p style="color:red;"><strong>警告:</strong> 这将会同时删除此页面下的所有列、区块和链接！此操作无法撤销。</p>
            <form method="POST" action="manage_pages.php">
                <input type="hidden" name="action_manage_page" value="delete_confirm">
                <input type="hidden" name="page_id_to_delete" value="<?php echo (int)$page_confirm_delete['id']; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">
                <button type="submit" class="btn btn-danger">确认删除</button>
                <a href="manage_pages.php" class="btn btn-secondary" style="margin-left:10px;">取消</a>
            </form>

        <?php else: // List view (default) ?>
            <?php if (!empty($pages_list)): ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>标题</th>
                            <th>顺序</th>
                            <th>默认?</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages_list as $page_item_list): // Renamed variable ?>
                            <tr>
                                <td><?php echo escape_html($page_item_list['title']); ?></td>
                                <td><?php echo (int)$page_item_list['display_order']; ?></td>
                                <td>
                                    <?php if ($page_item_list['id'] == $current_default_page_id): ?>
                                        <span class="btn btn-sm btn-success disabled" style="cursor:default; opacity:0.7;">当前默认</span>
                                    <?php else: ?>
                                        <?php // Add CSRF token to GET link for set_default for basic protection, better to use POST though ?>
                                        <a href="manage_pages.php?action=set_default&page_id=<?php echo (int)$page_item_list['id']; ?>&token=<?php echo urlencode($csrf_token);?>" class="btn btn-sm btn-info">设为默认</a>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="index.php?page_id=<?php echo (int)$page_item_list['id']; ?>" class="btn btn-sm btn-primary" title="查看">查看</a>
                                    <a href="manage_pages.php?action=edit&edit_id=<?php echo (int)$page_item_list['id']; ?>" class="btn btn-sm btn-warning" title="编辑">编辑</a>
                                    <a href="manage_pages.php?action=delete&delete_id=<?php echo (int)$page_item_list['id']; ?>" class="btn btn-sm btn-danger" title="删除">删除</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>还没有创建任何导航页面。 <a href="manage_pages.php?action=add" class="btn btn-sm btn-success">立即添加一个？</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?></p>
    </footer>
</body>
</html>