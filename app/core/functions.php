<?php
// dh/app/core/functions.php

/**
 * HTML转义
 */
function escape_html(?string $string): string {
    if ($string === null) return '';
    return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * URL重定向
 */
function redirect(string $url): void {
    header("Location: " . $url);
    exit;
}

/**
 * 获取当前用户ID
 */
function getCurrentUserId(PDO $db): ?int {
    if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    
    // 首先尝试从session获取用户ID
    if (isset($_SESSION['user_id'])) {
        return (int)$_SESSION['user_id'];
    }

    // 作为备选方案，从数据库获取默认用户
    if (defined('DEFAULT_USERNAME')) {
        try {
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([DEFAULT_USERNAME]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $_SESSION['user_id'] = (int)$user['id']; // 保存到session中
                return (int)$user['id'];
            }
        } catch (PDOException $e) {
            error_log("Error getting user ID: " . $e->getMessage());
        }
    }
    return null;
}

/**
 * 获取用户设置
 */
function getUserSettings(PDO $db, int $userId): ?array {
    try {
        $stmt = $db->prepare("SELECT * FROM settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch();
        return $settings ?: null;
    } catch (PDOException $e) { error_log("Error fetching settings for user $userId: " . $e->getMessage()); return null; }
}

/**
 * 更新用户设置
 * @param PDO $db
 * @param int $userId
 * @param array $settings
 * @return bool
 */
function updateUserSettings(PDO $db, int $userId, array $settings): bool {
    try {
        $db->beginTransaction();
        
        $sql = "INSERT INTO settings 
                (user_id, theme_name, background_image_url, background_fixed, 
                custom_css, weather_city, links_per_block, guest_password) 
                VALUES 
                (:user_id, :theme, :bg_url, :bg_fixed, :css, :weather, :links, :guest_pass)
                ON DUPLICATE KEY UPDATE 
                theme_name = VALUES(theme_name),
                background_image_url = VALUES(background_image_url),
                background_fixed = VALUES(background_fixed),
                custom_css = VALUES(custom_css),
                weather_city = VALUES(weather_city),
                links_per_block = VALUES(links_per_block),
                guest_password = VALUES(guest_password)";
        
        $stmt = $db->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $userId,
            ':theme' => $settings['theme_name'],
            ':bg_url' => $settings['background_image_url'] ?: null,
            ':bg_fixed' => $settings['background_fixed'],
            ':css' => $settings['custom_css'] ?: null,
            ':weather' => $settings['weather_city'] ?: null,
            ':links' => $settings['links_per_block'],
            ':guest_pass' => $settings['guest_password']
        ]);
        
        if ($result) {
            $db->commit();
            error_log("Settings updated successfully for user $userId");
            return true;
        } else {
            $db->rollBack();
            error_log("Failed to update settings for user $userId");
            return false;
        }
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error updating settings for user $userId: " . $e->getMessage());
        return false;
    }
}

/**
 * 获取用户所有页面
 */
function getUserPages(PDO $db, int $userId): array {
    try {
        $stmt = $db->prepare("SELECT * FROM pages WHERE user_id = ? ORDER BY display_order ASC, id ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) { error_log("Error fetching pages for user $userId: " . $e->getMessage()); return []; }
}

/**
 * 根据ID获取页面标题 (用于manage_blocks等)
 */
function getPageTitleById(PDO $db, ?int $pageId, int $userId): string {
    if ($pageId === null) return "未知页面";
    try {
        $stmt = $db->prepare("SELECT title FROM pages WHERE id = ? AND user_id = ?");
        $stmt->execute([$pageId, $userId]);
        $page = $stmt->fetch();
        return $page ? $page['title'] : "页面 {$pageId} 未找到";
    } catch (PDOException $e) { error_log("Error fetching page title $pageId: " . $e->getMessage()); return "错误"; }
}


/**
 * 获取页面的列
 */
function getPageColumns(PDO $db, int $pageId): array {
    try {
        // Optionally, verify pageId belongs to current user if not already done
        $stmt = $db->prepare("SELECT * FROM columns WHERE page_id = ? ORDER BY display_order ASC, id ASC");
        $stmt->execute([$pageId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) { error_log("Error fetching columns for page $pageId: " . $e->getMessage()); return []; }
}

/**
 * 获取列的区块
 */
function getColumnBlocks(PDO $db, int $columnId): array {
    try {
        $stmt = $db->prepare("SELECT * FROM blocks WHERE column_id = ? ORDER BY display_order ASC, id ASC");
        $stmt->execute([$columnId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) { error_log("Error fetching blocks for column $columnId: " . $e->getMessage()); return []; }
}

/**
 * 获取链接区块的链接
 * @param PDO $db 数据库连接
 * @param int $blockId 区块ID
 * @param int|null $limit 限制返回的链接数量，null表示不限制
 * @return array 链接数组
 */
function getBlockLinks(PDO $db, int $blockId, ?int $limit = null): array {
    try {
        $sql = "SELECT * FROM links WHERE block_id = ? ORDER BY display_order ASC, id ASC";
        if ($limit !== null && $limit > 0) {
            $sql .= " LIMIT " . (int)$limit;
        }
        error_log("Executing SQL: " . $sql . " with blockId: " . $blockId);
        $stmt = $db->prepare($sql);
        $stmt->execute([$blockId]);
        $result = $stmt->fetchAll();
        error_log("Found " . count($result) . " links for block " . $blockId);
        return $result;
    } catch (PDOException $e) {
        error_log("Error fetching links for block $blockId: " . $e->getMessage());
        return [];
    }
}

/**
 * 获取区块中的链接总数
 * @param PDO $db 数据库连接
 * @param int $blockId 区块ID
 * @return int 链接总数
 */
function getBlockLinksCount(PDO $db, int $blockId): int {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM links WHERE block_id = ?");
        $stmt->execute([$blockId]);
        return (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Error counting links for block $blockId: " . $e->getMessage());
        return 0;
    }
}

/**
 * 生成一个简单的 CSRF token (示例，生产环境应使用更健壮的库)
 */
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证 CSRF token
 */
function verifyCsrfToken(string $token): bool {
    if (isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
        // unset($_SESSION['csrf_token']); // Token can be one-time or per session
        return true;
    }
    return false;
}

?>