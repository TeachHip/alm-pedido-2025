<?php
// admin/edit-section.php - Create/Edit section form
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/SectionRepository-DB.php';

$sectionRepo = new SectionRepository();
$errors = [];
$section = null;
$isEdit = false;

// Check if editing existing section
if (isset($_GET['section_id'])) {
    $sectionId = (int)$_GET['section_id'];
    $section = $sectionRepo->getById($sectionId);
    
    if (!$section) {
        die("Sección no encontrada");
    }
    
    $isEdit = true;
}

// Get max display order for new sections
if (!$isEdit) {
    $allSections = $sectionRepo->getAll();
    $maxOrder = 0;
    foreach ($allSections as $s) {
        if ($s['display_order'] > $maxOrder) {
            $maxOrder = $s['display_order'];
        }
    }
    $defaultOrder = $maxOrder + 1;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isEdit ? 'Editar' : 'Nueva'; ?> Sección - AlMercáu</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="admin-header">
        <h1><?php echo $isEdit ? 'Editar' : 'Nueva'; ?> Sección</h1>
        <div>
            <a href="sections.php" class="logout-btn">← Volver a Secciones</a>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

    <div class="form-container">
        <form action="save-section.php" method="POST" enctype="multipart/form-data">
            <?php if ($isEdit): ?>
            <input type="hidden" name="section_id" value="<?php echo $section['id']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label for="name">Nombre de la Sección *</label>
                <input type="text" 
                       id="name" 
                       name="name" 
                       value="<?php echo $section ? htmlspecialchars($section['name']) : ''; ?>" 
                       required
                       maxlength="100">
                <small>Nombre visible para los usuarios</small>
            </div>

            <div class="form-group">
                <label for="key">Clave (Key) *</label>
                <input type="text" 
                       id="key" 
                       name="key" 
                       value="<?php echo $section ? htmlspecialchars($section['key']) : ''; ?>" 
                       required
                       pattern="[a-z0-9_-]+"
                       maxlength="50">
                <small>Identificador único (solo minúsculas, números, guiones y guiones bajos). Ej: frutas-verduras</small>
            </div>

            <div class="form-group">
                <label for="description">Descripción</label>
                <textarea id="description" 
                          name="description" 
                          rows="3"><?php echo $section ? htmlspecialchars($section['description']) : ''; ?></textarea>
                <small>Descripción opcional de la sección</small>
            </div>

            <div class="form-group">
                <label for="image">Nombre de imagen de sección:</label>
                <input type="text" name="image" id="image" value="<?php echo $section ? htmlspecialchars(str_replace('grimgs/', '', $section['image'])) : ''; ?>" placeholder="imagen.jpg">
                <small>Solo el nombre del archivo (ejemplo: pedido-expres.jpg). La ruta será siempre 'grimgs/'.</small>
            </div>

            <div class="form-group">
                <label for="display_order">Orden de Visualización</label>
                <input type="number" 
                       id="display_order" 
                       name="display_order" 
                       value="<?php echo $section ? $section['display_order'] : $defaultOrder; ?>" 
                       min="0">
                <small>Número que determina el orden de aparición (menor = primero)</small>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" 
                           name="active" 
                           value="1" 
                           <?php echo (!$section || $section['active']) ? 'checked' : ''; ?>>
                    Sección Activa
                </label>
                <small>Las secciones inactivas no se muestran en ningún lugar</small>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" 
                           name="visible" 
                           value="1" 
                           <?php echo (!$section || $section['visible']) ? 'checked' : ''; ?>>
                    Sección Visible
                </label>
                <small>Controla si la sección aparece en el listado público</small>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save">
                    💾 <?php echo $isEdit ? 'Guardar Cambios' : 'Crear Sección'; ?>
                </button>
                <a href="sections.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>
