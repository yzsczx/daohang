<?php
// dh/create_settings_table.php
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

// 创建设置表
try {
    // 创建用户设置表
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            setting_key TEXT NOT NULL,
            setting_value TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(user_id, setting_key)
        )
    ");
    
    // 添加索引
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_settings_user_id ON user_settings(user_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_user_settings_key ON user_settings(setting_key)");
    
    // 设置默认值
    $stmt = $db->prepare("
        INSERT OR IGNORE INTO user_settings (user_id, setting_key, setting_value)
        VALUES (?, 'links_per_block', '10')
    ");
    $stmt->execute([$current_user_id]);
    
    echo "设置表创建成功，并设置了默认值。";
} catch (PDOException $e) {
    echo "创建设置表时出错: " . $e->getMessage();
}
