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

    // BEGIN ticket de compra / invoice feature settings -- if the invoice
    // feature is ever removed, this block and its matching HTML section
    // below (search "END ticket de compra") are the only things in this
    // file that need to go with it.
    $businessName = $settingsRepo->get('business_name', '');
    $businessNif = $settingsRepo->get('business_nif', '');
    $associationName = $settingsRepo->get('association_name', '');
    $businessAddress = $settingsRepo->get('business_address', '');
    $invoiceDueDays = $settingsRepo->get('invoice_due_days', '7');
    $smsSenderAlias = $settingsRepo->get('sms_sender_alias', '');
    // datetime-local inputs need "Y-m-d\TH:i" (no seconds); stored value is
    // "Y-m-d H:i:s" (same shape as invoices.due_date elsewhere).
    $deadlinePedidoExpres = str_replace(' ', 'T', substr($settingsRepo->get('deadline_pedido_expres', ''), 0, 16));
    $deadlinePedidoGrupo = str_replace(' ', 'T', substr($settingsRepo->get('deadline_pedido_grupo', ''), 0, 16));
    // END ticket de compra / invoice feature settings
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

    <!-- BEGIN ticket de compra / invoice feature settings block -- see the
         matching BEGIN/END markers in this file's PHP above. Also touches
         actions/save-invoice-settings.php, InvoiceRepository-DB.php,
         services/LabsMobileClient.php, and ticket.php if ever removed. -->
    <div class="edit-form" style="margin-top: 20px;">
        <form action="actions/save-invoice-settings.php" method="POST">
            <div class="form-group">
                <label>Ticket de compra y SMS</label>
                <p style="color:#666; font-size: 14px;">
                    Datos mostrados en el ticket de compra y remitente de los SMS con el enlace al ticket.
                </p>
            </div>
            <div class="form-group">
                <label for="business_name">Nombre del negocio</label>
                <input type="text" id="business_name" name="business_name" value="<?php echo htmlspecialchars($businessName); ?>" maxlength="255">
            </div>
            <div class="form-group">
                <label for="association_name">Nombre de la asociación</label>
                <input type="text" id="association_name" name="association_name" value="<?php echo htmlspecialchars($associationName); ?>" maxlength="255">
            </div>
            <div class="form-group">
                <label for="business_address">Dirección</label>
                <input type="text" id="business_address" name="business_address" value="<?php echo htmlspecialchars($businessAddress); ?>" maxlength="255">
            </div>
            <div class="form-group">
                <label for="business_nif">NIF</label>
                <input type="text" id="business_nif" name="business_nif" value="<?php echo htmlspecialchars($businessNif); ?>" maxlength="50">
            </div>
            <div class="form-group">
                <label for="invoice_due_days">Días para el pago (por defecto)</label>
                <input type="number" id="invoice_due_days" name="invoice_due_days" value="<?php echo htmlspecialchars($invoiceDueDays); ?>" min="1" step="1">
                <small>Se usa solo si el pedido no lleva ningún producto de Pedido Exprés ni Pedido de Grupo</small>
            </div>
            <div class="form-group">
                <label for="deadline_pedido_expres">Fecha límite de pago — Pedido Exprés</label>
                <input type="datetime-local" id="deadline_pedido_expres" name="deadline_pedido_expres" value="<?php echo htmlspecialchars($deadlinePedidoExpres); ?>">
                <small>Cualquier pedido con al menos un producto de esta sección debe pagarse antes de esta fecha/hora. Vacío = sin límite propio (usa "Días para el pago")</small>
            </div>
            <div class="form-group">
                <label for="deadline_pedido_grupo">Fecha límite de pago — Pedido de Grupo</label>
                <input type="datetime-local" id="deadline_pedido_grupo" name="deadline_pedido_grupo" value="<?php echo htmlspecialchars($deadlinePedidoGrupo); ?>">
                <small>Cualquier pedido con al menos un producto de esta sección debe pagarse antes de esta fecha/hora. Vacío = sin límite propio (usa "Días para el pago")</small>
            </div>
            <div class="form-group">
                <label for="sms_sender_alias">Remitente SMS</label>
                <input type="text" id="sms_sender_alias" name="sms_sender_alias" value="<?php echo htmlspecialchars($smsSenderAlias); ?>" maxlength="16">
                <small>Numérico mientras LabsMobile no apruebe el alias "ALMERCAU"</small>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn-save">💾 Guardar</button>
            </div>
        </form>
    </div>
    <!-- END ticket de compra / invoice feature settings block -->
</body>
</html>
