<?php
// dh/reset_admin_password.php
// 管理员密码重置页面

declare(strict_types=1);

// 包含必要的文件
require_once __DIR__ . '/app/core/config.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/functions.php';
require_once __DIR__ . '/app/core/admin_check.php'; // 添加管理员检查

// 检查用户是否已登录且是管理员
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

$db = getDBConnection();
$success_message = '';
$error_message = '';
$csrf_token = generateCsrfToken();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证CSRF令牌
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        $error_message = '无效的请求或会话已过期，请重试。';
    } else {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        // 验证密码
        if (empty($new_password)) {
            $error_message = '请输入新密码。';
        } elseif ($new_password !== $confirm_password) {
            $error_message = '两次输入的密码不一致。';
        } elseif (strlen($new_password) < 6) {
            $error_message = '密码长度不能少于6个字符。';
        } else {
            try {
                // 检查DEFAULT_USERNAME是否定义
                if (!defined('DEFAULT_USERNAME')) {
                    throw new Exception("DEFAULT_USERNAME 常量未定义");
                }

                // 查询用户
                $stmt = $db->prepare("SELECT id, username FROM users WHERE username = ?");
                $stmt->execute([DEFAULT_USERNAME]);
                $user = $stmt->fetch();

                if (!$user) {
                    throw new Exception("找不到管理员用户: " . DEFAULT_USERNAME);
                }

                // 生成密码哈希
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                // 更新密码
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $result = $stmt->execute([$password_hash, $user['id']]);

                if (!$result) {
                    throw new Exception("执行更新密码查询失败");
                }

                $rows_affected = $stmt->rowCount();

                if ($rows_affected === 0) {
                    throw new Exception("没有记录被更新");
                }

                // 验证密码是否真的更新了
                $verify_stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                $verify_stmt->execute([$user['id']]);
                $updated_user = $verify_stmt->fetch();

                if (!$updated_user) {
                    throw new Exception("无法验证更新后的用户");
                }

                $verify_result = password_verify($new_password, $updated_user['password_hash']);

                if (!$verify_result) {
                    throw new Exception("密码验证失败");
                }

                $success_message = "管理员密码已成功更新！";

            } catch (Exception $e) {
                $error_message = "错误: " . $e->getMessage();
                error_log("Error during password reset: " . $e->getMessage());
            }
        }
    }
}

// 获取当前用户名
$current_username = DEFAULT_USERNAME ?? '管理员';

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>重置管理员密码 - <?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="manage-page-header">
        <h1><?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?> - 重置管理员密码</h1>
        <nav class="user-actions">
            <a href="settings.php" class="btn btn-sm btn-secondary">返回设置</a>
            <a href="index.php" class="btn btn-sm btn-secondary">返回主页</a>
        </nav>
    </header>

    <div class="container">
        <div class="page-header">
            <h2>重置管理员密码</h2>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo escape_html($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo escape_html($success_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="reset_admin_password.php">
            <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">

            <div class="form-group">
                <label for="username">用户名:</label>
                <input type="text" id="username" value="<?php echo escape_html($current_username); ?>" readonly class="form-control-plaintext">
                <small class="form-text">管理员用户名无法更改。</small>
            </div>

            <div class="form-group">
                <label for="new_password">新密码:</label>
                <input type="password" id="new_password" name="new_password" class="form-control" required>
                <small class="form-text">密码长度至少为6个字符。</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">确认新密码:</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                <small class="form-text">再次输入新密码以确认。</small>
            </div>

            <button type="submit" class="btn btn-primary">更新密码</button>
            <a href="settings.php" class="btn btn-secondary" style="margin-left:10px;">取消</a>
        </form>
    </div>

    <footer>
        <p>&copy; <?php echo date('Y'); ?> <?php echo escape_html(defined('APP_NAME') ? APP_NAME : "我的导航"); ?></p>
    </footer>
</body>
</html>
