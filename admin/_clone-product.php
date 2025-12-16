<?php
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

include dirname(__FILE__) . '/../includes/data-loader.php';
$appData = loadAppData();
$products = $appData['products'];
$sections = $appData['sections'];

// Get product to clone
$section = $_GET['section'] ?? '';
$productId = $_GET['id'] ?? '';

if (empty($section) || $productId === '' || !isset($products[$section][$productId])) {
    header('Location: products.php');
    exit;
}

$originalProduct = $products[$section][$productId];

// Create cloned product data (remove ID, append "Copia" to name)
$clonedProduct = [
    'name' => $originalProduct['name'] . ' (Copia)',
    'price' => $originalProduct['price'],
    'image' => $originalProduct['image'],
    'description' => $originalProduct['description'],
    'visible' => false  // Start with hidden to avoid accidental duplicates
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clonar Producto - AlMercáu</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        .edit-form { background: white; padding: 20px; border-radius: 10px; max-width: 600px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, textarea, select { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px; }
        textarea { height: 100px; }
        .form-actions { margin-top: 20px; text-align: center; }
        .btn-save { background: #6f42c1; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
        .btn-cancel { background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px; }
        .clone-notice { background: #e7f3ff; color: #004085; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="admin-header">
        <h1>Clonar Producto</h1>
        <a href="products.php" class="logout-btn">← Volver</a>
    </div>

    <div class="edit-form">
        <div class="clone-notice">
            <strong>⚠️ Clonando:</strong> <?php echo htmlspecialchars($originalProduct['name']); ?><br>
            <small>El nuevo producto empezará oculto. Revísalo y actívalo cuando esté listo.</small>
        </div>

        <form method="POST" action="save-product.php">
            <input type="hidden" name="original_section" value="">
            <input type="hidden" name="original_id" value="">

            <div class="form-group">
                <label>Sección:</label>
                <select name="section" required>
                    <option value="">Seleccionar sección</option>
                    <?php foreach ($sections as $key => $name): ?>
                        <option value="<?php echo $key; ?>" <?php echo ($key === $section) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Nombre del Producto:</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($clonedProduct['name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Precio para Socios (€):</label>
                <input type="number" step="0.01" name="price_member" value="<?php echo $clonedProduct['price']; ?>" required>
            </div>

            <div class="form-group">
                <label>Precio Público (€):</label>
                <input type="number" step="0.01" name="price_public" value="<?php echo number_format($clonedProduct['price'] * 1.2, 2); ?>" required>
            </div>

            <div class="form-group">
                <label>Imagen (ruta):</label>
                <input type="text" name="image" value="<?php echo htmlspecialchars($clonedProduct['image']); ?>" placeholder="primgs/imagen.jpg">
            </div>

            <div class="form-group">
                <label>Descripción:</label>
                <textarea name="description" placeholder="Descripción del producto..."><?php echo htmlspecialchars($clonedProduct['description']); ?></textarea>
            </div>

            <div class="form-group">
    <label>
        <input type="checkbox" name="visible" value="1" <?php echo $clonedProduct['visible'] ? 'checked' : ''; ?>>
        Producto visible en la tienda
    </label>
    <small style="color: #ccc;">Recomendado: dejar desactivado inicialmente para revisar la copia.</small>
</div>

            <div class="form-actions">
                <button type="submit" class="btn-save">Crear Copia del Producto</button>
                <a href="products.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </div>
</body>
</html>