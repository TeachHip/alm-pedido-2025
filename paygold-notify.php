<?php
/**
 * paygold-notify.php - PayGold (Redsys) payment-confirmation webhook.
 *
 * Set as Ds_Merchant_MerchantURL on every payment link request (see
 * PayGoldClient::requestPaymentLink() / includes/InvoiceHelper.php) --
 * Redsys calls this directly, server-to-server, the moment a customer's
 * payment completes. This is the authoritative signal; nothing about the
 * customer's own browser reaching a "thank you" page proves payment
 * actually happened (could be interrupted, closed, or spoofed).
 *
 * Input parsing mirrors the official Redsys reference example exactly
 * (_mats/redsys-example/backend/notification/index.php): merge JSON body
 * with $_GET/$_POST, since the exact transport shape isn't guaranteed.
 * Always responds 200 regardless of outcome (matches the same reference
 * example) so Redsys doesn't treat a rejected/invalid notification as a
 * delivery failure and retry indefinitely.
 */
require_once __DIR__ . '/includes/repositories/InvoiceRepository-DB.php';
require_once __DIR__ . '/includes/services/PayGoldClient.php';

header('Content-Type: application/json');

$jsonBody = json_decode(file_get_contents('php://input'), true);
$receivedParams = array_merge($_GET, $_POST, is_array($jsonBody) ? $jsonBody : []);

try {
    $apiKeysFile = __DIR__ . '/includes/config/api-keys-DB.php';
    if (file_exists($apiKeysFile)) {
        require_once $apiKeysFile;
    }

    $environment = (defined('PAYGOLD_ENVIRONMENT') && PAYGOLD_ENVIRONMENT) ? PAYGOLD_ENVIRONMENT : 'TEST';
    $secretConstant = $environment === 'PROD' ? 'PAYGOLD_SECRET_KEY_PROD' : 'PAYGOLD_SECRET_KEY_TEST';

    $configured = defined('PAYGOLD_MERCHANT_CODE') && PAYGOLD_MERCHANT_CODE !== ''
        && defined('PAYGOLD_TERMINAL') && PAYGOLD_TERMINAL !== ''
        && defined($secretConstant) && constant($secretConstant) !== '';

    if (!$configured) {
        error_log('PayGold notification received but PayGold is not configured -- ignoring.');
    } else {
        $client = new PayGoldClient(PAYGOLD_MERCHANT_CODE, PAYGOLD_TERMINAL, constant($secretConstant), $environment);
        $result = $client->verifyNotification($receivedParams);

        if (!$result['success']) {
            error_log('PayGold notification rejected: ' . $result['error']);
        } else {
            $invoiceRepo = new InvoiceRepository();
            $invoice = $invoiceRepo->findByReference($result['order']);

            if (!$invoice) {
                error_log('PayGold notification for unknown order reference: ' . $result['order']);
            } elseif ($result['approved']) {
                if ($invoice['payment_status'] !== 'paid') {
                    $invoiceRepo->markPaid($invoice['id']);
                }
            } else {
                error_log('PayGold notification: payment not approved for order ' . $result['order']);
            }
        }
    }
} catch (Exception $e) {
    error_log('Error handling PayGold notification: ' . $e->getMessage());
}

echo json_encode(['status' => 'ok']);
