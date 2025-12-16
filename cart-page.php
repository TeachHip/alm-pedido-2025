<?php
// cart-page.php - Shopping cart page
$pageTitle = 'Carrito - AlMercáu';
?>
<?php include 'assets/head.php'; ?>
<?php include 'assets/header.php'; ?>

<div class="container cart-page">
    <a href="./" class="back-btn">&larr; Volver a la compra</a>
    <h2>Carrito</h2>

    <div id="cart-items">
        <?php
        $cart = json_decode($_COOKIE['cart'] ?? '[]', true) ?: [];
        if (empty($cart)):
        ?>
        <div class="empty-cart">
            <p>Tu carrito está vacío</p>
            <a href="index.php" class="btn">Continuar comprando</a>
        </div>
        <?php else:
            $total = 0;
            foreach ($cart as $item):
                $itemTotal = ($item['price'] ?? 0) * ($item['quantity'] ?? 0);
                $total += $itemTotal;
        ?>
        <div class="cart-item">
            <div class="cart-item-name"><?php echo htmlspecialchars($item['name'] ?? ''); ?></div>
            <img src="<?php echo htmlspecialchars($item['image'] ?? ''); ?>"
                alt="<?php echo htmlspecialchars($item['name'] ?? ''); ?>" class="cart-item-image"
                onerror="this.src='https://placehold.co/80x80/25D366/ffffff?text=Imagen'">
            <div class="cart-item-info">
                <div class="cart-item-price"><?php echo number_format($item['price'] ?? 0, 2); ?>€ unidad</div>
                <div class="cart-item-total">Total: <?php echo number_format($itemTotal, 2); ?>€</div>
            </div>
        </div>

        <!-- QUANTITY CONTROLS AS SEPARATE ROW - CENTERED ACROSS CARD -->
        <div class="cart-item-quantity-container">
            <button class="quantity-btn"
                onclick="updateQuantity('<?php echo $item['id']; ?>', <?php echo $item['quantity'] - 1; ?>)">-</button>
            <span class="quantity-value"><?php echo $item['quantity']; ?></span>
            <button class="quantity-btn"
                onclick="updateQuantity('<?php echo $item['id']; ?>', <?php echo $item['quantity'] + 1; ?>)">+</button>
        </div>
        <?php endforeach; ?>

        <script>
            // Just update cart total display
            document.addEventListener('DOMContentLoaded', function () {
                const cartTotal = document.getElementById('cart-total');
                if (cartTotal) cartTotal.textContent = '<?php echo number_format($total, 2); ?>';
            });
        </script>
        <?php endif; ?>
    </div>

    <?php if (!empty($cart)): ?>
    <a href="#" onclick="clearCart(); return false;" class="empty-cart-link">
        <i class="fas fa-trash-alt"></i> Vaciar carrito
    </a>
    <div class="cart-total">
        Total: <span id="cart-total">0.00</span>€
    </div>

    <!-- SIMPLE BUTTON THAT WILL DEFINITELY WORK -->
<button type="button" class="whatsapp-btn" onclick="sendWhatsAppMessage()">
    Enviar pedido por WhatsApp
</button>
    <?php endif; ?>
</div>

<?php include 'assets/footer.php'; ?>
</body>

</html>