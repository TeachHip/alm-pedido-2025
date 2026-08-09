<div class="floating-cart">
    <button class="cart-button" onclick="window.location.href='cart-page.php'">
        🛒 <span id="cart-count" class="cart-count"><?php
            // Pre-render cart count from PHP to avoid flash
            require_once __DIR__ . '/../includes/CartHelper.php';
            $cart = parseCartCookie();

            $count = 0;
            foreach ($cart as $item) {
                $count += isset($item['quantity']) ? $item['quantity'] : 0;
            }
            echo $count;
        ?></span>
    </button>
</div>