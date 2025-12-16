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
    
    // Toggle visibility
    $result = $productRepo->toggleVisibility($product_id);
    
    if ($result) {
        error_log("Toggled visibility for product ID: $product_id");
    }
    
} catch (Exception $e) {
    error_log("Error toggling visibility: " . $e->getMessage());
    header('Location: products.php?error=' . urlencode($e->getMessage()));
    exit;
}

header('Location: products.php');
exit;
?>