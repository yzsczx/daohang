<?php
// dh/get_favicon.php - 简单的图标抓取脚本
declare(strict_types=1);

// 禁止显示错误，确保输出纯JSON
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

// 设置内容类型为JSON
header('Content-Type: application/json');

// 防止任何输出缓冲干扰JSON输出
ob_start();

try {
    // 检查URL参数
    if (!isset($_GET['url']) || empty($_GET['url'])) {
        throw new Exception('缺少URL参数');
    }
    
    $url = trim($_GET['url']);
    
    // 提取域名
    $domain = extractDomain($url);
    if (empty($domain)) {
        throw new Exception('无法从URL提取域名: ' . $url);
    }
    
    // 使用Google Favicon服务
    $faviconUrl = "https://www.google.com/s2/favicons?domain=" . urlencode($domain);
    
    // 返回成功响应
    echo json_encode([
        'success' => true,
        'icon_url' => $faviconUrl
    ]);
    
} catch (Exception $e) {
    // 记录错误
    error_log("get_favicon: 错误: " . $e->getMessage());
    
    // 返回错误响应
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} catch (Throwable $t) {
    // 捕获所有可能的错误，包括致命错误
    error_log("get_favicon: 严重错误: " . $t->getMessage());
    
    // 清除之前的所有输出
    ob_end_clean();
    
    // 重新设置头部
    header('Content-Type: application/json');
    
    // 输出JSON错误
    echo json_encode([
        'error' => '服务器内部错误: ' . $t->getMessage()
    ]);
}

// 确保输出缓冲区被刷新并关闭
ob_end_flush();

/**
 * 从URL中提取域名
 * 
 * @param string $url URL
 * @return string|null 域名或null（如果无法提取）
 */
function extractDomain(string $url): ?string {
    // 确保URL格式正确
    if (!filter_var($url, FILTER_VALIDATE_URL) && strpos($url, 'http') !== 0) {
        // 尝试修复URL
        if (strpos($url, '//') === 0) {
            $url = 'http:' . $url;
        } elseif (strpos($url, '/') !== 0) {
            $url = 'http://' . $url;
        } else {
            $url = 'http://' . $url;
        }
    }
    
    // 尝试使用parse_url
    $parsedUrl = parse_url($url);
    if (isset($parsedUrl['host'])) {
        return $parsedUrl['host'];
    }
    
    // 尝试使用正则表达式
    if (preg_match('/^(?:https?:\/\/)?(?:www\.)?([^\/]+)/i', $url, $matches)) {
        return $matches[1];
    }
    
    return null;
}
