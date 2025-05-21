<?php
// dh/simple_reset_password.php
// 一个非常简单的密码重置脚本，直接使用SQL更新密码

// 连接到数据库
$db_path = __DIR__ . '/database.db';
$dsn = 'sqlite:' . $db_path;

try {
    // 显示所有错误
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
    
    echo "尝试连接到数据库: $db_path<br>";
    
    // 连接到数据库
    $db = new PDO($dsn);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "数据库连接成功<br>";
    
    // 设置新密码
    $new_password = 'admin123';
    $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
    
    echo "生成的密码哈希: $password_hash<br>";
    echo "密码哈希长度: " . strlen($password_hash) . "<br>";
    
    // 查询所有用户
    $users = $db->query("SELECT id, username FROM users")->fetchAll();
    
    if (count($users) === 0) {
        echo "数据库中没有用户<br>";
    } else {
        echo "找到 " . count($users) . " 个用户:<br>";
        
        foreach ($users as $user) {
            echo "用户ID: " . $user['id'] . ", 用户名: " . $user['username'] . "<br>";
            
            // 更新此用户的密码
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $result = $stmt->execute([$password_hash, $user['id']]);
            
            if ($result) {
                $rows = $stmt->rowCount();
                echo "密码已更新，影响的行数: $rows<br>";
            } else {
                echo "密码更新失败<br>";
            }
        }
        
        echo "<strong>所有用户的密码已重置为: $new_password</strong><br>";
    }
    
    // 检查users表结构
    echo "<h3>users表结构:</h3>";
    $table_info = $db->query("PRAGMA table_info(users)")->fetchAll();
    
    echo "<table border='1'>";
    echo "<tr><th>CID</th><th>名称</th><th>类型</th><th>非空</th><th>默认值</th><th>主键</th></tr>";
    
    foreach ($table_info as $column) {
        echo "<tr>";
        echo "<td>" . $column['cid'] . "</td>";
        echo "<td>" . $column['name'] . "</td>";
        echo "<td>" . $column['type'] . "</td>";
        echo "<td>" . $column['notnull'] . "</td>";
        echo "<td>" . $column['dflt_value'] . "</td>";
        echo "<td>" . $column['pk'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "一般错误: " . $e->getMessage() . "<br>";
}
