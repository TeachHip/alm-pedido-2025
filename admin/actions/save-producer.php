<?php
// admin/actions/save-producer.php - Save producer data
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/ProducerRepository-DB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../producers.php');
    exit;
}

$producerRepo = new ProducerRepository();

$isEdit = isset($_POST['producer_id']);
$producerId = $isEdit ? (int) $_POST['producer_id'] : null;
$name = trim($_POST['name'] ?? '');
$active = isset($_POST['active']) ? 1 : 0;

$errors = [];
if ($name === '') {
    $errors[] = 'El nombre es obligatorio';
} elseif ($name === ProducerRepository::PLACEHOLDER_NAME) {
    $errors[] = "\"" . ProducerRepository::PLACEHOLDER_NAME . "\" está reservado para el marcador automático";
}

// Same-name-different-case check as SectionRepository's key check.
if (empty($errors)) {
    $existing = $producerRepo->findByName($name);
    if ($existing && (!$isEdit || $existing['id'] != $producerId)) {
        $errors[] = 'Ya existe un productor con ese nombre';
    }
}

if (!empty($errors)) {
    $errorMsg = implode(', ', $errors);
    header('Location: ../edit-producer.php' . ($isEdit ? "?producer_id=$producerId" : '') . '&error=' . urlencode($errorMsg));
    exit;
}

try {
    if ($isEdit) {
        $success = $producerRepo->update($producerId, $name, $active);
    } else {
        $success = $producerRepo->create($name, $active);
    }

    if (!$success) {
        throw new Exception('Error al guardar el productor');
    }

    header('Location: ../producers.php?success=1');
} catch (Exception $e) {
    error_log("Error saving producer: " . $e->getMessage());
    $errorMsg = 'Error al guardar: ' . $e->getMessage();
    header('Location: ../edit-producer.php' . ($isEdit ? "?producer_id=$producerId" : '') . '&error=' . urlencode($errorMsg));
}
exit;
?>
