<?php
// admin/sections.php - Section management interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

// Load database repository
require_once dirname(__FILE__) . '/../includes/repositories/SectionRepository-DB.php';

try {
    $sectionRepo = new SectionRepository();
    
    // Get all sections with product counts
    $sections = $sectionRepo->getAllWithProductCountAdmin();
    
} catch (Exception $e) {
    error_log("Error loading sections: " . $e->getMessage());
    die("Error: No se pudieron cargar las secciones.");
}
$pageTitle = 'Gestionar Secciones - AlMercáu';
$pageH1 = 'Gestionar Secciones';
$activeNav = 'sections';
$successMessage = 'Sección guardada correctamente';
$deletedMessage = 'Sección eliminada correctamente';
include dirname(__FILE__) . '/partials/head.php';
?>
    <link rel="stylesheet" href="../assets/admin/sortable-table.css?v=<?php echo APP_VERSION_SAFE; ?>">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="../assets/admin/toggle-indicator.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    <script src="../assets/admin/sortable-list.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        initSortableList(document.querySelector('.sections-tbody'), { dataKey: 'sectionId', saveUrl: 'actions/update-section-order.php' });
    });
    </script>
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <div class="save-order-notice">
        ✅ Orden guardado correctamente
    </div>

    <a href="edit-section.php" class="add-product">+ Añadir Sección</a>

    <div class="sections-table">
        <?php if (empty($sections)): ?>
        <div class="empty-state">
            <p>No hay secciones cargadas en el sistema.</p>
        </div>
        <?php else: ?>
            <p class="admin-tip">
                💡 <strong>Tip:</strong> Arrastra las filas para reordenar las secciones.
            </p>
            
            <div class="table-scroll">
            <table width="100%">
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th width="20%">Nombre</th>
                        <th width="15%">Clave</th>
                        <th width="10%">Productos</th>
                        <th width="8%">Visible</th>
                        <th width="15%">Acciones</th>
                    </tr>
                </thead>
                <tbody class="sections-tbody">
                    <?php foreach ($sections as $section): ?>
                    <tr data-section-id="<?php echo $section['id']; ?>">
                        <td>
                            <span class="drag-handle" title="Arrastra para reordenar">⋮⋮</span>
                            <?php echo $section['id']; ?>
                        </td>
                        <td>
                            <?php if (!empty($section['image'])): ?>
                            <img src="../<?php echo htmlspecialchars($section['image']); ?>" 
                                 class="section-image-thumb" alt="">
                            <?php endif; ?>
                            <strong><?php echo htmlspecialchars($section['name']); ?></strong>
                            <?php if (!empty($section['description'])): ?>
                            <br><small style="color: #999;"><?php echo htmlspecialchars(substr($section['description'], 0, 50)); ?><?php echo strlen($section['description']) > 50 ? '...' : ''; ?></small>
                            <?php endif; ?>
                        </td>
                        <td><code><?php echo htmlspecialchars($section['key']); ?></code></td>
                        <td class="product-count">
                            📦 <?php echo $section['product_count']; ?> productos
                        </td>
                        <td class="visibility-cell">
                            <a href="#" onclick="return adminToggle('actions/toggle-section-visibility.php?section_id=<?php echo $section['id']; ?>', this, {valueKey: 'visible', errorMessage: 'Error al cambiar la visibilidad'});">
                            <?php if ($section['visible']): ?>
                            <span class="visible-indicator">✓</span>
                            <br><small>Visible</small>
                            <?php else: ?>
                            <span class="hidden-indicator">✗</span>
                            <br><small>Oculto</small>
                            <?php endif; ?>
                            </a>
                        </td>
                        <td class="action-buttons">
                            <a href="edit-section.php?section_id=<?php echo $section['id']; ?>" class="btn-edit">Editar</a>
                            <a href="actions/delete-section.php?section_id=<?php echo $section['id']; ?>"
                               class="btn-delete"
                               onclick="return confirm('¿Eliminar esta sección? Se eliminarán también todos sus productos. Esta acción no se puede deshacer.')">
                                Eliminar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
