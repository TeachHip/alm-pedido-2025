<?php
// admin/settings.php - Global settings management interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/SettingsRepository-DB.php';

try {
    $settingsRepo = new SettingsRepository();
    $showDualPricing = $settingsRepo->getBool('show_dual_pricing', false);
    $feeAmount = $settingsRepo->get('pedido_expres_fee_amount', '0');
    $feeLabel = $settingsRepo->get('pedido_expres_fee_label', '');
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
    die("Error: No se pudieron cargar las configuraciones.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración Global - AlMercáu</title>
    <link rel="stylesheet" href="styles.css">
    <script>
    function toggleSetting(key, element) {
        fetch('save-settings.php?key=' + key)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const indicator = element.querySelector('.visible-indicator, .hidden-indicator');
                    const text = element.querySelector('small');

                    if (data.value) {
                        indicator.classList.remove('hidden-indicator');
                        indicator.classList.add('visible-indicator');
                        indicator.textContent = '✓';
                        text.textContent = 'Sí, mostrar 2 precios';
                    } else {
                        indicator.classList.remove('visible-indicator');
                        indicator.classList.add('hidden-indicator');
                        indicator.textContent = '✗';
                        text.textContent = 'No, solo precio público';
                    }
                } else {
                    alert('Error al cambiar la configuración');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al cambiar la configuración');
            });
        return false;
    }
    </script>
</head>
<body>
    <div class="admin-header">
        <h1>Configuración Global</h1>
        <div>
            <a href="index.php" class="logout-btn">← Volver</a>
            <a href="logout.php" class="logout-btn">Cerrar Sesión</a>
        </div>
    </div>

    <?php if (isset($_GET['success'])): ?>
    <div class="success-message">
        ✅ Configuración guardada correctamente
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="error-message">
        ❌ <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
    <?php endif; ?>

    <div class="edit-form">
        <div class="form-group">
            <label>Precios en la tienda</label>
            <p style="color:#666; font-size: 14px;">
                Si está desactivado, se muestra solo el precio público (un único precio por producto).
                Si está activado, se muestra el precio público tachado junto al precio de socia.
            </p>
            <a href="#" class="setting-toggle" onclick="return toggleSetting('show_dual_pricing', this);" style="text-decoration:none;">
                <?php if ($showDualPricing): ?>
                <span class="visible-indicator">✓</span>
                <br><small>Sí, mostrar 2 precios</small>
                <?php else: ?>
                <span class="hidden-indicator">✗</span>
                <br><small>No, solo precio público</small>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- AI: Pedido Expres cart fee, see AI/CHANGELOG.md -->
    <div class="edit-form" style="margin-top: 20px;">
        <form action="save-fee-settings.php" method="POST">
            <div class="form-group">
                <label>Cargo fijo por carrito con producto de "Pedido Exprés"</label>
                <p style="color:#666; font-size: 14px;">
                    Se cobra una sola vez por carrito (no por producto/unidad) cuando el carrito contiene
                    al menos un producto de la sección "Pedido Exprés". Importe 0 = desactivado.
                </p>
            </div>
            <div class="form-group">
                <label for="fee_amount">Importe (€)</label>
                <input type="number" id="fee_amount" name="fee_amount" value="<?php echo htmlspecialchars($feeAmount); ?>" min="0" step="0.01">
            </div>
            <div class="form-group">
                <label for="fee_label">Texto a mostrar</label>
                <input type="text" id="fee_label" name="fee_label" value="<?php echo htmlspecialchars($feeLabel); ?>" maxlength="255">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save">💾 Guardar</button>
            </div>
        </form>
    </div>
</body>
</html>
