<?php
// admin/actions/toggle-visibility.php - Toggle product visibility via AJAX
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/ProductRepository-DB.php';

header('Content-Type: application/json');

if (!isset($_GET['product_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Product ID not provided']);
    exit;
}

$productId = (int) $_GET['product_id'];
$productRepo = new ProductRepository();

try {
    $product = $productRepo->getById($productId);

    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit;
    }

    $newVisibility = $product['visible'] ? 0 : 1;
    $success = $productRepo->setVisibility($productId, $newVisibility);

    if ($success) {
        echo json_encode(['success' => true, 'visible' => (bool) $newVisibility]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update product']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error toggling product visibility: " . $e->getMessage());
    echo json_encode(['error' => 'Server error']);
}
