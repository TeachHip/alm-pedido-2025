<?php
// admin/actions/cancel-invoice.php - Manually cancel a ticket de compra.
// Admin has no restriction beyond it still being active (unlike the
// customer-facing cancel-order.php, which also requires payment_status
// still pending -- an admin can cancel a paid order too, e.g. a mistake
// or a refund already handled outside the app).
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

if ($invoice['status'] !== 'active') {
    header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&error=' . urlencode('Este ticket ya no está activo'));
    exit;
}

if ($invoiceRepo->cancel($invoiceId)) {
    header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&success=cancelled');
} else {
    header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&error=' . urlencode('Error al cancelar el ticket'));
}
exit;
