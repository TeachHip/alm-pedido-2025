<?php
// Sync PHP with localStorage by reading cart from cookie
$cartData = isset($_COOKIE['cart']) ? json_decode(urldecode($_COOKIE['cart']), true) : null;
$cart = [];

if ($cartData) {
    // Check if new format with timestamp
    if (isset($cartData['items']) && isset($cartData['lastUpdated'])) {
        // Check if cart is older than 48 hours (172800000 ms)
        $age = (time() * 1000) - $cartData['lastUpdated'];
        if ($age <= 172800000) {
            $cart = $cartData['items'];
        }
    } elseif (is_array($cartData)) {
        // Old format (plain array), still support it
        $cart = $cartData;
    }
}

// Helper function to check if cart cookie is still valid
function isCartValid(): bool {
    return isset($_COOKIE['cart']);
}

// Optional: Clean up expired cart data
if (isset($_COOKIE['cart']) && empty($cart)) {
    setcookie('cart', '', time() - 3600, '/');
}
?>