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
$deadlinePedidoExpresRaw = trim($_POST['deadline_pedido_expres'] ?? '');
$deadlinePedidoGrupoRaw = trim($_POST['deadline_pedido_grupo'] ?? '');

$errors = [];
if ($businessName === '') $errors[] = 'El nombre del negocio es obligatorio';
if ($dueDaysRaw === '' || !ctype_digit($dueDaysRaw) || (int) $dueDaysRaw < 1) {
    $errors[] = 'Los días para el pago deben ser un número entero mayor que 0';
}
// datetime-local posts "Y-m-d\TH:i" (or empty); stored as "Y-m-d H:i:s"
// (same shape as invoices.due_date), empty = no section-specific deadline.
foreach (['deadline_pedido_expres' => $deadlinePedidoExpresRaw, 'deadline_pedido_grupo' => $deadlinePedidoGrupoRaw] as $field => $raw) {
    if ($raw !== '' && !DateTime::createFromFormat('Y-m-d\TH:i', $raw)) {
        $errors[] = 'La fecha límite indicada no es válida';
    }
}

if (!empty($errors)) {
    header('Location: ../settings.php?error=' . urlencode(implode(', ', $errors)));
    exit;
}

$deadlinePedidoExpres = $deadlinePedidoExpresRaw !== '' ? str_replace('T', ' ', $deadlinePedidoExpresRaw) . ':00' : '';
$deadlinePedidoGrupo = $deadlinePedidoGrupoRaw !== '' ? str_replace('T', ' ', $deadlinePedidoGrupoRaw) . ':00' : '';

try {
    $settingsRepo = new SettingsRepository();
    $settingsRepo->set('business_name', $businessName);
    $settingsRepo->set('business_nif', $businessNif);
    $settingsRepo->set('association_name', $associationName);
    $settingsRepo->set('business_address', $businessAddress);
    $settingsRepo->set('invoice_due_days', (string) (int) $dueDaysRaw);
    $settingsRepo->set('sms_sender_alias', $smsSenderAlias);
    $settingsRepo->set('deadline_pedido_expres', $deadlinePedidoExpres);
    $settingsRepo->set('deadline_pedido_grupo', $deadlinePedidoGrupo);

    header('Location: ../settings.php?success=1');
} catch (Exception $e) {
    error_log("Error saving invoice settings: " . $e->getMessage());
    header('Location: ../settings.php?error=' . urlencode('Error al guardar la configuración'));
}
exit;
