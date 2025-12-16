<?php
// admin/save-section.php - Save section data
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/SectionRepository-DB.php';

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

// Handle image upload
$imageName = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = $_FILES['image']['type'];
    
    if (!in_array($fileType, $allowedTypes)) {
        $errors[] = "Tipo de imagen no permitido. Use JPG, PNG, GIF o WebP";
    } else {
        $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $imageName = uniqid('section_') . '.' . $extension;
        $uploadPath = dirname(__FILE__) . '/../imgs/' . $imageName;
        
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
            $errors[] = "Error al subir la imagen";
            $imageName = null;
        }
    }
} elseif ($isEdit) {
    // Keep existing image if not uploading new one
    $existingSection = $sectionRepo->getById($sectionId);
    $imageName = $existingSection['image'];
}

// If there are errors, redirect back
if (!empty($errors)) {
    $errorMsg = implode(', ', $errors);
    header("Location: edit-section.php" . ($isEdit ? "?section_id=$sectionId" : "") . "&error=" . urlencode($errorMsg));
    exit;
}

// Prepare data
$data = [
    'key' => trim($_POST['key']),
    'name' => trim($_POST['name']),
    'description' => trim($_POST['description'] ?? ''),
    'image' => $imageName,
    'display_order' => (int)($_POST['display_order'] ?? 0),
    'active' => isset($_POST['active']) ? 1 : 0,
    'visible' => isset($_POST['visible']) ? 1 : 0
];

try {
    if ($isEdit) {
        $success = $sectionRepo->update($sectionId, $data);
    } else {
        $success = $sectionRepo->create($data);
    }
    
    if ($success) {
        header("Location: sections.php?success=1");
        exit;
    } else {
        throw new Exception("Error al guardar la sección");
    }
} catch (Exception $e) {
    error_log("Error saving section: " . $e->getMessage());
    $errorMsg = "Error al guardar: " . $e->getMessage();
    header("Location: edit-section.php" . ($isEdit ? "?section_id=$sectionId" : "") . "&error=" . urlencode($errorMsg));
    exit;
}
