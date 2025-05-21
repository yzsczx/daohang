<?php
// dh/add_background_fixed.php
// 添加背景固定字段到设置表

// 显示所有错误
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 数据库路径
$db_path = __DIR__ . '/database.db';
echo "数据库路径: $db_path<br>";

try {
    // 连接到数据库
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "数据库连接成功<br>";

    // 检查settings表是否存在
    $table_exists = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='settings'")->fetchColumn();

    if (!$table_exists) {
        echo "settings表不存在，无法更新<br>";
        exit;
    }

    // 检查background_fixed字段是否存在
    $columns = $db->query("PRAGMA table_info(settings)")->fetchAll(PDO::FETCH_ASSOC);
    $field_exists = false;

    foreach ($columns as $column) {
        if ($column['name'] === 'background_fixed') {
            $field_exists = true;
            break;
        }
    }

    if ($field_exists) {
        echo "background_fixed字段已存在，无需更新<br>";
    } else {
        // 添加background_fixed字段
        $db->exec("ALTER TABLE settings ADD COLUMN background_fixed INTEGER DEFAULT 1");
        echo "成功添加background_fixed字段<br>";

        // 更新所有记录，设置默认值为1（固定）
        $db->exec("UPDATE settings SET background_fixed = 1 WHERE background_fixed IS NULL");
        echo "成功更新所有记录的background_fixed字段为1（固定）<br>";

        // 显示所有设置记录
        $settings = $db->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>设置表中的所有记录:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>用户ID</th><th>主题</th><th>背景图片URL</th><th>背景固定</th><th>链接数量</th></tr>";

        foreach ($settings as $setting) {
            echo "<tr>";
            echo "<td>" . $setting['id'] . "</td>";
            echo "<td>" . $setting['user_id'] . "</td>";
            echo "<td>" . $setting['theme_name'] . "</td>";
            echo "<td>" . ($setting['background_image_url'] ?: '无') . "</td>";
            echo "<td>" . (isset($setting['background_fixed']) ? $setting['background_fixed'] : '未设置') . "</td>";
            echo "<td>" . ($setting['links_per_block'] ?: '10') . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    }

    // 添加直接修改背景固定设置的功能
    echo "<h2>直接修改背景固定设置</h2>";
    echo "<form method='POST' action=''>";
    echo "<input type='hidden' name='action' value='update_background_fixed'>";
    echo "<select name='background_fixed'>";
    echo "<option value='1'>固定（不随页面滚动）</option>";
    echo "<option value='0'>滚动（随页面滚动）</option>";
    echo "</select>";
    echo "<button type='submit'>更新所有用户的背景固定设置</button>";
    echo "</form>";

    // 处理表单提交
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_background_fixed') {
        $new_value = isset($_POST['background_fixed']) ? (int)$_POST['background_fixed'] : 1;

        try {
            $db->exec("UPDATE settings SET background_fixed = $new_value");
            echo "<p style='color: green;'>成功将所有用户的背景固定设置更新为: " . ($new_value ? "固定" : "滚动") . "</p>";

            // 显示更新后的设置
            $settings = $db->query("SELECT * FROM settings")->fetchAll(PDO::FETCH_ASSOC);
            echo "<h3>更新后的设置:</h3>";
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>用户ID</th><th>背景固定</th></tr>";

            foreach ($settings as $setting) {
                echo "<tr>";
                echo "<td>" . $setting['id'] . "</td>";
                echo "<td>" . $setting['user_id'] . "</td>";
                echo "<td>" . $setting['background_fixed'] . "</td>";
                echo "</tr>";
            }

            echo "</table>";
        } catch (Exception $e) {
            echo "<p style='color: red;'>更新失败: " . $e->getMessage() . "</p>";
        }
    }

    echo "<h2>更新完成</h2>";
    echo "<p>现在您可以在设置页面中自定义背景图片是否随页面滚动。</p>";
    echo "<p><a href='settings.php'>点击这里前往设置页面</a></p>";

} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "一般错误: " . $e->getMessage() . "<br>";
}
