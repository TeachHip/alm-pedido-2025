<?php
// admin/products.php - Product management interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

// Load database repositories
require_once dirname(__FILE__) . '/../includes/repositories/ProductRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/repositories/SectionRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/repositories/ProductOptionRepository-DB.php';

try {
    $productRepo = new ProductRepository();
    $sectionRepo = new SectionRepository();
    $optionRepo = new ProductOptionRepository();

    // Get all products with section info, ordered by section and display_order
    $productsArray = $productRepo->getAll();

    // Option counts per product, for the "N opciones" badge
    $optionCounts = $optionRepo->getCountsGroupedByProduct();
    
    // Get sections as associative array
    $sectionsArray = $sectionRepo->getAll();
    $sections = [];
    foreach ($sectionsArray as $section) {
        $sections[$section['id']] = $section;
    }
    
    // Group products by section for drag-and-drop
    $productsBySection = [];
    foreach ($productsArray as $product) {
        $sectionId = $product['section_id'];
        if (!isset($productsBySection[$sectionId])) {
            $productsBySection[$sectionId] = [];
        }
        $productsBySection[$sectionId][] = $product;
    }
    
    // Sort products within each section by display_order
    foreach ($productsBySection as $sectionId => $products) {
        usort($productsBySection[$sectionId], function($a, $b) {
            return $a['display_order'] - $b['display_order'];
        });
    }
    
} catch (Exception $e) {
    error_log("Error loading products: " . $e->getMessage());
    die("Error: No se pudieron cargar los datos del producto.");
}
$pageTitle = 'Gestionar Productos - AlMercáu';
$pageH1 = 'Gestionar Productos';
$activeNav = 'products';
$successMessage = 'Producto guardado correctamente';
$deletedMessage = 'Producto eliminado correctamente';
include dirname(__FILE__) . '/partials/head.php';
?>
    <link rel="stylesheet" href="../assets/admin/sortable-table.css?v=<?php echo APP_VERSION_SAFE; ?>">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="../assets/admin/toggle-indicator.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    <script src="../assets/admin/sortable-list.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    <script src="../assets/admin/filter-toggle.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.section-tbody').forEach(function(tbody) {
            initSortableList(tbody, { dataKey: 'productId', saveUrl: 'actions/update-order.php' });
        });
        initFilterToggle({
            buttonId: 'toggle-visible-btn',
            cookieName: 'admin_show_only_visible',
            rowSelector: 'tr[data-product-id]',
            dataAttr: 'data-visible',
            filterLabel: 'Mostrar solo visibles',
            showAllLabel: 'Mostrar todos'
        });
    });
    </script>
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <div class="save-order-notice">
        ✅ Orden guardado correctamente
    </div>

    <a href="edit-product.php" class="add-product">+ Añadir Producto</a>

    <button id="toggle-visible-btn" type="button" style="margin-left: 15px; padding: 7px 16px; font-size: 15px; border-radius: 5px; border: 1px solid #bbb; background: #f8f8f8; cursor: pointer;">
        Mostrar solo visibles
    </button>

    <div class="products-table">
        <?php if (empty($productsBySection)): ?>
        <div class="empty-state">
            <p>No hay productos cargados en el sistema.</p>
        </div>
        <?php else: ?>
            <p class="admin-tip">
                💡 <strong>Tip:</strong> Arrastra las filas para reordenar los productos dentro de cada sección.
            </p>
            
            <?php foreach ($productsBySection as $sectionId => $products): ?>
                <?php if (isset($sections[$sectionId])): ?>
                <div class="section-group">
                    <div class="section-header">
                        📂 <?php echo htmlspecialchars($sections[$sectionId]['name']); ?>
                        (<?php echo count($products); ?> productos)
                    </div>
                    
                    <table width="100%">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="7%">Fin de stock</th>
                                <th width="20%">Nombre</th>
                                <th width="10%">Precio Socio</th>
                                <th width="10%">Precio Público</th>
                                <th width="8%">Visible</th>
                                <th width="15%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="section-tbody">
                            <?php foreach ($products as $product): ?>
                            <tr data-product-id="<?php echo $product['id']; ?>" data-visible="<?php echo $product['visible'] ? '1' : '0'; ?>">
                                <td>
                                    <span class="drag-handle" title="Arrastra para reordenar">⋮⋮</span>
                                    <?php echo $product['id']; ?>
                                    <br>
                                    <img src="../primgs/<?php echo htmlspecialchars($product['image']); ?>" width="40" style="width:40px; margin-top: 5px;" alt="">
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($product['almost_out_of_stock']): ?>
                                    <span style="color: #ff6b6b; font-size: 18px;" title="Fin de stock">⚠️</span>
                                    <?php else: ?>
                                    <span style="color: #ccc;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($product['name']); ?>
                                    <?php if (!empty($optionCounts[$product['id']])): ?>
                                    <br><small class="badge badge-success"><?php echo $optionCounts[$product['id']]; ?> opciones</small>
                                    <?php endif; ?>
                                </td>
                                <td>€<?php echo number_format($product['price_member'], 2); ?></td>
                                <td>€<?php echo number_format($product['price_public'], 2); ?></td>
                                <td class="visibility-cell">
                                    <a href="#" onclick="return adminToggle('actions/toggle-visibility.php?product_id=<?php echo $product['id']; ?>', this, {valueKey: 'visible', trueLabel: 'Visible', falseLabel: 'Oculto', errorMessage: 'Error al cambiar la visibilidad', dataAttr: 'data-visible'});">
                                    <?php if ($product['visible']): ?>
                                    <span class="visible-indicator">✓</span>
                                    <br><small>Visible</small>
                                    <?php else: ?>
                                    <span class="hidden-indicator">✗</span>
                                    <br><small>Oculto</small>
                                    <?php endif; ?>
                                    </a>
                                </td>
                                <td class="action-buttons">
                                    <a href="edit-product.php?product_id=<?php echo $product['id']; ?>" class="btn-edit">Editar</a>
                                    <a href="edit-product.php?product_id=<?php echo $product['id']; ?>&clone=1" class="btn-clone">Clonar</a>
                                    <a href="actions/delete-product.php?product_id=<?php echo $product['id']; ?>"
                                       class="btn-delete"
                                       onclick="return confirm('¿Eliminar este producto permanentemente? Esta acción no se puede deshacer.')">
                                        Eliminar
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
