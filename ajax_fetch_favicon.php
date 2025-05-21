<?php
// dh/ajax_fetch_favicon.php
declare(strict_types=1);

// 禁止显示错误，确保输出纯JSON
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// 设置内容类型为JSON
header('Content-Type: application/json');

// 防止任何输出缓冲干扰JSON输出
ob_start();

// 捕获所有错误
try {
    // 包含必要的文件
    require_once __DIR__ . '/app/core/config.php';
    require_once __DIR__ . '/app/core/database.php';
    require_once __DIR__ . '/app/core/auth.php';
    require_once __DIR__ . '/app/core/functions.php';
    require_once __DIR__ . '/app/core/favicon_fetcher.php';

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
if (!isset($_GET['url']) || empty($_GET['url'])) {
    echo json_encode(['error' => '缺少URL参数']);
    exit;
}

$url = trim($_GET['url']);

// 尝试抓取图标
try {
    // 记录请求
    error_log("ajax_fetch_favicon: 尝试为 URL {$url} 抓取图标");

    // 设置超时时间，避免长时间等待
    ini_set('default_socket_timeout', 5);

    $iconUrl = FaviconFetcher::fetch($url);

    if ($iconUrl) {
        error_log("ajax_fetch_favicon: 成功抓取图标: {$iconUrl}");
        echo json_encode(['success' => true, 'icon_url' => $iconUrl]);
    } else {
        error_log("ajax_fetch_favicon: 未找到图标");
        // 使用Google Favicon服务作为备选
        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) {
            // 尝试修复URL
            if (strpos($url, '//') === 0) {
                $url = 'http:' . $url;
            } elseif (strpos($url, '/') !== 0) {
                $url = 'http://' . $url;
            } else {
                $url = 'http://' . $url;
            }
            $domain = parse_url($url, PHP_URL_HOST);
        }

        if ($domain) {
            $googleFaviconUrl = "https://www.google.com/s2/favicons?domain=" . urlencode($domain);
            error_log("ajax_fetch_favicon: 使用Google Favicon服务: {$googleFaviconUrl}");
            echo json_encode(['success' => true, 'icon_url' => $googleFaviconUrl]);
        } else {
            echo json_encode(['error' => '无法解析URL']);
        }
    }
} catch (Exception $e) {
    error_log("ajax_fetch_favicon: 抓取图标时出错: " . $e->getMessage());
    echo json_encode(['error' => '抓取图标时出错: ' . $e->getMessage()]);
}
} catch (Throwable $t) {
    // 捕获所有可能的错误，包括致命错误
    error_log("ajax_fetch_favicon: 严重错误: " . $t->getMessage());

    // 清除之前的所有输出
    ob_end_clean();

    // 重新设置头部
    header('Content-Type: application/json');

    // 输出JSON错误
    echo json_encode(['error' => '服务器内部错误，请查看日志']);
}

// 确保输出缓冲区被刷新并关闭
ob_end_flush();
