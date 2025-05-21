<?php
// dh/index.php
declare(strict_types=1);

// --- Top-level error reporting and logging (for debugging) ---
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
$local_debug_log_path_index = __DIR__ . '/debug_index.log';
$timestamp_now_index = date('Y-m-d H:i:s');
$initial_log_message_index = "--- INDEX.PHP EXECUTION STARTED (Bootstrap Grid): {$timestamp_now_index} ---\n";
if (is_writable(__DIR__)) {
    file_put_contents($local_debug_log_path_index, $initial_log_message_index, FILE_APPEND);
} else {
    error_log(str_replace("\n", " ", $initial_log_message_index) . "(Local directory " . __DIR__ . " not writable for debug_index.log)");
}
// --- End of top-level error reporting ---

define('APP_CONFIG_FILE_IDX', __DIR__ . '/app/core/config.php');
define('APP_INSTALL_LOCK_FILE_IDX', __DIR__ . '/app/core/.install_lock');

$is_installed_properly = false;
$db_main_check = null;
$db = null;

error_log("Index.php: Starting installation checks.", 3, $local_debug_log_path_index);
if (file_exists(APP_CONFIG_FILE_IDX) && file_exists(APP_INSTALL_LOCK_FILE_IDX)) {
    try {
        require_once APP_CONFIG_FILE_IDX;
        require_once __DIR__ . '/app/core/database.php';
        $db_main_check = getDBConnection();
        if (!defined('DEFAULT_USERNAME')) { throw new Exception("DEFAULT_USERNAME not defined.");}
        $stmt_user_check = $db_main_check->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_user_check->execute([DEFAULT_USERNAME]);
        if ($stmt_user_check->fetch()) {
            try { $db_main_check->query("SELECT 1 FROM `pages` LIMIT 1"); $is_installed_properly = true; }
            catch (PDOException $e) { $is_installed_properly = false; error_log("Index: pages table check failed: ".$e->getMessage(), 3, $local_debug_log_path_index); }
        } else { $is_installed_properly = false; error_log("Index: Admin user not found.", 3, $local_debug_log_path_index); }
    } catch (Throwable $e) { $is_installed_properly = false; error_log("Index: Install check exception: ".$e->getMessage(), 3, $local_debug_log_path_index); }
} else { error_log("Index.php: Config or lock file missing.", 3, $local_debug_log_path_index); }

if (!$is_installed_properly) { header('Location: install.php'); exit; }
error_log("Index.php: Installation checks passed.", 3, $local_debug_log_path_index);

if (isset($db_main_check) && ($db_main_check instanceof PDO)) { $db = $db_main_check; }
else { try { $db = getDBConnection(); } catch (Exception $e) { die("DB connection failed after install check."); } }

require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/functions.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') { logoutUser(); }

