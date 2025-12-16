<?php
// includes/auth.php - Database session-based authentication
session_start();

require_once __DIR__ . '/UserRepository-DB.php';

function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

function getLoggedInUser() {
    return $_SESSION['admin_user'] ?? null;
}

function loginAdmin($username, $password) {
    try {
        $userRepo = new UserRepository();
        $user = $userRepo->authenticate($username, $password);
        
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