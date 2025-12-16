<?php
// Load database repository
require_once 'includes/ProductRepository-DB.php';
require_once 'includes/SectionRepository-DB.php';

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
        <img src="<?php echo !empty($product['image']) ? 'primgs/' . htmlspecialchars($product['image']) : 'https://placehold.co/400x300/25D366/ffffff?text=Imagen+no+disponible'; ?>"
             alt="<?php echo htmlspecialchars($product['name']); ?>"
             class="detail-image"
             onerror="this.src='https://placehold.co/400x300/25D366/ffffff?text=Imagen+no+disponible'">
        <div class="detail-info">
            <h2 class="detail-name"><?php echo htmlspecialchars($product['name']); ?></h2>
            <div class="detail-price">
                 <del class="greyed"><?php echo number_format($product['price_public'], 2); ?>€</del> |
                        <?php echo number_format($product['price_member'], 2); ?>€
            </div>
            <p class="detail-description"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            <div class="product-quantity">
                <button class="quantity-btn" onclick="updateProductQuantity('product-<?php echo $product['id']; ?>', -1)">-</button>
                <span class="quantity-value" id="quantity-product-<?php echo $product['id']; ?>">1</span>
                <button class="quantity-btn" onclick="updateProductQuantity('product-<?php echo $product['id']; ?>', 1)">+</button>
            </div>
            <button class="add-to-cart-btn" onclick="addToCartFromProduct('product-<?php echo $product['id']; ?>', '<?php echo addslashes($product['name']); ?>', <?php echo $product['price_member']; ?>, '<?php echo !empty($product['image']) ? 'primgs/' . addslashes($product['image']) : ''; ?>')">
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