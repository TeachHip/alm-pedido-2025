<?php
// my-orders.php - Logged-in member's recent orders. The most recent one
// doubles as the "thank you" confirmation (per the user's own explicit
// call: this page IS that moment, not a separate page), rendered with the
// exact same partials/invoice-card.php markup ticket.php uses -- same
// receipt, same pay link, no design drift between the two pages. Older
// ones (within the same 14-day window, see
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
$orders = $invoiceRepo->findRecentByMember($member['id']);

$latest = $orders ? array_shift($orders) : null;

$settingsRepo = new SettingsRepository();
$businessName = $settingsRepo->get('business_name', 'AlMercáu');
$associationName = $settingsRepo->get('association_name', '');
$businessAddress = $settingsRepo->get('business_address', '');
$businessNif = $settingsRepo->get('business_nif', '');

function orderStatusLabel($invoice) {
    if ($invoice['payment_status'] === 'paid') return ['label' => '✅ Pagado', 'class' => 'invoice-banner-success'];
    $isOverdue = strtotime($invoice['due_date']) < time();
    if ($isOverdue) return ['label' => '⚠️ Plazo vencido', 'class' => 'invoice-banner-warning'];
    return ['label' => '⏳ Pendiente de pago', 'class' => 'invoice-banner-warning'];
}

$pageTitle = 'Mis Pedidos - AlMercáu';
include 'partials/head.php';
include 'partials/header.php';
?>

<div class="container">
    <h2>Mis pedidos</h2>

    <?php if (!$latest): ?>
    <div class="empty-state">
        <p>No tienes pedidos en los últimos 14 días.</p>
    </div>
    <?php else: ?>
    <h3>¡Gracias por tu pedido!</h3>
    <?php
        $invoice = $latest;
        $items = $invoiceRepo->getItems($invoice['id']);
        include 'partials/invoice-card.php';
    ?>

    <?php if (!empty($orders)): ?>
    <h3 style="margin-top: 25px;">Pedidos anteriores</h3>
    <ul class="invoice-items-list">
        <?php foreach ($orders as $order): $s = orderStatusLabel($order); ?>
        <li>
            <a href="ticket.php?token=<?php echo htmlspecialchars($order['token']); ?>">
                <?php echo htmlspecialchars($order['ticket_number']); ?>
            </a>
            &mdash; <?php echo number_format($order['total_amount'], 2); ?>€
            &mdash; <?php echo $s['label']; ?>
            &mdash; <?php echo date('d/m/Y', strtotime($order['created_at'])); ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'partials/footer.php'; ?>
</body>
</html>
