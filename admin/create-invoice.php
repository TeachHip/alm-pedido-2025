<?php
// admin/create-invoice.php - Turn a completed cart/order into a ticket de compra
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/repositories/CartRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/repositories/MemberRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/repositories/SettingsRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/repositories/InvoiceRepository-DB.php';

$cartId = (int) ($_GET['cart_id'] ?? 0);
if (!$cartId) {
    die("Falta el id del pedido");
}

// Orders now get a ticket automatically at checkout (see save-cart.php) --
// this manual flow is a fallback for legacy pre-automation carts. Don't
// let it create a duplicate for one that already has a ticket.
$existingInvoice = (new InvoiceRepository())->findByCartId($cartId);
if ($existingInvoice) {
    header('Location: invoice-created.php?invoice_id=' . $existingInvoice['id']);
    exit;
}

try {
    $cartRepo = new CartRepository();
    $order = $cartRepo->getOrderWithItems($cartId);

    if (!$order) {
        die("Pedido no encontrado");
    }

    if (empty($order['cart']['member_id'])) {
        die("Este pedido no tiene un miembro asociado (pedido anterior a exigir login en el checkout) — no se puede crear un ticket de compra.");
    }

    $memberRepo = new MemberRepository();
    $member = $memberRepo->findById($order['cart']['member_id']);

    $settingsRepo = new SettingsRepository();
    $dueDays = (int) $settingsRepo->get('invoice_due_days', '7');
    $dueDate = date('d/m/Y', strtotime("+{$dueDays} days"));
} catch (Exception $e) {
    error_log("Error loading order for invoice: " . $e->getMessage());
    die("Error: No se pudo cargar el pedido.");
}

$pageH1 = 'Crear Ticket de Compra';
$pageTitle = $pageH1 . ' - AlMercáu';
$activeNav = 'orders';
$backUrl = 'orders.php';
include dirname(__FILE__) . '/partials/head.php';
?>
    <link rel="stylesheet" href="../assets/admin/forms.css?v=<?php echo APP_VERSION_SAFE; ?>">
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <div class="edit-form">
        <div id="form-error-summary" class="error-message" style="display:none;"></div>

        <h3>Pedido <?php echo htmlspecialchars($order['ticket']); ?></h3>
        <table width="100%" style="margin-bottom: 15px;">
            <?php foreach ($order['items'] as $item): ?>
            <tr>
                <td>
                    <?php echo (int) $item['quantity']; ?>x <?php echo htmlspecialchars($item['product_ticket_name'] ?: $item['product_name']); ?>
                    <?php if (!empty($item['option_label'])): ?> (<?php echo htmlspecialchars($item['option_label']); ?>)<?php endif; ?>
                </td>
                <td style="text-align:right;"><?php echo number_format($item['subtotal'], 2); ?>€</td>
            </tr>
            <?php endforeach; ?>
            <?php if (!empty($order['cart']['fee_amount'])): ?>
            <tr>
                <td><?php echo htmlspecialchars($order['cart']['fee_label']); ?></td>
                <td style="text-align:right;"><?php echo number_format($order['cart']['fee_amount'], 2); ?>€</td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><strong>Total</strong></td>
                <td style="text-align:right;"><strong><?php echo number_format($order['cart']['total_price'], 2); ?>€</strong></td>
            </tr>
        </table>

        <p><strong>Miembro:</strong> <?php echo $member ? htmlspecialchars($member['alias']) . ' (' . htmlspecialchars($memberRepo->formatPhoneForDisplay($member['phone'])) . ')' : '— miembro no encontrado —'; ?></p>
        <p><strong>Fecha límite de pago:</strong> <?php echo $dueDate; ?> (<?php echo $dueDays; ?> días, según configuración)</p>

        <form method="POST" action="actions/create-invoice.php">
            <input type="hidden" name="cart_id" value="<?php echo $cartId; ?>">
            <div class="form-actions">
                <button type="submit" class="btn-save">Crear Ticket de Compra</button>
                <a href="orders.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>
