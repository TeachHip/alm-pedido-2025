<?php
// admin/get-order-details.php - API to fetch order details
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

header('Content-Type: application/json');

require_once dirname(__FILE__) . '/../../includes/repositories/CartRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/repositories/InvoiceRepository-DB.php';

try {
    $orderId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$orderId) {
        echo json_encode(['success' => false, 'error' => 'ID inválido']);
        exit;
    }

    $cartRepo = new CartRepository();
    $orderData = $cartRepo->getOrderWithItems($orderId);

    if (!$orderData) {
        echo json_encode(['success' => false, 'error' => 'Pedido no encontrado']);
        exit;
    }

    // Prefer the invoice's real ticket number over $orderData['ticket']
    // (CartRepository's own recomputed, drift-prone number) -- same fix as
    // admin/orders.php's row-level display, see the comment there.
    $invoiceRepo = new InvoiceRepository();
    $invoice = $invoiceRepo->findByCartId($orderId);
    $ticket = $invoice ? $invoice['ticket_number'] : $orderData['ticket'];

    echo json_encode([
        'success' => true,
        'cart' => $orderData['cart'],
        'items' => $orderData['items'],
        'ticket' => $ticket
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}
