<?php
// cart-page.php - Shopping cart page
// member-auth.php must load before any output on this page specifically --
// unlike index.php/section.php/product.php/ticket.php, this page echoes a
// <script> block before including partials/header.php (which needs a
// session started for the member menu), and session_start() has to happen
// before the response body starts.
require_once 'includes/maintenance.php';
enforceMaintenanceMode();

require_once 'includes/member-auth.php';
require_once 'includes/repositories/ProductRepository-DB.php';
require_once 'includes/repositories/SettingsRepository-DB.php';
include 'partials/00.php';
$pageTitle = 'Carrito - AlMercáu';

// Pedido Expres cart fee
$settingsRepo = new SettingsRepository();
$productRepo = new ProductRepository();
$feeAmount = (float) $settingsRepo->get('pedido_expres_fee_amount', '0');
$feeLabel = $settingsRepo->get('pedido_expres_fee_label', '');
$pedidoExpresProductIds = [];
if ($feeAmount > 0) {
    $pedidoExpresProductIds = array_map('intval', array_column($productRepo->getBySectionKey('flash', false), 'id'));
}
?>
<script>
    window.pedidoExpresFeeAmount = <?php echo json_encode($feeAmount); ?>;
    window.pedidoExpresFeeLabel = <?php echo json_encode($feeLabel); ?>;
    window.pedidoExpresProductIds = <?php echo json_encode($pedidoExpresProductIds); ?>;
</script>
<?php include 'partials/head.php'; ?>
<?php include 'partials/header.php'; ?>

<div class="container cart-page">
    <a href="./" class="back-btn">&larr; Volver a la compra</a>
    <h2>Carrito</h2>

    <div id="cart-items">
        <?php
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
        <?php endforeach;

        // Pedido Expres cart fee (PHP fallback for non-JS rendering)
        $cartHasPedidoExpres = false;
        if (!empty($pedidoExpresProductIds)) {
            foreach ($cart as $item) {
                $numericId = extractProductId($item['id'] ?? null);
                if (in_array($numericId, $pedidoExpresProductIds, true)) {
                    $cartHasPedidoExpres = true;
                    break;
                }
            }
        }
        if ($cartHasPedidoExpres) {
            $total += $feeAmount;
        }
        ?>
        <?php if ($cartHasPedidoExpres): ?>
        <div class="cart-item cart-fee-item">
            <div class="cart-item-name"><?php echo htmlspecialchars($feeLabel); ?></div>
            <div class="cart-item-info">
                <div class="cart-item-total"><?php echo number_format($feeAmount, 2); ?>€</div>
            </div>
        </div>
        <?php endif; ?>

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

    <?php if (!isMemberLoggedIn()): ?>
    <p class="login-required-notice">
        Debes iniciar sesión para enviar tu pedido.
        <a href="member-login.php?return_to=<?php echo urlencode($_SERVER['REQUEST_URI'] ?? '/cart-page.php'); ?>">Inicia sesión</a>
        o pásate por AlMercáu para darte de alta.
    </p>
    <?php else: ?>
    <p class="login-required-notice">
        Pedido para recogida en tienda antes de una semana.<br>Plazo para productos de Pedido de Grupo, Pedido Exprés y frescos en general, como indicado por whatsapp.
    </p>
    <?php endif; ?>

    <button type="button" class="whatsapp-btn" onclick="sendWhatsAppMessage()">
    1. Hacer pedido <small>(1/3)</small>
</button>
    <?php endif; ?>
</div>

<?php include 'partials/footer.php'; ?>
</body>

</html>