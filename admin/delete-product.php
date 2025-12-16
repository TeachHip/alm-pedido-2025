<?php
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

// Load database repository
require_once dirname(__FILE__) . '/../includes/ProductRepository-DB.php';

$product_id = $_GET['product_id'] ?? '';

if (empty($product_id)) {
    header('Location: products.php');
    exit;
}

try {
    $productRepo = new ProductRepository();
    
    // Check if product exists
    $product = $productRepo->getById($product_id);
    if (!$product) {
        header('Location: products.php?error=Producto no encontrado');
        exit;
    }
    
    // Delete the product
    $result = $productRepo->delete($product_id);
    
    if ($result) {
        error_log("Deleted product ID: $product_id");
        header('Location: products.php?deleted=1');
    } else {
        header('Location: products.php?error=No se pudo eliminar el producto');
    }
    
} catch (Exception $e) {
    error_log("Error deleting product: " . $e->getMessage());
    header('Location: products.php?error=' . urlencode($e->getMessage()));
}
exit;
?>