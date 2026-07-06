<?php
// admin/get-order-details.php - API to fetch order details
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

header('Content-Type: application/json');

require_once dirname(__FILE__) . '/../includes/CartRepository-DB.php';

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
    
    echo json_encode([
        'success' => true,
        'cart' => $orderData['cart'],
        'items' => $orderData['items'],
        'ticket' => $orderData['ticket']
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching order details: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error del servidor']);
}
