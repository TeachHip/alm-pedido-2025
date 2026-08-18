<?php
// admin/invoice-created.php - Confirmation page after creating a ticket de compra.
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/repositories/InvoiceRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/InvoiceHelper.php';

try {
    $invoiceId = (int) ($_GET['invoice_id'] ?? 0);
    $invoiceRepo = new InvoiceRepository();
    $invoice = $invoiceRepo->findById($invoiceId);

    if (!$invoice) {
        die("Ticket no encontrado");
    }

    $baseUrl = buildAppBaseUrl('/admin');
    $invoiceUrl = buildTicketUrl($invoice['token'], $baseUrl);
} catch (Exception $e) {
    error_log("Error loading invoice: " . $e->getMessage());
    die("Error: No se pudo cargar el ticket.");
}

$pageH1 = 'Ticket de Compra Creado';
$pageTitle = $pageH1 . ' - AlMercáu';
$activeNav = 'orders';
$backUrl = 'orders.php';
$successMessage = 'Marcado como pagado';
include dirname(__FILE__) . '/partials/head.php';
include dirname(__FILE__) . '/partials/header.php';
?>

    <div class="edit-form">
        <h3>✅ Ticket <?php echo htmlspecialchars($invoice['ticket_number']); ?> creado</h3>
        <p>Enlace del ticket:</p>
        <p><a href="<?php echo htmlspecialchars($invoiceUrl); ?>" target="_blank"><?php echo htmlspecialchars($invoiceUrl); ?></a></p>

        <?php if ($invoice['paygold_payment_url']): ?>
        <p>Enlace de pago<?php echo strpos($invoice['paygold_payment_url'], 'mock-payment.php') !== false ? ' (simulado)' : ''; ?>:</p>
        <p><a href="<?php echo htmlspecialchars($invoice['paygold_payment_url']); ?>" target="_blank"><?php echo htmlspecialchars($invoice['paygold_payment_url']); ?></a></p>
        <?php endif; ?>

        <?php if ($invoice['payment_status'] === 'paid'): ?>
        <p>✅ Pagado el <?php echo date('d/m/Y H:i', strtotime($invoice['paid_at'])); ?></p>
        <?php endif; ?>

        <div class="form-actions">
            <?php if ($invoice['payment_status'] !== 'paid'): ?>
            <a href="actions/mark-invoice-paid.php?invoice_id=<?php echo $invoice['id']; ?>" class="btn-save" onclick="return confirm('¿Confirmas que el pago de este ticket se ha recibido?');">
                ✅ Marcar como pagado
            </a>
            <?php endif; ?>
            <a href="orders.php" class="btn-cancel">Volver a Pedidos</a>
        </div>
    </div>
</body>
</html>
