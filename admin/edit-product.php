<?php
// admin/edit-product.php - Form interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

// Load database repositories
require_once dirname(__FILE__) . '/../includes/repositories/ProductRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/repositories/SectionRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/repositories/ProductOptionRepository-DB.php';
require_once dirname(__FILE__) . '/../includes/repositories/ProducerRepository-DB.php';

try {
    $productRepo = new ProductRepository();
    $sectionRepo = new SectionRepository();
    $optionRepo = new ProductOptionRepository();
    $producerRepo = new ProducerRepository();
    // Active producers only, in order, for the dropdown -- except the
    // product's *currently assigned* producer is always included even if
    // it's since been deactivated, so editing a product never silently
    // reassigns it away from a producer just because that producer was
    // hidden elsewhere.
    $producerOptions = [];
    foreach ($producerRepo->getAllActive() as $p) {
        $producerOptions[$p['id']] = $p['name'];
    }

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
    $options = [];
    $isEdit = false;
    $pageTitle = 'Añadir Producto';
    $buttonText = 'Crear Producto';

    if (!empty($product_id)) {
        $productData = $productRepo->getById($product_id);

        if ($productData) {
            $options = $optionRepo->getByProductId($product_id);
            $product = [
                'name' => $productData['name'],
                'ticket_name' => $productData['ticket_name'],
                'section' => $productData['section_key'],
                'price' => $productData['price_member'],
                'price2' => $productData['price_public'],
                'iva_rate' => $productData['iva_rate'],
                'image' => $productData['image'],
                'description' => $productData['description'],
                'visible' => $productData['visible'],
                'almost_out_of_stock' => $productData['almost_out_of_stock'],
                'producer_id' => $productData['producer_id']
            ];

            // Currently-assigned producer got deactivated since -- keep it
            // selectable/visible here so saving the form (unrelated fields)
            // doesn't quietly bump it back to 'Sin asignar'.
            if (!isset($producerOptions[$product['producer_id']])) {
                $currentProducer = $producerRepo->getById($product['producer_id']);
                if ($currentProducer) {
                    $producerOptions[$currentProducer['id']] = $currentProducer['name'] . ' (inactivo)';
                }
            }

            if ($isClone) {
                // Clone mode - create copy with modified name
                $product['name'] .= ' (Copia)';
                $product['ticket_name'] .= ' (Copia)';
                $product['visible'] = false;
                $pageTitle = 'Clonar Producto';
                $buttonText = 'Crear Copia';
                $isEdit = false;
                // Carry over options as new rows (no id) so they're inserted under the clone
                $options = array_map(function ($opt) {
                    $opt['id'] = null;
                    return $opt;
                }, $options);
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
            'ticket_name' => '',
            'section' => '',
            'price' => 0,
            'price2' => 0,
            'iva_rate' => '4',
            'image' => '',
            'description' => '',
            'visible' => true,
            'almost_out_of_stock' => false,
            'producer_id' => 1 // 'Sin asignar' placeholder (migration 021)
        ];
    }
    
    // Set current section for the dropdown
    $currentSection = $product['section'] ?? '';
    
} catch (Exception $e) {
    error_log("Error loading product: " . $e->getMessage());
    die("Error: No se pudo cargar el producto.");
}
$pageH1 = $pageTitle;
$pageTitle = $pageTitle . ' - AlMercáu';
$activeNav = 'products';
$backUrl = 'products.php';
include dirname(__FILE__) . '/partials/head.php';
?>
    <link rel="stylesheet" href="../assets/admin/forms.css?v=<?php echo APP_VERSION_SAFE; ?>">
    <script src="../assets/admin/form-validate.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
    <script src="../assets/admin/field-hint-toggle.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <?php if ($isClone): ?>
    <div class="clone-notice">
        <strong>⚠️ Clonando producto:</strong> Revisa los datos antes de activar la visibilidad.
    </div>
    <?php endif; ?>

    <div class="edit-form">
        <div id="form-error-summary" class="error-message" style="display:none;"></div>
        <form method="POST" action="actions/save-product.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="original_product_id" value="<?php echo $isEdit ? $product_id : ''; ?>">

            <div class="form-row">
                <div class="form-group">
                    <label>Sección:</label>
                    <select name="section" required>
                        <option value="">Seleccionar sección</option>
                        <?php foreach ($sections as $key => $name): ?>
                            <option value="<?php echo $key; ?>" <?php echo ($key === $currentSection) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" data-error-for="section"></span>
                </div>

                <div class="form-group">
                    <label>IVA:</label>
                    <select name="iva_rate" required>
                        <?php foreach (['4', '10', '21'] as $rate): ?>
                        <option value="<?php echo $rate; ?>" <?php echo ($product['iva_rate'] === $rate) ? 'selected' : ''; ?>><?php echo $rate; ?>%</option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" data-error-for="iva_rate"></span>
                </div>
            </div>

            <div class="form-group">
                <label>Productor:</label>
                <select name="producer_id">
                    <?php foreach ($producerOptions as $id => $label): ?>
                    <option value="<?php echo $id; ?>" <?php echo ($id == $product['producer_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="admin-tip" style="margin:4px 0 0;">¿Falta un productor? Añádelo en <a href="producers.php">Productores</a>.</small>
            </div>

            <div class="form-group">
                <label>Nombre del Producto (tienda):</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
                <span class="field-error" data-error-for="name"></span>
            </div>

            <div class="form-group">
                <label>Título en el ticket de compra (más corto):</label>
                <input type="text" name="ticket_name" value="<?php echo htmlspecialchars($product['ticket_name']); ?>" required>
                <span class="field-error" data-error-for="ticket_name"></span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Precio para Socios (€):</label>
                    <input type="number" step="0.05" name="price_member" value="<?php echo number_format($product['price'], 2); ?>" required>
                    <span class="field-error" data-error-for="price_member"></span>
                </div>

                <div class="form-group">
                    <label>Precio Público (€):</label>
                    <input type="number" step="0.05" name="price_public" value="<?php echo number_format($product['price2'], 2); ?>" required>
                    <span class="field-error" data-error-for="price_public"></span>
                </div>
            </div>

            <div class="form-divider"></div>

            <div class="form-row-checkboxes">
                <div class="form-check">
                    <label>
                        <input type="checkbox" name="visible" value="1" <?php echo ($product['visible'] ?? true) ? 'checked' : ''; ?>>
                        Producto visible en la tienda
                    </label>
                    <?php if ($isClone): ?>
                    <small class="clone-hint">Recomendado: revisar la copia antes de activar.</small>
                    <?php endif; ?>
                </div>

                <div class="form-check">
                    <label>
                        <input type="checkbox" name="almost_out_of_stock" value="1" <?php echo ($product['almost_out_of_stock'] ?? false) ? 'checked' : ''; ?>>
                        Fin de stock
                    </label>
                    <button type="button" class="field-hint-toggle" aria-expanded="false" aria-controls="hint-stock">?</button>
                    <div class="field-hint" id="hint-stock">Aparecerá también en la categoría "Fin de stock"</div>
                </div>
            </div>

            <div class="form-section-heading">Más detalles (opcional)</div>

            <div class="form-group form-group-optional">
                <div class="image-upload-row">
                    <?php if (!empty($product['image'])): ?>
                    <img src="../primgs/<?php echo htmlspecialchars($product['image']); ?>" alt="Imagen actual" class="current-image-thumb">
                    <?php endif; ?>
                    <div class="image-upload-controls">
                        <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
                        <button type="button" class="field-hint-toggle" aria-expanded="false" aria-controls="hint-image">?</button>
                        <div class="field-hint" id="hint-image">Mínimo 600x600px, máximo 2MB. JPG, PNG, GIF o WEBP. Se recorta al centro (cuadrado) y se ajusta a 800x800px.<?php if ($isClone && !empty($product['image'])): ?> Sube una nueva para esta copia.<?php endif; ?></div>
                    </div>
                </div>
                <span class="field-error" data-error-for="image"></span>
            </div>

            <div class="form-group form-group-optional">
                <label>Descripción:</label>
                <textarea name="description" placeholder="Descripción del producto..."><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>

            <div class="form-group form-group-optional">
                <label>Opciones (variaciones con precio propio, ej. peso/color)
                    <button type="button" class="field-hint-toggle" aria-expanded="false" aria-controls="hint-options">?</button>
                </label>
                <div class="field-hint" id="hint-options">De momento esto solo se guarda — la tienda todavía muestra el precio base de arriba (que sigue siendo obligatorio). Cuando conectemos las opciones a la tienda, su precio sustituirá al precio base.</div>
                <div id="options-list">
                    <?php foreach ($options as $opt): ?>
                    <div class="option-row" style="display:flex; gap:8px; align-items:center; margin-top:8px;">
                        <input type="hidden" name="option_id[]" value="<?php echo htmlspecialchars($opt['id'] ?? ''); ?>">
                        <input type="text" name="option_label[]" placeholder="Ej: 3kg" value="<?php echo htmlspecialchars($opt['label']); ?>" style="flex:2;">
                        <input type="number" step="0.05" name="option_price_member[]" placeholder="Precio socio" value="<?php echo number_format($opt['price_member'], 2); ?>" style="flex:1;">
                        <input type="number" step="0.05" name="option_price_public[]" placeholder="Precio público" value="<?php echo number_format($opt['price_public'], 2); ?>" style="flex:1;">
                        <button type="button" class="btn-cancel" onclick="this.closest('.option-row').remove()">✕</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="add-option-btn" style="margin-top:8px;">+ Añadir opción</button>
                <span class="field-error" data-error-for="options"></span>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn-save"><?php echo $buttonText; ?></button>
                <a href="products.php" class="btn-cancel">Cancelar</a>
            </div>
        </form>
    </div>

    <script>
    document.getElementById('add-option-btn').addEventListener('click', function() {
        const row = document.createElement('div');
        row.className = 'option-row';
        row.style.cssText = 'display:flex; gap:8px; align-items:center; margin-top:8px;';
        row.innerHTML = `
            <input type="hidden" name="option_id[]" value="">
            <input type="text" name="option_label[]" placeholder="Ej: 3kg" style="flex:2;">
            <input type="number" step="0.05" name="option_price_member[]" placeholder="Precio socio" style="flex:1;">
            <input type="number" step="0.05" name="option_price_public[]" placeholder="Precio público" style="flex:1;">
            <button type="button" class="btn-cancel" onclick="this.closest('.option-row').remove()">✕</button>
        `;
        document.getElementById('options-list').appendChild(row);
    });

    // Client-side validation: block submit until every error is resolved.
    // Mirrors actions/save-product.php's server-side checks (which stay in place as the real safety net).
    adminValidateForm(document.querySelector('.edit-form form'), [
        { name: 'section', message: 'Falta elegir una sección para el producto.' },
        { name: 'name', message: 'Falta el nombre del producto.' },
        { name: 'ticket_name', message: 'Falta el título para el ticket de compra.' },
        { name: 'price_member', type: 'number', message: 'El precio para socios debe ser mayor que 0€.' },
        { name: 'price_public', type: 'number', message: 'El precio público debe ser mayor que 0€.' }
    ], function(form) {
        // Each option row: either fully blank (ignored) or a complete label + both prices
        const errors = {};
        form.querySelectorAll('#options-list .option-row').forEach(function(row) {
            const label = row.querySelector('[name="option_label[]"]').value.trim();
            const pm = row.querySelector('[name="option_price_member[]"]').value;
            const pp = row.querySelector('[name="option_price_public[]"]').value;
            const anyFilled = label !== '' || pm !== '' || pp !== '';
            if (!anyFilled || errors.options) return;

            if (!label) {
                errors.options = 'Cada opción con precio necesita una etiqueta (ej: "3kg").';
            } else if (!(parseFloat(pm) > 0) || !(parseFloat(pp) > 0)) {
                errors.options = `La opción "${label}" necesita ambos precios (socio y público), mayores que 0.`;
            }
        });

        // Image: fast client-side checks (type/size) -- min-dimensions is
        // server-side only (needs to actually decode the file).
        const imageFile = form.querySelector('[name="image"]').files[0];
        if (imageFile) {
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(imageFile.type)) {
                errors.image = 'Formato no soportado (usa JPG, PNG, GIF o WEBP).';
            } else if (imageFile.size > 2 * 1024 * 1024) {
                errors.image = 'La imagen supera el tamaño máximo de 2MB.';
            }
        }

        return errors;
    });
    </script>
</body>
</html>