<?php
// dh/find_all_databases.php
// 查找所有可能的数据库文件并检查其内容

// 显示所有错误
ini_set('display_errors', '1');
error_reporting(E_ALL);

echo "<h1>查找所有可能的数据库文件</h1>";

// 递归查找所有数据库文件
function findDatabaseFiles($dir, $extensions = ['db', 'sqlite', 'sqlite3']) {
    $results = [];
    
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        
        $path = $dir . '/' . $file;
        
        if (is_dir($path)) {
            // 递归查找子目录
            $results = array_merge($results, findDatabaseFiles($path, $extensions));
        } else {
            // 检查文件扩展名
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), $extensions)) {
                $results[] = $path;
            }
        }
    }
    
    return $results;
}

// 查找所有数据库文件
$db_files = findDatabaseFiles(__DIR__);

if (empty($db_files)) {
    echo "<p>没有找到数据库文件</p>";
} else {
    echo "<p>找到 " . count($db_files) . " 个数据库文件:</p>";
    
    echo "<table border='1'>";
    echo "<tr><th>文件路径</th><th>大小</th><th>修改时间</th><th>操作</th></tr>";
    
    foreach ($db_files as $file) {
        $relative_path = str_replace(__DIR__ . '/', '', $file);
        echo "<tr>";
        echo "<td>" . $relative_path . "</td>";
        echo "<td>" . filesize($file) . " 字节</td>";
        echo "<td>" . date("Y-m-d H:i:s", filemtime($file)) . "</td>";
        echo "<td><a href='?examine=" . urlencode($file) . "'>检查</a></td>";
        echo "</tr>";
    }
    
    echo "</table>";
}

// 检查指定的数据库文件
if (isset($_GET['examine'])) {
    $db_file = $_GET['examine'];
    
    if (!file_exists($db_file)) {
        echo "<p>文件不存在: $db_file</p>";
    } else {
        echo "<h2>检查数据库文件: " . str_replace(__DIR__ . '/', '', $db_file) . "</h2>";
        
        try {
            // 连接到数据库
            $db = new PDO('sqlite:' . $db_file);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 获取所有表
            $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
            
            if (empty($tables)) {
                echo "<p>数据库中没有表</p>";
            } else {
                echo "<p>数据库中有 " . count($tables) . " 个表:</p>";
                
                echo "<ul>";
                foreach ($tables as $table) {
                    echo "<li><a href='?examine=" . urlencode($db_file) . "&table=" . urlencode($table) . "'>$table</a></li>";
                }
                echo "</ul>";
            }
            
            // 检查指定的表
            if (isset($_GET['table'])) {
                $table = $_GET['table'];
                
                echo "<h3>表: $table</h3>";
                
                // 获取表结构
                $columns = $db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC);
                
                echo "<h4>表结构:</h4>";
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
                
                // 获取表数据
                $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
                echo "<h4>表数据 (共 $count 行):</h4>";
                
                if ($count > 0) {
                    $rows = $db->query("SELECT * FROM $table LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo "<table border='1'>";
                    
                    // 表头
                    echo "<tr>";
                    foreach (array_keys($rows[0]) as $key) {
                        echo "<th>" . $key . "</th>";
                    }
                    echo "</tr>";
                    
                    // 数据行
                    foreach ($rows as $row) {
                        echo "<tr>";
                        foreach ($row as $key => $value) {
                            // 如果是密码字段，只显示长度
                            if (strpos(strtolower($key), 'password') !== false || strpos(strtolower($key), 'hash') !== false) {
                                echo "<td>[密码哈希，长度: " . strlen($value) . "]</td>";
                            } else {
                                echo "<td>" . htmlspecialchars($value) . "</td>";
                            }
                        }
                        echo "</tr>";
                    }
                    
                    echo "</table>";
                    
                    // 如果是users表，提供密码重置选项
                    if ($table === 'users') {
                        echo "<h4>重置用户密码:</h4>";
                        echo "<form method='POST' action='?examine=" . urlencode($db_file) . "&table=" . urlencode($table) . "'>";
                        echo "<select name='user_id'>";
                        foreach ($rows as $row) {
                            echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['username']) . " (ID: " . $row['id'] . ")</option>";
                        }
                        echo "</select>";
                        echo "<input type='text' name='new_password' placeholder='新密码' value='admin123'>";
                        echo "<button type='submit' name='reset_password'>重置密码</button>";
                        echo "</form>";
                        
                        // 处理密码重置
                        if (isset($_POST['reset_password'])) {
                            $user_id = $_POST['user_id'] ?? '';
                            $new_password = $_POST['new_password'] ?? '';
                            
                            if (empty($user_id) || empty($new_password)) {
                                echo "<p>请选择用户并输入新密码</p>";
                            } else {
                                // 生成密码哈希
                                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                                
                                // 更新密码
                                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                                $result = $stmt->execute([$password_hash, $user_id]);
                                
                                if ($result) {
                                    $rows_affected = $stmt->rowCount();
                                    echo "<p>密码更新执行完成。受影响的行数: $rows_affected</p>";
                                    
                                    if ($rows_affected > 0) {
                                        echo "<p>密码已成功重置为: $new_password</p>";
                                        echo "<p><a href='?examine=" . urlencode($db_file) . "&table=" . urlencode($table) . "'>刷新</a></p>";
                                    } else {
                                        echo "<p>密码未更改（没有记录被更新）</p>";
                                    }
                                } else {
                                    echo "<p>密码更新失败</p>";
                                }
                            }
                        }
                    }
                }
            }
            
        } catch (PDOException $e) {
            echo "<p>数据库错误: " . $e->getMessage() . "</p>";
        } catch (Exception $e) {
            echo "<p>一般错误: " . $e->getMessage() . "</p>";
        }
    }
}
