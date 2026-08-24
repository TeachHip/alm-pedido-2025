<?php
// my-orders.php - Logged-in member's recent orders. The most recent one
// doubles as the "thank you" confirmation (per the user's own explicit
// call: this page IS that moment, not a separate page), rendered with the
// exact same partials/invoice-card.php markup ticket.php uses -- same
// receipt, same pay link, no design drift between the two pages. Older
// ones (paid: 2 months, unpaid: 7 days -- see
// InvoiceRepository::findRecentByMember()) listed more plainly below.
require_once 'includes/member-auth.php';
require_once 'includes/repositories/InvoiceRepository-DB.php';
require_once 'includes/repositories/MemberRepository-DB.php';
require_once 'includes/repositories/SettingsRepository-DB.php';

if (!isMemberLoggedIn()) {
    header('Location: member-login.php?return_to=' . urlencode($_SERVER['REQUEST_URI'] ?? '/my-orders.php'));
    exit;
}

$member = getLoggedInMember();
$invoiceRepo = new InvoiceRepository();
$orders = array_map([$invoiceRepo, 'autoExpireIfOverdue'], $invoiceRepo->findRecentByMember($member['id']));

$latest = $orders ? array_shift($orders) : null;

$settingsRepo = new SettingsRepository();
$businessName = $settingsRepo->get('business_name', 'AlMercáu');
$associationName = $settingsRepo->get('association_name', '');
$businessAddress = $settingsRepo->get('business_address', '');
$businessNif = $settingsRepo->get('business_nif', '');

function orderStatusLabel($invoice) {
    // Cancelado (a person cancelled it) and Vencido (deadline passed
    // automatically, nobody acted) are deliberately distinct -- see
    // InvoiceRepository::autoExpireIfOverdue(), which already ran on
    // $invoice above by the time this is called.
    if ($invoice['status'] === 'cancelled') return ['label' => '❌ Cancelado', 'class' => 'invoice-banner-warning'];
    if ($invoice['payment_status'] === 'paid') return ['label' => '✅ Pagado', 'class' => 'invoice-banner-success'];
    if ($invoice['payment_status'] === 'expired') return ['label' => '⚠️ Vencido', 'class' => 'invoice-banner-warning'];
    return ['label' => '⏳ Pendiente de pago', 'class' => 'invoice-banner-warning'];
}

$pageTitle = 'Mis Pedidos - AlMercáu';
include 'partials/head.php';
include 'partials/header.php';
?>

<div class="container">
    <h2>Mis pedidos</h2>

    <?php if (isset($_GET['cancelled'])): ?>
    <div class="invoice-banner invoice-banner-success">✅ Pedido cancelado</div>
    <?php elseif (isset($_GET['error'])): ?>
    <div class="invoice-banner invoice-banner-warning"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <?php if (!$latest): ?>
    <div class="empty-state">
        <p>No tienes pedidos recientes.</p>
    </div>
    <?php else: ?>
    <h3>¡Gracias por tu pedido!</h3>
    <?php
        $invoice = $latest;
        $items = $invoiceRepo->getItems($invoice['id']);
        include 'partials/invoice-card.php';
    ?>
    <?php if ($invoice['status'] === 'active' && $invoice['payment_status'] === 'pending'): ?>
    <form method="POST" action="cancel-order.php" onsubmit="return confirm('¿Seguro que quieres cancelar este pedido? Esta acción no se puede deshacer.');">
        <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
        <button type="submit" class="empty-cart-link link-button">🗑️ Cancelar pedido</button>
    </form>
    <?php endif; ?>

    <?php if (!empty($orders)): ?>
    <h3 style="margin-top: 25px;">Pedidos 2 meses anteriores</h3>
    <ul class="invoice-items-list">
        <?php foreach ($orders as $order): $s = orderStatusLabel($order); ?>
        <li>
            <a href="ticket.php?token=<?php echo htmlspecialchars($order['token']); ?>">
                <?php echo htmlspecialchars($order['ticket_number']); ?>
            </a>
            &mdash; <?php echo number_format($order['total_amount'], 2); ?>€
            &mdash; <?php echo $s['label']; ?>
            &mdash; <?php echo date('d/m/Y', strtotime($order['created_at'])); ?>
            <?php if ($order['status'] === 'active' && $order['payment_status'] === 'pending'): ?>
            &mdash; <form method="POST" action="cancel-order.php" style="display: inline;" onsubmit="return confirm('¿Seguro que quieres cancelar este pedido? Esta acción no se puede deshacer.');">
                <input type="hidden" name="invoice_id" value="<?php echo $order['id']; ?>">
                <button type="submit" class="link-button">Cancelar</button>
            </form>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'partials/footer.php'; ?>
</body>
</html>
