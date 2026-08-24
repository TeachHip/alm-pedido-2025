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
    $invoice = $invoiceRepo->autoExpireIfOverdue($invoice);

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
$businessNif = $settingsRepo->get('business_nif', '');

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
        // Redsys redirects the customer's own browser back here after they
        // complete/fail a payment (see InvoiceHelper::requestPaymentLink()'s
        // urlOk/urlKo). Gated on payment_status still being 'pending' so a
        // stale query string never contradicts the invoice's real, current
        // state -- if the webhook already landed, the card's own "✅ Pagado"
        // banner (below) speaks for itself, no extra note needed.
    ?>
    <?php if (isset($_GET['from_payment']) && $invoice['payment_status'] === 'pending'): ?>
    <div class="invoice-banner invoice-banner-warning">⏳ Estamos confirmando tu pago, puede tardar unos segundos. Actualiza la página en un momento si no ves el cambio.</div>
    <?php elseif (isset($_GET['payment_failed']) && $invoice['payment_status'] === 'pending'): ?>
    <div class="invoice-banner invoice-banner-warning">⚠️ El pago no se completó. Puedes intentarlo de nuevo con el enlace de abajo.</div>
    <?php endif; ?>
    <?php include 'partials/invoice-card.php'; ?>
<?php endif; ?>
</div>

<?php include 'partials/footer.php'; ?>
</body>
</html>
