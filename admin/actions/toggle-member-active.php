<?php
// admin/actions/toggle-member-active.php - Toggle member active status via AJAX
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/MemberRepository-DB.php';

header('Content-Type: application/json');

if (!isset($_GET['member_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Member ID not provided']);
    exit;
}

$memberId = (int) $_GET['member_id'];
$memberRepo = new MemberRepository();

try {
    $member = $memberRepo->findById($memberId);

    if (!$member) {
        http_response_code(404);
        echo json_encode(['error' => 'Member not found']);
        exit;
    }

    $newActive = $member['active'] ? 0 : 1;
    $success = $memberRepo->setActive($memberId, $newActive);

    // Deactivating also revokes any open session immediately
    if (!$newActive) {
        $memberRepo->clearSessionToken($memberId);
    }

    if ($success) {
        echo json_encode(['success' => true, 'active' => (bool) $newActive]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update member']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Error toggling member active: " . $e->getMessage());
    echo json_encode(['error' => 'Server error']);
}
