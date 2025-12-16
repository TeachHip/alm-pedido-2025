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
            document.addEventListener('DOMContentLoaded', function () {
                // ORIGINAL WORKING CODE: Generate WhatsApp link
                const whatsappLink = document.getElementById('whatsapp-link');
                if (whatsappLink && <?php echo!empty($cart) ? 'true' : 'false'; ?> ) {
                    let message = "Hola! Quiero hacer este pedido:%0A%0A";
                    <?php foreach($cart as $item): ?>
                        message +=
                        "- <?php echo $item['quantity']; ?>x <?php echo $item['name']; ?> (<?php echo number_format($item['price'], 2); ?>€ c/u)%0A";
                    <?php endforeach; ?>
                    message += "%0ATotal: <?php echo number_format($total, 2); ?>€%0A%0A¡Gracias!";

                    whatsappLink.href = "https://wa.me/?text=" + message;
                    whatsappLink.target = "_blank";
                }

                // Update total display
                const cartTotal = document.getElementById('cart-total');
                if (cartTotal) cartTotal.textContent = '<?php echo number_format($total, 2); ?>';
            });

            function sendWhatsAppMessage() {
                console.log('Button clicked!'); // This will help us debug

                // Check if cart is empty
                if (!cart || cart.length === 0) {
                    alert('Tu carrito está vacío');
                    return;
                }

                console.log('Cart has items:', cart); // Debug log

                // Build the message
                let message = "¡Hola! Me interesan estos productos:%0A%0A";

                cart.forEach(item => {
                    const itemTotal = (item.price * item.quantity).toFixed(2);
                    message += `- ${item.quantity}x ${item.name} - ${itemTotal}€%0A`;
                });

                const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                message += `%0ATotal: ${total.toFixed(2)}€%0A%0A¡Gracias!`;

                const phoneNumber = "34123456789";

                // Create the WhatsApp URL
                const whatsappURL = `https://wa.me/${phoneNumber}?text=${message}`;

                console.log('Opening WhatsApp URL:', whatsappURL); // Debug log

                // Open WhatsApp
                window.open(whatsappURL, '_blank');
            }
            // SIMPLE TEST FUNCTION - ADD THIS
            function sendWhatsAppMessage() {
                // First, test if function is being called
                document.getElementById('debug-content').innerHTML =
                    'Function called! Cart items: ' + (cart ? cart.length : '0');

                // Show alert to confirm function is working
                alert('WhatsApp button clicked! Function is working.');

                // If we get this far, the function is being called
                // Now let's test if cart has items
                if (!cart || cart.length === 0) {
                    alert('Cart is empty!');
                    return;
                }

                // Simple test message
                const testMessage = "Test message from cart";
                const phoneNumber = "34123456789"; // ← CHANGE THIS!
                const whatsappURL = `https://wa.me/${phoneNumber}?text=${testMessage}`;

                // Open WhatsApp
                window.open(whatsappURL, '_blank');
            }
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