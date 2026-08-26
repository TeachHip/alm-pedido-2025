<?php
// admin/actions/update-fulfillment.php - Set an invoice's warehouse/pickup
// state (Recogida column in admin/orders.php). JSON endpoint, called via
// fetch from the order's expandable detail row so saving it doesn't
// collapse or reload the whole orders list.
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/InvoiceRepository-DB.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$invoiceId = (int) ($_POST['invoice_id'] ?? 0);
$status = $_POST['fulfillment_status'] ?? '';
$note = $_POST['fulfillment_note'] ?? null;

$invoiceRepo = new InvoiceRepository();
$invoice = $invoiceRepo->findById($invoiceId);

if (!$invoice) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
    exit;
}

// Recogida only makes sense once the money has actually arrived -- guard
// server-side too, not just by hiding the control in the UI.
if ($invoice['payment_status'] !== 'paid') {
    http_response_code(409);
    echo json_encode(['success' => false, 'error' => 'El pedido aún no está pagado']);
    exit;
}

if ($invoiceRepo->setFulfillmentStatus($invoiceId, $status, $note)) {
    echo json_encode(['success' => true, 'fulfillment_status' => $status]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Estado inválido']);
}
