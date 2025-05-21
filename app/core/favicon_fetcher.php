<?php
// dh/app/core/favicon_fetcher.php
declare(strict_types=1);

/**
 * 网站图标抓取类
 * 用于从网站URL中抓取favicon图标
 */
class FaviconFetcher {

    /**
     * 从URL中抓取favicon
     *
     * @param string $url 网站URL
     * @return string|null 图标URL或null（如果未找到）
     */
    public static function fetch(string $url): ?string {
        try {
            // 提取域名
            $domain = self::extractDomain($url);
            if (empty($domain)) {
                error_log("FaviconFetcher: 无法从URL提取域名: {$url}");
                // 尝试直接使用URL
                $domain = $url;
            }

            // 记录日志
            error_log("FaviconFetcher: 为域名 {$domain} 抓取图标");

            // 直接使用Google Favicon服务
            $googleFaviconUrl = "https://www.google.com/s2/favicons?domain=" . urlencode($domain);
            error_log("FaviconFetcher: 使用Google Favicon服务: {$googleFaviconUrl}");
            return $googleFaviconUrl;

            /* 以下方法暂时禁用，因为可能导致错误
            // 尝试方法1: 直接访问 /favicon.ico
            $faviconUrl = $baseUrl . '/favicon.ico';
            if (self::checkImageExists($faviconUrl)) {
                error_log("FaviconFetcher: 在 {$faviconUrl} 找到图标");
                return $faviconUrl;
            }

            // 尝试方法2: 解析HTML页面查找<link rel="icon">或<link rel="shortcut icon">标签
            try {
                $html = self::fetchUrl($baseUrl);
                if ($html) {
                    // 查找所有图标链接
                    $iconUrls = self::extractIconUrlsFromHtml($html, $baseUrl);
                    if (!empty($iconUrls)) {
                        error_log("FaviconFetcher: 从HTML中找到 " . count($iconUrls) . " 个图标链接");

                        // 按优先级排序并返回第一个有效的图标
                        foreach ($iconUrls as $iconUrl) {
                            if (self::checkImageExists($iconUrl)) {
                                error_log("FaviconFetcher: 选择图标 {$iconUrl}");
                                return $iconUrl;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                error_log("FaviconFetcher: 解析HTML时出错: " . $e->getMessage());
            }

            // 尝试方法3: 使用Google Favicon服务
            $googleFaviconUrl = "https://www.google.com/s2/favicons?domain=" . urlencode($domain);
            error_log("FaviconFetcher: 使用Google Favicon服务: {$googleFaviconUrl}");
            return $googleFaviconUrl;
            */
        } catch (Exception $e) {
            error_log("FaviconFetcher: 抓取图标时出错: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 从HTML中提取图标URL
     *
     * @param string $html HTML内容
     * @param string $baseUrl 基础URL（用于解析相对路径）
     * @return array 图标URL数组
     */
    private static function extractIconUrlsFromHtml(string $html, string $baseUrl): array {
        $iconUrls = [];

        // 使用正则表达式查找所有图标链接
        $patterns = [
            '/<link[^>]*rel=["\'](?:shortcut )?icon["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i',
            '/<link[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\'](?:shortcut )?icon["\'][^>]*>/i',
            '/<link[^>]*rel=["\']apple-touch-icon["\'][^>]*href=["\']([^"\']+)["\'][^>]*>/i',
            '/<link[^>]*href=["\']([^"\']+)["\'][^>]*rel=["\']apple-touch-icon["\'][^>]*>/i'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $match) {
                    // 处理相对URL
                    if (strpos($match, 'http') !== 0 && strpos($match, '//') !== 0) {
                        if (strpos($match, '/') === 0) {
                            $match = $baseUrl . $match;
                        } else {
                            $match = $baseUrl . '/' . $match;
                        }
                    } else if (strpos($match, '//') === 0) {
                        $match = 'http:' . $match;
                    }

                    $iconUrls[] = $match;
                }
            }
        }

        return $iconUrls;
    }

    /**
     * 检查图像URL是否存在
     *
     * @param string $url 图像URL
     * @return bool 是否存在
     */
    private static function checkImageExists(string $url): bool {
        $headers = @get_headers($url);
        if ($headers && strpos($headers[0], '200') !== false) {
            // 检查内容类型是否为图像
            foreach ($headers as $header) {
                if (stripos($header, 'Content-Type:') !== false &&
                    (stripos($header, 'image/') !== false || stripos($header, 'icon') !== false)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 获取URL内容
     *
     * @param string $url URL
     * @return string|null 内容或null（如果获取失败）
     */
    private static function fetchUrl(string $url): ?string {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n",
                'timeout' => 5
            ]
        ]);

        $content = @file_get_contents($url, false, $context);
        return $content !== false ? $content : null;
    }

    /**
     * 从URL中提取域名
     *
     * @param string $url URL
     * @return string|null 域名或null（如果无法提取）
     */
    private static function extractDomain(string $url): ?string {
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
}
