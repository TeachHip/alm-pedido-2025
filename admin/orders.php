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

    // Batch-fetch invoices for this page's carts in one query instead of
    // one findByCartId() call per row (was up to 25 extra queries per page load).
    $invoicesByCartId = $invoiceRepo->findByCartIds(array_column($orders, 'id'));

} catch (Exception $e) {
    error_log("Error loading orders: " . $e->getMessage());
    die("Error: No se pudieron cargar los pedidos.");
}
$pageTitle = 'Pedidos - AlMercáu';
$pageH1 = '📋 Pedidos';
$activeNav = 'orders';
include dirname(__FILE__) . '/partials/head.php';
?>
    <link rel="stylesheet" href="../assets/admin/orders.css?v=<?php echo APP_VERSION_SAFE; ?>">
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <label for="order-payment-filter" style="margin-bottom: 15px; display: inline-block; font-size: 15px;">
        Mostrar:
        <select id="order-payment-filter" style="padding: 6px 10px; font-size: 15px; border-radius: 5px; border: 1px solid #bbb;">
            <option value="all">Todos</option>
            <option value="paid_pending">Pagados y pendientes</option>
            <option value="paid">Pagados</option>
            <option value="pending">Pendientes</option>
        </select>
    </label>
    <button id="toggle-picked-orders-btn" type="button" style="margin-bottom: 15px; margin-left: 8px; padding: 7px 16px; font-size: 15px; border-radius: 5px; border: 1px solid #bbb; background: #f8f8f8; cursor: pointer;">
        Ocultar recogidos
    </button>

    <div class="products-table">
        <?php if (empty($orders)): ?>
        <div class="empty-state">
            <p>No hay pedidos registrados en el sistema.</p>
        </div>
        <?php else: ?>
        <p class="admin-tip">
            💡 <strong>Tip:</strong> Haz clic en una fila para ver los detalles del pedido.
        </p>

        <div class="table-scroll">
        <table width="100%">
            <thead>
                <tr>
                    <th width="4%"></th>
                    <th width="12%">Ticket</th>
                    <th width="11%">Fecha</th>
                    <th width="13%">Miembro</th>
                    <th width="8%">Total</th>
                    <th width="9%">Estado</th>
                    <th width="10%">Recogida</th>
                    <th width="8%">Productos</th>
                    <th width="14%">Ticket de compra</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order):
                    $invoice = $invoicesByCartId[$order['id']] ?? null;
                    $invoice = $invoiceRepo->autoExpireIfOverdue($invoice);
                    // The real ticket number lives on the invoice, generated
                    // once at creation time -- CartRepository::getTicketNumber()
                    // recomputes a totally different number on the fly (the
                    // cart's position among carts that month), so it drifts
                    // from the real one as soon as cart/invoice counts diverge
                    // (abandoned carts, corrections, etc). Only fall back to
                    // it for a cart that has no invoice yet.
                    $ticket = $invoice ? $invoice['ticket_number'] : $cartRepo->getTicketNumber($order['id'], $order);
                    $statusLabel = [
                        'active' => 'Activo',
                        'completed' => 'Completado',
                        'abandoned' => 'Abandonado'
                    ];
                    // Once an invoice exists, ITS status is the meaningful
                    // one to show -- the cart's own status never changes
                    // again after checkout (cancel-order.php/cancel-invoice.php
                    // only touch the invoice), so showing the cart status here
                    // would silently hide a cancelled/superseded ticket behind
                    // whatever "Completado" the cart got at submission time.
                    if ($invoice) {
                        if ($invoice['status'] === 'cancelled') {
                            $orderStatusDisplay = '❌ Cancelado';
                        } elseif ($invoice['status'] === 'superseded') {
                            $orderStatusDisplay = '🔄 Sustituido';
                        } elseif ($invoice['payment_status'] === 'paid') {
                            $orderStatusDisplay = '✅ Pagado';
                        } elseif ($invoice['payment_status'] === 'expired') {
                            $orderStatusDisplay = '⚠️ Vencido';
                        } else {
                            $orderStatusDisplay = '⏳ Pendiente de pago';
                        }
                    } else {
                        $orderStatusDisplay = $statusLabel[$order['status']] ?? $order['status'];
                    }
                    // Cancelado/Vencido are hidden by default (see the
                    // toggle-visible-btn-style filter button below) --
                    // everything else ("live": pending/paid/no-invoice-yet)
                    // stays visible.
                    $isDeadOrder = $invoice && ($invoice['status'] === 'cancelled' || $invoice['payment_status'] === 'expired');
                    // Drives the "Mostrar:" payment filter (all / paid & pending /
                    // pending) -- a cart with no invoice yet counts as "pending"
                    // too (nothing's been paid), same as it already did under the
                    // old boolean live/dead filter.
                    if ($isDeadOrder) {
                        $paymentTier = 'dead';
                    } elseif ($invoice && $invoice['payment_status'] === 'paid') {
                        $paymentTier = 'paid';
                    } else {
                        $paymentTier = 'pending';
                    }
                    // Recogida can't actually be set until paid (see
                    // update-fulfillment.php's own guard) -- show '—' rather
                    // than a "Pendiente" that looks the same for every unpaid
                    // row and can't be told apart from a genuinely actionable one.
                    $fulfillmentLabels = ['pending' => '⏳ Pendiente', 'partial' => '🟡 Parcial', 'picked' => '✅ Recogido'];
                    $fulfillmentDisplay = ($invoice && $invoice['payment_status'] === 'paid')
                        ? ($fulfillmentLabels[$invoice['fulfillment_status']] ?? $fulfillmentLabels['pending'])
                        : '—';
                    // '1' = still needs picking (pending/partial), '0' = already
                    // picked -- named/valued so the shared initFilterToggle widget's
                    // "onlyTrue hides rows where attr !== '1'" semantics do the
                    // right thing (hide picked orders by default).
                    $needsPicking = !$invoice || $invoice['fulfillment_status'] !== 'picked';
                ?>
                <tr class="order-row" data-payment-tier="<?php echo $paymentTier; ?>" data-pending-pickup="<?php echo $needsPicking ? '1' : '0'; ?>" onclick="toggleOrderDetails(<?php echo $order['id']; ?>)">
                    <td>
                        <span class="expand-icon" id="icon-<?php echo $order['id']; ?>">▶</span>
                    </td>
                    <td><?php echo htmlspecialchars($ticket); ?></td>
                    <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                    <td class="member-cell" onclick="event.stopPropagation();">
                        <?php if ($order['member_alias']): ?>
                        <span class="member-tooltip-trigger" tabindex="0">
                            <?php echo htmlspecialchars($order['member_alias']); ?><?php if ($order['member_number']): ?> (#<?php echo htmlspecialchars($order['member_number']); ?>)<?php endif; ?>
                            <div class="member-tooltip">
                                <button type="button" class="member-tooltip-close" aria-label="Cerrar">✕</button>
                                <div><strong>Alias:</strong> <?php echo htmlspecialchars($order['member_alias']); ?></div>
                                <?php if ($order['member_internal_alias']): ?>
                                <div><strong>Nombre interno:</strong> <?php echo htmlspecialchars($order['member_internal_alias']); ?></div>
                                <?php endif; ?>
                                <div><strong>Teléfono:</strong> <?php echo htmlspecialchars($order['member_phone'] ?? '—'); ?></div>
                            </div>
                        </span>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </td>
                    <td><?php echo number_format($order['total_price'] ?? 0, 2); ?>€</td>
                    <td><?php echo $orderStatusDisplay; ?></td>
                    <td id="fulfillment-badge-<?php echo $order['id']; ?>"><?php echo $fulfillmentDisplay; ?></td>
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
                    <td colspan="9">
                        <div class="order-details" id="content-<?php echo $order['id']; ?>">
                            <p>Cargando detalles...</p>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

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
        // Member tooltip: hover shows it; clicking the trigger pins it open
        // (stays visible after the mouse leaves) until the ✕ closes it.
        document.addEventListener('click', function(e) {
            const closeBtn = e.target.closest('.member-tooltip-close');
            if (closeBtn) {
                e.stopPropagation();
                closeBtn.closest('.member-tooltip').classList.remove('is-pinned');
                return;
            }
            const trigger = e.target.closest('.member-tooltip-trigger');
            if (trigger) {
                e.stopPropagation();
                trigger.querySelector('.member-tooltip').classList.toggle('is-pinned');
            }
        });

        // Two independent filters (payment tier + hide-picked) applied
        // together against the same rows -- combined in one function rather
        // than two separate widgets each setting row.style.display on their
        // own, which would silently let the last one to run undo the other's
        // decision instead of properly ANDing both.
        function getCookie(name) {
            const match = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
            return match ? match.pop() : null;
        }

        function setCookie(name, value) {
            const expires = new Date();
            expires.setFullYear(expires.getFullYear() + 1);
            document.cookie = name + '=' + value + '; expires=' + expires.toUTCString() + '; path=/; samesite=lax';
        }

        function applyOrderFilters() {
            const paymentFilter = document.getElementById('order-payment-filter').value;
            const hidePicked = document.getElementById('toggle-picked-orders-btn').dataset.active === '1';

            document.querySelectorAll('tr.order-row').forEach(function(row) {
                const tier = row.getAttribute('data-payment-tier');
                let visible = true;
                if (paymentFilter === 'paid_pending' && tier === 'dead') visible = false;
                if (paymentFilter === 'paid' && tier !== 'paid') visible = false;
                if (paymentFilter === 'pending' && tier !== 'pending') visible = false;
                if (visible && hidePicked && row.getAttribute('data-pending-pickup') !== '1') visible = false;
                row.style.display = visible ? '' : 'none';
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const paymentSelect = document.getElementById('order-payment-filter');
            paymentSelect.value = getCookie('admin_orders_payment_filter') || 'paid_pending';
            paymentSelect.addEventListener('change', function() {
                setCookie('admin_orders_payment_filter', paymentSelect.value);
                applyOrderFilters();
            });

            const pickedBtn = document.getElementById('toggle-picked-orders-btn');
            const savedHidePicked = getCookie('admin_orders_hide_picked');
            const hidePicked = savedHidePicked !== null ? savedHidePicked === '1' : true;
            pickedBtn.dataset.active = hidePicked ? '1' : '0';
            pickedBtn.textContent = hidePicked ? 'Mostrar recogidos' : 'Ocultar recogidos';
            pickedBtn.addEventListener('click', function() {
                const nowHide = pickedBtn.dataset.active !== '1';
                pickedBtn.dataset.active = nowHide ? '1' : '0';
                pickedBtn.textContent = nowHide ? 'Mostrar recogidos' : 'Ocultar recogidos';
                setCookie('admin_orders_hide_picked', nowHide ? '1' : '0');
                applyOrderFilters();
            });

            applyOrderFilters();
        });

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

                                if (data.invoice_id && data.payment_status === 'paid') {
                                    html += buildFulfillmentControl(orderId, data.invoice_id, data.fulfillment_status || 'pending', data.fulfillment_note || '');
                                } else if (data.invoice_id) {
                                    html += '<div class="fulfillment-control fulfillment-control-disabled">Recogida disponible una vez pagado el pedido.</div>';
                                }

                                contentDiv.innerHTML = html;
                                if (data.invoice_id && data.payment_status === 'paid') {
                                    initFulfillmentControl(orderId);
                                }
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

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        function fulfillmentLabel(status) {
            return { pending: '⏳ Pendiente', partial: '🟡 Parcial', picked: '✅ Recogido' }[status] || '⏳ Pendiente';
        }

        // Recogida (pickup) control inside the expanded order-details panel.
        // Saved via fetch so it updates in place -- no full page reload,
        // no losing the expanded row / scroll position.
        function buildFulfillmentControl(orderId, invoiceId, status, note) {
            const options = ['pending', 'partial', 'picked'].map(function(value) {
                return '<option value="' + value + '"' + (value === status ? ' selected' : '') + '>' + fulfillmentLabel(value) + '</option>';
            }).join('');

            return '<div class="fulfillment-control">' +
                '<label for="fulfillment-select-' + orderId + '"><strong>Recogida:</strong></label> ' +
                '<select id="fulfillment-select-' + orderId + '">' + options + '</select> ' +
                '<button type="button" class="btn-edit" onclick="saveFulfillment(' + orderId + ', ' + invoiceId + ')">Guardar</button> ' +
                '<span id="fulfillment-saved-' + orderId + '" class="fulfillment-saved-note">Guardado ✓</span>' +
                '<textarea id="fulfillment-note-' + orderId + '" class="fulfillment-note-field" placeholder="Nota (ej. productos que faltan)" rows="2" style="display: ' + (status === 'partial' ? 'block' : 'none') + ';">' + escapeHtml(note) + '</textarea>' +
            '</div>';
        }

        function initFulfillmentControl(orderId) {
            const select = document.getElementById('fulfillment-select-' + orderId);
            const textarea = document.getElementById('fulfillment-note-' + orderId);
            select.addEventListener('change', function() {
                textarea.style.display = select.value === 'partial' ? 'block' : 'none';
            });
        }

        function saveFulfillment(orderId, invoiceId) {
            const select = document.getElementById('fulfillment-select-' + orderId);
            const textarea = document.getElementById('fulfillment-note-' + orderId);
            const status = select.value;
            const note = textarea.value;

            fetch('actions/update-fulfillment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'invoice_id=' + encodeURIComponent(invoiceId) + '&fulfillment_status=' + encodeURIComponent(status) + '&fulfillment_note=' + encodeURIComponent(note)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('fulfillment-badge-' + orderId).textContent = fulfillmentLabel(status);

                        // Keep the "Ocultar recogidos" filter in sync without a
                        // reload -- update the row's flag and re-run the
                        // combined filter (see applyOrderFilters() above).
                        const row = document.querySelector('tr.order-row[onclick*="toggleOrderDetails(' + orderId + ')"]');
                        if (row) {
                            row.setAttribute('data-pending-pickup', status === 'picked' ? '0' : '1');
                            applyOrderFilters();
                        }

                        const saved = document.getElementById('fulfillment-saved-' + orderId);
                        saved.classList.add('is-visible');
                        setTimeout(function() { saved.classList.remove('is-visible'); }, 2000);
                    } else {
                        alert('Error al guardar: ' + (data.error || 'Desconocido'));
                    }
                })
                .catch(function(error) {
                    console.error('Fetch error:', error);
                    alert('Error de conexión al guardar');
                });
        }
    </script>
</body>
</html>
