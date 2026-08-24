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
    $client = PayGoldClient::fromConfig();

    if (!$client) {
        error_log('PayGold notification received but PayGold is not configured -- ignoring.');
    } else {
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
                    // The money moved regardless of what happened to the
                    // invoice meanwhile -- always record it. But if it's not
                    // 'active' (e.g. an admin cancelled it while the customer
                    // had the payment page open), that combination looks
                    // identical to a normal "cancelled after a manual
                    // refund" case with no way to tell them apart later, so
                    // flag it distinctly for Hop to reconcile by hand.
                    if ($invoice['status'] !== 'active') {
                        error_log('PayGold notification: payment approved for order ' . $result['order'] . ' but invoice ' . $invoice['id'] . ' status is "' . $invoice['status'] . '", not active -- needs manual reconciliation (was it cancelled after this payment, not before?).');
                    }
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
