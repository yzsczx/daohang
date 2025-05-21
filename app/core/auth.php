<?php
// dh/app/core/auth.php

// config.php (which starts session) and database.php should be included before these functions are called.

function isLoggedIn(): bool {
    if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function loginUser(PDO $db, string $password): bool {
    if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    try {
        if (!defined('DEFAULT_USERNAME')) { return false; }

        // 检查是否是管理员密码
        $stmt = $db->prepare("SELECT id, password_hash, username FROM users WHERE username = ?");
        $stmt->execute([DEFAULT_USERNAME]);
        $user = $stmt->fetch();

        // 记录登录尝试
        error_log("Login attempt for user: " . DEFAULT_USERNAME);

        if ($user) {
            error_log("User found, checking password");
            error_log("Stored password hash: " . $user['password_hash']);
            error_log("Password hash length: " . strlen($user['password_hash']));

            $verify_result = password_verify($password, $user['password_hash']);
            error_log("Password verification result: " . ($verify_result ? 'true' : 'false'));

            if ($verify_result) {
                error_log("Password verified, setting session");
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['user_id'] = (int)$user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = 'admin'; // 设置为管理员角色
                return true;
            } else {
                error_log("Password verification failed");
            }
        } else {
            error_log("User not found: " . DEFAULT_USERNAME);
        }

        // 检查是否是访客密码
        $stmt = $db->prepare("SELECT guest_password FROM settings WHERE user_id = ?");
        $stmt->execute([(int)$user['id']]);
        $settings = $stmt->fetch();

        if ($settings && isset($settings['guest_password']) && $password === $settings['guest_password']) {
            session_regenerate_id(true); // Prevent session fixation
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = '访客';
            $_SESSION['user_role'] = 'guest'; // 设置为访客角色
            return true;
        }
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
    }
    return false;
}

function logoutUser(): void {
    if (session_status() == PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }
    $_SESSION = array(); // Unset all session variables
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    @session_destroy();
    header("Location: index.php");
    exit;
}

/**
 * Creates the initial admin user and their settings.
 * @return string "created", "exists", "error_config", "error_insert_user", "error_pdo"
 */
function checkAndInitializeUser(PDO $db): string {
    if (!defined('DEFAULT_USERNAME') || !defined('INITIAL_PASSWORD') || !defined('APP_NAME')) {
        error_log("Core constants not defined for user/app initialization.");
        return "error_config";
    }

    try {
        $stmt_check = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt_check->execute([DEFAULT_USERNAME]);
        if ($stmt_check->fetch()) {
            return "exists";
        }

        $hashed_password = password_hash(INITIAL_PASSWORD, PASSWORD_DEFAULT);
        $insertUserStmt = $db->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
        if (!$insertUserStmt->execute([DEFAULT_USERNAME, $hashed_password])) {
             error_log("Failed to execute user insert query for " . DEFAULT_USERNAME);
             return "error_insert_user";
        }
        $userId = $db->lastInsertId();

        if ($userId) {
            $settingsCheckStmt = $db->prepare("SELECT user_id FROM settings WHERE user_id = ?");
            $settingsCheckStmt->execute([$userId]);
            if (!$settingsCheckStmt->fetch()) {
                // Insert default settings for the new user
                $defaultSiteName = defined('APP_NAME') ? APP_NAME : '我的导航页'; // Use from config if defined
                $insertSettingsStmt = $db->prepare("INSERT INTO settings (user_id, theme_name) VALUES (?, 'default')");
                // If you store APP_NAME in settings, you'd do it here
                // $updateAppNameStmt = $db->prepare("UPDATE settings SET app_name = ? WHERE user_id = ?");
                // $updateAppNameStmt->execute([$defaultSiteName, $userId]);
                if (!$insertSettingsStmt->execute([$userId])) {
                    error_log("Failed to insert settings for new user ID: $userId");
                }
            }
            return "created";
        } else {
            error_log("User insert query executed for " . DEFAULT_USERNAME . " but lastInsertId was not set or was zero.");
            return "error_insert_user_id";
        }
    } catch (PDOException $e) {
        error_log("User initialization PDO error: " . $e->getMessage());
        return "error_pdo";
    }
}
?>