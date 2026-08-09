<?php
/**
 * Cart Helper
 * Shared logic for reading the client-side cart cookie and normalizing cart
 * item IDs. Previously duplicated across partials/00.php, partials/cart-component.php,
 * cart-page.php, and save-cart.php. See AI/CHANGELOG.md.
 */

/**
 * Parse the 'cart' cookie into a plain array of items.
 * Handles both the current format ({items, lastUpdated}, expired after 48h)
 * and the legacy format (a plain array, no expiry).
 */
function parseCartCookie() {
    if (!isset($_COOKIE['cart'])) {
        return [];
    }

    $cartData = json_decode(urldecode($_COOKIE['cart']), true);
    if (!$cartData) {
        return [];
    }

    if (isset($cartData['items']) && isset($cartData['lastUpdated'])) {
        $age = (time() * 1000) - $cartData['lastUpdated'];
        return $age <= 172800000 ? $cartData['items'] : [];
    }

    if (is_array($cartData)) {
        return $cartData;
    }

    return [];
}

/**
 * Normalize a cart item's id/product_id (which may be 'product-7' or plain 7)
 * down to its numeric product id.
 */
function extractProductId($rawId) {
    if (is_string($rawId) && strpos($rawId, 'product-') === 0) {
        return (int) str_replace('product-', '', $rawId);
    }
    return (int) $rawId;
}
