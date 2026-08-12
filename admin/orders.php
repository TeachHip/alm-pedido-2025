<?php
// admin/orders.php - Orders management interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

// Load database repository
require_once dirname(__FILE__) . '/../includes/repositories/CartRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/repositories/InvoiceRepository-DB.php';

try {
    $cartRepo = new CartRepository();
    $invoiceRepo = new InvoiceRepository();
    
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
$pageTitle = 'Pedidos - AlMercáu';
$pageH1 = '📋 Pedidos';
$activeNav = 'orders';
include dirname(__FILE__) . '/partials/head.php';
?>
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
        .pagination-link {
            padding: 8px 15px;
            margin: 0 5px;
            background: #25D366;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <div class="products-table">
        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <p>No hay pedidos registrados en el sistema.</p>
        </div>
        <?php else: ?>
        <p class="admin-tip">
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
                    <th width="15%">Ticket de compra</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order):
                    $ticket = $cartRepo->getTicketNumber($order['id']);
                    $invoice = $invoiceRepo->findByCartId($order['id']);
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
                    <td onclick="event.stopPropagation();">
                        <?php if ($invoice): ?>
                        <a href="invoice-created.php?invoice_id=<?php echo $invoice['id']; ?>" class="btn-edit">Ver ticket</a>
                        <?php else: ?>
                        <a href="create-invoice.php?cart_id=<?php echo $order['id']; ?>" class="btn-edit">Crear ticket</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr id="details-<?php echo $order['id']; ?>" style="display: none;">
                    <td colspan="7">
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
                <a href="?page=<?php echo $page - 1; ?>" class="pagination-link">← Anterior</a>
            <?php endif; ?>

            <span style="margin: 0 10px;">Página <?php echo $page; ?> de <?php echo $totalPages; ?></span>

            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="pagination-link">Siguiente →</a>
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
                    fetch('actions/get-order-details.php?id=' + orderId)
                        .then(response => response.json())
                        .then(data => {
                            console.log('Order data:', data); // Debug
                            if (data.success) {
                                let html = '<h3>Pedido ' + data.ticket + '</h3>';
                                html += '<div style="margin-top: 10px; font-family: monospace;">';
                                
                                data.items.forEach(item => {
                                    html += '<div class="order-item">';
                                    const productLabel = item.option_label ? item.product_name + ' (' + item.option_label + ')' : item.product_name;
                                    html += item.quantity + 'x ' + productLabel + ' - ' + parseFloat(item.subtotal).toFixed(2) + '€';
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
