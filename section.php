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
    $products = $productRepo->getBySectionVisible($sectionId);
    
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
                <a href="product.php?id=<?php echo $product['id']; ?>" class="product-link">
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
                        <del class="greyed"><?php echo number_format($product['price_public'], 2); ?>€</del> |
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