<?php
// cancel-order.php - Let a member cancel their own still-unpaid order.
// Deliberately stricter than admin's cancel-invoice.php: only the invoice's
// own member can cancel it (checked below, not just "any logged-in
// member"), and only while it's still unpaid -- a paid order needs the
// business involved (refund), not a customer self-service action.
require_once 'includes/member-auth.php';
require_once 'includes/repositories/InvoiceRepository-DB.php';

$member = getValidatedMember();
if (!$member) {
    header('Location: member-login.php?return_to=' . urlencode('/my-orders.php'));
    exit;
}

$invoiceId = (int) ($_GET['invoice_id'] ?? 0);
$invoiceRepo = new InvoiceRepository();
$invoice = $invoiceRepo->findById($invoiceId);

if (!$invoice || (int) $invoice['member_id'] !== (int) $member['id']) {
    // Doesn't exist, or belongs to someone else -- same redirect either
    // way, no hint to a member probing other invoice IDs.
    header('Location: my-orders.php?error=' . urlencode('Pedido no encontrado'));
    exit;
}

if ($invoice['status'] !== 'active' || $invoice['payment_status'] !== 'pending') {
    header('Location: my-orders.php?error=' . urlencode('Este pedido ya no se puede cancelar'));
    exit;
}

if ($invoiceRepo->cancel($invoiceId)) {
    header('Location: my-orders.php?cancelled=1');
} else {
    header('Location: my-orders.php?error=' . urlencode('Error al cancelar el pedido'));
}
exit;
