<?php
// dh/local_favicon.php - 本地图标抓取服务
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
    
    // 构建可能的图标URL
    $iconUrl = getFaviconUrl($domain);
    
    // 返回成功响应
    echo json_encode([
        'success' => true,
        'icon_url' => $iconUrl
    ]);
    
} catch (Exception $e) {
    // 记录错误
    error_log("local_favicon: 错误: " . $e->getMessage());
    
    // 返回错误响应
    echo json_encode([
        'error' => $e->getMessage()
    ]);
} catch (Throwable $t) {
    // 捕获所有可能的错误，包括致命错误
    error_log("local_favicon: 严重错误: " . $t->getMessage());
    
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

/**
 * 获取网站图标URL
 * 
 * @param string $domain 域名
 * @return string 图标URL
 */
function getFaviconUrl(string $domain): string {
    // 尝试方法1: 直接使用域名的favicon.ico
    $iconUrl = "https://{$domain}/favicon.ico";
    
    // 设置请求超时
    $context = stream_context_create([
        'http' => [
            'timeout' => 2, // 2秒超时
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n"
        ]
    ]);
    
    // 检查图标是否存在
    $headers = @get_headers($iconUrl, 1, $context);
    if ($headers && strpos($headers[0], '200') !== false) {
        return $iconUrl;
    }
    
    // 尝试方法2: 使用www子域名
    if (strpos($domain, 'www.') !== 0) {
        $wwwDomain = 'www.' . $domain;
        $iconUrl = "https://{$wwwDomain}/favicon.ico";
        $headers = @get_headers($iconUrl, 1, $context);
        if ($headers && strpos($headers[0], '200') !== false) {
            return $iconUrl;
        }
    }
    
    // 尝试方法3: 使用默认图标
    return "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAAbwAAAG8B8aLcQwAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAB9SURBVDiNY/z//z8DJYCJgUIwDAxgYWBgYHj06BHD7du3GWbMmMHAwMDA8P//f4bPnz8zPHnyhOHLly8M//79w6ojKiqKwcnJieHZs2cMJiYmDIKCggysrKwMjIyMDB8+fGD4/fs3AycnJ4OAgABWRfgA48iLTEqzcuQbAAAA//9A/Af/6nZM7wAAAABJRU5ErkJggg==";
}
