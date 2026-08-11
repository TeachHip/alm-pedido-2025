<?php
/**
 * includes/member-auth.php - Member (customer) session-based authentication.
 *
 * Deliberately parallel to includes/auth.php (admin auth) but not identical:
 * - Session keys use a member_ prefix ($_SESSION['member_logged_in'],
 *   $_SESSION['member']) so they can never collide with admin's
 *   admin_logged_in/admin_user keys -- both can coexist in the same
 *   native PHP session.
 * - Rolling 1-month expiry from LAST VISIT (not fixed from login time).
 *   PHP's session.cookie_lifetime alone only rolls from login, so the
 *   cookie is re-issued on every authenticated request (see
 *   requireMemberAuth()). The authoritative check is members.session_last_seen_at
 *   in the DB, not PHP's own session GC -- shared-host session.gc_maxlifetime
 *   (~24 min default) isn't reliably raisable via ini_set() across hosts,
 *   so trusting it alone would risk silently logging out active members.
 * - members.session_token exists for REVOCATION, not the expiry math: there's
 *   no self-service password reset at launch, so changing a password (e.g.
 *   after a lost phone) must be able to kill an already-open session.
 *   MemberRepository::updatePassword() already clears it; requireMemberAuth()
 *   compares the session's copy against the DB on every call.
 * - Single session_token per member (not a sessions table): logging in on a
 *   second device silently logs out the first. Acceptable at this scale
 *   (in-person, one-device signup) -- see AI/plans v10 for the tradeoff.
 */

const MEMBER_SESSION_LIFETIME = 2592000; // 30 days, in seconds

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => MEMBER_SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/repositories/MemberRepository-DB.php';

function isMemberLoggedIn() {
    return isset($_SESSION['member_logged_in']) && $_SESSION['member_logged_in'] === true;
}

function getLoggedInMember() {
    return $_SESSION['member'] ?? null;
}

/**
 * Attempt to log a member in. Returns true on success.
 */
function loginMember($phone, $password) {
    try {
        $memberRepo = new MemberRepository();
        $member = $memberRepo->authenticate($phone, $password);

        if (!$member) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $memberRepo->setSessionToken($member['id'], $token);

        $_SESSION['member_logged_in'] = true;
        $_SESSION['member'] = [
            'id' => $member['id'],
            'alias' => $member['alias'],
            'phone' => $member['phone'],
            'membership_type' => $member['membership_type'],
        ];
        $_SESSION['member_session_token'] = $token;

        session_regenerate_id(true);

        return true;
    } catch (Exception $e) {
        error_log("Member login error: " . $e->getMessage());
        return false;
    }
}

function logoutMember() {
    if (isset($_SESSION['member']['id'])) {
        try {
            (new MemberRepository())->clearSessionToken($_SESSION['member']['id']);
        } catch (Exception $e) {
            error_log("Member logout error: " . $e->getMessage());
        }
    }
    $_SESSION['member_logged_in'] = false;
    unset($_SESSION['member'], $_SESSION['member_session_token']);
    session_destroy();
}

/**
 * Validates the session flag, the revocable token, and the rolling 1-month
 * expiry; refreshes the cookie/last-seen timestamp and returns the member
 * row on success. Returns null (and clears the stale session) on any
 * failure -- doesn't redirect, so it's usable from JSON endpoints too
 * (see save-cart.php) as well as requireMemberAuth() below.
 */
function getValidatedMember() {
    if (!isMemberLoggedIn()) {
        return null;
    }

    $memberId = $_SESSION['member']['id'] ?? null;
    $sessionToken = $_SESSION['member_session_token'] ?? null;

    if (!$memberId || !$sessionToken) {
        logoutMember();
        return null;
    }

    $memberRepo = new MemberRepository();
    $member = $memberRepo->findById($memberId);

    if (!$member || !$member['active'] || $member['session_token'] !== $sessionToken) {
        // Revoked (password changed elsewhere), deactivated, or a stale token
        logoutMember();
        return null;
    }

    if ($member['session_last_seen_at']) {
        $ageSeconds = time() - strtotime($member['session_last_seen_at']);
        if ($ageSeconds > MEMBER_SESSION_LIFETIME) {
            logoutMember();
            return null;
        }
    }

    // Still valid: roll the expiry forward from this visit
    $memberRepo->touchLastSeen($memberId);
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie(session_name(), session_id(), [
        'expires' => time() + MEMBER_SESSION_LIFETIME,
        'path' => '/',
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
    ]);

    return $member;
}

/**
 * Guard for pages that require a logged-in member (checkout, not browsing --
 * login is deliberately NOT required to view products/sections). Redirects
 * to member-login.php (with a return-to param) if the session isn't valid.
 */
function requireMemberAuth() {
    if (!getValidatedMember()) {
        $returnTo = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: member-login.php?return_to=' . urlencode($returnTo));
        exit;
    }
}
