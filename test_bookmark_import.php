<?php
// 测试书签导入
declare(strict_types=1);

// 包含必要的文件
require_once __DIR__ . '/app/core/config.php';
require_once __DIR__ . '/app/core/database.php';
require_once __DIR__ . '/app/core/auth.php';
require_once __DIR__ . '/app/core/functions.php';

// 检查是否上传了文件
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['bookmark_file'])) {
    if ($_FILES['bookmark_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['bookmark_file']['tmp_name'];
        $file_content = file_get_contents($file_tmp_path);
        
        if ($file_content === false) {
            echo "<p style='color:red'>无法读取上传的文件内容</p>";
        } else {
            // 显示文件内容预览
            echo "<h2>文件内容预览</h2>";
            echo "<pre>" . htmlspecialchars(substr($file_content, 0, 2000)) . "</pre>";
            
            // 分析文件结构
            echo "<h2>文件结构分析</h2>";
            
            // 检查基本标签
            $has_dl = stripos($file_content, '<dl') !== false;
            $has_dt = stripos($file_content, '<dt') !== false;
            $has_a = stripos($file_content, '<a') !== false;
            $has_href = stripos($file_content, 'href=') !== false;
            
            echo "<p>包含 &lt;dl&gt; 标签: " . ($has_dl ? "是" : "否") . "</p>";
            echo "<p>包含 &lt;dt&gt; 标签: " . ($has_dt ? "是" : "否") . "</p>";
            echo "<p>包含 &lt;a&gt; 标签: " . ($has_a ? "是" : "否") . "</p>";
            echo "<p>包含 href 属性: " . ($has_href ? "是" : "否") . "</p>";
            
            // 使用正则表达式查找链接
            $pattern = '/<a\s+[^>]*href=(["\'])(.*?)\1[^>]*>(.*?)<\/a>/is';
            preg_match_all($pattern, $file_content, $matches, PREG_SET_ORDER);
            
            echo "<p>找到 " . count($matches) . " 个链接</p>";
            
            if (count($matches) > 0) {
                echo "<h3>链接示例:</h3>";
                echo "<ul>";
                for ($i = 0; $i < min(10, count($matches)); $i++) {
                    $url = $matches[$i][2];
                    $title = strip_tags($matches[$i][3]);
                    echo "<li><strong>" . htmlspecialchars($title) . "</strong>: " . htmlspecialchars($url) . "</li>";
                }
                echo "</ul>";
            }
            
            // 尝试使用 DOM 解析
            $doc = new DOMDocument();
            libxml_use_internal_errors(true);
            $doc->loadHTML($file_content);
            $errors = libxml_get_errors();
            libxml_clear_errors();
            
            echo "<h3>DOM 解析结果:</h3>";
            echo "<p>解析错误数: " . count($errors) . "</p>";
            
            if (count($errors) > 0) {
                echo "<p>前 5 个错误:</p>";
                echo "<ul>";
                for ($i = 0; $i < min(5, count($errors)); $i++) {
                    echo "<li>" . htmlspecialchars($errors[$i]->message) . "</li>";
                }
                echo "</ul>";
            }
            
            $xpath = new DOMXPath($doc);
            $dlNodes = $xpath->query('//dl');
            $dtNodes = $xpath->query('//dt');
            $aNodes = $xpath->query('//a[@href]');
            
            echo "<p>DOM 中找到的 &lt;dl&gt; 节点数: " . $dlNodes->length . "</p>";
            echo "<p>DOM 中找到的 &lt;dt&gt; 节点数: " . $dtNodes->length . "</p>";
            echo "<p>DOM 中找到的 &lt;a&gt; 节点数: " . $aNodes->length . "</p>";
        }
    } else {
        echo "<p style='color:red'>文件上传失败，错误代码: " . $_FILES['bookmark_file']['error'] . "</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>测试书签导入</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
        h1, h2, h3 { color: #333; }
        pre { background-color: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; }
        button { padding: 8px 15px; background-color: #4CAF50; color: white; border: none; cursor: pointer; }
        button:hover { background-color: #45a049; }
    </style>
</head>
<body>
    <h1>测试书签导入</h1>
    <p>上传书签文件以查看其结构和内容</p>
    
    <form action="test_bookmark_import.php" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="bookmark_file">选择书签HTML文件:</label>
            <input type="file" id="bookmark_file" name="bookmark_file" accept=".html,.htm" required>
        </div>
        <button type="submit">分析文件</button>
    </form>
</body>
</html>
