<div class="floating-cart">
    <button class="cart-button" onclick="window.location.href='cart-page.php'">
        🛒 <span id="cart-count" class="cart-count"><?php
            // Pre-render cart count from PHP to avoid flash
            $cartData = isset($_COOKIE['cart']) ? $_COOKIE['cart'] : '[]';
$cart = json_decode($cartData, true);
if (empty($cart) || !is_array($cart)) {
    $cart = [];
}
            $count = 0;
            foreach ($cart as $item) {
                $count += isset($item['quantity']) ? $item['quantity'] : 0;
            }
            echo $count;
        ?></span>
    </button>
</div>