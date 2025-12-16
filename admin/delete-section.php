<?php
// admin/delete-section.php - Delete a section
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/SectionRepository-DB.php';

if (!isset($_GET['section_id'])) {
    header("Location: sections.php?error=" . urlencode("ID de sección no especificado"));
    exit;
}

$sectionId = (int)$_GET['section_id'];
$sectionRepo = new SectionRepository();

try {
    // Get section to check if it exists
    $section = $sectionRepo->getById($sectionId);
    
    if (!$section) {
        header("Location: sections.php?error=" . urlencode("Sección no encontrada"));
        exit;
    }
    
    // Delete the section (products will be cascade deleted if foreign key is set)
    $success = $sectionRepo->delete($sectionId);
    
    if ($success) {
        header("Location: sections.php?deleted=1");
    } else {
        header("Location: sections.php?error=" . urlencode("Error al eliminar la sección"));
    }
} catch (Exception $e) {
    error_log("Error deleting section: " . $e->getMessage());
    header("Location: sections.php?error=" . urlencode("Error: " . $e->getMessage()));
}
exit;
