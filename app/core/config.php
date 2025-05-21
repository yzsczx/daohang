<?php
// dh/app/core/config.php.sample
// **复制此文件为 config.php 并填写您的数据库信息**

// --- 数据库配置 ---
define('DB_HOST', 'localhost');        // 数据库主机，通常是 localhost
define('DB_NAME', 'daohang');       // 确保数据库名称正确
define('DB_USER', 'your_username'); // 检查用户名
define('DB_PASS', 'your_password'); // 检查密码
define('DB_CHARSET', 'utf8mb4');       // 数据库字符集

// --- 应用设置 ---
define('APP_NAME', '我的个人导航');   // 您的网站名称
define('DEFAULT_USERNAME', 'admin');       // 默认管理员用户名 (安装时创建)
define('INITIAL_PASSWORD', '1111');      // 默认管理员初始密码 (安装时创建)

// --- 错误报告 ---
// 开发时:
error_reporting(E_ALL);
ini_set('display_errors', '1');
// 生产环境建议:
// error_reporting(E_ALL);
// ini_set('display_errors', '0');
// ini_set('log_errors', '1');
// ini_set('error_log', __DIR__ . '/../../php_error.log'); // 确保此路径可写

// --- Session 设置 ---
if (session_status() == PHP_SESSION_NONE) {
    // 增强 session 安全性 (如果使用HTTPS)
    // session_set_cookie_params([
    //     'lifetime' => 0, // Session cookie lasts until browser closes
    //     'path' => '/',
    //     'domain' => $_SERVER['HTTP_HOST'],
    //     'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    //     'httponly' => true,
    //     'samesite' => 'Lax' // or 'Strict'
    // ]);
    @session_start();
}
?>