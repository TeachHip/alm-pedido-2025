<?php
// admin/edit-product.php - Form interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

// Load database repositories
require_once dirname(__FILE__) . '/../includes/ProductRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/SectionRepository-DB.php';

try {
    $productRepo = new ProductRepository();
    $sectionRepo = new SectionRepository();
    
    // Get sections as associative array
    $sectionsArray = $sectionRepo->getAll();
    $sections = [];
    foreach ($sectionsArray as $section) {
        $sections[$section['key']] = $section['name'];
    }
    
    // Determine action: add, edit, or clone
    $product_id = $_GET['product_id'] ?? '';
    $isClone = isset($_GET['clone']);
    
    $product = null;
    $isEdit = false;
    $pageTitle = 'Añadir Producto';
    $buttonText = 'Crear Producto';
    
    if (!empty($product_id)) {
        $productData = $productRepo->getById($product_id);
        
        if ($productData) {
            $product = [
                'name' => $productData['name'],
                'section' => $productData['section_key'],
                'price' => $productData['price_member'],
                'price2' => $productData['price_public'],
                'image' => $productData['image'],
                'description' => $productData['description'],
                'visible' => $productData['visible']
            ];
            
            if ($isClone) {
                // Clone mode - create copy with modified name
                $product['name'] .= ' (Copia)';
                $product['visible'] = false;
                $pageTitle = 'Clonar Producto';
                $buttonText = 'Crear Copia';
                $isEdit = false;
            } else {
                // Edit mode
                $pageTitle = 'Editar Producto';
                $buttonText = 'Guardar Cambios';
                $isEdit = true;
            }
        }
    }
    
    // Add new product mode - defaults
    if (!$product) {
        $product = [
            'name' => '',
            'section' => '',
            'price' => 0,
            'price2' => 0,
            'image' => '',
            'description' => '',
            'visible' => true
        ];
    }
    
    // Set current section for the dropdown
    $currentSection = $product['section'] ?? '';
    
} catch (Exception $e) {
    error_log("Error loading product: " . $e->getMessage());
    die("Error: No se pudo cargar el producto.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - AlMercáu</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="admin-header">
        <h1><?php echo $pageTitle; ?></h1>
        <a href="products.php" class="logout-btn">← Volver</a>
    </div>

    <?php if ($isClone): ?>
    <div class="clone-notice">
        <strong>⚠️ Clonando producto:</strong> Revisa los datos antes de activar la visibilidad.
    </div>
    <?php endif; ?>

    <div class="edit-form">
        <form method="POST" action="save-product.php">
            <input type="hidden" name="original_product_id" value="<?php echo $isEdit ? $product_id : ''; ?>"> <!-- CHANGED: original_product_id -->

            <div class="form-group">
                <label>Sección:</label>
                <select name="section" required>
                    <option value="">Seleccionar sección</option>
                    <?php foreach ($sections as $key => $name): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($key === $currentSection) ? 'selected' : ''; ?>> <!-- CHANGED: $currentSection instead of $section -->
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Nombre del Producto:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Precio para Socios (€):</label>
                <input type="number" step="0.05" name="price_member" value="<?php echo number_format($product['price'], 2); ?>" required> <!-- CHANGED: price_member -->
            </div>

            <div class="form-group">
                <label>Precio Público (€):</label>
                <input type="number" step="0.05" name="price_public" value="<?php echo number_format($product['price2'], 2); ?>" required>
            </div>

            <div class="form-group">
                <label>Imagen (ruta):</label>
                <input type="text" name="image" value="<?php echo htmlspecialchars($product['image']); ?>" placeholder="primgs/imagen.jpg">
            </div>

            <div class="form-group">
                <label>Descripción:</label>
                <textarea name="description" placeholder="Descripción del producto..."><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label>
                    <input type="checkbox" name="visible" value="1" <?php echo ($product['visible'] ?? true) ? 'checked' : ''; ?>>
                    Producto visible en la tienda
                </label>
                <?php if ($isClone): ?>
                <small class="clone-hint">Recomendado: revisar la copia antes de activar.</small>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><?php echo $buttonText; ?></button>
                <a href="products.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>