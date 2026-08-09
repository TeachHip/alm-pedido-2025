<?php
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

// Load database repositories
require_once dirname(__FILE__) . '/../../includes/repositories/ProductRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/repositories/SectionRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/repositories/ProductOptionRepository-DB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../products.php');
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
$almostOutOfStock = isset($_POST['almost_out_of_stock']) ? 1 : 0;

// Product options (variants), see AI/CHANGELOG.md
$optionIds = $_POST['option_id'] ?? [];
$optionLabels = $_POST['option_label'] ?? [];
$optionPricesMember = $_POST['option_price_member'] ?? [];
$optionPricesPublic = $_POST['option_price_public'] ?? [];
$options = [];
foreach ($optionLabels as $i => $label) {
    $options[] = [
        'id' => $optionIds[$i] ?? null,
        'label' => $label,
        'price_member' => $optionPricesMember[$i] ?? 0,
        'price_public' => $optionPricesPublic[$i] ?? 0
    ];
}

// Validate required fields
$missingFields = [];
if (empty($section_key)) $missingFields[] = 'Sección';
if (empty($name)) $missingFields[] = 'Nombre';
if ($priceMember <= 0) $missingFields[] = 'Precio Socio';
if ($pricePublic <= 0) $missingFields[] = 'Precio Público';

if (!empty($missingFields)) {
    header('Location: ../products.php?error=' . urlencode('Faltan campos obligatorios: ' . implode(', ', $missingFields)));
    exit;
}

try {
    $productRepo = new ProductRepository();
    $sectionRepo = new SectionRepository();
    $optionRepo = new ProductOptionRepository();

    // Get section ID from key
    $section = $sectionRepo->getByKey($section_key);
    if (!$section) {
        header('Location: ../products.php?error=Invalid section');
        exit;
    }

// Determine display_order
if (!empty($original_product_id)) {
    // For updates, preserve the existing display_order
    $existingProduct = $productRepo->getById($original_product_id);
    $displayOrder = $existingProduct['display_order'];
} else {
    // For new products, get the max order in the section and add 1
    $sectionProducts = $productRepo->getBySectionId($section['id'], false);
    $displayOrder = count($sectionProducts) + 1;
}

    $productData = [
        'section_id' => $section['id'],
        'name' => $name,
        'price_member' => $priceMember,
        'price_public' => $pricePublic,
        'image' => $image,
        'description' => $description,
        'display_order' => $displayOrder,
        'active' => 1,
        'visible' => $visible,
        'almost_out_of_stock' => $almostOutOfStock
    ];

    if (!empty($original_product_id)) {
        // Update existing product
        $result = $productRepo->update($original_product_id, $productData);
        $productId = $original_product_id;
        error_log("Updated product ID: $original_product_id");
    } else {
        // Create new product
        $productId = $productRepo->create($productData);
        error_log("Created new product ID: $productId");
    }

    $optionRepo->syncForProduct($productId, $options);

    header('Location: ../products.php?success=1');

} catch (Exception $e) {
    error_log("Error saving product: " . $e->getMessage());
    header('Location: ../products.php?error=' . urlencode($e->getMessage()));
}
exit;
?>