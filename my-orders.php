<?php
// my-orders.php - Logged-in member's recent orders. The most recent one
// doubles as the "thank you" confirmation (per the user's own explicit
// call: this page IS that moment, not a separate page), rendered with the
// exact same partials/invoice-card.php markup ticket.php uses -- same
// receipt, same pay link, no design drift between the two pages. Older
// ones (paid: 2 months, unpaid: 7 days -- see
// InvoiceRepository::findRecentByMember()) listed more plainly below.
require_once 'includes/maintenance.php';
enforceMaintenanceMode();

require_once 'includes/member-auth.php';
require_once 'includes/repositories/InvoiceRepository-DB.php';
require_once 'includes/repositories/MemberRepository-DB.php';
require_once 'includes/repositories/SettingsRepository-DB.php';
require_once 'includes/InvoiceHelper.php';

if (!isMemberLoggedIn()) {
    header('Location: member-login.php?return_to=' . urlencode($_SERVER['REQUEST_URI'] ?? '/my-orders.php'));
    exit;
}

$member = getLoggedInMember();
$invoiceRepo = new InvoiceRepository();
$orders = array_map([$invoiceRepo, 'autoExpireIfOverdue'], $invoiceRepo->findRecentByMember($member['id']));

// Silently keep each still-payable order's PayGold link fresh -- Redsys
// caps a real link's own validity well under the order's actual due_date
// (see InvoiceHelper::refreshPaymentLinkIfStale()), so a customer who's
// simply slow to pay shouldn't hit a dead Redsys page. No visible message
// yet -- deliberately silent for now, may add customer-facing copy later.
$baseUrl = buildAppBaseUrl('');
$orders = array_map(function ($order) use ($baseUrl) {
    return refreshPaymentLinkIfStale($order, $baseUrl);
}, $orders);

$latest = $orders ? array_shift($orders) : null;

$settingsRepo = new SettingsRepository();
$businessName = $settingsRepo->get('business_name', 'AlMercáu');
$associationName = $settingsRepo->get('association_name', '');
$businessAddress = $settingsRepo->get('business_address', '');
$businessNif = $settingsRepo->get('business_nif', '');

function orderStatusIcon($invoice) {
    // Cancelado (a person cancelled it) and Vencido (deadline passed
    // automatically, nobody acted) are deliberately distinct icons -- see
    // InvoiceRepository::autoExpireIfOverdue(), which already ran on
    // $invoice above by the time this is called. No text label here --
    // each group's own heading already says what section it is.
    if ($invoice['status'] === 'cancelled') return '❌';
    if ($invoice['payment_status'] === 'paid') return '✅';
    if ($invoice['payment_status'] === 'expired') return '⚠️';
    return '⏳';
}

$pageTitle = 'Mis Pedidos - AlMercáu';
include 'partials/head.php';
include 'partials/header.php';
?>

<div class="container">
    <h2>Mis pedidos</h2>

    <?php if (isset($_GET['error'])): ?>
    <div class="invoice-banner invoice-banner-warning"><?php echo htmlspecialchars($_GET['error']); ?></div>
    <?php endif; ?>

    <?php if (!$latest): ?>
    <div class="empty-state">
        <p>No tienes pedidos recientes.</p>
    </div>
    <?php else: ?>
    <?php
        $invoice = $latest;
        $items = $invoiceRepo->getItems($invoice['id']);
    ?>
    <?php if ($invoice['status'] === 'active' && $invoice['payment_status'] !== 'expired'): ?>
    <h3>¡Gracias por tu pedido!</h3>
    <?php endif; ?>
    <?php include 'partials/invoice-card.php'; ?>
    <?php if (!($invoice['status'] === 'active' && $invoice['payment_status'] !== 'expired')): ?>
    <div class="continue-shopping-row">
        <a href="index.php" class="btn">Continuar comprando</a>
    </div>
    <?php endif; ?>
    <?php if ($invoice['status'] === 'active' && $invoice['payment_status'] === 'pending'): ?>
    <form method="POST" action="cancel-order.php" onsubmit="return confirm('¿Seguro que quieres cancelar este pedido? Esta acción no se puede deshacer.');">
        <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
        <button type="submit" class="empty-cart-link link-button">🗑️ Cancelar pedido</button>
    </form>
    <?php endif; ?>

    <?php if (!empty($orders)):
        // Three groups instead of one flat list: paid, still-pending, and
        // "the rest" (cancelled + vencido together). Each row keeps its
        // own real orderStatusLabel() regardless of which group it landed
        // in -- Cancelado and Vencido stay visually distinct even though
        // they share a heading.
        $paidOrders = [];
        $pendingOrders = [];
        $otherOrders = [];
        foreach ($orders as $order) {
            if ($order['payment_status'] === 'paid') {
                $paidOrders[] = $order;
            } elseif ($order['status'] === 'active' && $order['payment_status'] === 'pending') {
                $pendingOrders[] = $order;
            } else {
                $otherOrders[] = $order;
            }
        }
    ?>

    <div class="orders-list-block">

    <?php if ($paidOrders): ?>
    <div class="order-summary-scroll">
    <table class="order-summary-table">
        <thead>
        <tr><th colspan="4" class="order-section-heading">Pedidos 2 meses anteriores</th></tr>
        </thead>
        <tbody>
        <?php foreach ($paidOrders as $order): ?>
        <tr>
            <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
            <td><a href="ticket.php?token=<?php echo htmlspecialchars($order['token']); ?>"><?php echo htmlspecialchars($order['ticket_number']); ?></a></td>
            <td class="order-summary-price"><?php echo number_format($order['total_amount'], 2); ?>€</td>
            <td><?php echo orderStatusIcon($order); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php if ($pendingOrders): ?>
    <div class="order-summary-scroll">
    <table class="order-summary-table">
        <thead>
        <tr><th colspan="4" class="order-section-heading order-group-heading">Pendientes de pago</th></tr>
        </thead>
        <tbody>
        <?php foreach ($pendingOrders as $order): ?>
        <tr>
            <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
            <td><a href="ticket.php?token=<?php echo htmlspecialchars($order['token']); ?>"><?php echo htmlspecialchars($order['ticket_number']); ?></a></td>
            <td class="order-summary-price"><?php echo number_format($order['total_amount'], 2); ?>€</td>
            <td><?php echo orderStatusIcon($order); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php if ($otherOrders): ?>
    <div class="order-summary-scroll">
    <table class="order-summary-table">
        <thead>
        <tr><th colspan="4" class="order-section-heading order-group-heading">Cancelados</th></tr>
        </thead>
        <tbody>
        <?php foreach ($otherOrders as $order): ?>
        <tr>
            <td><?php echo date('d/m/Y', strtotime($order['created_at'])); ?></td>
            <td><?php echo htmlspecialchars($order['ticket_number']); ?></td>
            <td class="order-summary-price"><?php echo number_format($order['total_amount'], 2); ?>€</td>
            <td><?php echo orderStatusIcon($order); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    </div>

    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include 'partials/footer.php'; ?>
</body>
</html>
