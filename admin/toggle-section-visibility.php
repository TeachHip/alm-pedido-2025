<?php
// admin/toggle-section-visibility.php - Toggle section visibility via AJAX
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/SectionRepository-DB.php';

header('Content-Type: application/json');

if (!isset($_GET['section_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Section ID not provided']);
    exit;
}

$sectionId = (int)$_GET['section_id'];
$sectionRepo = new SectionRepository();

try {
    $section = $sectionRepo->getById($sectionId);
    
    if (!$section) {
        http_response_code(404);
        echo json_encode(['error' => 'Section not found']);
        exit;
    }
    
    // Toggle visibility
    $newVisibility = $section['visible'] ? 0 : 1;
    
    $data = [
        'key' => $section['key'],
        'name' => $section['name'],
        'description' => $section['description'],
        'image' => $section['image'],
        'display_order' => $section['display_order'],
        'active' => $section['active'],
        'visible' => $newVisibility
    ];
    
    $success = $sectionRepo->update($sectionId, $data);
    
    if ($success) {
        echo json_encode(['success' => true, 'visible' => $newVisibility]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update section']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error toggling section visibility: " . $e->getMessage());
    echo json_encode(['error' => 'Server error']);
}
