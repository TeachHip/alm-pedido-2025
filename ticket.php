<?php
// ticket.php - Public "ticket de compra" page. Token-only access (this is a
// closed community, see AI/plans v10) -- no member login required to view.
require_once 'includes/repositories/InvoiceRepository-DB.php';
require_once 'includes/repositories/SettingsRepository-DB.php';
require_once 'includes/repositories/MemberRepository-DB.php';

$token = $_GET['token'] ?? '';
$invoice = null;
$items = [];
$forwardToken = null; // set when this ticket was superseded, links to the replacement

if (preg_match('/^[a-f0-9]{32}$/', $token)) {
    $invoiceRepo = new InvoiceRepository();
    $invoice = $invoiceRepo->findByToken($token);

    if ($invoice) {
        $items = $invoiceRepo->getItems($invoice['id']);

        if ($invoice['status'] === 'superseded' && $invoice['superseded_by_invoice_id']) {
            $newer = $invoiceRepo->findById($invoice['superseded_by_invoice_id']);
            $forwardToken = $newer ? $newer['token'] : null;
        }
    }
}

$settingsRepo = new SettingsRepository();
$businessName = $settingsRepo->get('business_name', 'AlMercáu');
$associationName = $settingsRepo->get('association_name', '');
$businessAddress = $settingsRepo->get('business_address', '');

$pageTitle = $invoice ? "Ticket {$invoice['ticket_number']} - AlMercáu" : 'Ticket no encontrado - AlMercáu';
include 'partials/head.php';
include 'partials/header.php';
?>

<div class="container">
<?php if (!$invoice): ?>
    <div class="empty-state">
        <p>Ticket no encontrado.</p>
    </div>
<?php else: ?>
    <?php
        $isOverdue = $invoice['payment_status'] === 'pending' && strtotime($invoice['due_date']) < time();
        $cardClass = 'invoice-card';
        if ($invoice['status'] === 'superseded') $cardClass .= ' invoice-superseded';
        if ($invoice['status'] === 'cancelled') $cardClass .= ' invoice-cancelled';
    ?>

    <?php if ($invoice['status'] === 'superseded'): ?>
    <div class="invoice-banner invoice-banner-warning">
        ⚠️ Este ticket ha sido sustituido por uno nuevo.
        <?php if ($forwardToken): ?>
            <a href="ticket.php?token=<?php echo htmlspecialchars($forwardToken); ?>">Ver ticket actual →</a>
        <?php endif; ?>
    </div>
    <?php elseif ($invoice['status'] === 'cancelled'): ?>
    <div class="invoice-banner invoice-banner-warning">❌ Este ticket ha sido cancelado.</div>
    <?php elseif ($invoice['payment_status'] === 'paid'): ?>
    <div class="invoice-banner invoice-banner-success">✅ Pagado</div>
    <?php elseif ($isOverdue): ?>
    <div class="invoice-banner invoice-banner-warning">⚠️ Plazo de pago vencido — contacta con AlMercáu.</div>
    <?php endif; ?>

    <div class="<?php echo $cardClass; ?>">
        <div class="invoice-header">
            <h2><?php echo htmlspecialchars($businessName); ?></h2>
            <?php if ($associationName): ?><p><?php echo htmlspecialchars($associationName); ?></p><?php endif; ?>
            <?php if ($businessAddress): ?><p><?php echo htmlspecialchars($businessAddress); ?></p><?php endif; ?>
            <p class="invoice-ticket">Ticket de compra <?php echo htmlspecialchars($invoice['ticket_number']); ?></p>
        </div>

        <div class="invoice-meta">
            <p><strong>Miembro:</strong> AM<?php echo MemberRepository::formatMemberNumber($invoice['member_number']); ?></p>
            <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></p>
            <p><strong>Fecha límite de pago:</strong> <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></p>
        </div>

        <ul class="invoice-items-list">
            <?php foreach ($items as $item): ?>
            <li>
                <?php echo (int) $item['quantity']; ?>x
                <?php echo htmlspecialchars($item['product_name']); ?>
                <?php if ($item['option_label']): ?> (<?php echo htmlspecialchars($item['option_label']); ?>)<?php endif; ?>
                - <?php echo number_format($item['line_total'], 2); ?>€
                <?php if ($item['iva_rate']): ?> <small>(IVA <?php echo htmlspecialchars($item['iva_rate']); ?>% inc)</small><?php endif; ?>
            </li>
            <?php endforeach; ?>
            <?php if ($invoice['surcharge_amount']): ?>
            <li><?php echo htmlspecialchars($invoice['surcharge_label']); ?> - <?php echo number_format($invoice['surcharge_amount'], 2); ?>€</li>
            <?php endif; ?>
        </ul>

        <div class="invoice-total">
            Total: <?php echo number_format($invoice['total_amount'], 2); ?>€, impuestos incluidos
        </div>

        <?php if ($invoice['status'] === 'active' && $invoice['payment_status'] === 'pending' && !$isOverdue): ?>
            <?php if ($invoice['paygold_payment_url']): ?>
                <p class="invoice-pay-line">Haz el abono en el siguiente enlace: <a href="<?php echo htmlspecialchars($invoice['paygold_payment_url']); ?>"><?php echo htmlspecialchars($invoice['paygold_payment_url']); ?></a></p>
            <?php else: ?>
                <p class="invoice-pending-note">Pago pendiente — te contactaremos con las instrucciones.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>

<?php include 'partials/footer.php'; ?>
</body>
</html>
