<?php
// dh/restrict_guest_access.php
declare(strict_types=1);

// 包含必要的文件
require_once __DIR__ . '/app/core/config.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/functions.php';

// 检查用户是否已登录
if (!isLoggedIn()) {
    echo "请先登录。";
    exit;
}

// 获取当前用户ID
$current_user_id = getCurrentUserId($db);
if ($current_user_id === null) {
    echo "无法获取用户ID。";
    exit;
}

// 添加访客密码字段
try {
    // 检查guest_password字段是否存在
    $stmt = $db->query("PRAGMA table_info(settings)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasGuestPassword = false;
    
    foreach ($columns as $column) {
        if ($column['name'] === 'guest_password') {
            $hasGuestPassword = true;
            break;
        }
    }
    
    // 如果字段不存在，添加它
    if (!$hasGuestPassword) {
        $db->exec("ALTER TABLE settings ADD COLUMN guest_password TEXT DEFAULT '1111'");
        echo "成功添加guest_password字段。<br>";
    } else {
        echo "guest_password字段已存在。<br>";
    }
    
    // 为所有用户设置默认值
    $stmt = $db->prepare("UPDATE settings SET guest_password = '1111' WHERE guest_password IS NULL");
    $stmt->execute();
    echo "成功设置默认值。<br>";
    
    echo "设置表更新成功。<br>";
    echo "现在您可以在设置页面中设置访客密码，并且系统将区分管理员和访客权限。<br>";
    echo "管理员使用原密码登录，访客使用您设置的访客密码登录。<br>";
    echo "访客只能查看内容，无法进行修改操作。<br>";
} catch (PDOException $e) {
    echo "更新设置表时出错: " . $e->getMessage();
}
