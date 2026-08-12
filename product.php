<?php
// Load database repository
require_once 'includes/repositories/ProductRepository-DB.php';
require_once 'includes/repositories/SectionRepository-DB.php';
require_once 'includes/repositories/SettingsRepository-DB.php';
require_once 'includes/repositories/ProductOptionRepository-DB.php';
require_once 'includes/PriceHelper.php';

// Include 00.php for cart functionality - cookie
include 'partials/00.php';

try {
    $productRepo = new ProductRepository();
    $sectionRepo = new SectionRepository();
    
    // Validate parameters
    $productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $product = $productRepo->getById($productId);
    
    // Check if product exists and is visible
    if (!$product || !$product['visible']) {
        header('Location: index.php');
        exit;
    }
    
    // Get section info
    $section = $sectionRepo->getById($product['section_id']);

    // AI: show_dual_pricing toggle (admin/settings.php), see AI/CHANGELOG.md
    $showDualPricing = (new SettingsRepository())->getBool('show_dual_pricing', false);
    $cartPrice = getCartPrice($product, $showDualPricing);

    // AI: product options (variants), see AI/CHANGELOG.md and includes/PriceHelper.php
    $options = (new ProductOptionRepository())->getByProductId($productId);
    $hasOptions = !empty($options);
    $cartLines = $hasOptions ? resolveCartLines($product, $options, $showDualPricing) : [];

    $pageTitle = "{$product['name']} - AlMercáu";
    
} catch (Exception $e) {
    error_log("Error loading product: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

//START HTML
include 'partials/head.php';
include 'partials/header.php';
?>

<div class="container">
    <a href="section.php?section=<?php echo $product['section_id']; ?>" class="back-btn">&larr; Volver a <?php echo htmlspecialchars($section['name']); ?></a>

    <div class="product-detail">
        <div style="position: relative;">
            <?php if ($product['almost_out_of_stock']): ?>
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #FFFF00; color: black; padding: 5px 8px 8px 8px; border-radius: 5px; font-size: 13px; font-weight: bold; z-index: 10; text-align: center; line-height: 1.3; white-space: nowrap;">
                ⚠️ Fin de stock
            </div>
            <?php endif; ?>
            <img src="<?php echo !empty($product['image']) ? 'primgs/' . htmlspecialchars($product['image']) : 'https://placehold.co/400x300/25D366/ffffff?text=Imagen+no+disponible'; ?>"
                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                 class="detail-image"
                 onerror="this.src='https://placehold.co/400x300/25D366/ffffff?text=Imagen+no+disponible'">
        </div>
        <div class="detail-info">
            <h2 class="detail-name"><?php echo htmlspecialchars($product['name']); ?></h2>
            <!-- AI: dual/single price controlled by show_dual_pricing setting, see AI/CHANGELOG.md and includes/PriceHelper.php -->
            <div class="detail-price" id="price-display-<?php echo $product['id']; ?>">
                <?php echo $hasOptions ? $cartLines[0]['priceHtml'] : renderPriceHtml($product, $showDualPricing); ?>
            </div>
            <p class="detail-description"><?php echo nl2br(htmlspecialchars($product['description'] ?? '')); ?></p>

            <?php if ($hasOptions): ?>
                <?php include 'partials/product-option-select.php'; ?>
            <?php endif; ?>

            <div class="product-quantity">
                <button class="quantity-btn" onclick="updateProductQuantity('product-<?php echo $product['id']; ?>', -1)">-</button>
                <span class="quantity-value" id="quantity-product-<?php echo $product['id']; ?>">1</span>
                <button class="quantity-btn" onclick="updateProductQuantity('product-<?php echo $product['id']; ?>', 1)">+</button>
            </div>
            <?php if ($hasOptions): ?>
            <button class="add-to-cart-btn" onclick="addToCartFromOptions('<?php echo $product['id']; ?>', 'option-select-<?php echo $product['id']; ?>', false)">
                Al carro!
            </button>
            <?php else: ?>
            <button class="add-to-cart-btn" onclick="addToCartFromProduct('product-<?php echo $product['id']; ?>', '<?php echo addslashes($product['name']); ?>', <?php echo $cartPrice; /* AI: price_member or price_public depending on show_dual_pricing */ ?>, '<?php echo !empty($product['image']) ? 'primgs/' . addslashes($product['image']) : ''; ?>')">
                Al carro!
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
    include 'partials/cart-component.php';
    include 'partials/footer.php';
?>
</body>
</html>