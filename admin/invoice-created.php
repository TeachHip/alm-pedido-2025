<?php
// admin/invoice-created.php - Confirmation page after creating a ticket de
// compra: shows the ticket.php link and a "send SMS" button (Stage 1 =
// manual send).
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/repositories/InvoiceRepository-DB.php';

$invoiceId = (int) ($_GET['invoice_id'] ?? 0);
$invoiceRepo = new InvoiceRepository();
$invoice = $invoiceRepo->findById($invoiceId);

if (!$invoice) {
    die("Ticket no encontrado");
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'almercau.org';
$basePath = rtrim(str_replace('/admin', '', dirname($_SERVER['PHP_SELF'])), '/');
$invoiceUrl = "$scheme://$host$basePath/ticket.php?token=" . $invoice['token'];

$pageH1 = 'Ticket de Compra Creado';
$pageTitle = $pageH1 . ' - AlMercáu';
$activeNav = 'orders';
$backUrl = 'orders.php';
$successMessage = 'SMS enviado correctamente';
include dirname(__FILE__) . '/partials/head.php';
include dirname(__FILE__) . '/partials/header.php';
?>

    <div class="edit-form">
        <h3>✅ Ticket <?php echo htmlspecialchars($invoice['ticket_number']); ?> creado</h3>
        <p>Enlace del ticket:</p>
        <p><a href="<?php echo htmlspecialchars($invoiceUrl); ?>" target="_blank"><?php echo htmlspecialchars($invoiceUrl); ?></a></p>

        <?php if ($invoice['sms_sent_at']): ?>
        <p>📱 SMS enviado el <?php echo date('d/m/Y H:i', strtotime($invoice['sms_sent_at'])); ?></p>
        <?php endif; ?>

        <div class="form-actions">
            <a href="actions/send-invoice-sms.php?invoice_id=<?php echo $invoice['id']; ?>" class="btn-save" onclick="return confirm('¿Enviar el SMS con el enlace del ticket de compra?');">
                📱 <?php echo $invoice['sms_sent_at'] ? 'Reenviar SMS' : 'Enviar SMS'; ?>
            </a>
            <a href="orders.php" class="btn-cancel">Volver a Pedidos</a>
        </div>
    </div>
</body>
</html>
