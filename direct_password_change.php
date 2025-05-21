<?php
// dh/direct_password_change.php
// 直接在数据库中修改密码

// 显示所有错误
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 数据库路径
$db_path = __DIR__ . '/database.db';
echo "数据库路径: $db_path<br>";

$success_message = '';
$error_message = '';
$debug_info = '';

// 默认密码
$default_password = 'admin123';

try {
    // 连接到数据库
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "数据库连接成功<br>";
    
    // 获取所有用户
    $users = $db->query("SELECT id, username, password_hash FROM users")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($users)) {
        $error_message = '数据库中没有用户';
    } else {
        echo "找到 " . count($users) . " 个用户:<br>";
        
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>用户名</th><th>密码哈希长度</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . $user['username'] . "</td>";
            echo "<td>" . strlen($user['password_hash']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // 处理表单提交
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_id = $_POST['user_id'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $reset_to_default = isset($_POST['reset_to_default']);
            
            if ($reset_to_default) {
                $new_password = $default_password;
            }
            
            if (empty($user_id)) {
                $error_message = '请选择用户';
            } elseif (empty($new_password)) {
                $error_message = '请输入新密码';
            } else {
                // 生成密码哈希
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                echo "新密码: $new_password<br>";
                echo "新密码哈希: $password_hash<br>";
                echo "新密码哈希长度: " . strlen($password_hash) . "<br>";
                
                // 直接执行SQL更新密码
                $sql = "UPDATE users SET password_hash = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $result = $stmt->execute([$password_hash, $user_id]);
                
                if ($result) {
                    $rows_affected = $stmt->rowCount();
                    echo "密码更新执行完成。受影响的行数: $rows_affected<br>";
                    
                    if ($rows_affected > 0) {
                        // 验证密码是否真的更新了
                        $verify_stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                        $verify_stmt->execute([$user_id]);
                        $updated_user = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($updated_user) {
                            echo "更新后的密码哈希: {$updated_user['password_hash']}<br>";
                            echo "更新后的密码哈希长度: " . strlen($updated_user['password_hash']) . "<br>";
                            
                            $verify_result = password_verify($new_password, $updated_user['password_hash']);
                            echo "密码验证结果: " . ($verify_result ? '成功' : '失败') . "<br>";
                            
                            if ($verify_result) {
                                $success_message = "密码已成功更新为: $new_password";
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
    <title>直接修改密码</title>
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
        input[type="text"], input[type="password"], select {
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
        <h1>直接修改密码</h1>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($users)): ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="user_id">选择用户:</label>
                    <select id="user_id" name="user_id" required>
                        <option value="">-- 请选择用户 --</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?> (ID: <?php echo $user['id']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="new_password">新密码:</label>
                    <input type="text" id="new_password" name="new_password" placeholder="输入新密码">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="reset_to_default"> 重置为默认密码 (<?php echo htmlspecialchars($default_password); ?>)
                    </label>
                </div>
                
                <button type="submit" class="btn">更新密码</button>
                <a href="index.php" class="btn" style="background-color: #6c757d; margin-left: 10px;">返回首页</a>
            </form>
        <?php endif; ?>
        
        <?php if ($debug_info): ?>
            <div class="debug-info">
                <h3>调试信息</h3>
                <pre><?php echo $debug_info; ?></pre>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
