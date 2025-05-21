<?php
// dh/check_login_system.php
// 检查登录系统的工作方式

// 显示所有错误
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 包含必要的文件
require_once __DIR__ . '/app/core/config.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/functions.php';

echo "<h1>登录系统检查</h1>";

// 检查数据库连接
echo "<h2>数据库连接检查</h2>";
try {
    $db = getDBConnection();
    echo "数据库连接成功<br>";
    
    // 检查数据库类型
    echo "数据库类型: " . get_class($db) . "<br>";
    echo "数据库驱动: " . $db->getAttribute(PDO::ATTR_DRIVER_NAME) . "<br>";
    
    // 检查数据库文件路径（如果是SQLite）
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $db_path = __DIR__ . '/database.db';
        echo "SQLite数据库文件路径: $db_path<br>";
        echo "数据库文件大小: " . (file_exists($db_path) ? filesize($db_path) : '文件不存在') . " 字节<br>";
    }
} catch (Exception $e) {
    echo "数据库连接失败: " . $e->getMessage() . "<br>";
}

// 检查用户表
echo "<h2>用户表检查</h2>";
try {
    // 获取用户数量
    $user_count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    echo "用户表中有 $user_count 个用户<br>";
    
    // 获取所有用户
    $users = $db->query("SELECT id, username, password_hash FROM users")->fetchAll(PDO::FETCH_ASSOC);
    
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
    
    // 检查第一个用户的密码
    if (!empty($users)) {
        $first_user = $users[0];
        echo "<h3>第一个用户的密码检查</h3>";
        echo "用户ID: " . $first_user['id'] . "<br>";
        echo "用户名: " . $first_user['username'] . "<br>";
        echo "密码哈希: " . $first_user['password_hash'] . "<br>";
        echo "密码哈希长度: " . strlen($first_user['password_hash']) . "<br>";
        
        // 检查常见密码
        $common_passwords = ['admin', 'password', '123456', 'admin123', '1111'];
        
        echo "<h4>常见密码验证</h4>";
        echo "<table border='1'>";
        echo "<tr><th>密码</th><th>验证结果</th></tr>";
        
        foreach ($common_passwords as $password) {
            $result = password_verify($password, $first_user['password_hash']);
            echo "<tr>";
            echo "<td>" . $password . "</td>";
            echo "<td>" . ($result ? '✓ 匹配' : '✗ 不匹配') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // 检查自定义密码
        echo "<h4>自定义密码验证</h4>";
        echo "<form method='POST' action=''>";
        echo "<input type='text' name='test_password' placeholder='输入要测试的密码'>";
        echo "<button type='submit'>验证</button>";
        echo "</form>";
        
        if (isset($_POST['test_password'])) {
            $test_password = $_POST['test_password'];
            $result = password_verify($test_password, $first_user['password_hash']);
            echo "密码 '$test_password' 验证结果: " . ($result ? '✓ 匹配' : '✗ 不匹配') . "<br>";
        }
    }
} catch (Exception $e) {
    echo "用户表检查失败: " . $e->getMessage() . "<br>";
}

// 检查登录函数
echo "<h2>登录函数检查</h2>";
echo "<p>loginUser() 函数源代码:</p>";
echo "<pre>";
$login_function = new ReflectionFunction('loginUser');
$filename = $login_function->getFileName();
$start_line = $login_function->getStartLine();
$end_line = $login_function->getEndLine();

$file = file($filename);
for ($i = $start_line - 1; $i < $end_line; $i++) {
    echo htmlspecialchars($file[$i]);
}
echo "</pre>";

// 检查登录过程
echo "<h2>登录过程测试</h2>";
echo "<form method='POST' action=''>";
echo "<div style='margin-bottom: 10px;'>";
echo "<label for='login_username'>用户名:</label>";
echo "<input type='text' id='login_username' name='login_username' value='admin'>";
echo "</div>";
echo "<div style='margin-bottom: 10px;'>";
echo "<label for='login_password'>密码:</label>";
echo "<input type='password' id='login_password' name='login_password'>";
echo "</div>";
echo "<button type='submit' name='test_login'>测试登录</button>";
echo "</form>";

if (isset($_POST['test_login'])) {
    $username = $_POST['login_username'] ?? '';
    $password = $_POST['login_password'] ?? '';
    
    echo "尝试使用用户名 '$username' 和密码 '$password' 登录<br>";
    
    // 检查用户是否存在
    $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "找到用户: ID={$user['id']}, 用户名={$user['username']}<br>";
        
        // 验证密码
        $result = password_verify($password, $user['password_hash']);
        echo "密码验证结果: " . ($result ? '✓ 匹配' : '✗ 不匹配') . "<br>";
        
        // 使用loginUser函数测试
        $login_result = loginUser($db, $password);
        echo "loginUser() 函数结果: " . ($login_result ? '✓ 登录成功' : '✗ 登录失败') . "<br>";
        
        if ($login_result) {
            echo "登录成功，会话变量:<br>";
            echo "user_id: " . ($_SESSION['user_id'] ?? '未设置') . "<br>";
            echo "username: " . ($_SESSION['username'] ?? '未设置') . "<br>";
            echo "user_role: " . ($_SESSION['user_role'] ?? '未设置') . "<br>";
        }
    } else {
        echo "找不到用户名为 '$username' 的用户<br>";
    }
}

// 检查配置文件
echo "<h2>配置文件检查</h2>";
echo "<p>config.php 文件内容:</p>";
echo "<pre>";
$config_file = __DIR__ . '/app/core/config.php';
if (file_exists($config_file)) {
    $config_content = file_get_contents($config_file);
    // 隐藏密码
    $config_content = preg_replace('/([\'"]DB_PASS[\'"]\s*,\s*[\'"]).*?([\'"])/', '$1******$2', $config_content);
    echo htmlspecialchars($config_content);
} else {
    echo "配置文件不存在: $config_file";
}
echo "</pre>";

// 检查数据库文件
echo "<h2>数据库文件检查</h2>";
$db_files = glob(__DIR__ . '/*.db');
$db_files = array_merge($db_files, glob(__DIR__ . '/**/*.db'));

if (!empty($db_files)) {
    echo "<table border='1'>";
    echo "<tr><th>文件路径</th><th>大小</th><th>修改时间</th></tr>";
    
    foreach ($db_files as $file) {
        $relative_path = str_replace(__DIR__ . '/', '', $file);
        echo "<tr>";
        echo "<td>" . $relative_path . "</td>";
        echo "<td>" . filesize($file) . " 字节</td>";
        echo "<td>" . date("Y-m-d H:i:s", filemtime($file)) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "没有找到数据库文件<br>";
}
