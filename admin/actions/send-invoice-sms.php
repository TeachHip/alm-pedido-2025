<?php
// admin/actions/send-invoice-sms.php - Manually trigger the invoice SMS
// (Stage 1: manual button; Stage 2 automates this from save-cart.php).
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/InvoiceRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/repositories/MemberRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/repositories/SettingsRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/services/LabsMobileClient.php';
require_once dirname(__FILE__) . '/../../includes/InvoiceHelper.php';

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

$baseUrl = buildAppBaseUrl('/admin/actions');
$invoiceUrl = buildTicketUrl($invoice['token'], $baseUrl);

$settingsRepo = new SettingsRepository();
$senderAlias = $settingsRepo->get('sms_sender_alias', '');

$message = buildInvoiceSmsMessage($invoice['ticket_number'], $invoice['total_amount'], $invoiceUrl);

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
