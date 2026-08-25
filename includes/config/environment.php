<?php
/**
 * includes/config/environment.php - All environment settings, one file.
 *
 * No secrets live here (unlike config-DB.php and api-keys-DB.php) -- safe
 * to git-track: identical everywhere, works immediately on upload with no
 * manual setup, unless you deliberately edit one of the settings below.
 *
 * Layout follows wp-config.php's convention: everything you might ever
 * hand-edit is grouped right here at the top, each with its own comment;
 * everything below the "STOP EDITING" marker is derived logic you should
 * never need to touch.
 */

// =====================================================================
// SETTINGS -- hand-edit these, nothing else in this file
// =====================================================================

/**
 * Force a specific environment, skipping auto-detection (which otherwise
 * treats localhost/127.0.0.1/CLI as 'dev' and everything else as 'prod').
 * Use this to test dev-mode behavior (TEST PayGold, debug errors visible)
 * while genuinely reachable on the real domain, or the reverse.
 *
 * This file is git-tracked, so remember to set this back to null before
 * committing if you ever change it for a one-off test -- a forgotten
 * override here would silently apply to the next deploy too.
 */
$forceEnv = null; // 'dev' | 'prod' | null (null = auto-detect, recommended)

/**
 * Close the site to everyone except an active admin session (see
 * includes/maintenance.php for the actual gate + the "en mantenimiento"
 * page). A deployment-level act, which is why it's a hand-edited flag here
 * rather than a database setting in admin/settings.php.
 */
$maintenanceMode = false; // true | false

/**
 * The real production domain (no trailing slash). Used only when
 * APP_ENV is 'prod', to build PayGold webhook/redirect URLs safely --
 * hardcoding it here prevents a forged Host header on a raw HTTP request
 * from injecting an attacker's domain into those URLs (see
 * includes/InvoiceHelper.php's buildAppBaseUrl()). Not used in dev, which
 * derives the URL dynamically from the request instead (the php -S port
 * varies per run, and there's no real attacker on localhost to spoof).
 */
$productionSiteUrl = 'https://almercau.org'; // TODO: confirm this is the real production domain

// =====================================================================
// STOP EDITING -- everything below is derived from the settings above
// =====================================================================

function isLocalEnvironment() {
    if (php_sapi_name() === 'cli') {
        return true;
    }
    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
    return (bool) preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host);
}

// DEBUG_MODE (includes/db/config-DB.php) and PAYGOLD_ENVIRONMENT
// (includes/config/api-keys-DB.php) both derive from APP_ENV -- override
// either directly in its own file if IT needs to move independently of
// APP_ENV during a staged rollout (see those files).
define('APP_ENV', $forceEnv ?: (isLocalEnvironment() ? 'dev' : 'prod'));

define('SITE_URL', APP_ENV === 'prod' ? $productionSiteUrl : null);

define('MAINTENANCE_MODE', $maintenanceMode);
