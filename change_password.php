<?php
// dh/change_password.php
// 一个简单的密码修改页面，直接使用SQLite连接

// 显示所有错误
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 启动会话
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// 连接到数据库
$db_path = __DIR__ . '/database.db';
$dsn = 'sqlite:' . $db_path;

$success_message = '';
$error_message = '';

try {
    // 连接到数据库
    $db = new PDO($dsn);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // 验证输入
        if (empty($new_password)) {
            $error_message = '请输入新密码';
        } elseif ($new_password !== $confirm_password) {
            $error_message = '两次输入的密码不一致';
        } elseif (strlen($new_password) < 6) {
            $error_message = '密码长度不能少于6个字符';
        } else {
            // 生成密码哈希
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            // 更新所有用户的密码（简单起见，不区分用户）
            $stmt = $db->prepare("UPDATE users SET password_hash = ?");
            $result = $stmt->execute([$password_hash]);
            
            if ($result) {
                $rows_affected = $stmt->rowCount();
                if ($rows_affected > 0) {
                    $success_message = '密码已成功更新！';
                } else {
                    $error_message = '密码未更改（没有记录被更新）';
                }
            } else {
                $error_message = '密码更新失败';
            }
        }
    }
    
    // 获取当前用户信息
    $stmt = $db->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
} catch (PDOException $e) {
    $error_message = '数据库错误: ' . $e->getMessage();
} catch (Exception $e) {
    $error_message = '一般错误: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修改管理员密码</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .btn {
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background-color: #0069d9;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>修改管理员密码</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="new_password">新密码:</label>
                <input type="password" id="new_password" name="new_password" placeholder="输入新密码" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">确认新密码:</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="再次输入新密码" required>
            </div>
            
            <button type="submit" class="btn">更新密码</button>
            <a href="settings.php" class="btn" style="background-color: #6c757d; margin-left: 10px;">返回设置</a>
        </form>
    </div>
</body>
</html>
