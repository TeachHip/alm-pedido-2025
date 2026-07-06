<?php
// Load database repository
require_once 'includes/ProductRepository-DB.php';
require_once 'includes/SectionRepository-DB.php';
require_once 'includes/SettingsRepository-DB.php';

// Include 00.php for cart functionality - cookie
include 'assets/00.php';

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
    $cartPrice = $showDualPricing ? $product['price_member'] : $product['price_public'];

    $pageTitle = "{$product['name']} - AlMercáu";
    
} catch (Exception $e) {
    error_log("Error loading product: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

//START HTML
include 'assets/head.php';
include 'assets/header.php';
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
            <!-- AI: dual/single price controlled by show_dual_pricing setting, see AI/CHANGELOG.md -->
            <div class="detail-price">
                <?php if ($showDualPricing && $product['price_public'] != $product['price_member']): ?>
                <del class="greyed"><?php echo number_format($product['price_public'], 2); ?>€</del> |
                <?php endif; ?>
                <?php echo number_format($showDualPricing ? $product['price_member'] : $product['price_public'], 2); ?>€
            </div>
            <p class="detail-description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            <div class="product-quantity">
                <button class="quantity-btn" onclick="updateProductQuantity('product-<?php echo $product['id']; ?>', -1)">-</button>
                <span class="quantity-value" id="quantity-product-<?php echo $product['id']; ?>">1</span>
                <button class="quantity-btn" onclick="updateProductQuantity('product-<?php echo $product['id']; ?>', 1)">+</button>
            </div>
            <button class="add-to-cart-btn" onclick="addToCartFromProduct('product-<?php echo $product['id']; ?>', '<?php echo addslashes($product['name']); ?>', <?php echo $cartPrice; /* AI: price_member or price_public depending on show_dual_pricing */ ?>, '<?php echo !empty($product['image']) ? 'primgs/' . addslashes($product['image']) : ''; ?>')">
                Al carro!
            </button>
        </div>
    </div>
</div>

<?php
    include 'assets/cart-component.php';
    include 'assets/footer.php';
?>
<script src="assets/script.js"></script>
</body>
</html>