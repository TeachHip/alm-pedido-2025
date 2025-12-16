<?php
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/ProductRepository-DB.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['orders']) || !is_array($data['orders'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

try {
    $productRepo = new ProductRepository();
    
    // Update display orders
    $orderData = [];
    foreach ($data['orders'] as $item) {
        if (isset($item['id']) && isset($item['order'])) {
            $orderData[$item['id']] = $item['order'];
        }
    }
    
    if (empty($orderData)) {
        throw new Exception('No valid order data provided');
    }
    
    $productRepo->updateMultipleDisplayOrders($orderData);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Error updating product order: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>