<?php
// dh/app/views/installation/s0_requirements_check.php
// $php_version_ok, $pdo_mysql_ok, $mbstring_ok, $app_core_writable are set in install.php
$all_ok = $php_version_ok && $pdo_mysql_ok && $mbstring_ok && $app_core_writable;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>安装向导 - 环境检查</title>
    <link rel="stylesheet" href="css/style.css"> <?php // Main style.css for basic layout ?>
    <style>
        .install-container { max-width: 700px; margin: 30px auto; padding: 25px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .install-container h1 { text-align: center; margin-bottom: 25px; color: #333; }
        .requirement-list { list-style: none; padding: 0; }
        .requirement-list li { padding: 10px 0; border-bottom: 1px solid #eee; }
        .requirement-list li:last-child { border-bottom: none; }
        .requirement-list .status { float: right; font-weight: bold; }
        .requirement-list .status.ok { color: #28a745; }
        .requirement-list .status.fail { color: #dc3545; }
        .notes { margin-top: 20px; padding: 10px; background-color: #f8f9fa; border: 1px solid #ddd; border-radius: 4px; font-size: 0.9em; }
        .btn-container { text-align: center; margin-top: 30px; }
    </style>
</head>
<body>
    <div class="install-container">
        <h1>导航网站 - 安装向导 (环境检查)</h1>

        <?php if (isset($install_view_error)): ?>
            <div class="alert alert-danger"><?php echo $install_view_error; ?></div>
        <?php endif; ?>
        <?php if (isset($install_view_success)): ?>
            <div class="alert alert-success"><?php echo $install_view_success; ?></div>
        <?php endif; ?>

        <ul class="requirement-list">
            <li>PHP 版本 (需要 &gt;= 7.4.0) <span class="status <?php echo $php_version_ok ? 'ok' : 'fail'; ?>"><?php echo PHP_VERSION; ?> (<?php echo $php_version_ok ? '通过' : '失败'; ?>)</span></li>
            <li>PDO MySQL 扩展 <span class="status <?php echo $pdo_mysql_ok ? 'ok' : 'fail'; ?>"><?php echo $pdo_mysql_ok ? '已启用 (通过)' : '未启用 (失败)'; ?></span></li>
            <li>mbstring 扩展 <span class="status <?php echo $mbstring_ok ? 'ok' : 'fail'; ?>"><?php echo $mbstring_ok ? '已启用 (通过)' : '未启用 (失败)'; ?></span></li>
            <li><code>app/core/</code> 目录可写 <span class="status <?php echo $app_core_writable ? 'ok' : 'fail'; ?>"><?php echo $app_core_writable ? '可写 (通过)' : '不可写 (失败)'; ?></span></li>
        </ul>

        <?php if (!$all_ok): ?>
            <div class="alert alert-danger" style="margin-top:20px;">
                <strong>环境检查未通过！</strong> 请确保满足以上所有条件后再继续安装。
                <?php if (!$app_core_writable): ?>
                    <br>对于 <code>app/core/</code> 目录不可写，请检查服务器文件权限，确保PHP脚本有权在该目录下创建 <code>config.php</code> 和 <code>.install_lock</code> 文件。通常权限设置为 755 或 775 (如果是 PHP-FPM 以特定用户运行，可能需要调整用户组)。**安装完成后，建议将 <code>config.php</code> 设置为只读权限 (例如 444 或 644)。**
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-success" style="margin-top:20px;">环境检查通过！</div>
            <div class="btn-container">
                <a href="install.php?step=db_config_form" class="btn btn-primary">下一步：数据库配置</a>
            </div>
        <?php endif; ?>

         <div class="notes">
            <p><strong>说明:</strong></p>
            <ul>
                <li>PDO MySQL 扩展是PHP连接MySQL数据库所必需的。</li>
                <li>mbstring 扩展用于处理多字节字符（如中文），对书签标题等非常重要。</li>
                <li><code>app/core/</code> 目录需要可写，以便安装程序能够自动生成 <code>config.php</code> 数据库配置文件和 <code>.install_lock</code> 安装锁定文件。</li>
            </ul>
        </div>
    </div>
</body>
</html>