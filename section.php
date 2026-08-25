<?php
require_once 'includes/maintenance.php';
enforceMaintenanceMode();

// Load database repositories
require_once 'includes/repositories/SectionRepository-DB.php';
require_once 'includes/repositories/ProductRepository-DB.php';
require_once 'includes/repositories/SettingsRepository-DB.php';
require_once 'includes/repositories/ProductOptionRepository-DB.php';
require_once 'includes/PriceHelper.php';

// Include 00.php for cart functionality - cookie
include 'partials/00.php';

try {
    $sectionRepo = new SectionRepository();
    $productRepo = new ProductRepository();

    // Validate section parameter
    $sectionId = isset($_GET['section']) ? (int)$_GET['section'] : 0;
    $section = $sectionRepo->getById($sectionId);

    if (!$section || !$section['visible'] || !$section['active']) {
        header('Location: index.php');
        exit;
    }

    // Get visible products for this section
    // Special case: 'fin_stock' shows products with almost_out_of_stock flag
    if ($section['key'] === 'fin_stock') {
        $products = $productRepo->getBySectionKey('fin_stock', true);
    } else {
        $products = $productRepo->getBySectionVisible($sectionId);
    }

    $sectionName = $section['name'];
    $sectionDescription = $section['description'] ?? '';
    $pageTitle = "$sectionName - AlMercáu";

    // show_dual_pricing toggle (admin/settings.php)
    $settingsRepo = new SettingsRepository();
    $showDualPricing = $settingsRepo->getBool('show_dual_pricing', false);

    // Pedido Exprés / Pedido de Grupo are the only sections with a fixed,
    // admin-set payment deadline (see InvoiceHelper::createInvoiceFromCart(),
    // matched by section key -- stable even if the name is edited later).
    $sectionDeadlineSettingKey = ['flash' => 'deadline_pedido_expres', 'pedido_g' => 'deadline_pedido_grupo'][$section['key']] ?? null;
    $sectionDeadline = $sectionDeadlineSettingKey ? $settingsRepo->get($sectionDeadlineSettingKey, '') : '';

    // Product options (variants), batch-fetched to avoid N+1 queries -- see includes/PriceHelper.php
    $optionsByProduct = (new ProductOptionRepository())->getByProductIds(array_column($products, 'id'));

    // Pedido Expres cart fee footline
    $pedidoExpresFeeAmount = (float) $settingsRepo->get('pedido_expres_fee_amount', '0');
    $pedidoExpresFeeLabel = $settingsRepo->get('pedido_expres_fee_label', '');

} catch (Exception $e) {
    error_log("Error loading section: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

//START HTML
include 'partials/head.php';
include 'partials/header.php';
?>

<div class="container">
    <a href="./" class="back-btn">&larr; Volver a la compra</a>
    <div class="section-title-row">
        <h2><?php echo htmlspecialchars($sectionName); ?></h2>
        <?php if ($sectionDeadline): ?>
        <span class="section-deadline-note">Fecha límite de pago: <?php echo date('d/m/Y H:i', strtotime($sectionDeadline)); ?></span>
        <?php endif; ?>
    </div>

<?php if (empty($products)): ?>
    <div class="empty-state">
        <p>No hay productos disponibles en esta sección</p>
    </div>
<?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $product):
            $options = $optionsByProduct[$product['id']] ?? [];
            $hasOptions = !empty($options);
            $cartLines = $hasOptions ? resolveCartLines($product, $options, $showDualPricing) : [];
        ?>
            <div class="product-card">
                <a href="product.php?id=<?php echo $product['id']; ?>" class="product-link" style="position: relative; display: block;">
                    <?php include 'partials/out-of-stock-badge.php'; ?>
                    <img src="<?php echo !empty($product['image']) ? 'primgs/' . htmlspecialchars($product['image']) : 'https://placehold.co/300x200/25D366/ffffff?text=Imagen+no+disponible'; ?>"
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         class="product-image"
                         onerror="this.src='https://placehold.co/300x200/25D366/ffffff?text=Imagen+no+disponible'">
                </a>
                <div class="product-info">
                    <a href="product.php?id=<?php echo $product['id']; ?>" class="product-link">
                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                    </a>
                    <!-- Dual/single price controlled by show_dual_pricing setting, see includes/PriceHelper.php -->
                    <div class="product-price" id="price-display-<?php echo $product['id']; ?>">
                        <?php echo $hasOptions ? $cartLines[0]['priceHtml'] : renderPriceHtml($product, $showDualPricing); ?>
                    </div>

                    <?php if ($hasOptions): ?>
                        <?php include 'partials/product-option-select.php'; ?>
                    <?php endif; ?>

                    <div class="product-quantity">
                        <button class="quantity-btn" onclick="updateProductQuantity('product-<?php echo $product['id']; ?>', -1)">-</button>
                        <span class="quantity-value" id="quantity-product-<?php echo $product['id']; ?>">1</span>
                        <button class="quantity-btn" onclick="updateProductQuantity('product-<?php echo $product['id']; ?>', 1)">+</button>
                    </div>
                    <?php if ($hasOptions): ?>
                    <button class="btn" onclick="addToCartFromOptions('<?php echo $product['id']; ?>', 'option-select-<?php echo $product['id']; ?>', true)">
                        Al carro!
                    </button>
                    <?php else: ?>
                    <button class="btn" onclick="addToCartFromSection('product-<?php echo $product['id']; ?>', '<?php echo addslashes($product['name']); ?>', <?php echo getCartPrice($product, $showDualPricing); ?>, '<?php echo !empty($product['image']) ? 'primgs/' . addslashes($product['image']) : ''; ?>')">
                        Al carro!
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>



        <div class="container page-desc">
<?php if (!empty($sectionDescription)): ?>
            <p><?php echo nl2br(htmlspecialchars($sectionDescription)); ?></p>

    <?php endif; ?>

    <!-- Pedido Expres cart fee footline -->
    <?php if ($section['key'] === 'flash' && $pedidoExpresFeeAmount > 0): ?>

            <p>⚠️ <strong><?php echo htmlspecialchars($pedidoExpresFeeLabel); ?></strong>: <?php echo number_format($pedidoExpresFeeAmount, 2); ?>€ (cuota por pedido, no por producto).</p>
    <?php endif; ?>
        </div>



<?php
    include 'partials/cart-component.php';
    include 'partials/footer.php';
?>
</body>
</html>
</html>