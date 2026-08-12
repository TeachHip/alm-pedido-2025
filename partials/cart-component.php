<?php
// Pre-render cart count from PHP to avoid flash. Reuse $cart if a caller
// (usually partials/00.php) already parsed the cookie this request instead
// of parsing it again -- falls back to parsing it directly so this partial
// still works if ever included on its own.
if (!isset($cart)) {
    require_once __DIR__ . '/../includes/CartHelper.php';
    $cart = parseCartCookie();
}

$count = 0;
foreach ($cart as $item) {
    $count += isset($item['quantity']) ? $item['quantity'] : 0;
}
?>
<div class="floating-cart">
    <button class="cart-button" onclick="window.location.href='cart-page.php'">
        🛒 <span id="cart-count" class="cart-count"><?php echo $count; ?></span>
    </button>
</div>