if (!isLoggedIn()) {
    // 初始化登录页背景样式变量，防止未定义警告
    $login_bg_style = '';
    // 可选：设置默认背景图片
    $default_bg = 'images/background.jpg';
    if (file_exists(__DIR__ . '/' . $default_bg)) {
        $login_bg_style = 'style="background-image: url(\'' . $default_bg . '\'); background-size: cover; background-attachment: fixed; background-position: center;"';
    }

    error_log("Index.php: User not logged in. Displaying login form.", 3, $local_debug_log_path_index);
    // ... (Login form HTML - Use the one from your working version) ...
    $error_message_login = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if (loginUser($db, $_POST['password'])) { header('Location: index.php'); exit; }
        else { $error_message_login = '密码错误！'; }
    }
    $app_title_login = defined('APP_NAME') ? APP_NAME : "我的导航";
    $initial_password_login = defined('INITIAL_PASSWORD') ? INITIAL_PASSWORD : '1111';
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>登录 - <?php echo escape_html($app_title_login); ?></title>
        <link rel="stylesheet" href="css/style.css">
        <style>
            .login-page {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: rgba(0, 0, 0, 0.5);
            }
            .login-container {
                background: rgba(255, 255, 255, 0.9);
                padding: 2rem;
                border-radius: 8px;
                box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
                backdrop-filter: blur(10px);
                -webkit-backdrop-filter: blur(10px);
                width: 90%;
                max-width: 400px;
            }
            .login-container h1 {
                margin-bottom: 1.5rem;
                color: #333;
                text-align: center;
                font-size: 1.8rem;
            }
            .login-form {
                display: flex;
                flex-direction: column;
                gap: 1rem;
            }
            .login-form input {
                padding: 0.8rem;
                border: 1px solid #ddd;
                border-radius: 4px;
                width: 100%;
                font-size: 1rem;
            }
            .login-form button {
                padding: 0.8rem;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 1rem;
                transition: background 0.3s;
            }
            .login-form button:hover {
                background: #0056b3;
            }
            .error {
                color: #dc3545;
                text-align: center;
                margin-bottom: 1rem;
                padding: 0.5rem;
                background: rgba(220, 53, 69, 0.1);
                border-radius: 4px;
            }
        </style>
    </head>
    <body class="login-page" <?php echo $login_bg_style; ?>>
        <div class="login-container">
            <h1><?php echo escape_html($app_title_login); ?></h1>
            <?php if (!empty($error_message_login)): ?>
                <div class="error"><?php echo escape_html($error_message_login); ?></div>
            <?php endif; ?>
            <form method="POST" action="index.php" class="login-form">
                <div>
                    <input type="password" 
                           name="password" 
                           id="password" 
                           placeholder="请输入密码" 
                           required 
                           autocomplete="current-password">
                </div>
                <button type="submit">登录</button>
            </form>
        </div>
    </body>
    </html>
    <?php exit;
}

// --- User is logged in, display dashboard ---
$current_user_id = getCurrentUserId($db);
if ($current_user_id === null) { error_log("Index.php: CRITICAL - isLoggedIn true, getCurrentUserId null.",3,$local_debug_log_path_index); logoutUser(); }
error_log("Index.php: User {$current_user_id} dashboard.", 3, $local_debug_log_path_index);

$user_pages = []; $user_settings = null; $current_page_id_to_load = null;
$current_page_data = null; $columns_on_page = [];
$app_name_main = defined('APP_NAME') ? APP_NAME : "我的导航";
$current_page_title = $app_name_main;
$page_error_main = null;

try {
    $user_pages = getUserPages($db, $current_user_id);
    $user_settings = getUserSettings($db, $current_user_id);
    // Page selection logic
    if (isset($_GET['page_id']) && ctype_digit((string)$_GET['page_id'])) {
        $requested_page_id = (int)$_GET['page_id'];
        foreach ($user_pages as $u_page) { if ($u_page['id'] == $requested_page_id && $u_page['user_id'] == $current_user_id) { $current_page_id_to_load = $requested_page_id; $current_page_data = $u_page; $current_page_title = $u_page['title']; break; } }
    }
    if ($current_page_id_to_load === null && !empty($user_pages)) {
        $target_page = null;
        if ($user_settings && isset($user_settings['default_page_id']) && $user_settings['default_page_id'] !== null) { foreach ($user_pages as $page_check) { if ($page_check['id'] == $user_settings['default_page_id']) { $target_page = $page_check; break; } } }
        if ($target_page === null) { $target_page = $user_pages[0]; }
        $current_page_id_to_load = (int)$target_page['id']; $current_page_data = $target_page; $current_page_title = $target_page['title'];
    } elseif (empty($user_pages)) { $current_page_title = "无页面 - " . $app_name_main; }
    if ($current_page_id_to_load !== null) { $columns_on_page = getPageColumns($db, $current_page_id_to_load); }
} catch (PDOException $e) { $page_error_main = "加载数据出错。"; error_log("Index.php: PDOEx fetching data: ".$e->getMessage(),3,$local_debug_log_path_index); }
 catch (Throwable $th) { $page_error_main = "未知错误。"; error_log("Index.php: Error fetching data: ".$th->getMessage(),3,$local_debug_log_path_index); }

