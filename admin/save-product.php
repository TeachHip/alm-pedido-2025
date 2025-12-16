<?php
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

// Load database repositories
require_once dirname(__FILE__) . '/../includes/ProductRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/SectionRepository-DB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: products.php');
    exit;
}

// Get form data
$original_product_id = $_POST['original_product_id'] ?? '';
$section_key = $_POST['section'] ?? '';
$name = trim($_POST['name'] ?? '');
$priceMember = floatval($_POST['price_member'] ?? 0);
$pricePublic = floatval($_POST['price_public'] ?? 0);
$image = trim($_POST['image'] ?? '');
$description = trim($_POST['description'] ?? '');
$visible = isset($_POST['visible']) ? 1 : 0;

// Validate required fields
if (empty($section_key) || empty($name) || $priceMember <= 0 || $pricePublic <= 0) {
    header('Location: products.php?error=Missing required fields');
    exit;
}

try {
    $productRepo = new ProductRepository();
    $sectionRepo = new SectionRepository();
    
    // Get section ID from key
    $section = $sectionRepo->getByKey($section_key);
    if (!$section) {
        header('Location: products.php?error=Invalid section');
        exit;
    }
    
    $productData = [
        'section_id' => $section['id'],
        'name' => $name,
        'price_member' => $priceMember,
        'price_public' => $pricePublic,
        'image' => $image,
        'description' => $description,
        'display_order' => 1,
        'active' => 1,
        'visible' => $visible,
        'almost_out_of_stock' => 0
    ];
    
    if (!empty($original_product_id)) {
        // Update existing product
        $result = $productRepo->update($original_product_id, $productData);
        error_log("Updated product ID: $original_product_id");
    } else {
        // Create new product
        $newId = $productRepo->create($productData);
        error_log("Created new product ID: $newId");
    }
    
    header('Location: products.php?success=1');
    
} catch (Exception $e) {
    error_log("Error saving product: " . $e->getMessage());
    header('Location: products.php?error=' . urlencode($e->getMessage()));
}
exit;
?>