<?php
// dh/app/core/database.php

// config.php 应该在使用此文件前被加载，因为它定义了DB常量
// require_once __DIR__ . '/config.php'; // 通常由调用者确保

function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_CHARSET')) {
            // Critical error: config.php not loaded or DB constants not defined
            // This should ideally be caught by the installer or a pre-flight check
            $message = "数据库配置常量未定义。请确保 app/core/config.php 文件存在且已正确配置。";
            if (isset($_GET['action']) && $_GET['action'] === 'install_check_db_connection') { // Special case for installer ajax check
                echo json_encode(['success' => false, 'message' => $message]);
                exit;
            }
            // For general use, make it very visible
            error_log($message);
            die("<div style='font-family: sans-serif; padding: 20px; background-color: #ffe0e0; border: 1px solid #a00; color: #a00;'><h1>配置错误</h1><p>{$message}</p></div>");
        }

        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (\PDOException $e) {
            $errorMessage = "数据库连接失败: " . $e->getMessage();
            error_log($errorMessage); // Log the detailed error
            if (isset($_GET['action']) && $_GET['action'] === 'install_check_db_connection') { // Special case for installer ajax check
                echo json_encode(['success' => false, 'message' => $errorMessage]);
                exit;
            }
            // For general use, show a user-friendly message
            die("<div style='font-family: sans-serif; padding: 20px; background-color: #ffe0e0; border: 1px solid #a00; color: #a00;'><h1>数据库错误</h1><p>无法连接到数据库。请检查您的 <code>app/core/config.php</code> 文件中的配置，并确保数据库服务正在运行。详细错误已记录。</p></div>");
        }
    }
    return $pdo;
}
?>