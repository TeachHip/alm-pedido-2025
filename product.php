<?php
include 'includes/data-loader.php';
$appData = loadAppData();

// Use the data as before
$sections = $appData['sections'];
$products = $appData['products'];

// Include 00.php for cart functionality - cookie
include 'assets/00.php';

// Validate parameters
$sectionKey = $_GET['section'] ?? '';
$productIndex = isset($_GET['id']) ? (int)$_GET['id'] : -1;

// Validates?
if (!array_key_exists($sectionKey, $sections) ||
    !isset($products[$sectionKey][$productIndex]) ||
    (isset($products[$sectionKey][$productIndex]['visible']) &&
     !$products[$sectionKey][$productIndex]['visible'])) {
    header('Location: index.php');
    exit;
}

$product = $products[$sectionKey][$productIndex];
$productId = getProductId($sectionKey, $productIndex);
$pageTitle = "{$product['name']} - AlMercáu";

//START HTML
include 'assets/head.php';
include 'assets/header.php';
?>

<div class="container">
    <a href="section.php?section=<?php echo $sectionKey; ?>" class="back-btn">&larr; Volver a <?php echo htmlspecialchars($sections[$sectionKey]); ?></a>

    <div class="product-detail">
        <img src="<?php echo htmlspecialchars($product['image']); ?>"
             alt="<?php echo htmlspecialchars($product['name']); ?>"
             class="detail-image"
             onerror="this.src='https://placehold.co/400x300/25D366/ffffff?text=Imagen+no+disponible'">
        <div class="detail-info">
            <h2 class="detail-name"><?php echo htmlspecialchars($product['name']); ?></h2>
            <div class="detail-price">
                 <del class="greyed"><?php echo number_format($product['price2'], 2); ?>€</del> |
                        <?php echo number_format($product['price'], 2); ?>€
            </div>
            <p class="detail-description"><?= str_replace('\n',"<br>", $product['description']); ?></p>
            <div class="product-quantity">
                <button class="quantity-btn" onclick="updateProductQuantity('<?php echo $productId; ?>', -1)">-</button>
                <span class="quantity-value" id="quantity-<?php echo $productId; ?>">1</span>
                <button class="quantity-btn" onclick="updateProductQuantity('<?php echo $productId; ?>', 1)">+</button>
            </div>
            <button class="add-to-cart-btn" onclick="addToCartFromProduct('<?php echo $productId; ?>', '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>, '<?php echo $product['image']; ?>')">
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