$body_classes_arr_idx = []; $custom_bg_style_str_idx = '';
if ($user_settings) {
    // 设置主题类
    if (isset($user_settings['theme_name']) && !empty($user_settings['theme_name'])) {
        if ($user_settings['theme_name'] === 'dark') {
            $body_classes_arr_idx[] = 'theme-dark';
        }
    }

    // 设置背景图片
    $background_image_url = $user_settings['background_image_url'] ?? '';
    $background_fixed = isset($user_settings['background_fixed']) ? (int)$user_settings['background_fixed'] : 1; // 默认固定
    $background_attachment = $background_fixed ? 'fixed' : 'scroll';

    // 记录背景设置
    error_log("用户设置: " . print_r($user_settings, true));
    error_log("背景图片URL: " . $background_image_url);
    error_log("背景固定设置: " . $background_fixed);
    error_log("背景附着方式: " . $background_attachment);

    if (empty($background_image_url)) {
        // 如果用户没有设置背景图片，使用默认背景
        $default_bg = 'images/background.jpg';
        if (file_exists(__DIR__ . '/' . $default_bg)) {
            $custom_bg_style_str_idx = 'style="background-image: url(\'' . $default_bg . '\'); background-size: cover; background-attachment: ' . $background_attachment . '; background-position: center;"';
            error_log("使用默认背景图片: " . $default_bg);
            error_log("背景样式: " . $custom_bg_style_str_idx);
        }
    } else {
        // 使用用户设置的背景图片
        $custom_bg_style_str_idx = 'style="background-image: url(\'' . escape_html($background_image_url) . '\'); background-size: cover; background-attachment: ' . $background_attachment . '; background-position: center;"';
        error_log("使用用户设置的背景图片");
        error_log("背景样式: " . $custom_bg_style_str_idx);
    }
}
$body_theme_class_str_idx = implode(' ', $body_classes_arr_idx);

$page_message_main = $_SESSION['page_message'] ?? null;
if (!$page_error_main) { $page_error_main = $_SESSION['page_error'] ?? null; }
unset($_SESSION['page_message'], $_SESSION['page_error']);
$csrf_token_main_idx = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo escape_html($current_page_title) . ' - ' . escape_html($app_name_main); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <?php if (isset($user_settings['theme_name']) && !empty($user_settings['theme_name']) && $user_settings['theme_name'] !== 'default'): $theme_file_idx = preg_replace('/[^a-z0-9_-]/i','',$user_settings['theme_name']); if(file_exists(__DIR__.'/css/themes/'.$theme_file_idx.'.css')): ?><link rel="stylesheet" href="css/themes/<?php echo escape_html($theme_file_idx); ?>.css"><?php endif; endif; ?>
    <?php if (isset($user_settings['custom_css']) && !empty($user_settings['custom_css'])): ?><style type="text/css"><?php echo $user_settings['custom_css']; ?></style><?php endif; ?>
