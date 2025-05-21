<?php
// dh/app/views/installation/s1_db_config_form.php
// $config_exists_for_view, $install_view_error, $install_view_success are set in install.php
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>安装向导 - 步骤 1: 配置</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="install-container container"> <?php /* Added .container for consistent styling */ ?>
        <h1>步骤 1: 数据库及站点配置</h1>

        <?php if (isset($install_view_error)): ?>
            <div class="alert alert-danger"><?php echo $install_view_error; /* Error msg might contain HTML */ ?></div>
        <?php endif; ?>
        <?php if (isset($install_view_success)): ?>
            <div class="alert alert-success"><?php echo install_escape_html($install_view_success); ?></div>
        <?php endif; ?>

        <?php if (isset($config_exists_for_view) && $config_exists_for_view && !isset($_GET['retry'])): ?>
            <div class="alert alert-info">
                检测到 <code>app/core/config.php</code> 文件已存在。
                如果需要重新配置，请手动删除该文件，或 <a href="install.php?step=db_config_form&force_reconfig=1">点击这里强制重新配置</a> (将覆盖现有配置)。
                <br>否则，您可以尝试 <a href="install.php?step=test_db_connection" class="btn btn-sm btn-info" style="margin-top:5px;">测试现有配置的数据库连接</a>。
            </div>
        <?php endif; ?>

        <p class="info">请输入您的 MySQL 数据库连接信息、站点名称和初始管理员账户。这些信息将被写入 <code>app/core/config.php</code> 文件。</p>

        <form method="POST" action="install.php?step=db_config_form"> <?php /* Submit to current step for processing */ ?>
            <input type="hidden" name="action_install_config" value="save_db_config">

            <h2>数据库设置</h2>
            <div class="form-group">
                <label for="db_host">数据库主机:</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo install_escape_html($_POST['db_host'] ?? 'localhost'); ?>" required>
                <small class="form-text">通常是 localhost 或 127.0.0.1。</small>
            </div>
            <div class="form-group">
                <label for="db_name">数据库名称:</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo install_escape_html($_POST['db_name'] ?? ''); ?>" required>
                <small class="form-text">请确保此数据库已存在，或者您的数据库用户有权限创建它 (脚本不会自动创建数据库)。</small>
            </div>
            <div class="form-group">
                <label for="db_user">数据库用户名:</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo install_escape_html($_POST['db_user'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="db_pass">数据库密码:</label>
                <input type="password" id="db_pass" name="db_pass" value="<?php echo install_escape_html($_POST['db_pass'] ?? ''); ?>">
            </div>

            <h2>网站设置</h2>
            <div class="form-group">
                <label for="site_name">网站名称:</label>
                <input type="text" id="site_name" name="site_name" value="<?php echo install_escape_html($_POST['site_name'] ?? '我的个人导航'); ?>" required>
            </div>

            <h2>管理员账户设置</h2>
            <div class="form-group">
                <label for="admin_username">管理员用户名:</label>
                <input type="text" id="admin_username" name="admin_username" value="<?php echo install_escape_html($_POST['admin_username'] ?? 'admin'); ?>" required>
            </div>
            <div class="form-group">
                <label for="admin_password">管理员密码:</label>
                <input type="password" id="admin_password" name="admin_password" value="<?php echo install_escape_html($_POST['admin_password'] ?? '1111'); ?>" required>
                <small class="form-text">初始密码，登录后建议修改。</small>
            </div>

            <button type="submit" class="btn btn-primary">保存配置并测试连接</button>
             <a href="install.php?step=check_requirements" class="btn btn-secondary" style="margin-left:10px;">返回上一步</a>
        </form>
    </div>
</body>
</html>