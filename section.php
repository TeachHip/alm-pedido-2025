<?php
// Load database repositories
require_once 'includes/SectionRepository-DB.php';
require_once 'includes/ProductRepository-DB.php';

// Include 00.php for cart functionality - cookie
include 'assets/00.php';

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

} catch (Exception $e) {
    error_log("Error loading section: " . $e->getMessage());
    header('Location: index.php');
    exit;
}

//START HTML
include 'assets/head.php';
include 'assets/header.php';
?>

<div class="container">
    <a href="./" class="back-btn">&larr; Volver a la compra</a>
    <h2><?php echo htmlspecialchars($sectionName); ?></h2>

<?php if (empty($products)): ?>
    <div class="empty-state">
        <p>No hay productos disponibles en esta sección</p>
    </div>
<?php else: ?>
    <div class="product-grid">
        <?php foreach ($products as $product): ?>
            <div class="product-card">
                <a href="product.php?id=<?php echo $product['id']; ?>" class="product-link" style="position: relative; display: block;">
                    <?php if ($product['almost_out_of_stock']): ?>
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #FFFF00; color: black; padding: 5px 8px 8px 8px; border-radius: 5px; font-size: 13px; font-weight: bold; z-index: 10; text-align: center; line-height: 1.3; white-space: nowrap;">
                        ⚠️ Fin de stock
                    </div>
                    <?php endif; ?>
                    <img src="<?php echo !empty($product['image']) ? 'primgs/' . htmlspecialchars($product['image']) : 'https://placehold.co/300x200/25D366/ffffff?text=Imagen+no+disponible'; ?>"
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         class="product-image"
                         onerror="this.src='https://placehold.co/300x200/25D366/ffffff?text=Imagen+no+disponible'">
                </a>
                <div class="product-info">
                    <a href="product.php?id=<?php echo $product['id']; ?>" class="product-link">
                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                    </a>
                    <div class="product-price">
					<?php
						if ($product['price_public'] != $product['price_member']) {
					?>
						<del class="greyed"><?php echo number_format($product['price_public'], 2); ?>€</del> |
					<?php 
						}							
					?>
 
                        <?php echo number_format($product['price_member'], 2); ?>€
                    </div>
                    <div class="product-quantity">
                        <button class="quantity-btn" onclick="updateProductQuantity('product-<?php echo $product['id']; ?>', -1)">-</button>
                        <span class="quantity-value" id="quantity-product-<?php echo $product['id']; ?>">1</span>
                        <button class="quantity-btn" onclick="updateProductQuantity('product-<?php echo $product['id']; ?>', 1)">+</button>
                    </div>
                    <button class="btn" onclick="addToCartFromSection('product-<?php echo $product['id']; ?>', '<?php echo addslashes($product['name']); ?>', <?php echo $product['price_member']; ?>, '<?php echo !empty($product['image']) ? 'primgs/' . addslashes($product['image']) : ''; ?>')">
                        Al carro!
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>



<?php if (!empty($sectionDescription)): ?>
        <div class="container page-desc">
            <p><?php echo nl2br(htmlspecialchars($sectionDescription)); ?></p>
        </div>
    <?php endif; ?>



<?php
    include 'assets/cart-component.php';
    include 'assets/footer.php';
?>
<script src="assets/script.js"></script>
</body>
</html>
</html>