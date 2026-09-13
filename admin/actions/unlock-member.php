<?php
// admin/actions/unlock-member.php - Clear a member's brute-force login
// lockout (see includes/repositories/LoginLockoutTrait.php). Otherwise the
// only way out for a real locked-out customer is waiting LOCKOUT_MINUTES,
// with no admin visibility into it happening at all -- added 2026-09-13
// after that exact gap caused a real customer to give up and leave.
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/MemberRepository-DB.php';

$memberId = (int) ($_GET['member_id'] ?? 0);
if (!$memberId) {
    header('Location: ../members.php?error=' . urlencode('Falta el id del miembro'));
    exit;
}

$memberRepo = new MemberRepository();

try {
    $memberRepo->unlockMember($memberId);
    header('Location: ../members.php?success=1');
} catch (Exception $e) {
    error_log("Error unlocking member: " . $e->getMessage());
    header('Location: ../members.php?error=' . urlencode('Error al desbloquear el miembro'));
}
exit;
