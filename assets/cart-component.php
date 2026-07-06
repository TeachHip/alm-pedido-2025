<div class="floating-cart">
    <button class="cart-button" onclick="window.location.href='cart-page.php'">
        🛒 <span id="cart-count" class="cart-count"><?php
            // Pre-render cart count from PHP to avoid flash
            $cartData = isset($_COOKIE['cart']) ? json_decode(urldecode($_COOKIE['cart']), true) : null;
            $cart = [];
            
            if ($cartData) {
                // Check if new format with timestamp
                if (isset($cartData['items']) && isset($cartData['lastUpdated'])) {
                    // Check if cart is older than 48 hours (172800000 ms = 172800 seconds)
                    $age = (time() * 1000) - $cartData['lastUpdated'];
                    if ($age <= 172800000) {
                        $cart = $cartData['items'];
                    }
                } elseif (is_array($cartData)) {
                    // Old format (plain array), still support it
                    $cart = $cartData;
                }
            }
            
            $count = 0;
            foreach ($cart as $item) {
                $count += isset($item['quantity']) ? $item['quantity'] : 0;
            }
            echo $count;
        ?></span>
    </button>
</div>