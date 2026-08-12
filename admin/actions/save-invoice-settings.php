<?php
// admin/actions/save-invoice-settings.php - Save ticket-de-compra + SMS settings
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/SettingsRepository-DB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../settings.php');
    exit;
}

$businessName = trim($_POST['business_name'] ?? '');
$businessNif = trim($_POST['business_nif'] ?? '');
$associationName = trim($_POST['association_name'] ?? '');
$businessAddress = trim($_POST['business_address'] ?? '');
$dueDaysRaw = trim($_POST['invoice_due_days'] ?? '');
$smsSenderAlias = trim($_POST['sms_sender_alias'] ?? '');

$errors = [];
if ($businessName === '') $errors[] = 'El nombre del negocio es obligatorio';
if ($dueDaysRaw === '' || !ctype_digit($dueDaysRaw) || (int) $dueDaysRaw < 1) {
    $errors[] = 'Los días para el pago deben ser un número entero mayor que 0';
}

if (!empty($errors)) {
    header('Location: ../settings.php?error=' . urlencode(implode(', ', $errors)));
    exit;
}

try {
    $settingsRepo = new SettingsRepository();
    $settingsRepo->set('business_name', $businessName);
    $settingsRepo->set('business_nif', $businessNif);
    $settingsRepo->set('association_name', $associationName);
    $settingsRepo->set('business_address', $businessAddress);
    $settingsRepo->set('invoice_due_days', (string) (int) $dueDaysRaw);
    $settingsRepo->set('sms_sender_alias', $smsSenderAlias);

    header('Location: ../settings.php?success=1');
} catch (Exception $e) {
    error_log("Error saving invoice settings: " . $e->getMessage());
    header('Location: ../settings.php?error=' . urlencode('Error al guardar la configuración'));
}
exit;
