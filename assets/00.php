<?php
// Sync PHP with localStorage by reading cart from cookie (PHP 8.4)
$cart = json_decode($_COOKIE['cart'] ?? '[]', true) ?: [];

// Helper function to check if cart cookie is still valid
function isCartValid(): bool {
    return isset($_COOKIE['cart']);
}

// Optional: Clean up expired cart data
if (isset($_COOKIE['cart']) && empty($cart)) {
    setcookie('cart', '', time() - 3600, '/');
}
?>