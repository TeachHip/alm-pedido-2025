<?php
// admin/product-summary.php - Producer order-quantity report: how many
// units of each product to ask a producer for, once a Pedido Exprés/Grupo
// round's deadline passes. Only counts paid invoices (deliberately
// conservative -- see the 2026-08-26 plan). Relies on
// invoice_items.section_key (020_add_invoice_item_section_key.sql), a
// per-line snapshot since a single order can mix items from several
// sections at once.
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/repositories/InvoiceRepository-DB.php';

// Only these two sections have a real deadline/round concept -- same
// hardcoded pair InvoiceHelper.php's own deadline-matching uses.
$sectionOptions = [
    'flash' => 'Pedido Exprés',
    'pedido_g' => 'Pedido de Grupo',
];

$section = $_GET['section'] ?? 'flash';
if (!isset($sectionOptions[$section])) {
    $section = 'flash';
}
$fromDate = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$toDate = $_GET['to'] ?? date('Y-m-d');

try {
    $invoiceRepo = new InvoiceRepository();
    $totals = $invoiceRepo->getProductTotals($section, $fromDate, $toDate);
} catch (Exception $e) {
    error_log("Error loading product summary: " . $e->getMessage());
    die("Error: No se pudo cargar el resumen de productos.");
}

$totalUnits = array_sum(array_column($totals, 'total_quantity'));
$totalOrders = array_sum(array_column($totals, 'order_count'));

$pageTitle = 'Resumen productores - AlMercáu';
$pageH1 = '📦 Resumen para productores';
$activeNav = 'product-summary';
include dirname(__FILE__) . '/partials/head.php';
?>
    <link rel="stylesheet" href="../assets/admin/forms.css?v=<?php echo APP_VERSION_SAFE; ?>">
    <link rel="stylesheet" href="../assets/admin/product-summary.css?v=<?php echo APP_VERSION_SAFE; ?>">
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <form method="GET" class="product-summary-filters">
        <div class="form-group">
            <label for="section">Sección</label>
            <select id="section" name="section">
                <?php foreach ($sectionOptions as $key => $label): ?>
                <option value="<?php echo htmlspecialchars($key); ?>"<?php echo $key === $section ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="from">Desde</label>
            <input type="date" id="from" name="from" value="<?php echo htmlspecialchars($fromDate); ?>">
        </div>
        <div class="form-group">
            <label for="to">Hasta</label>
            <input type="date" id="to" name="to" value="<?php echo htmlspecialchars($toDate); ?>">
        </div>
        <div class="form-group">
            <button type="submit" class="btn-save">Ver resumen</button>
        </div>
    </form>
    <p class="admin-tip">💡 Solo se cuentan pedidos ya pagados.</p>

    <?php if (empty($totals)): ?>
    <div class="empty-state">
        <p>No hay pedidos pagados de <?php echo htmlspecialchars($sectionOptions[$section]); ?> entre estas fechas.</p>
    </div>
    <?php else: ?>
    <p class="admin-tip">
        <strong><?php echo count($totals); ?></strong> productos distintos,
        <strong><?php echo $totalUnits; ?></strong> unidades totales,
        <strong><?php echo $totalOrders; ?></strong> pedidos.
    </p>

    <div class="products-table">
        <div class="table-scroll">
        <table width="100%">
            <thead>
                <tr>
                    <th width="60%">Producto</th>
                    <th width="20%">Cantidad</th>
                    <th width="20%">Nº pedidos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($totals as $row): ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($row['product_name']); ?>
                        <?php if ($row['option_label']): ?> <small>(<?php echo htmlspecialchars($row['option_label']); ?>)</small><?php endif; ?>
                    </td>
                    <td><?php echo (int) $row['total_quantity']; ?></td>
                    <td><?php echo (int) $row['order_count']; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>
