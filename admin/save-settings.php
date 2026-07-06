<?php
// admin/save-settings.php - Toggle a boolean global setting via AJAX
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/SettingsRepository-DB.php';

header('Content-Type: application/json');

if (!isset($_GET['key'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Setting key not provided']);
    exit;
}

// Whitelist of settings togglable from this endpoint
$allowedKeys = ['show_dual_pricing'];
$key = $_GET['key'];

if (!in_array($key, $allowedKeys, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid setting key']);
    exit;
}

$settingsRepo = new SettingsRepository();

try {
    $newValue = !$settingsRepo->getBool($key, false);
    $success = $settingsRepo->setBool($key, $newValue);

    if ($success) {
        echo json_encode(['success' => true, 'value' => $newValue]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update setting']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error toggling setting: " . $e->getMessage());
    echo json_encode(['error' => 'Server error']);
}
