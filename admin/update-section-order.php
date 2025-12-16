<?php
// admin/update-section-order.php - Update section display order via AJAX
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/SectionRepository-DB.php';

header('Content-Type: application/json');

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['orders']) || !is_array($data['orders'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$sectionRepo = new SectionRepository();

try {
    $success = $sectionRepo->updateMultipleDisplayOrders($data['orders']);
    
    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Failed to update orders']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error updating section orders: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
