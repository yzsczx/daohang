<?php
// dh/update_settings_table.php
declare(strict_types=1);

// 包含必要的文件
require_once __DIR__ . '/app/core/config.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/functions.php';

// 获取数据库连接
try {
    $db = getDBConnection();
} catch (Exception $e) {
    echo "数据库连接失败: " . $e->getMessage();
    exit;
}

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

// 更新设置表
try {
    // 检查links_per_block字段是否存在
    $stmt = $db->query("PRAGMA table_info(settings)");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasLinksPerBlock = false;
    $hasBackgroundFixed = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'links_per_block') {
            $hasLinksPerBlock = true;
        }
        if ($column['name'] === 'background_fixed') {
            $hasBackgroundFixed = true;
        }
    }

    // 如果links_per_block字段不存在，添加它
    if (!$hasLinksPerBlock) {
        $db->exec("ALTER TABLE settings ADD COLUMN links_per_block INTEGER DEFAULT 10");
        echo "成功添加links_per_block字段。<br>";
    } else {
        echo "links_per_block字段已存在。<br>";
    }

    // 如果background_fixed字段不存在，添加它
    if (!$hasBackgroundFixed) {
        $db->exec("ALTER TABLE settings ADD COLUMN background_fixed INTEGER DEFAULT 1");
        echo "成功添加background_fixed字段。<br>";
    } else {
        echo "background_fixed字段已存在。<br>";
    }

    // 为所有用户设置默认值
    $stmt = $db->prepare("UPDATE settings SET links_per_block = 10 WHERE links_per_block IS NULL");
    $stmt->execute();
    $stmt = $db->prepare("UPDATE settings SET background_fixed = 1 WHERE background_fixed IS NULL");
    $stmt->execute();
    echo "成功设置默认值。<br>";

    echo "设置表更新成功。";
} catch (PDOException $e) {
    echo "更新设置表时出错: " . $e->getMessage();
}
