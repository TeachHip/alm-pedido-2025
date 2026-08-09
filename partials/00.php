<?php
// Sync PHP with localStorage by reading cart from cookie
require_once __DIR__ . '/../includes/CartHelper.php';
$cart = parseCartCookie();

// Helper function to check if cart cookie is still valid
function isCartValid(): bool {
    return isset($_COOKIE['cart']);
}

// Optional: Clean up expired cart data
if (isset($_COOKIE['cart']) && empty($cart)) {
    setcookie('cart', '', time() - 3600, '/');
}
?>