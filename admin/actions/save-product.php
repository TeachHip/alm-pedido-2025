<?php
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

// Load database repositories
require_once dirname(__FILE__) . '/../../includes/repositories/ProductRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/repositories/SectionRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/repositories/ProductOptionRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/ImageUploadHelper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../products.php');
    exit;
}

// Confirm the uploaded file actually arrived via this HTTP request (not
// some other path PHP happens to be able to read) before ever touching it.
if (!empty($_FILES['image']['tmp_name']) && !is_uploaded_file($_FILES['image']['tmp_name'])) {
    header('Location: ../products.php?error=' . urlencode('Error al subir la imagen'));
    exit;
}

// Get form data
$original_product_id = $_POST['original_product_id'] ?? '';
$section_key = $_POST['section'] ?? '';
$name = trim($_POST['name'] ?? '');
$ticketName = trim($_POST['ticket_name'] ?? '');
$priceMember = floatval($_POST['price_member'] ?? 0);
$pricePublic = floatval($_POST['price_public'] ?? 0);
$ivaRate = $_POST['iva_rate'] ?? '';
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
if (empty($ticketName)) $missingFields[] = 'Título en el ticket de compra';
if ($priceMember <= 0) $missingFields[] = 'Precio Socio';
if ($pricePublic <= 0) $missingFields[] = 'Precio Público';
if (!in_array($ivaRate, ['4', '10', '21'], true)) $missingFields[] = 'IVA';

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

// Determine display_order, and (for updates) fetch the previously-stored
// image so we know what to replace/delete below.
$previousImage = null;
$active = 1;
if (!empty($original_product_id)) {
    // For updates, preserve the existing display_order and active/deprecated
    // state -- this form has no "active" field, so without this an edit
    // would silently un-deprecate an "antiguo" product.
    $existingProduct = $productRepo->getById($original_product_id);
    $displayOrder = $existingProduct['display_order'];
    $previousImage = $existingProduct['image'];
    $active = $existingProduct['active'];
} else {
    // For new products, get the max order in the section and add 1
    $sectionProducts = $productRepo->getBySectionId($section['id'], false);
    $displayOrder = count($sectionProducts) + 1;
}

    // Returns null if no new file was uploaded (keep whatever the product
    // already had -- a clone starts with no image since no products share
    // one, per how images are named/deleted below).
    $newImage = processListingImageUpload($_FILES['image'] ?? null, 'primgs');
    $image = $newImage ?: $previousImage;

    $productData = [
        'section_id' => $section['id'],
        'name' => $name,
        'ticket_name' => $ticketName,
        'price_member' => $priceMember,
        'price_public' => $pricePublic,
        'iva_rate' => $ivaRate,
        'image' => $image,
        'description' => $description,
        'display_order' => $displayOrder,
        'active' => $active,
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

    // Only remove the old file once the DB row has been safely updated to
    // point at the new one.
    if ($newImage && $previousImage && $previousImage !== $newImage) {
        deleteListingImage($previousImage, 'primgs');
    }

    $optionRepo->syncForProduct($productId, $options);

    header('Location: ../products.php?success=1');

} catch (Exception $e) {
    error_log("Error saving product: " . $e->getMessage());
    header('Location: ../products.php?error=' . urlencode($e->getMessage()));
}
exit;
?>