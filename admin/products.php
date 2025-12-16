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
    
    // Get all products with section info, ordered by section and display_order
    $productsArray = $productRepo->getAll();
    
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Productos - AlMercáu</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .section-group {
            margin-bottom: 30px;
        }
        .section-header {
            background: #f0f0f0;
            padding: 10px 15px;
            font-weight: bold;
            font-size: 16px;
            border-left: 4px solid #25D366;
            margin-bottom: 10px;
        }
        .sortable-ghost {
            opacity: 0.4;
            background: #f0f0f0;
        }
        .sortable-drag {
            opacity: 1;
            cursor: move !important;
        }
        tbody tr {
            cursor: move;
        }
        tbody tr:hover {
            background: #f9f9f9;
        }
        .drag-handle {
            cursor: grab;
            padding: 5px;
            color: #999;
        }
        .drag-handle:active {
            cursor: grabbing;
        }
        .save-order-notice {
            background: #e8f5e9;
            border: 1px solid #4caf50;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            display: none;
        }
    </style>
    <script>
    function toggleVisibility(productId, element) {
        fetch('toggle-visibility.php?product_id=' + productId)
            .then(response => {
                if (response.ok) {
                    const cell = element.closest('.visibility-cell');
                    const indicator = cell.querySelector('.visible-indicator, .hidden-indicator');
                    const text = cell.querySelector('small');
                    
                    if (indicator.classList.contains('visible-indicator')) {
                        indicator.classList.remove('visible-indicator');
                        indicator.classList.add('hidden-indicator');
                        indicator.textContent = '✗';
                        text.textContent = 'Oculto';
                    } else {
                        indicator.classList.remove('hidden-indicator');
                        indicator.classList.add('visible-indicator');
                        indicator.textContent = '✓';
                        text.textContent = 'Visible';
                    }
                } else {
                    alert('Error al cambiar la visibilidad');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cambiar la visibilidad');
            });
        return false;
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        const sectionTables = document.querySelectorAll('.section-tbody');
        
        sectionTables.forEach(function(tbody) {
            new Sortable(tbody, {
                animation: 150,
                handle: 'tr',
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    saveOrder(tbody);
                }
            });
        });
    });
    
    function saveOrder(tbody) {
        const rows = tbody.querySelectorAll('tr');
        const orders = [];
        
        rows.forEach(function(row, index) {
            const productId = row.dataset.productId;
            orders.push({
                id: productId,
                order: index + 1
            });
        });
        
        fetch('update-order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ orders: orders })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showSaveNotice();
            } else {
                alert('Error al guardar el orden');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al guardar el orden');
        });
    }
    
    function showSaveNotice() {
        const notice = document.querySelector('.save-order-notice');
        notice.style.display = 'block';
        setTimeout(() => {
            notice.style.display = 'none';
        }, 2000);
    }
    </script>
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

    <div class="save-order-notice">
        ✅ Orden guardado correctamente
    </div>

    <a href="edit-product.php" class="add-product">+ Añadir Producto</a>

    <div class="products-table">
        <?php if (empty($productsBySection)): ?>
        <div class="empty-state">
            <p>No hay productos cargados en el sistema.</p>
        </div>
        <?php else: ?>
            <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
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
                                <th width="20%">Nombre</th>
                                <th width="10%">Precio Socio</th>
                                <th width="10%">Precio Público</th>
                                <th width="8%">Visible</th>
                                <th width="15%">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="section-tbody">
                            <?php foreach ($products as $product): ?>
                            <tr data-product-id="<?php echo $product['id']; ?>">
                                <td>
                                    <span class="drag-handle" title="Arrastra para reordenar">⋮⋮</span>
                                    <?php echo $product['id']; ?>
                                    <br>
                                    <img src="../primgs/<?php echo htmlspecialchars($product['image']); ?>" width="40" style="width:40px; margin-top: 5px;" alt="">
                                </td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td>€<?php echo number_format($product['price_member'], 2); ?></td>
                                <td>€<?php echo number_format($product['price_public'], 2); ?></td>
                                <td class="visibility-cell">
                                    <a href="#" onclick="return toggleVisibility(<?php echo $product['id']; ?>, this);">
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
                                    <a href="delete-product.php?product_id=<?php echo $product['id']; ?>"
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
