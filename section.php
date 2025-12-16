<?php
include 'includes/data-loader.php';
$appData = loadAppData();

// data loader
$sections = $appData['sections']; // This now holds the full section data array
$sectionsFull = $appData['sectionsFull']; // NEW: Get full section data
$sectionImages = $appData['sectionImages'];
$products = $appData['products'];

// Include 00.php for cart functionality - cookie
include 'assets/00.php';

// Validate section parameter
$sectionKey = $_GET['section'] ?? '';
if (!array_key_exists($sectionKey, $sections)) {
    header('Location: index.php');
    exit;
}

$sectionName = $sections[$sectionKey];
$sectionProducts = $products[$sectionKey] ?? [];
$pageTitle = "$sectionName - AlMercáu";

$sectionDescription = $sectionsFull[$sectionKey]['description'] ?? '';

//START HTML
include 'assets/head.php';
include 'assets/header.php';
?>

<div class="container">
    <a href="./" class="back-btn">&larr; Volver a la compra</a>
    <h2><?php echo htmlspecialchars($sectionName); ?></h2>

<?php
// Filter out hidden products
$visibleProducts = array_filter($sectionProducts, function($product) {
    return $product['visible'] ?? true; // Show if visible is true or not set
});

if (empty($visibleProducts)): ?>
    <div class="empty-state">
        <p>No hay productos disponibles en esta sección</p>
    </div>
<?php else: ?>
    <div class="product-grid">
        <?php foreach ($visibleProducts as $index => $product):
            // We need to find the original index for the product ID
            $originalIndex = array_search($product, $sectionProducts);
            $productId = getProductId($sectionKey, $originalIndex);
        ?>
            <div class="product-card">
                <a href="product.php?section=<?php echo $sectionKey; ?>&id=<?php echo $index; ?>" class="product-link">
                    <img src="<?php echo htmlspecialchars($product['image']); ?>"
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         class="product-image"
                         onerror="this.src='https://placehold.co/300x200/25D366/ffffff?text=Imagen+no+disponible'">
                </a>
                <div class="product-info">
                    <a href="product.php?section=<?php echo $sectionKey; ?>&id=<?php echo $index; ?>" class="product-link">
                        <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                    </a>
                    <div class="product-price">
                        <del class="greyed"><?php echo number_format($product['price2'], 2); ?>€</del> |
                        <?php echo number_format($product['price'], 2); ?>€
                    </div>
                    <div class="product-quantity">
                        <button class="quantity-btn" onclick="updateProductQuantity('<?php echo $productId; ?>', -1)">-</button>
                        <span class="quantity-value" id="quantity-<?php echo $productId; ?>">1</span>
                        <button class="quantity-btn" onclick="updateProductQuantity('<?php echo $productId; ?>', 1)">+</button>
                    </div>
                    <button class="btn" onclick="addToCartFromSection('<?php echo $productId; ?>', '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, '<?php echo $product['image']; ?>')">
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