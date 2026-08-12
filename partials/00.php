<?php
// Sync PHP with localStorage by reading cart from cookie
require_once __DIR__ . '/../includes/CartHelper.php';
$cart = parseCartCookie();

// Clean up expired cart data
if (isset($_COOKIE['cart']) && empty($cart)) {
    setcookie('cart', '', time() - 3600, '/');
}
?>