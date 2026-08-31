<?php
// admin/actions/toggle-producer-active.php - Toggle producer active status via AJAX
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/ProducerRepository-DB.php';

header('Content-Type: application/json');

if (!isset($_GET['producer_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Producer ID not provided']);
    exit;
}

$producerId = (int) $_GET['producer_id'];
$producerRepo = new ProducerRepository();

try {
    $producer = $producerRepo->getById($producerId);

    if (!$producer) {
        http_response_code(404);
        echo json_encode(['error' => 'Producer not found']);
        exit;
    }

    // The 'Sin asignar' placeholder is not toggleable -- see edit-producer.php.
    if ($producer['name'] === ProducerRepository::PLACEHOLDER_NAME) {
        http_response_code(400);
        echo json_encode(['error' => 'Placeholder producer cannot be deactivated']);
        exit;
    }

    $newActive = $producer['active'] ? 0 : 1;
    $success = $producerRepo->setActive($producerId, $newActive);

    if ($success) {
        echo json_encode(['success' => true, 'active' => (bool) $newActive]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update producer']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error toggling producer active: " . $e->getMessage());
    echo json_encode(['error' => 'Server error']);
}
