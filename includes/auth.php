<?php
// includes/auth.php - Database session-based authentication

// 1 hour, rolling from last activity -- requireAdminAuth() re-issues the
// cookie on every authenticated request (see below), so an admin actively
// working never gets logged out mid-task; only genuine inactivity for the
// full hour does. Simpler than member-auth.php's rolling mechanism
// (cookie-only, no DB last-seen column/touch) since admin sessions don't
// need that file's revocation-on-password-change guarantee. Named
// constant, same pattern as member-auth.php's MEMBER_SESSION_LIFETIME.
const ADMIN_SESSION_LIFETIME = 60 * 60;

// Explicit cookie params matter here: this app's production host defaults
// to httponly=Off/secure=Off for session cookies (confirmed via phpinfo,
// 2026-08-18), which member-auth.php already correctly overrides -- this
// file used to just call session_start() bare, silently inheriting the
// insecure host default for the admin session cookie specifically.
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    // Set gc_maxlifetime too, not just the cookie -- so the actual idle
    // timeout is this app's own deliberate choice, not whatever the host
    // happens to default to server-side.
    ini_set('session.gc_maxlifetime', ADMIN_SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => ADMIN_SESSION_LIFETIME,
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
 * wrong credentials. No 'locked' case -- UserRepository::authenticate()
 * uses an escalating delay on repeated wrong attempts instead of a hard
 * lockout (2026-09-13), so a legitimate admin is never blocked outright.
 */
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
    // No session_destroy() -- that wipes the ENTIRE native PHP session,
    // including a coexisting member session's keys (member_logged_in,
    // member, ...), logging a customer out of their own account as a side
    // effect of an admin logging out in the same browser (found via real
    // testing, 2026-08-25). Unsetting just this system's own keys is
    // already a complete logout for it.
    $_SESSION['admin_logged_in'] = false;
    unset($_SESSION['admin_user']);
}

function requireAdminAuth() {
    if (!isAdminLoggedIn()) {
        // Only bring them back to where they were for a genuine session
        // EXPIRY, not a deliberate logout -- logoutAdmin() explicitly sets
        // admin_logged_in to false (a known value), while an expired or
        // never-started session simply never sets that key at all, so
        // isset() alone tells the two apart. A deliberate logout should
        // land back on the normal dashboard, not wherever they happened to
        // click "Cerrar Sesión" from.
        $wasExplicitLogout = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === false;
        if ($wasExplicitLogout) {
            header('Location: login.php');
        } else {
            $returnTo = $_SERVER['REQUEST_URI'] ?? '';
            header('Location: login.php?return_to=' . urlencode($returnTo));
        }
        exit;
    }

    // Rolling expiry: re-issue the session cookie with a fresh
    // ADMIN_SESSION_LIFETIME on every authenticated page load, same
    // mechanism member-auth.php's getValidatedMember() uses for its own
    // cookie. session_regenerate_id(true) at login already sets an initial
    // cookie with this lifetime (via session_set_cookie_params() above) --
    // this is what keeps rolling it forward afterward.
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(session_name(), session_id(), [
        'expires' => time() + ADMIN_SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
    ]);
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