</head>
<body class="<?php echo escape_html($body_theme_class_str_idx); ?>" <?php echo $custom_bg_style_str_idx; ?>>
    <header class="p-3 mb-3 border-bottom bg-dark text-white sticky-top">
        <div class="container-fluid">
            <div class="d-flex flex-wrap align-items-center justify-content-center justify-content-lg-start">
                <a href="index.php" class="d-flex align-items-center mb-2 mb-lg-0 text-white text-decoration-none me-lg-auto"><span class="fs-4"><?php echo escape_html($app_name_main); ?></span></a>
                <ul class="nav col-12 col-lg-auto mb-2 justify-content-center mb-md-0">
                    <?php if (!empty($user_pages)): foreach ($user_pages as $page_item_nav_idx): ?>
                        <li><a href="index.php?page_id=<?php echo (int)$page_item_nav_idx['id']; ?>" class="nav-link px-2 <?php echo ($page_item_nav_idx['id'] == $current_page_id_to_load) ? 'text-secondary active-page-tab' : 'text-white'; ?>"><?php echo escape_html($page_item_nav_idx['title']); ?></a></li>
                    <?php endforeach; endif; ?>
                    <?php if (isAdmin()): // 只有管理员才能看到管理页面链接 ?>
                     <li><a href="manage_pages.php" class="nav-link px-2 text-white" title="管理页面">⚙️ 页面</a></li>
                    <?php endif; ?>
                </ul>
                <div class="dropdown text-end ms-lg-3">
                    <a href="#" class="d-block link-light text-decoration-none dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-user-circle fa-lg"></i> <?php echo escape_html($_SESSION['username'] ?? '用户'); ?></a>
                    <ul class="dropdown-menu text-small dropdown-menu-dark">
                        <?php if (isAdmin()): // 只有管理员才能看到设置和导入/导出选项 ?>
                        <li><a class="dropdown-item" href="settings.php">设置</a></li>
                        <li><a class="dropdown-item" href="import_bookmarks.php">导入/导出书签</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="index.php?action=logout">登出</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </header>

    <main id="dashboard-main" class="container-fluid mt-4">
        <?php if (isset($page_error_main) && $page_error_main): ?><div class="container"><div class="alert alert-danger page-message" role="alert"><?php echo escape_html($page_error_main); ?></div></div><?php endif; ?>
        <?php if (isset($page_message_main) && $page_message_main): ?><div class="container"><div class="alert alert-success alert-dismissible fade show page-message" role="alert"><?php echo escape_html($page_message_main); ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div><?php endif; ?>

        <?php if ($current_page_id_to_load === null && empty($user_pages)): ?>
            <div class="container"><div class="empty-state text-center p-5"><h2>开始您的导航之旅</h2><p class="lead">您还没有创建任何导航页面。</p><a href="manage_pages.php?action=add" class="btn btn-primary btn-lg">创建新页面</a></div></div>
        <?php elseif ($current_page_id_to_load === null && !empty($user_pages)): ?>
            <div class="container"><div class="empty-state text-center p-5"><h2>页面加载错误</h2><p class="lead">无法确定要加载哪个页面。</p></div></div>
        <?php elseif ($current_page_id_to_load !== null && empty($columns_on_page) && $current_page_data): ?>
             <div class="container"><div class="empty-state text-center p-5"><h2>页面: "<?php echo escape_html($current_page_data['title']); ?>" 内容为空</h2><p class="lead">此页面还没有任何列和区块。</p><a href="manage_blocks.php?action=add&page_id=<?php echo (int)$current_page_id_to_load; ?>" class="btn btn-info">为此页面添加区块 (需先手动创建列)</a></div></div>
        <?php elseif ($current_page_id_to_load !== null): // Page exists, render blocks using Bootstrap grid ?>
            <?php
            $all_blocks_for_page = [];
            if (!empty($columns_on_page)) {
                foreach ($columns_on_page as $column_data) {
                    $blocks_in_col = getColumnBlocks($db, (int)$column_data['id']);
                    foreach ($blocks_in_col as $block_d) {
                        $block_d['original_column_id_for_add_block'] = $column_data['id']; // Store original col ID
                        $all_blocks_for_page[] = $block_d;
                    }
                }
            }
            ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 px-md-3"> <?php // 1 col on xs, 2 on md, 3 on lg. Adjust lg as needed. ?>
                <?php if (!empty($all_blocks_for_page)): ?>
                    <?php foreach ($all_blocks_for_page as $block_item_bs_grid): ?>
                        <div class="col d-flex align-items-stretch"> <?php // d-flex and align-items-stretch for equal height cards in a row ?>
                            <div class="card w-100" id="block-<?php echo (int)$block_item_bs_grid['id']; ?>" data-block-type="<?php echo escape_html($block_item_bs_grid['type']); ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0"><?php echo escape_html($block_item_bs_grid['title']); ?></h5>
                                    <div class="block-actions-dropdown dropdown">
                                        <button class="btn btn-sm btn-outline-secondary border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="区块操作"><i class="fas fa-ellipsis-v"></i></button>
                                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                                            <li><a class="dropdown-item" href="manage_blocks.php?action=edit&edit_id=<?php echo (int)$block_item_bs_grid['id']; ?>&page_id=<?php echo (int)$current_page_id_to_load; ?>"><i class="fas fa-pencil-alt fa-fw me-2"></i>编辑区块</a></li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li><a class="dropdown-item text-danger" href="manage_blocks.php?action=delete&delete_id=<?php echo (int)$block_item_bs_grid['id']; ?>&page_id=<?php echo (int)$current_page_id_to_load; ?>&confirm=1&csrf_token=<?php echo urlencode($csrf_token_main_idx); ?>" onclick="return confirm('删除区块 “<?php echo escape_html(addslashes($block_item_bs_grid['title'])); ?>”？');"><i class="fas fa-trash-alt fa-fw me-2"></i>删除区块</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <?php if ($block_item_bs_grid['type'] === 'links'): ?>
                                        <?php
                                        // 获取链接总数
                                        $total_links_count = getBlockLinksCount($db, (int)$block_item_bs_grid['id']);
                                        // 获取用户设置的链接数量
                                        $links_limit = 10; // 默认值
                                        if (isset($user_settings) && isset($user_settings['links_per_block'])) {
                                            $links_limit = (int)$user_settings['links_per_block'];
                                        }
                                        // 获取有限数量的链接
                                        $links_list_bs_grid = getBlockLinks($db, (int)$block_item_bs_grid['id'], $links_limit);
                                        // 检查是否有更多链接
                                        $has_more_links = $total_links_count > $links_limit;
                                        ?>
                                        <?php if (!empty($links_list_bs_grid)): ?>
                                            <ul class="list-unstyled links-list-bs mb-0" id="links-list-<?php echo (int)$block_item_bs_grid['id']; ?>">
                                                <?php foreach ($links_list_bs_grid as $link_item_bs_grid): ?>
                                                    <li class="link-item-bs mb-2">
                                                        <a href="<?php echo escape_html($link_item_bs_grid['url']); ?>" target="_blank" class="d-flex align-items-center text-decoration-none link-item-anchor">
                                                            <?php if (!empty($link_item_bs_grid['icon_url'])): ?><img src="<?php echo escape_html($link_item_bs_grid['icon_url']); ?>" class="link-favicon me-2" onerror="this.style.display='none'; this.onerror=null;"><?php else: ?><span class="link-favicon-placeholder me-2"><i class="fas fa-link"></i></span><?php endif; ?>
                                                            <span class="link-title flex-grow-1"><?php echo escape_html($link_item_bs_grid['title']); ?></span>
                                                        </a>
                                                        <div class="link-actions-bs ms-2">
                                                            <a href="manage_links.php?action=edit&edit_id=<?php echo (int)$link_item_bs_grid['id']; ?>&block_id=<?php echo (int)$block_item_bs_grid['id']; ?>&page_id=<?php echo (int)$current_page_id_to_load; ?>" class="btn-icon"><i class="fas fa-pencil-alt"></i></a>
                                                            <a href="manage_links.php?action=delete&delete_id=<?php echo (int)$link_item_bs_grid['id']; ?>&block_id=<?php echo (int)$block_item_bs_grid['id']; ?>&page_id=<?php echo (int)$current_page_id_to_load; ?>&confirm=1&csrf_token=<?php echo urlencode($csrf_token_main_idx); ?>" class="btn-icon text-danger" onclick="return confirm('删除链接 “<?php echo escape_html(addslashes($link_item_bs_grid['title'])); ?>”？');"><i class="fas fa-times"></i></a>
                                                        </div>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>

                                            <?php if ($has_more_links): ?>
                                            <div class="text-center mt-2">
                                                <button class="btn btn-sm btn-outline-secondary show-more-links"
                                                        data-block-id="<?php echo (int)$block_item_bs_grid['id']; ?>"
                                                        data-page-id="<?php echo (int)$current_page_id_to_load; ?>"
                                                        data-total-links="<?php echo $total_links_count; ?>"
                                                        data-loaded-links="<?php echo $links_limit; ?>">
                                                    <i class="fas fa-chevron-down"></i> 显示更多 (<?php echo $total_links_count - $links_limit; ?>)
                                                </button>
                                            </div>
                                            <?php endif; ?>

                                        <?php else: ?><p class="text-muted small">此区块中还没有链接。</p><?php endif; ?>
                                        <?php if (isAdmin()): // 只有管理员才能看到添加链接按钮 ?>
                                        <div class="mt-auto pt-2 text-center add-new-item"><a href="manage_links.php?action=add&block_id=<?php echo (int)$block_item_bs_grid['id']; ?>&page_id=<?php echo (int)$current_page_id_to_load; ?>" class="btn btn-sm btn-outline-primary"><i class="fas fa-plus fa-fw"></i> 添加链接</a></div>
                                        <?php endif; ?>
                                    <?php elseif ($block_item_bs_grid['type'] === 'notes'): ?>
                                         <div class="notes-content flex-grow-1"><?php $note_cfg_bs_grid = json_decode($block_item_bs_grid['config_json'] ?: '{}', true); $note_txt_bs_grid = $note_cfg_bs_grid['content'] ?? ''; echo empty($note_txt_bs_grid) ? '<p class="text-muted small">空笔记...</p>' : nl2br(escape_html($note_txt_bs_grid)); ?></div>
                                        <?php if (isAdmin()): // 只有管理员才能看到编辑笔记按钮 ?>
                                        <div class="mt-auto pt-2 text-center add-new-item"><a href="manage_blocks.php?action=edit&edit_id=<?php echo (int)$block_item_bs_grid['id']; ?>&page_id=<?php echo (int)$current_page_id_to_load; ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pencil-alt fa-fw"></i> 编辑笔记</a></div>
                                        <?php endif; ?>
                                    <?php else: ?><div class="text-muted small flex-grow-1"><?php echo escape_html($block_item_bs_grid['title']); ?> (待实现)</div><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: // Page has columns (checked by parent elseif), but those columns have no blocks ?>
                     <div class="container"><div class="empty-state text-center p-5"><h2>此页面尚无区块</h2><p class="lead">当前页面 "<?php echo escape_html($current_page_data['title'] ?? '未知页面'); ?>" 中还没有任何区块。</p><a href="manage_blocks.php?action=add&column_id=<?php echo (int)$columns_on_page[0]['id']; ?>&page_id=<?php echo (int)$current_page_id_to_load; ?>" class="btn btn-primary">添加新区块</a></div></div>
                <?php endif; ?>
                 <div class="add-block-to-page-area text-center p-3 mt-4">
                    <?php if ($current_page_id_to_load !== null && !empty($columns_on_page)): ?>
                    <a href="manage_blocks.php?action=add&column_id=<?php echo (int)$columns_on_page[0]['id']; // Add to first column by default ?>&page_id=<?php echo (int)$current_page_id_to_load; ?>" class="btn btn-lg btn-success"><i class="fas fa-plus-circle fa-fw"></i> 添加新区块到此页面</a>
                    <?php elseif ($current_page_id_to_load !== null): ?>
                    <a href="manage_blocks.php?action=add&page_id=<?php echo (int)$current_page_id_to_load; ?>" class="btn btn-lg btn-success"><i class="fas fa-plus-circle fa-fw"></i> 添加新区块到此页面 (将创建默认列)</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    <footer class="container-fluid text-center py-3 mt-4 bg-light border-top">
        <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo escape_html($app_name_main); ?></p>
    </footer>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>