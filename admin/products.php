<?php
// admin/products.php - Product management interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

// Load database repositories
require_once dirname(__FILE__) . '/../includes/ProductRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/SectionRepository-DB.php';

try {
    $productRepo = new ProductRepository();
    $sectionRepo = new SectionRepository();
    
    // Get all products with section info
    $productsArray = $productRepo->getAll();
    
    // Convert to associative array keyed by product ID for compatibility
    $products = [];
    foreach ($productsArray as $product) {
        $products[$product['id']] = [
            'name' => $product['name'],
            'section' => $product['section_key'],
            'price' => $product['price_member'],
            'price2' => $product['price_public'],
            'image' => $product['image'],
            'description' => $product['description'],
            'visible' => $product['visible']
        ];
    }
    
    // Get sections as associative array
    $sectionsArray = $sectionRepo->getAll();
    $sections = [];
    foreach ($sectionsArray as $section) {
        $sections[$section['key']] = $section['name'];
    }
    
} catch (Exception $e) {
    error_log("Error loading products: " . $e->getMessage());
    die("Error: No se pudieron cargar los datos del producto.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Productos - AlMercáu</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="admin-header">
        <h1>Gestionar Productos</h1>
        <div>
            <a href="index.php" class="logout-btn">← Volver</a>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="success-message">
        ✅ Producto guardado correctamente
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="success-message">
        ✅ Producto eliminado correctamente
    </div>
    <?php endif; ?>

    <a href="edit-product.php" class="add-product">+ Añadir Producto</a>

    <div class="products-table">
        <?php if (empty($products)): ?>
        <div class="empty-state">
            <p>No hay productos cargados en el sistema.</p>
        </div>
        <?php else: ?>
        <table width="100%">
            <thead>
                <tr>
                    <th width="5%">ID</th>
                    <th width="20%">Nombre</th>
                    <th width="10%">Sección</th>
                    <th width="10%">Precio Socio</th>
                    <th width="10%">Precio Público</th>
                    <th width="8%">Visible</th>
                    <th width="15%">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $productCount = 0;
                foreach ($products as $productId => $product):
                    $productCount++;
                    $isVisible = $product['visible'] ?? true;
                    $sectionKey = $product['section'] ?? '';
                ?>
                <tr>
                    <td><?php echo $productCount; ?>
                        <img src="../primgs/<?= htmlspecialchars($product['image']); ?>" width=60 style="width:60px;" alt="xxx">
                    </td>
                    <td><?php echo htmlspecialchars($product['name'] ?? 'Nombre no disponible'); ?></td>
                    <td><?php echo htmlspecialchars($sections[$sectionKey] ?? $sectionKey); ?></td>
                    <td>€<?php echo number_format($product['price'] ?? 0, 2); ?></td>
                    <td>€<?php echo number_format(($product['price2'] ?? 0), 2); ?></td>
                    <td class="visibility-cell">
                        <a href="toggle-visibility.php?product_id=<?php echo $productId; ?>">
                        <?php if ($isVisible): ?>
                        <span class="visible-indicator">✓</span>
                        <br><small>Visible</small>
                        <?php else: ?>
                        <span class="hidden-indicator">✗</span>
                        <br><small>Oculto</small>
                        <?php endif; ?>
                        </a>
                    </td>
                    <td class="action-buttons">
                        <a href="edit-product.php?product_id=<?php echo $productId; ?>" class="btn-edit">Editar</a>
                        <a href="edit-product.php?product_id=<?php echo $productId; ?>&clone=1" class="btn-clone">Clonar</a>
                        <!--
                        <a href="toggle-visibility.php?product_id=<?php echo $productId; ?>"
                           class="<?php echo $isVisible ? 'btn-hide' : 'btn-show'; ?>"
                           onclick="return confirm('¿<?php echo $isVisible ? 'Ocultar' : 'Mostrar'; ?> este producto?')">
                            <?php echo $isVisible ? 'Ocultar' : 'Mostrar'; ?>
                        </a>-->
                        <a href="delete-product.php?product_id=<?php echo $productId; ?>"
                           class="btn-delete"
                           onclick="return confirm('¿Eliminar este producto permanentemente? Esta acción no se puede deshacer.')">
                            Eliminar
                        </a>
                    </td>
                </tr>
                <?php
                endforeach;
                ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</body>
</html>