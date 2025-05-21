<?php
// dh/ajax_load_more_links.php
declare(strict_types=1);

// 包含必要的文件
require_once __DIR__ . '/app/core/config.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/functions.php';

header('Content-Type: application/json');

// 初始化数据库连接
try {
    $db = getDBConnection();
} catch (Exception $e) {
    error_log("Database connection error in ajax_load_more_links.php: " . $e->getMessage());
    echo json_encode(['error' => '数据库连接失败']);
    exit;
}

// 检查用户是否已登录
if (!isLoggedIn()) {
    echo json_encode(['error' => '未登录或会话已过期']);
    exit;
}

// 获取当前用户ID
$current_user_id = getCurrentUserId($db);
if ($current_user_id === null) {
    echo json_encode(['error' => '无法获取用户ID']);
    exit;
}

// 检查请求参数
if (!isset($_GET['block_id']) || !isset($_GET['offset']) || !isset($_GET['limit']) || !isset($_GET['page_id'])) {
    echo json_encode(['error' => '缺少必要参数']);
    exit;
}

$block_id = (int)$_GET['block_id'];
$offset = (int)$_GET['offset'];
$page_id = (int)$_GET['page_id'];

// 获取用户设置的链接数量
$limit = 10; // 默认值
$user_settings = getUserSettings($db, $current_user_id);
if (isset($user_settings) && isset($user_settings['links_per_block'])) {
    $limit = (int)$user_settings['links_per_block'];
}
// 如果请求中指定了limit，则使用请求中的值
if (isset($_GET['limit']) && (int)$_GET['limit'] > 0) {
    $limit = (int)$_GET['limit'];
}

// 验证区块所有权
try {
    $stmt = $db->prepare(
        "SELECT b.id
         FROM blocks b
         JOIN columns c ON b.column_id = c.id
         JOIN pages p ON c.page_id = p.id
         WHERE b.id = ? AND p.id = ? AND p.user_id = ?"
    );
    $stmt->execute([$block_id, $page_id, $current_user_id]);
    $block = $stmt->fetch();

    if (!$block) {
        echo json_encode(['error' => '无效的区块ID或权限不足']);
        exit;
    }
} catch (PDOException $e) {
    echo json_encode(['error' => '数据库错误']);
    exit;
}

// 获取更多链接
try {
    $stmt = $db->prepare("SELECT * FROM links WHERE block_id = ? ORDER BY display_order ASC, id ASC LIMIT ?, ?");
    $stmt->execute([$block_id, $offset, $limit]);
    $links = $stmt->fetchAll();

    // 准备HTML输出
    $html = '';
    foreach ($links as $link) {
        $html .= '<li class="link-item-bs mb-2">';
        $html .= '<a href="' . escape_html($link['url']) . '" target="_blank" class="d-flex align-items-center text-decoration-none link-item-anchor">';

        if (!empty($link['icon_url'])) {
            $html .= '<img src="' . escape_html($link['icon_url']) . '" class="link-favicon me-2" onerror="this.style.display=\'none\'; this.onerror=null;">';
        } else {
            $html .= '<span class="link-favicon-placeholder me-2"><i class="fas fa-link"></i></span>';
        }

        $html .= '<span class="link-title flex-grow-1">' . escape_html($link['title']) . '</span>';
        $html .= '</a>';
        $html .= '<div class="link-actions-bs ms-2">';
        $html .= '<a href="manage_links.php?action=edit&edit_id=' . (int)$link['id'] . '&block_id=' . $block_id . '&page_id=' . $page_id . '" class="btn-icon"><i class="fas fa-pencil-alt"></i></a>';

        // 生成CSRF令牌
        $csrf_token = generateCsrfToken();

        $html .= '<a href="manage_links.php?action=delete&delete_id=' . (int)$link['id'] . '&block_id=' . $block_id . '&page_id=' . $page_id . '&confirm=1&csrf_token=' . urlencode($csrf_token) . '" class="btn-icon text-danger" onclick="return confirm(\'删除链接 &quot;' . escape_html(addslashes($link['title'])) . '&quot;？\');"><i class="fas fa-times"></i></a>';
        $html .= '</div>';
        $html .= '</li>';
    }

    echo json_encode([
        'success' => true,
        'html' => $html,
        'count' => count($links)
    ]);

} catch (PDOException $e) {
    echo json_encode(['error' => '获取链接失败']);
    exit;
}
