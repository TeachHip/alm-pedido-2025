<?php
// admin/save-section.php - Save section data
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/SectionRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/ImageUploadHelper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../sections.php');
    exit;
}

// Confirm the uploaded file actually arrived via this HTTP request (not
// some other path PHP happens to be able to read) before ever touching it.
if (!empty($_FILES['image']['tmp_name']) && !is_uploaded_file($_FILES['image']['tmp_name'])) {
    header('Location: ../sections.php?error=' . urlencode('Error al subir la imagen'));
    exit;
}

$sectionRepo = new SectionRepository();
$errors = [];

// Check if editing or creating
$isEdit = isset($_POST['section_id']);
$sectionId = $isEdit ? (int)$_POST['section_id'] : null;

// Validate required fields
if (empty($_POST['name'])) {
    $errors[] = "El nombre de la sección es obligatorio";
}

if (empty($_POST['key'])) {
    $errors[] = "La clave (key) es obligatoria";
}

// Validate key format (only lowercase, numbers, hyphens, underscores)
if (!empty($_POST['key']) && !preg_match('/^[a-z0-9_-]+$/', $_POST['key'])) {
    $errors[] = "La clave solo puede contener minúsculas, números, guiones y guiones bajos";
}

// Check if key already exists (for new sections or if key changed)
if (!empty($_POST['key'])) {
    $existingSection = $sectionRepo->getByKey($_POST['key']);
    if ($existingSection && (!$isEdit || $existingSection['id'] != $sectionId)) {
        $errors[] = "Ya existe una sección con esa clave";
    }
}

// Previously-stored image (for updates), so we know what to keep/replace/delete below.
$previousImage = null;
if ($isEdit) {
    $existingSection = $sectionRepo->getById($sectionId);
    $previousImage = $existingSection ? $existingSection['image'] : null;
}

// If there are errors, redirect back
if (!empty($errors)) {
    $errorMsg = implode(', ', $errors);
    header("Location: ../edit-section.php" . ($isEdit ? "?section_id=$sectionId" : "") . "&error=" . urlencode($errorMsg));
    exit;
}

try {
    // Returns null if no new file was uploaded (keep whatever the section
    // already had). Sections store the full 'grimgs/...' path, unlike
    // products (bare filename) -- prepend it here to match that convention.
    $newImage = processListingImageUpload($_FILES['image'] ?? null, 'grimgs');
    $imageName = $newImage ? ('grimgs/' . $newImage) : $previousImage;

    $data = [
        'key' => trim($_POST['key']),
        'name' => trim($_POST['name']),
        'description' => trim($_POST['description'] ?? ''),
        'image' => $imageName,
        'display_order' => (int)($_POST['display_order'] ?? 0),
        'active' => isset($_POST['active']) ? 1 : 0,
        'visible' => isset($_POST['visible']) ? 1 : 0
    ];

    if ($isEdit) {
        $success = $sectionRepo->update($sectionId, $data);
    } else {
        $success = $sectionRepo->create($data);
    }

    if (!$success) {
        throw new Exception("Error al guardar la sección");
    }

    // Only remove the old file once the DB row has been safely updated to
    // point at the new one.
    if ($newImage && $previousImage && $previousImage !== $imageName) {
        deleteListingImage($previousImage, 'grimgs');
    }

    header("Location: ../sections.php?success=1");
    exit;
} catch (Exception $e) {
    error_log("Error saving section: " . $e->getMessage());
    $errorMsg = "Error al guardar: " . $e->getMessage();
    header("Location: ../edit-section.php" . ($isEdit ? "?section_id=$sectionId" : "") . "&error=" . urlencode($errorMsg));
    exit;
}
