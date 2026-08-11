<?php
// admin/actions/send-invoice-sms.php - Manually trigger the invoice SMS
// (Stage 1: manual button; Stage 2 automates this from save-cart.php).
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/InvoiceRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/repositories/MemberRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/repositories/SettingsRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/services/LabsMobileClient.php';

$invoiceId = (int) ($_GET['invoice_id'] ?? 0);

$invoiceRepo = new InvoiceRepository();
$invoice = $invoiceRepo->findById($invoiceId);

if (!$invoice) {
    header('Location: ../orders.php?error=' . urlencode('Ticket no encontrado'));
    exit;
}

$memberRepo = new MemberRepository();
$member = $memberRepo->findById($invoice['member_id']);

if (!$member) {
    header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&error=' . urlencode('Miembro no encontrado'));
    exit;
}

$apiKeysFile = dirname(__FILE__) . '/../../includes/config/api-keys-DB.php';
if (!file_exists($apiKeysFile)) {
    header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&error=' . urlencode('Falta configurar includes/config/api-keys-DB.php'));
    exit;
}
require_once $apiKeysFile;

if (LABSMOBILE_USERNAME === '' || LABSMOBILE_TOKEN === '') {
    header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&error=' . urlencode('Credenciales de LabsMobile sin configurar todavía'));
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'almercau.org';
$basePath = rtrim(str_replace('/admin/actions', '', dirname($_SERVER['PHP_SELF'])), '/');
$invoiceUrl = "$scheme://$host$basePath/ticket.php?token=" . $invoice['token'];

$settingsRepo = new SettingsRepository();
$senderAlias = $settingsRepo->get('sms_sender_alias', '');

$message = "AlMercáu: tu ticket de compra " . $invoice['ticket_number'] . " (" . number_format($invoice['total_amount'], 2) . "€) esta listo: " . $invoiceUrl;

try {
    $client = new LabsMobileClient(LABSMOBILE_USERNAME, LABSMOBILE_TOKEN);
    $result = $client->sendSms($member['phone'], $message, $senderAlias);

    if ($result['success']) {
        $invoiceRepo->markSmsSent($invoiceId);
        header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&success=1');
    } else {
        header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&error=' . urlencode('Error al enviar el SMS: ' . ($result['error'] ?? 'desconocido')));
    }
} catch (Exception $e) {
    error_log("Error sending invoice SMS: " . $e->getMessage());
    header('Location: ../invoice-created.php?invoice_id=' . $invoiceId . '&error=' . urlencode('Error al enviar el SMS'));
}
exit;
