<?php
// dh/update_password.php
// 一个通用的密码修改页面，适应不同的数据库结构

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
$debug_info = '';

try {
    // 连接到数据库
    $db = new PDO($dsn);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 获取所有表
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    
    // 查找可能的用户表
    $user_table = null;
    $password_column = null;
    $id_column = null;
    
    foreach ($tables as $table) {
        // 获取表结构
        $columns = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
        
        // 检查是否有密码相关的列
        $has_password_column = false;
        $has_id_column = false;
        $password_col_name = null;
        $id_col_name = null;
        
        foreach ($columns as $column) {
            $col_name = strtolower($column['name']);
            
            // 检查是否是密码列
            if (strpos($col_name, 'password') !== false || strpos($col_name, 'hash') !== false) {
                $has_password_column = true;
                $password_col_name = $column['name'];
            }
            
            // 检查是否是ID列
            if ($col_name === 'id' || $column['pk'] == 1) {
                $has_id_column = true;
                $id_col_name = $column['name'];
            }
        }
        
        // 如果表有密码列和ID列，可能是用户表
        if ($has_password_column && $has_id_column) {
            $user_table = $table;
            $password_column = $password_col_name;
            $id_column = $id_col_name;
            break;
        }
    }
    
    $debug_info .= "找到的表: " . implode(', ', $tables) . "<br>";
    $debug_info .= "可能的用户表: " . ($user_table ?: '未找到') . "<br>";
    if ($user_table) {
        $debug_info .= "密码列: $password_column<br>";
        $debug_info .= "ID列: $id_column<br>";
    }
    
    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_table && $password_column) {
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
            
            // 更新当前用户的密码
            $stmt = $db->prepare("UPDATE $user_table SET $password_column = ? WHERE $id_column = ?");
            $result = $stmt->execute([$password_hash, $_SESSION['user_id']]);
            
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
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $error_message = '无法确定用户表或密码列';
    }
    
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
    <title>修改密码</title>
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
        .debug-info {
            margin-top: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>修改密码</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if ($user_table && $password_column): ?>
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
        <?php else: ?>
            <div class="alert alert-danger">无法确定用户表或密码列。请联系管理员。</div>
            <a href="settings.php" class="btn">返回设置</a>
        <?php endif; ?>
        
        <div class="debug-info">
            <h3>调试信息</h3>
            <?php echo $debug_info; ?>
        </div>
    </div>
</body>
</html>
