<?php
// dh/initialize_database.php
// 初始化数据库，创建必要的表和用户

// 显示所有错误
ini_set('display_errors', '1');
error_reporting(E_ALL);

// 数据库路径
$db_path = __DIR__ . '/database.db';
echo "数据库路径: $db_path<br>";

try {
    // 检查数据库文件是否存在
    if (!file_exists($db_path)) {
        echo "数据库文件不存在，将创建新文件<br>";
    } else {
        echo "数据库文件已存在，大小: " . filesize($db_path) . " 字节<br>";
    }
    
    // 连接到数据库
    $db = new PDO('sqlite:' . $db_path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "数据库连接成功<br>";
    
    // 获取所有表
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "数据库中没有表，将创建必要的表<br>";
        
        // 创建用户表
        $db->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        echo "创建了 users 表<br>";
        
        // 创建设置表
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                theme_name TEXT DEFAULT 'default',
                background_image_url TEXT,
                custom_css TEXT,
                weather_city TEXT,
                links_per_block INTEGER DEFAULT 10,
                guest_password TEXT DEFAULT '1111',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        echo "创建了 settings 表<br>";
        
        // 创建页面表
        $db->exec("
            CREATE TABLE IF NOT EXISTS pages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                position INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        echo "创建了 pages 表<br>";
        
        // 创建区块表
        $db->exec("
            CREATE TABLE IF NOT EXISTS blocks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                page_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                content TEXT,
                position INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
            )
        ");
        echo "创建了 blocks 表<br>";
        
        // 创建链接表
        $db->exec("
            CREATE TABLE IF NOT EXISTS links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                block_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                url TEXT NOT NULL,
                icon_url TEXT,
                position INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (block_id) REFERENCES blocks(id) ON DELETE CASCADE
            )
        ");
        echo "创建了 links 表<br>";
        
        // 创建默认管理员用户
        $admin_username = 'admin';
        $admin_password = 'admin123';
        $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
        
        $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        $stmt->execute([$admin_username, $password_hash]);
        $admin_id = $db->lastInsertId();
        
        echo "创建了默认管理员用户 (ID: $admin_id, 用户名: $admin_username, 密码: $admin_password)<br>";
        
        // 创建默认设置
        $stmt = $db->prepare("INSERT INTO settings (user_id, theme_name, guest_password) VALUES (?, 'default', '1111')");
        $stmt->execute([$admin_id]);
        
        echo "创建了默认设置<br>";
        
        // 创建默认页面
        $stmt = $db->prepare("INSERT INTO pages (user_id, title, position) VALUES (?, '首页', 0)");
        $stmt->execute([$admin_id]);
        $page_id = $db->lastInsertId();
        
        echo "创建了默认页面 (ID: $page_id)<br>";
        
        // 创建默认区块
        $stmt = $db->prepare("INSERT INTO blocks (user_id, page_id, title, position) VALUES (?, ?, '常用链接', 0)");
        $stmt->execute([$admin_id, $page_id]);
        $block_id = $db->lastInsertId();
        
        echo "创建了默认区块 (ID: $block_id)<br>";
        
        // 创建默认链接
        $links = [
            ['百度', 'https://www.baidu.com', 'https://www.baidu.com/favicon.ico', 0],
            ['Google', 'https://www.google.com', 'https://www.google.com/favicon.ico', 1],
            ['淘宝', 'https://www.taobao.com', 'https://www.taobao.com/favicon.ico', 2],
            ['京东', 'https://www.jd.com', 'https://www.jd.com/favicon.ico', 3],
            ['哔哩哔哩', 'https://www.bilibili.com', 'https://www.bilibili.com/favicon.ico', 4]
        ];
        
        $stmt = $db->prepare("INSERT INTO links (user_id, block_id, title, url, icon_url, position) VALUES (?, ?, ?, ?, ?, ?)");
        
        foreach ($links as $link) {
            $stmt->execute([$admin_id, $block_id, $link[0], $link[1], $link[2], $link[3]]);
            echo "创建了默认链接: {$link[0]}<br>";
        }
        
        echo "<h2>数据库初始化完成！</h2>";
        echo "<p>您现在可以使用以下凭据登录:</p>";
        echo "<ul>";
        echo "<li>管理员用户名: $admin_username</li>";
        echo "<li>管理员密码: $admin_password</li>";
        echo "<li>访客密码: 1111</li>";
        echo "</ul>";
        echo "<p><a href='login.php'>点击这里登录</a></p>";
        
    } else {
        echo "数据库中已有以下表: " . implode(', ', $tables) . "<br>";
        echo "数据库已经初始化，不需要再次初始化<br>";
        
        // 检查是否有用户表
        if (in_array('users', $tables)) {
            // 获取用户数量
            $user_count = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
            echo "用户表中有 $user_count 个用户<br>";
            
            if ($user_count === 0) {
                echo "用户表为空，将创建默认管理员用户<br>";
                
                // 创建默认管理员用户
                $admin_username = 'admin';
                $admin_password = 'admin123';
                $password_hash = password_hash($admin_password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
                $stmt->execute([$admin_username, $password_hash]);
                $admin_id = $db->lastInsertId();
                
                echo "创建了默认管理员用户 (ID: $admin_id, 用户名: $admin_username, 密码: $admin_password)<br>";
                
                // 创建默认设置
                if (in_array('settings', $tables)) {
                    $stmt = $db->prepare("INSERT INTO settings (user_id, theme_name, guest_password) VALUES (?, 'default', '1111')");
                    $stmt->execute([$admin_id]);
                    echo "创建了默认设置<br>";
                }
            }
        }
    }
    
} catch (PDOException $e) {
    echo "数据库错误: " . $e->getMessage() . "<br>";
} catch (Exception $e) {
    echo "一般错误: " . $e->getMessage() . "<br>";
}
