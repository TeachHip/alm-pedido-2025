<?php
// admin/orders.php - Orders management interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

// Load database repository
require_once dirname(__FILE__) . '/../includes/CartRepository-DB.php';

try {
    $cartRepo = new CartRepository();
    
    // Pagination
    $perPage = 25;
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $offset = ($page - 1) * $perPage;
    
    // Get total count for pagination
    $totalOrders = $cartRepo->getOrdersCount();
    $totalPages = ceil($totalOrders / $perPage);
    
    // Get orders for current page
    $orders = $cartRepo->getAllOrders($perPage, $offset);
    
} catch (Exception $e) {
    error_log("Error loading orders: " . $e->getMessage());
    die("Error: No se pudieron cargar los pedidos.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos - AlMercáu</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .order-details {
            display: none;
            background: #f9f9f9;
            padding: 15px;
            margin-top: 10px;
            border-left: 3px solid #25D366;
        }
        .order-details.expanded {
            display: block;
        }
        .order-row {
            cursor: pointer;
        }
        .order-row:hover {
            background: #f0f0f0;
        }
        .expand-icon {
            transition: transform 0.2s;
            display: inline-block;
        }
        .expand-icon.rotated {
            transform: rotate(90deg);
        }
        .order-item {
            padding: 8px 0;
            border-bottom: 1px solid #ddd;
        }
        .order-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>📋 Pedidos</h1>
        <div>
            <a href="index.php" class="logout-btn">← Volver</a>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

    <div class="products-table">
        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <p>No hay pedidos registrados en el sistema.</p>
        </div>
        <?php else: ?>
        <p style="color: #666; font-size: 14px; margin-bottom: 15px;">
            💡 <strong>Tip:</strong> Haz clic en una fila para ver los detalles del pedido.
        </p>
        
        <table width="100%">
            <thead>
                <tr>
                    <th width="5%"></th>
                    <th width="15%">Ticket</th>
                    <th width="15%">Fecha</th>
                    <th width="10%">Total</th>
                    <th width="10%">Estado</th>
                    <th width="10%">Productos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): 
                    $ticket = $cartRepo->getTicketNumber($order['id']);
                    $statusLabel = [
                        'active' => 'Activo',
                        'completed' => 'Completado',
                        'abandoned' => 'Abandonado'
                    ];
                ?>
                <tr class="order-row" onclick="toggleOrderDetails(<?php echo $order['id']; ?>)">
                    <td>
                        <span class="expand-icon" id="icon-<?php echo $order['id']; ?>">▶</span>
                    </td>
                    <td><?php echo htmlspecialchars($ticket); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                    <td><?php echo number_format($order['total_price'] ?? 0, 2); ?>€</td>
                    <td><?php echo $statusLabel[$order['status']] ?? $order['status']; ?></td>
                    <td><?php echo $order['items_count']; ?> items</td>
                </tr>
                <tr id="details-<?php echo $order['id']; ?>" style="display: none;">
                    <td colspan="6">
                        <div class="order-details" id="content-<?php echo $order['id']; ?>">
                            <p>Cargando detalles...</p>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($totalPages > 1): ?>
        <div style="margin-top: 20px; text-align: center;">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" style="padding: 8px 15px; margin: 0 5px; background: #25D366; color: white; text-decoration: none; border-radius: 5px;">← Anterior</a>
            <?php endif; ?>
            
            <span style="margin: 0 10px;">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" style="padding: 8px 15px; margin: 0 5px; background: #25D366; color: white; text-decoration: none; border-radius: 5px;">Siguiente →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <script>
        const loadedOrders = {};
        
        function toggleOrderDetails(orderId) {
            const detailsRow = document.getElementById('details-' + orderId);
            const contentDiv = document.getElementById('content-' + orderId);
            const icon = document.getElementById('icon-' + orderId);
            
            if (detailsRow.style.display === 'none') {
                // Show details
                detailsRow.style.display = 'table-row';
                contentDiv.classList.add('expanded');
                icon.classList.add('rotated');
                
                // Load details if not already loaded
                if (!loadedOrders[orderId]) {
                    fetch('get-order-details.php?id=' + orderId)
                        .then(response => response.json())
                        .then(data => {
                            console.log('Order data:', data); // Debug
                            if (data.success) {
                                let html = '<h3>Pedido ' + data.ticket + '</h3>';
                                html += '<div style="margin-top: 10px; font-family: monospace;">';
                                
                                data.items.forEach(item => {
                                    html += '<div class="order-item">';
                                    html += item.quantity + 'x ' + item.product_name + ' - ' + parseFloat(item.subtotal).toFixed(2) + '€';
                                    html += '</div>';
                                });
                                
                                html += '</div>';
                                html += '<div style="margin-top: 15px; padding-top: 10px; border-top: 2px solid #25D366; font-weight: bold;">';
                                html += 'Total: ' + parseFloat(data.cart.total_price).toFixed(2) + '€';
                                html += '</div>';
                                
                                contentDiv.innerHTML = html;
                                loadedOrders[orderId] = true;
                            } else {
                                contentDiv.innerHTML = '<p style="color: red;">Error: ' + (data.error || 'Desconocido') + '</p>';
                            }
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                            contentDiv.innerHTML = '<p style="color: red;">Error de conexión</p>';
                        });
                }
            } else {
                // Hide details
                detailsRow.style.display = 'none';
                contentDiv.classList.remove('expanded');
                icon.classList.remove('rotated');
            }
        }
    </script>
</body>
</html>
