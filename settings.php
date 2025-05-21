<?php
// dh/settings.php
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

$user_settings = getUserSettings($db, $current_user_id);
if ($user_settings === null) { // Ensure settings row exists
    // Create a default settings row if it doesn't exist
    try {
        $stmt = $db->prepare("INSERT INTO settings (user_id, theme_name) VALUES (?, 'default') ON DUPLICATE KEY UPDATE user_id = ?"); // MySQL specific
        $stmt->execute([$current_user_id, $current_user_id]);
        $user_settings = getUserSettings($db, $current_user_id); // Fetch again
    } catch (PDOException $e) {
        error_log("Error ensuring user settings exist: " . $e->getMessage());
        // Handle error appropriately, maybe redirect with an error message
    }
}


$form_error = $_SESSION['form_error'] ?? null;
$form_success = $_SESSION['form_success'] ?? null;
unset($_SESSION['form_error'], $_SESSION['form_success']);

$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $_SESSION['form_error'] = '无效请求。';
    } else {
        try {
            $settings = [
                'theme_name' => trim($_POST['theme_name_setting'] ?? 'default'),
                'background_image_url' => trim($_POST['background_image_url_setting'] ?? ''),
                'background_fixed' => isset($_POST['background_fixed_setting']) ? 1 : 0,
                'custom_css' => trim($_POST['custom_css_setting'] ?? ''),
                'weather_city' => trim($_POST['weather_city_setting'] ?? ''),
                'links_per_block' => max(1, min(100, (int)($_POST['links_per_block'] ?? 10))),
                'guest_password' => trim($_POST['guest_password'] ?? '1111')
            ];

            if (updateUserSettings($db, $current_user_id, $settings)) {
                $_SESSION['form_success'] = "设置已成功保存！";
                $user_settings = getUserSettings($db, $current_user_id); // 刷新设置
            } else {
                throw new Exception("保存设置失败");
            }
        } catch (Exception $e) {
            error_log("Settings update error: " . $e->getMessage());
            $_SESSION['form_error'] = "保存设置时发生错误：" . $e->getMessage();
        }
        
        // 重定向以避免表单重复提交
        header("Location: settings.php");
        exit;
    }
}

$app_name_display = defined('APP_NAME') ? APP_NAME : '我的导航'; // Get from config
$theme_current = $user_settings['theme_name'] ?? 'default';
$background_current = $user_settings['background_image_url'] ?? '';
$css_current = $user_settings['custom_css'] ?? '';
$weather_city_current = $user_settings['weather_city'] ?? '';
$links_per_block_current = $user_settings['links_per_block'] ?? 10;

// Available themes (hardcoded for now, could be dynamic by scanning css/themes/)
$available_themes = [
    'default' => '默认主题 (浅色)',
    'dark'    => '暗色主题'
    // Add more theme options here, e.g., 'blue', 'green'
    // Ensure you have corresponding css/themes/theme_name.css files
];


?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>应用设置 - <?php echo escape_html($app_name_display); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="manage-page-header">
        <h1><?php echo escape_html($app_name_display); ?> - 应用设置</h1>
        <nav class="user-actions">
            <a href="index.php" class="btn btn-sm btn-secondary">返回主页</a>
            <a href="index.php?action=logout" class="btn btn-sm btn-secondary">登出</a>
        </nav>
    </header>

    <div class="container">
        <div class="page-header">
            <h2>个性化和功能设置</h2>
        </div>

        <?php if ($form_error): ?>
            <div class="alert alert-danger"><?php echo $form_error; ?></div>
        <?php endif; ?>
        <?php if ($form_success): ?>
            <div class="alert alert-success"><?php echo escape_html($form_success); ?></div>
        <?php endif; ?>

        <form method="POST" action="settings.php">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">

            <?php /*
            <div class="form-group">
                <label for="app_name_setting">网站显示名称:</label>
                <input type="text" id="app_name_setting" name="app_name_setting" value="<?php echo escape_html($app_name_display); ?>">
                <small class="form-text">此设置仅影响浏览器标签页标题和页面中的显示，不会修改核心配置文件中的 APP_NAME。</small>
            </div>
            */ ?>

            <div class="form-group">
                <label for="theme_name_setting">选择主题:</label>
                <select id="theme_name_setting" name="theme_name_setting" class="form-control">
                    <?php foreach ($available_themes as $value => $label): ?>
                        <option value="<?php echo escape_html($value); ?>" <?php echo ($theme_current === $value ? 'selected' : ''); ?>>
                            <?php echo escape_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="background_image_url_setting">背景图片 URL (可选):</label>
                <input type="url" id="background_image_url_setting" name="background_image_url_setting" class="form-control" value="<?php echo escape_html($background_current); ?>" placeholder="例如：https://example.com/background.jpg">
                <small class="form-text">留空则使用主题默认背景。图片会尝试覆盖整个背景。</small>
            </div>

            <div class="form-group">
                <div class="form-check">
                    <?php
                    $background_fixed_value = isset($user_settings['background_fixed']) ? (int)$user_settings['background_fixed'] : 1;
                    ?>
                    <input type="checkbox" id="background_fixed_setting" name="background_fixed_setting" 
                           class="form-check-input" 
                           <?php echo ($background_fixed_value == 1) ? 'checked' : ''; ?>>
                    <label for="background_fixed_setting" class="form-check-label">
                        背景图片固定（不随页面滚动）
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="custom_css_setting">自定义 CSS (可选):</label>
                <textarea id="custom_css_setting" name="custom_css_setting" rows="5" class="form-control" placeholder="例如：body { font-size: 18px; }"><?php echo escape_html($css_current); ?></textarea>
                <small class="form-text">在此处添加的 CSS 会覆盖主题样式。请谨慎使用。</small>
            </div>

            <hr style="margin: 20px 0;">
            <h3>小部件设置</h3>
            <div class="form-group">
                <label for="weather_city_setting">天气小部件 - 城市 (可选):</label>
                <input type="text" id="weather_city_setting" name="weather_city_setting" class="form-control" value="<?php echo escape_html($weather_city_current); ?>" placeholder="例如：北京 或 Beijing">
                <small class="form-text">用于天气小部件显示。需要天气API支持。</small>
            </div>

            <div class="form-group">
                <label for="links_per_block">每个区块显示的链接数量:</label>
                <input type="number" id="links_per_block" name="links_per_block" class="form-control" value="<?php echo (int)$links_per_block_current; ?>" min="1" max="100" required>
                <small class="form-text">设置首页每个区块默认显示的链接数量。超过此数量的链接将被隐藏，可通过"显示更多"按钮查看。</small>
            </div>

            <hr style="margin: 20px 0;">
            <h3>访问控制设置</h3>
            <div class="form-group">
                <label for="guest_password">访客密码:</label>
                <input type="text" id="guest_password" name="guest_password" class="form-control" value="<?php echo escape_html($user_settings['guest_password'] ?? '1111'); ?>" required>
                <small class="form-text">设置访客登录密码。访客只能查看内容，无法进行修改操作。</small>
            </div>

            <div class="form-group">
                <label>管理员密码:</label>
                <div>
                    <a href="reset_admin_password.php" class="btn btn-warning">修改管理员密码</a>
                </div>
                <small class="form-text">点击按钮进入专门的密码修改页面。</small>
            </div>
            <?php // TODO: Add settings for other widgets, like default search engine ?>


            <button type="submit" class="btn btn-primary">保存设置</button>
        </form>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo escape_html($app_name_display); ?></p>
    </footer>


</html></body></html>