<?php
// admin/sections.php - Section management interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

// Load database repository
require_once dirname(__FILE__) . '/../includes/SectionRepository-DB.php';

try {
    $sectionRepo = new SectionRepository();
    
    // Get all sections with product counts
    $sections = $sectionRepo->getAllWithProductCountAdmin();
    
} catch (Exception $e) {
    error_log("Error loading sections: " . $e->getMessage());
    die("Error: No se pudieron cargar las secciones.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestionar Secciones - AlMercáu</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .sections-table table {
            width: 100%;
            margin-top: 20px;
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
            font-size: 18px;
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
        .section-image-thumb {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-success {
            background: #e8f5e9;
            color: #4caf50;
        }
        .badge-danger {
            background: #ffebee;
            color: #f44336;
        }
        .product-count {
            color: #666;
            font-size: 14px;
        }
    </style>
    <script>
    function toggleVisibility(sectionId, element) {
        fetch('toggle-section-visibility.php?section_id=' + sectionId)
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
        const tbody = document.querySelector('.sections-tbody');
        
        if (tbody) {
            new Sortable(tbody, {
                animation: 150,
                handle: 'tr',
                ghostClass: 'sortable-ghost',
                dragClass: 'sortable-drag',
                onEnd: function(evt) {
                    saveOrder(tbody);
                }
            });
        }
    });
    
    function saveOrder(tbody) {
        const rows = tbody.querySelectorAll('tr');
        const orders = [];
        
        rows.forEach(function(row, index) {
            const sectionId = row.dataset.sectionId;
            orders.push({
                id: sectionId,
                order: index + 1
            });
        });
        
        fetch('update-section-order.php', {
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
        <h1>Gestionar Secciones</h1>
        <div>
            <a href="index.php" class="logout-btn">← Volver</a>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="success-message">
        ✅ Sección guardada correctamente
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted'])): ?>
    <div class="success-message">
        ✅ Sección eliminada correctamente
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="error-message">
        ❌ <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
    <?php endif; ?>

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
            <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
                💡 <strong>Tip:</strong> Arrastra las filas para reordenar las secciones.
            </p>
            
            <table>
                <thead>
                    <tr>
                        <th width="5%">ID</th>
                        <th width="20%">Nombre</th>
                        <th width="15%">Clave</th>
                        <th width="10%">Productos</th>
                        <th width="8%">Visible</th>
                        <th width="8%">Activo</th>
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
                            <a href="#" onclick="return toggleVisibility(<?php echo $section['id']; ?>, this);">
                            <?php if ($section['visible']): ?>
                            <span class="visible-indicator">✓</span>
                            <br><small>Visible</small>
                            <?php else: ?>
                            <span class="hidden-indicator">✗</span>
                            <br><small>Oculto</small>
                            <?php endif; ?>
                            </a>
                        </td>
                        <td>
                            <?php if ($section['active']): ?>
                            <span class="badge badge-success">Activo</span>
                            <?php else: ?>
                            <span class="badge badge-danger">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td class="action-buttons">
                            <a href="edit-section.php?section_id=<?php echo $section['id']; ?>" class="btn-edit">Editar</a>
                            <a href="delete-section.php?section_id=<?php echo $section['id']; ?>"
                               class="btn-delete"
                               onclick="return confirm('¿Eliminar esta sección? Se eliminarán también todos sus productos. Esta acción no se puede deshacer.')">
                                Eliminar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
