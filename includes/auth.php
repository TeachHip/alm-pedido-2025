<?php
// includes/auth.php - Database session-based authentication
//
// Explicit cookie params matter here: this app's production host defaults
// to httponly=Off/secure=Off for session cookies (confirmed via phpinfo,
// 2026-08-18), which member-auth.php already correctly overrides -- this
// file used to just call session_start() bare, silently inheriting the
// insecure host default for the admin session cookie specifically.
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/repositories/UserRepository-DB.php';

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function getLoggedInUser() {
    return $_SESSION['admin_user'] ?? null;
}

/**
 * Attempt to log an admin/worker in. Returns true on success, false on
 * wrong credentials, or 'locked' if UserRepository::authenticate() reports
 * the account is in a brute-force lockout (see includes/repositories/UserRepository-DB.php).
 */
function loginAdmin($username, $password) {
    try {
        $userRepo = new UserRepository();
        $user = $userRepo->authenticate($username, $password);

        if ($user === 'locked') {
            return 'locked';
        }

        if ($user) {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_user'] = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            return true;
        }
        
        return false;
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

function logoutAdmin() {
    $_SESSION['admin_logged_in'] = false;
    unset($_SESSION['admin_user']);
    session_destroy();
}

function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function isAdmin() {
    $user = getLoggedInUser();
    return $user && $user['role'] === 'admin';
}

function isWorker() {
    $user = getLoggedInUser();
    return $user && $user['role'] === 'worker';
}
?>