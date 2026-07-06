<?php
// admin/save-fee-settings.php - Save the Pedido Expres cart fee (amount + label)
include dirname(__FILE__) . '/../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../includes/SettingsRepository-DB.php';

$errors = [];

$amountRaw = trim($_POST['fee_amount'] ?? '');
$label = trim($_POST['fee_label'] ?? '');

if ($amountRaw === '' || !is_numeric($amountRaw) || (float)$amountRaw < 0) {
    $errors[] = "El importe debe ser un número igual o mayor que 0";
}

if ((float)$amountRaw > 0 && $label === '') {
    $errors[] = "El texto a mostrar es obligatorio si el importe es mayor que 0";
}

if (!empty($errors)) {
    header("Location: settings.php?error=" . urlencode(implode(', ', $errors)));
    exit;
}

try {
    $settingsRepo = new SettingsRepository();
    $settingsRepo->set('pedido_expres_fee_amount', number_format((float)$amountRaw, 2, '.', ''));
    $settingsRepo->set('pedido_expres_fee_label', $label);

    header("Location: settings.php?success=1");
    exit;
} catch (Exception $e) {
    error_log("Error saving fee settings: " . $e->getMessage());
    header("Location: settings.php?error=" . urlencode("Error al guardar la configuración"));
    exit;
}
