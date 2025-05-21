<?php
// dh/check_database.php
// 检查数据库结构

// 显示所有错误
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 启动会话
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 检查是否已登录
if (!isset($_SESSION['user_id'])) {
    echo "请先登录。";
    exit;
}

// 连接到数据库
$db_path = __DIR__ . '/database.db';
echo "尝试连接到数据库: $db_path<br>";

// 检查配置文件中的数据库设置
$config_file = __DIR__ . '/app/core/config.php';
if (file_exists($config_file)) {
    echo "找到配置文件: $config_file<br>";

    // 读取配置文件内容
    $config_content = file_get_contents($config_file);

    // 查找数据库相关的定义
    preg_match_all('/define\s*\(\s*[\'"]([^\'"]*)[\'"]\s*,\s*[\'"]([^\'"]*)[\'"]/', $config_content, $matches);

    if (!empty($matches[1])) {
        echo "<h3>配置文件中的设置:</h3>";
        echo "<table border='1'>";
        echo "<tr><th>常量</th><th>值</th></tr>";

        for ($i = 0; $i < count($matches[1]); $i++) {
            $constant = $matches[1][$i];
            $value = $matches[2][$i];

            // 不显示密码
            if (strpos(strtolower($constant), 'pass') !== false || strpos(strtolower($constant), 'password') !== false) {
                $value = '******';
            }

            echo "<tr><td>" . htmlspecialchars($constant) . "</td><td>" . htmlspecialchars($value) . "</td></tr>";
        }

        echo "</table>";
    } else {
        echo "在配置文件中没有找到常量定义<br>";
    }
} else {
    echo "配置文件不存在: $config_file<br>";
}

if (!file_exists($db_path)) {
    echo "错误: 数据库文件不存在!<br>";

    // 尝试查找其他可能的数据库文件
    $files = glob(__DIR__ . '/*.db');
    $files = array_merge($files, glob(__DIR__ . '/**/*.db'));
    $files = array_merge($files, glob(__DIR__ . '/**/**/*.db'));

    if (!empty($files)) {
        echo "在目录树中找到以下数据库文件:<br>";
        foreach ($files as $file) {
            $relative_path = str_replace(__DIR__ . '/', '', $file);
            echo "- " . $relative_path . " (大小: " . filesize($file) . " 字节)<br>";

            // 尝试打开每个数据库文件并检查表
            try {
                $test_db = new PDO('sqlite:' . $file);
                $test_tables = $test_db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

                if (empty($test_tables)) {
                    echo "&nbsp;&nbsp;此数据库中没有表<br>";
                } else {
                    echo "&nbsp;&nbsp;此数据库包含以下表: " . implode(', ', $test_tables) . "<br>";
                }
            } catch (Exception $e) {
                echo "&nbsp;&nbsp;无法打开此数据库: " . $e->getMessage() . "<br>";
            }
        }
    } else {
        echo "在整个目录树中没有找到任何 .db 文件<br>";
    }

    // 也检查 .sqlite 文件
    $sqlite_files = glob(__DIR__ . '/*.sqlite');
    $sqlite_files = array_merge($sqlite_files, glob(__DIR__ . '/**/*.sqlite'));
    $sqlite_files = array_merge($sqlite_files, glob(__DIR__ . '/**/**/*.sqlite'));

    if (!empty($sqlite_files)) {
        echo "<br>在目录树中找到以下 .sqlite 文件:<br>";
        foreach ($sqlite_files as $file) {
            $relative_path = str_replace(__DIR__ . '/', '', $file);
            echo "- " . $relative_path . " (大小: " . filesize($file) . " 字节)<br>";

            // 尝试打开每个数据库文件并检查表
            try {
                $test_db = new PDO('sqlite:' . $file);
                $test_tables = $test_db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

                if (empty($test_tables)) {
                    echo "&nbsp;&nbsp;此数据库中没有表<br>";
                } else {
                    echo "&nbsp;&nbsp;此数据库包含以下表: " . implode(', ', $test_tables) . "<br>";
                }
            } catch (Exception $e) {
                echo "&nbsp;&nbsp;无法打开此数据库: " . $e->getMessage() . "<br>";
            }
        }
    }

    exit;
}

try {
    // 连接到数据库
    $dsn = 'sqlite:' . $db_path;
    $db = new PDO($dsn);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "数据库连接成功<br>";

    // 获取所有表
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "数据库中没有表!<br>";
    } else {
        echo "<h2>数据库中的表:</h2>";
        echo "<ul>";
        foreach ($tables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";

        // 对每个表显示结构
        echo "<h2>表结构:</h2>";
        foreach ($tables as $table) {
            echo "<h3>表: $table</h3>";

            // 获取表结构
            $columns = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);

            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>名称</th><th>类型</th><th>非空</th><th>默认值</th><th>主键</th></tr>";

            foreach ($columns as $column) {
                echo "<tr>";
                echo "<td>" . $column['cid'] . "</td>";
                echo "<td>" . $column['name'] . "</td>";
                echo "<td>" . $column['type'] . "</td>";
                echo "<td>" . $column['notnull'] . "</td>";
                echo "<td>" . $column['dflt_value'] . "</td>";
                echo "<td>" . $column['pk'] . "</td>";
                echo "</tr>";
            }

            echo "</table>";

            // 显示表中的数据（限制为前10行）
            $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
            echo "<p>表中有 $count 行数据</p>";

            if ($count > 0) {
                echo "<h4>数据预览 (最多10行):</h4>";

                $rows = $db->query("SELECT * FROM $table LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($rows)) {
                    echo "<table border='1'>";

                    // 表头
                    echo "<tr>";
                    foreach (array_keys($rows[0]) as $key) {
                        echo "<th>" . htmlspecialchars($key) . "</th>";
                    }
                    echo "</tr>";

                    // 数据行
                    foreach ($rows as $row) {
                        echo "<tr>";
                        foreach ($row as $value) {
                            // 如果是密码字段，只显示长度
                            if (strpos(strtolower($value), 'password') !== false || strpos(strtolower($value), 'hash') !== false) {
                                echo "<td>[密码哈希，长度: " . strlen($value) . "]</td>";
                            } else {
                                echo "<td>" . htmlspecialchars($value) . "</td>";
                            }
                        }
                        echo "</tr>";
                    }

                    echo "</table>";
                }
            }
        }
    }

} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "一般错误: " . $e->getMessage() . "<br>";
}
