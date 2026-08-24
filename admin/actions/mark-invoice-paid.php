<?php
// admin/actions/mark-invoice-paid.php - Manually mark an invoice as paid.
// paygold-notify.php already does this automatically for real PayGold
// payments; this is the manual fallback -- for the mock payment page,
// cash/in-person payments, or any case the webhook didn't fire for.
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/InvoiceRepository-DB.php';

$invoiceId = (int) ($_GET['invoice_id'] ?? 0);

$invoiceRepo = new InvoiceRepository();
$invoice = $invoiceRepo->findById($invoiceId);

if (!$invoice) {
    header('Location: ../orders.php?error=' . urlencode('Ticket no encontrado'));
    exit;
}

if ($invoice['payment_status'] === 'paid') {
    header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&success=paid');
    exit;
}

if ($invoiceRepo->markPaid($invoiceId)) {
    header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&success=paid');
} else {
    header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&error=' . urlencode('Error al marcar como pagado'));
}
exit;
