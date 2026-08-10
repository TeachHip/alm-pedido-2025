<?php
// admin/settings.php - Global settings management interface
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/repositories/SettingsRepository-DB.php';

try {
    $settingsRepo = new SettingsRepository();
    $showDualPricing = $settingsRepo->getBool('show_dual_pricing', false);
    $feeAmount = $settingsRepo->get('pedido_expres_fee_amount', '0');
    $feeLabel = $settingsRepo->get('pedido_expres_fee_label', '');
} catch (Exception $e) {
    error_log("Error loading settings: " . $e->getMessage());
    die("Error: No se pudieron cargar las configuraciones.");
}
$pageTitle = 'Configuración Global - AlMercáu';
$pageH1 = 'Configuración Global';
$activeNav = 'settings';
$successMessage = 'Configuración guardada correctamente';
include dirname(__FILE__) . '/partials/head.php';
?>
    <link rel="stylesheet" href="../assets/admin/forms.css?v=<?php echo APP_VERSION_SAFE; ?>">
    <script src="../assets/admin/toggle-indicator.js?v=<?php echo APP_VERSION_SAFE; ?>"></script>
<?php include dirname(__FILE__) . '/partials/header.php'; ?>

    <div class="edit-form">
        <div class="form-group">
            <label>Precios en la tienda</label>
            <p style="color:#666; font-size: 14px;">
                Si está desactivado, se muestra solo el precio público (un único precio por producto).
                Si está activado, se muestra el precio público tachado junto al precio de socia.
            </p>
            <a href="#" class="setting-toggle" onclick="return adminToggle('actions/save-settings.php?key=show_dual_pricing', this, {valueKey: 'value', trueLabel: 'Sí, mostrar 2 precios', falseLabel: 'No, solo precio público', errorMessage: 'Error al cambiar la configuración'});" style="text-decoration:none;">
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
        <form action="actions/save-fee-settings.php" method="POST">
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
