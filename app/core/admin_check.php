<?php
// dh/app/core/admin_check.php
// 用于保护管理页面，只允许管理员访问

// 确保已经包含了auth.php
if (!function_exists('isAdmin')) {
    require_once __DIR__ . '/auth.php';
}

// 检查用户是否是管理员
if (!isAdmin()) {
    // 如果不是管理员，重定向到首页
    header('Location: index.php');
    exit;
}
