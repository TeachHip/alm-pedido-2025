<?php
// mock-payment.php - SIMULATED bank/payment page. Stand-in for the real
// PayGold/bank integration, which isn't built yet (see AI/plans v10 Stage
// 2). Token-only access, same security model as ticket.php. Not meant for
// production use -- swap for a real redirect to the bank's own hosted
// payment page once that integration exists, and delete this file.
require_once 'includes/repositories/InvoiceRepository-DB.php';
require_once 'includes/repositories/SettingsRepository-DB.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$invoice = null;

if (preg_match('/^[a-f0-9]{32}$/', $token)) {
    $invoiceRepo = new InvoiceRepository();
    $invoice = $invoiceRepo->findByToken($token);
}

$justPaid = false;
if ($invoice && $_SERVER['REQUEST_METHOD'] === 'POST' && $invoice['payment_status'] === 'pending') {
    $invoiceRepo->markPaid($invoice['id']);
    header('Location: ticket.php?token=' . urlencode($token));
    exit;
}

$settingsRepo = new SettingsRepository();
$businessName = $settingsRepo->get('business_name', 'AlMercáu');

$pageTitle = $invoice ? "Pago simulado - AlMercáu" : 'Enlace no válido - AlMercáu';
include 'partials/head.php';
include 'partials/header.php';
?>

<div class="container">
<?php if (!$invoice): ?>
    <div class="empty-state">
        <p>Enlace de pago no válido.</p>
    </div>
<?php else: ?>
    <div class="invoice-banner invoice-banner-warning">⚠️ Página de pago SIMULADA — no es un banco real. Sustituir por la integración real (PayGold) más adelante.</div>

    <div class="invoice-card">
        <div class="invoice-header">
            <h2><?php echo htmlspecialchars($businessName); ?></h2>
            <p class="invoice-ticket">Pago del ticket <?php echo htmlspecialchars($invoice['ticket_number']); ?></p>
        </div>

        <div class="invoice-total">
            Importe: <?php echo number_format($invoice['total_amount'], 2); ?>€
        </div>

        <?php if ($invoice['payment_status'] === 'paid'): ?>
        <div class="invoice-banner invoice-banner-success">✅ Este ticket ya está pagado.</div>
        <p class="invoice-pay-line"><a href="ticket.php?token=<?php echo htmlspecialchars($token); ?>">Ver ticket de compra →</a></p>
        <?php else: ?>
        <form method="POST" action="mock-payment.php" style="text-align: center; margin-top: 15px;">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            <button type="submit" class="btn add-to-cart-btn">Pagar ahora (simulado)</button>
        </form>
        <?php endif; ?>
    </div>
<?php endif; ?>
</div>

<?php include 'partials/footer.php'; ?>
</body>
</html>
