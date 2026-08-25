<?php
// cancel-order.php - Let a member cancel their own still-unpaid order.
// Deliberately stricter than admin's cancel-invoice.php: only the invoice's
// own member can cancel it (checked below, not just "any logged-in
// member"), and only while it's still unpaid -- a paid order needs the
// business involved (refund), not a customer self-service action.
//
// POST-only, not a GET link: a bare GET link is one prefetch/crawler/
// middle-click away from silently cancelling a real order with no user
// action ever confirming it (a JS confirm() only guards the anchor's own
// click, not direct navigation). Combined with member-auth.php's session
// cookie already being SameSite=Lax (blocks cookies on cross-site POSTs),
// requiring POST here closes both the accidental-trigger and CSRF angles
// without needing a separate token.
require_once 'includes/maintenance.php';
enforceMaintenanceMode();

require_once 'includes/member-auth.php';
require_once 'includes/repositories/InvoiceRepository-DB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: my-orders.php');
    exit;
}

$member = getValidatedMember();
if (!$member) {
    header('Location: member-login.php?return_to=' . urlencode('/my-orders.php'));
    exit;
}

$invoiceId = (int) ($_POST['invoice_id'] ?? 0);
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
    header('Location: my-orders.php');
} else {
    header('Location: my-orders.php?error=' . urlencode('Error al cancelar el pedido'));
}
exit;
