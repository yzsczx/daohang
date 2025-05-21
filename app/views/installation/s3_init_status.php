<?php
// dh/app/views/installation/s3_init_status.php
// $install_view_error, $install_view_success are set in install.php
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>安装向导 - 步骤 3: 初始化用户和设置</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="install-container container">
        <h1>步骤 3: 初始化管理员账户和设置</h1>

        <?php if (isset($install_view_error)): ?>
            <div class="alert alert-danger">
                <h4>初始化时出错：</h4>
                <p><?php echo $install_view_error; /* May contain HTML */ ?></p>
                <p>请检查PHP或数据库错误日志以获取更多信息。</p>
                 <p>
                    <a href="install.php?step=init_user_settings" class="btn btn-warning">重试初始化</a>
                    <a href="install.php?step=create_tables" class="btn btn-secondary" style="margin-left:10px;">返回上一步 (检查表)</a>
                </p>
            </div>
        <?php elseif (isset($install_view_success)): // Usually handled by redirect ?>
            <div class="alert alert-success">
                <p><?php echo install_escape_html($install_view_success); ?></p>
                <p class="info">正在自动跳转到完成页面...</p>
                <script>setTimeout(function(){ window.location.href = 'install.php?step=complete'; }, 1000);</script>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <p>数据库表已准备就绪！</p>
                <p>现在将创建初始管理员账户（用户名: <strong><?php echo install_escape_html(defined('DEFAULT_USERNAME') ? DEFAULT_USERNAME : 'admin'); ?></strong>, 密码: <strong><?php echo install_escape_html(defined('INITIAL_PASSWORD') ? INITIAL_PASSWORD : '1111'); ?></strong>）并进行基本设置。</p>
                 <div style="margin-top:20px; text-align:center;">
                    <a href="install.php?step=init_user_settings" class="btn btn-primary">开始初始化</a>
                    <a href="install.php?step=create_tables" class="btn btn-secondary" style="margin-left:10px;">返回上一步</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>