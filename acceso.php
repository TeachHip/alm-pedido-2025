<?php
/**
 * acceso.php - Testing-mode access gate.
 *
 * Visit this once per device/browser with the correct ?k= token to get a
 * long-lived cookie past the .htaccess block (see .htaccess) -- everyone
 * else sees the friendly "en pruebas" message. Replaces an earlier Basic
 * Auth version: that broke inside in-app webviews (links opened from
 * WhatsApp itself have no native HTTP-auth UI at all) and had inconsistent
 * browser credential-caching behavior across tabs/sessions. A cookie needs
 * no browser UI, so it can't hit either failure mode.
 *
 * Wrong/missing token returns a plain 404 rather than any kind of "access
 * denied" -- no reason to hint this endpoint does something special.
 */
$apiKeysFile = __DIR__ . '/includes/config/api-keys-DB.php';
if (file_exists($apiKeysFile)) {
    require_once $apiKeysFile;
}

$providedToken = $_GET['k'] ?? '';

if (defined('TESTING_ACCESS_TOKEN') && TESTING_ACCESS_TOKEN !== '' && hash_equals(TESTING_ACCESS_TOKEN, $providedToken)) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    setcookie('pedido_access', TESTING_ACCESS_TOKEN, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    header('Location: index.php');
    exit;
}

http_response_code(404);
echo 'Not found';
