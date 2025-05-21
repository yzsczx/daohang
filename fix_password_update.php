<?php
// dh/fix_password_update.php
// 检查和修复管理员密码更新功能

// 显示所有错误
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 启动会话
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    echo "请先登录。<a href='login.php'>点击这里登录</a>";
    exit;
}

// 数据库路径
$db_path = __DIR__ . '/database.db';
echo "数据库路径: $db_path<br>";

$success_message = '';
$error_message = '';
$debug_info = '';

try {
    // 连接到数据库
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "数据库连接成功<br>";
    
    // 获取当前用户ID
    $current_user_id = $_SESSION['user_id'];
    echo "当前用户ID: $current_user_id<br>";
    
    // 获取用户信息
    $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE id = ?");
    $stmt->execute([$current_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "找到用户: ID={$user['id']}, 用户名={$user['username']}<br>";
        echo "当前密码哈希长度: " . strlen($user['password_hash']) . "<br>";
        
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
                
                echo "新密码哈希: $password_hash<br>";
                echo "新密码哈希长度: " . strlen($password_hash) . "<br>";
                
                // 检查users表中password_hash字段的长度
                $table_info = $db->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
                $password_column = null;
                
                foreach ($table_info as $column) {
                    if ($column['name'] === 'password_hash') {
                        $password_column = $column;
                        break;
                    }
                }
                
                if ($password_column) {
                    echo "password_hash字段类型: {$password_column['type']}<br>";
                    
                    // 如果字段类型不是TEXT，尝试修改表结构
                    if (strtoupper($password_column['type']) !== 'TEXT') {
                        echo "password_hash字段不是TEXT类型，尝试修改表结构...<br>";
                        
                        // 创建临时表
                        $db->exec("
                            CREATE TABLE users_temp (
                                id INTEGER PRIMARY KEY AUTOINCREMENT,
                                username TEXT NOT NULL UNIQUE,
                                password_hash TEXT NOT NULL,
                                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                            )
                        ");
                        
                        // 复制数据
                        $db->exec("INSERT INTO users_temp SELECT * FROM users");
                        
                        // 删除原表
                        $db->exec("DROP TABLE users");
                        
                        // 重命名临时表
                        $db->exec("ALTER TABLE users_temp RENAME TO users");
                        
                        echo "表结构已修改，password_hash字段现在是TEXT类型<br>";
                    }
                }
                
                // 更新密码
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $result = $stmt->execute([$password_hash, $current_user_id]);
                
                if ($result) {
                    $rows_affected = $stmt->rowCount();
                    echo "密码更新执行完成。受影响的行数: $rows_affected<br>";
                    
                    if ($rows_affected > 0) {
                        // 验证密码是否真的更新了
                        $verify_stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                        $verify_stmt->execute([$current_user_id]);
                        $updated_user = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($updated_user) {
                            echo "更新后的密码哈希: {$updated_user['password_hash']}<br>";
                            echo "更新后的密码哈希长度: " . strlen($updated_user['password_hash']) . "<br>";
                            
                            $verify_result = password_verify($new_password, $updated_user['password_hash']);
                            echo "密码验证结果: " . ($verify_result ? '成功' : '失败') . "<br>";
                            
                            if ($verify_result) {
                                $success_message = '密码已成功更新！';
                            } else {
                                $error_message = '密码已更新，但验证失败';
                            }
                        } else {
                            $error_message = '无法验证更新后的用户';
                        }
                    } else {
                        $error_message = '密码未更改（没有记录被更新）';
                    }
                } else {
                    $error_message = '密码更新失败';
                }
            }
        }
    } else {
        $error_message = '找不到用户';
    }
    
} catch (PDOException $e) {
    $error_message = '数据库错误: ' . $e->getMessage();
    $debug_info .= "PDO异常: " . $e->getMessage() . "<br>";
    $debug_info .= "PDO错误代码: " . $e->getCode() . "<br>";
    $debug_info .= "PDO错误信息: " . print_r($e->errorInfo, true) . "<br>";
} catch (Exception $e) {
    $error_message = '一般错误: ' . $e->getMessage();
    $debug_info .= "异常: " . $e->getMessage() . "<br>";
    $debug_info .= "异常代码: " . $e->getCode() . "<br>";
    $debug_info .= "异常跟踪: " . $e->getTraceAsString() . "<br>";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>修复密码更新</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 800px;
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
        .debug-info {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            overflow-x: auto;
        }
        pre {
            margin: 0;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>修复密码更新</h1>
        
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
        
        <div class="debug-info">
            <h3>调试信息</h3>
            <pre><?php echo $debug_info; ?></pre>
        </div>
    </div>
</body>
</html>
