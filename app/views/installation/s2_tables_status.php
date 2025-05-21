<?php
// dh/app/views/installation/s2_tables_status.php
// $install_view_error, $install_view_success are set in install.php
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>安装向导 - 步骤 2: 创建数据库表</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="install-container container">
        <h1>步骤 2: 创建数据库表</h1>

        <?php if (isset($install_view_error)): ?>
            <div class="alert alert-danger">
                <h4>创建表时出错：</h4>
                <p><?php echo $install_view_error; /* May contain HTML */ ?></p>
                <p>请确保：</p>
                <ul>
                    <li><code>app/core/schema.sql</code> 文件存在且包含正确的 SQL 建表语句。</li>
                    <li><code>app/core/config.php</code> 中的数据库用户有权限在选定数据库中创建表。</li>
                    <li>数据库连接正常。</li>
                </ul>
                <p>
                    <a href="install.php?step=create_tables" class="btn btn-warning">重试创建表</a>
                    <a href="install.php?step=db_config_form&retry=1" class="btn btn-secondary" style="margin-left:10px;">返回修改数据库配置</a>
                </p>
            </div>
        <?php elseif (isset($install_view_success)): // This case is usually handled by redirect in install.php ?>
            <div class="alert alert-success">
                <p><?php echo install_escape_html($install_view_success); ?></p>
                <p class="info">正在自动跳转到下一步...</p>
                <script>setTimeout(function(){ window.location.href = 'install.php?step=init_user_settings'; }, 2000);</script>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <p>数据库连接成功！现在准备创建应用所需的数据库表。</p>
                <p>如果表已存在，此步骤可能会被跳过或报告错误（取决于具体实现）。</p>
                <p>点击下面的按钮开始创建表。这将从 <code>app/core/schema.sql</code> 文件执行建表语句。</p>
                <div style="margin-top:20px; text-align:center;">
                     <a href="install.php?step=create_tables" class="btn btn-primary">开始创建数据库表</a>
                     <a href="install.php?step=db_config_form&retry=1" class="btn btn-secondary" style="margin-left:10px;">返回上一步</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>