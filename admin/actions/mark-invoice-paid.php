<?php
// admin/actions/mark-invoice-paid.php - Manually mark an invoice as paid.
// No payment-confirmation webhook exists yet (Stage 3, investigate-only --
// see AI/plans v10), so whether the payment link is real PayGold or the
// mock payment page, Hop has to confirm payment happened and flip this by
// hand. This is the permanent fallback either way, not a mock-only stopgap.
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
