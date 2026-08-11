<?php
// admin/actions/save-member.php - Create/update a member
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/MemberRepository-DB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../members.php');
    exit;
}

$memberId = $_POST['member_id'] ?? '';
$isEdit = !empty($memberId);

$phone = trim($_POST['phone'] ?? '');
$alias = trim($_POST['alias'] ?? '');
$internalAlias = trim($_POST['internal_alias'] ?? '');
$notes = trim($_POST['notes'] ?? '');
$email = trim($_POST['email'] ?? '');
$membershipType = $_POST['membership_type'] ?? '';
$password = $_POST['password'] ?? '';
$active = isset($_POST['active']) ? 1 : 0;

// Validate required fields
$missingFields = [];
if (empty($phone)) $missingFields[] = 'Teléfono';
if (empty($alias)) $missingFields[] = 'Alias';
if (!in_array($membershipType, ['paying', 'non_paying'], true)) $missingFields[] = 'Tipo de socia';
if (!$isEdit && empty($password)) $missingFields[] = 'Contraseña inicial';

if (!empty($missingFields)) {
    $backTo = $isEdit ? "edit-member.php?member_id=$memberId" : 'edit-member.php';
    header('Location: ' . $backTo . '&error=' . urlencode('Faltan campos obligatorios: ' . implode(', ', $missingFields)));
    exit;
}

try {
    $memberRepo = new MemberRepository();

    $data = [
        'phone' => $phone,
        'alias' => $alias,
        'internal_alias' => $internalAlias !== '' ? $internalAlias : null,
        'notes' => $notes !== '' ? $notes : null,
        'email' => $email !== '' ? $email : null,
        'membership_type' => $membershipType,
        'active' => $active,
    ];

    if ($isEdit) {
        $memberRepo->update($memberId, $data);
        if ($password !== '') {
            $memberRepo->updatePassword($memberId, $password);
        }
    } else {
        $data['password'] = $password;
        $memberRepo->create($data);
    }

    header('Location: ../members.php?success=1');
} catch (PDOException $e) {
    // Duplicate phone (UNIQUE KEY) -> friendly message instead of a raw 500
    $backTo = $isEdit ? "edit-member.php?member_id=$memberId" : 'edit-member.php';
    if ($e->getCode() === '23000') {
        header('Location: ' . $backTo . '&error=' . urlencode('Ya existe un miembro con ese teléfono'));
    } else {
        error_log("Error saving member: " . $e->getMessage());
        header('Location: ' . $backTo . '&error=' . urlencode('Error al guardar el miembro'));
    }
} catch (Exception $e) {
    error_log("Error saving member: " . $e->getMessage());
    $backTo = $isEdit ? "edit-member.php?member_id=$memberId" : 'edit-member.php';
    header('Location: ' . $backTo . '&error=' . urlencode($e->getMessage()));
}
exit;
