<?php
/**
 * Invoice Helper
 * Turns a completed cart into a ticket de compra + a real PayGold payment
 * link (falling back to a mock payment page if PayGold isn't configured
 * yet, or if the request fails) -- the one shared place for logic that
 * was previously duplicated between the manual admin "Crear ticket"
 * action and the automatic checkout flow (save-cart.php). Deliberately
 * still "dumb" about pricing/membership: everything here reads
 * already-finalized numbers off the cart, per the same principle
 * InvoiceRepository documents.
 */

require_once __DIR__ . '/repositories/CartRepository-DB.php';
require_once __DIR__ . '/repositories/InvoiceRepository-DB.php';
require_once __DIR__ . '/repositories/SettingsRepository-DB.php';
require_once __DIR__ . '/services/PayGoldClient.php';

/**
 * Absolute base URL of the app (scheme + host + path), derived from the
 * current request. $stripFromPath removes the calling script's own
 * subdirectory from dirname($_SERVER['PHP_SELF']) -- e.g. admin/actions/*.php
 * pass '/admin/actions', root-level scripts (save-cart.php) pass ''.
 */
function buildAppBaseUrl($stripFromPath = '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'almercau.org';
    // dirname() returns a bare '\' (not '/') for root-level scripts on
    // Windows -- normalize before trimming, or that backslash leaks
    // straight into the URL (found via real testing: a Windows dev box
    // produced "http://host\/mock-payment.php...", a real corrupt link).
    $dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
    $basePath = rtrim(str_replace($stripFromPath, '', $dir), '/');
    return "$scheme://$host$basePath";
}

function buildTicketUrl($token, $baseUrl) {
    return rtrim($baseUrl, '/') . '/ticket.php?token=' . $token;
}

function buildMockPaymentUrl($token, $baseUrl) {
    return rtrim($baseUrl, '/') . '/mock-payment.php?token=' . $token;
}

/**
 * Request a real PayGold payment link if credentials are configured;
 * fall back to the mock payment page otherwise (missing config, or the
 * request itself failing) -- never blocks ticket creation either way.
 * Returns ['url', 'reference', 'is_mock'].
 */
function requestPaymentLink($invoiceId, $token, $dueDate, $totalAmount, $baseUrl) {
    $apiKeysFile = __DIR__ . '/config/api-keys-DB.php';
    if (file_exists($apiKeysFile)) {
        require_once $apiKeysFile;
    }

    $configured = defined('PAYGOLD_MERCHANT_CODE') && PAYGOLD_MERCHANT_CODE !== ''
        && defined('PAYGOLD_TERMINAL') && PAYGOLD_TERMINAL !== ''
        && defined('PAYGOLD_SECRET_KEY') && PAYGOLD_SECRET_KEY !== '';

    if ($configured) {
        try {
            $client = new PayGoldClient(PAYGOLD_MERCHANT_CODE, PAYGOLD_TERMINAL, PAYGOLD_SECRET_KEY, PAYGOLD_ENVIRONMENT ?: 'TEST');
            $orderRef = PayGoldClient::generateOrderReference($invoiceId);
            // Stage 3 (payment-confirmation webhook) isn't built yet, but
            // Redsys still requires a notification URL field on every request.
            $notificationUrl = rtrim($baseUrl, '/') . '/paygold-notify.php';
            $expiryDate = date('Y-m-d-H.i.s.000', strtotime($dueDate));

            $result = $client->requestPaymentLink($orderRef, $totalAmount, $notificationUrl, $expiryDate);
            if ($result['success']) {
                return ['url' => $result['payment_url'], 'reference' => $orderRef, 'is_mock' => false];
            }
            error_log("PayGold request failed, falling back to mock payment page: " . $result['error']);
        } catch (Exception $e) {
            error_log("PayGold request threw, falling back to mock payment page: " . $e->getMessage());
        }
    }

    return [
        'url' => buildMockPaymentUrl($token, $baseUrl),
        'reference' => 'MOCK-' . strtoupper(bin2hex(random_bytes(4))),
        'is_mock' => true,
    ];
}

/**
 * SMS text sent to the member: just the ticket link -- the payment link
 * lives on that ticket page itself (ticket.php already shows it), not
 * duplicated into the SMS.
 */
function buildInvoiceSmsMessage($ticketNumber, $totalAmount, $ticketUrl) {
    $message = "AlMercáu: tu ticket de compra " . $ticketNumber . " (" . number_format($totalAmount, 2) . "€) esta listo: " . $ticketUrl;
    return $message;
}

/**
 * Build a ticket de compra from a completed cart, and immediately generate
 * + store a mock payment link for it. Used by both the manual admin action
 * and the automatic checkout flow.
 * Returns ['success' => true, 'invoice_id', 'token', 'ticket_number',
 * 'total_amount', 'payment_url'] or ['success' => false, 'error'].
 */
function createInvoiceFromCart($cartId, $baseUrl) {
    try {
        $cartRepo = new CartRepository();
        $order = $cartRepo->getOrderWithItems($cartId);

        if (!$order) {
            throw new Exception('Pedido no encontrado');
        }

        $memberId = $order['cart']['member_id'] ?? null;
        if (!$memberId) {
            throw new Exception('Este pedido no tiene un miembro asociado');
        }

        $settingsRepo = new SettingsRepository();
        $dueDays = (int) $settingsRepo->get('invoice_due_days', '7');
        $dueDate = date('Y-m-d', strtotime("+{$dueDays} days")) . ' 23:59:59';

        $items = [];
        $subtotal = 0;
        foreach ($order['items'] as $item) {
            $items[] = [
                'product_name' => $item['product_ticket_name'] ?: $item['product_name'],
                'option_label' => $item['option_label'] ?? null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['price_snapshot'],
                'iva_rate' => $item['product_iva_rate'] ?? null,
                'line_total' => $item['subtotal'],
            ];
            $subtotal += (float) $item['subtotal'];
        }

        $invoiceRepo = new InvoiceRepository();
        $result = $invoiceRepo->create([
            'member_id' => $memberId,
            'cart_id' => $cartId,
            'items' => $items,
            'subtotal' => $subtotal,
            'surcharge_amount' => $order['cart']['fee_amount'] ?? null,
            'surcharge_label' => $order['cart']['fee_label'] ?? null,
            'total_amount' => $order['cart']['total_price'],
            'due_date' => $dueDate,
        ]);

        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Error desconocido');
        }

        $payment = requestPaymentLink($result['invoice_id'], $result['token'], $dueDate, $order['cart']['total_price'], $baseUrl);
        $invoiceRepo->setPaymentUrl($result['invoice_id'], $payment['url'], $payment['reference']);

        return [
            'success' => true,
            'invoice_id' => $result['invoice_id'],
            'token' => $result['token'],
            'ticket_number' => $result['ticket_number'],
            'total_amount' => $order['cart']['total_price'],
            'payment_url' => $payment['url'],
            'payment_is_mock' => $payment['is_mock'],
        ];
    } catch (Exception $e) {
        error_log("Error creating invoice from cart: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
