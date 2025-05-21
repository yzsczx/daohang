<?php
// dh/app/views/installation/s_final_complete.php
// $install_view_success, $install_view_warning may be set by install.php
// APP_NAME, DEFAULT_USERNAME, INITIAL_PASSWORD should be available if config.php was included
$app_name_final = defined('APP_NAME') ? APP_NAME : '我的导航页';
$admin_user_final = defined('DEFAULT_USERNAME') ? DEFAULT_USERNAME : 'admin';
$admin_pass_final = defined('INITIAL_PASSWORD') ? INITIAL_PASSWORD : '1111';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>安装完成！</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="install-container container">
        <h1><span style="color:green; font-size:1.5em; vertical-align:middle;">✔</span> 安装成功!</h1>

        <?php if (isset($install_view_success)): ?>
            <div class="alert alert-success"><?php echo install_escape_html($install_view_success); ?></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['install_view_warning'])): /* Check session directly if not unset */ ?>
            <div class="alert alert-warning"><?php echo install_escape_html($_SESSION['install_view_warning']); unset($_SESSION['install_view_warning']); ?></div>
        <?php endif; ?>
        
        <p class="success">您的导航网站已成功安装并配置完毕。</p>
        
        <div class="alert alert-warning" style="font-weight:bold; text-align:left;">
            <h4 style="margin-top:0;">重要安全提示:</h4>
            <p>为了您网站的安全，请立即从服务器上删除 <code>install.php</code> 文件！</p>
            <p>另外，建议检查并设置 <code>app/core/config.php</code> 文件权限为只读 (例如 444 或 644)，以防止意外修改。</p>
        </div>
        
        <h3>初始管理员账户信息:</h3>
        <p>网站名称: <strong><?php echo install_escape_html($app_name_final); ?></strong></p>
        <p>管理员用户名: <strong><?php echo install_escape_html($admin_user_final); ?></strong></p>
        <p>管理员密码: <strong><?php echo install_escape_html($admin_pass_final); ?></strong></p>
        <p><small>登录后，您应该可以在“设置”页面修改这些信息 (密码修改功能待实现)。</small></p>
        
        <div style="margin-top:30px; text-align:center;">
            <a href="index.php" class="btn btn-primary btn-lg" style="font-size:1.2em; padding:10px 25px;">访问我的导航网站</a>
        </div>
    </div>
</body>
</html>