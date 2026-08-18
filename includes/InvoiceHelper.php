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
require_once __DIR__ . '/repositories/MemberRepository-DB.php';
require_once __DIR__ . '/services/PayGoldClient.php';
require_once __DIR__ . '/services/LabsMobileClient.php';

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

    // Redsys issues a DIFFERENT signing key per environment (same merchant
    // code/terminal for both) -- picking the matching secret here means
    // flipping PAYGOLD_ENVIRONMENT is the only thing needed to switch, no
    // manual secret-swapping to forget.
    $environment = (defined('PAYGOLD_ENVIRONMENT') && PAYGOLD_ENVIRONMENT) ? PAYGOLD_ENVIRONMENT : 'TEST';
    $secretConstant = $environment === 'PROD' ? 'PAYGOLD_SECRET_KEY_PROD' : 'PAYGOLD_SECRET_KEY_TEST';

    $configured = defined('PAYGOLD_MERCHANT_CODE') && PAYGOLD_MERCHANT_CODE !== ''
        && defined('PAYGOLD_TERMINAL') && PAYGOLD_TERMINAL !== ''
        && defined($secretConstant) && constant($secretConstant) !== '';

    if ($configured) {
        try {
            $client = new PayGoldClient(PAYGOLD_MERCHANT_CODE, PAYGOLD_TERMINAL, constant($secretConstant), $environment);
            $orderRef = PayGoldClient::generateOrderReference($invoiceId);
            $notificationUrl = rtrim($baseUrl, '/') . '/paygold-notify.php';
            $expiryDate = date('Y-m-d-H.i.s.000', strtotime($dueDate));

            // Redirect back to ticket.php (not my-orders.php) after payment --
            // it's the token-secured public page, so it works regardless of
            // which device/browser the customer actually completes payment
            // on (often not the one they were logged into when ordering,
            // e.g. opening the pay link straight from the SMS on their
            // phone). from_payment=1 lets ticket.php show a brief "confirming
            // your payment" note if the webhook hasn't landed yet by the
            // time the browser redirect does (the two aren't ordered).
            $urlOk = rtrim($baseUrl, '/') . '/ticket.php?token=' . $token . '&from_payment=1';
            $urlKo = rtrim($baseUrl, '/') . '/ticket.php?token=' . $token . '&payment_failed=1';

            $result = $client->requestPaymentLink($orderRef, $totalAmount, $notificationUrl, $expiryDate, $urlOk, $urlKo);
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
 * Send the invoice SMS via LabsMobile if credentials are configured;
 * otherwise (or on any failure) return the composed text without sending,
 * same fail-soft principle as requestPaymentLink(). Never blocks the
 * caller. Returns ['sent' => bool, 'is_mock' => bool, 'message' => string].
 */
function sendInvoiceSms($invoiceId, $memberId, $ticketNumber, $totalAmount, $ticketUrl) {
    $message = buildInvoiceSmsMessage($ticketNumber, $totalAmount, $ticketUrl);

    $apiKeysFile = __DIR__ . '/config/api-keys-DB.php';
    if (file_exists($apiKeysFile)) {
        require_once $apiKeysFile;
    }

    $configured = defined('LABSMOBILE_USERNAME') && LABSMOBILE_USERNAME !== ''
        && defined('LABSMOBILE_TOKEN') && LABSMOBILE_TOKEN !== '';

    if ($configured) {
        try {
            $memberRepo = new MemberRepository();
            $member = $memberRepo->findById($memberId);
            if (!$member) {
                throw new Exception('Miembro no encontrado');
            }

            $settingsRepo = new SettingsRepository();
            $senderAlias = $settingsRepo->get('sms_sender_alias', '');
            $testMode = defined('LABSMOBILE_TEST_MODE') && LABSMOBILE_TEST_MODE;

            $client = new LabsMobileClient(LABSMOBILE_USERNAME, LABSMOBILE_TOKEN, $testMode);
            $result = $client->sendSms($member['phone'], $message, $senderAlias);

            if ($result['success']) {
                $invoiceRepo = new InvoiceRepository();
                $invoiceRepo->markSmsSent($invoiceId);
                return ['sent' => true, 'is_mock' => false, 'message' => $message];
            }
            error_log("LabsMobile SMS send failed, falling back to mock: " . ($result['error'] ?? 'unknown'));
        } catch (Exception $e) {
            error_log("LabsMobile SMS send threw, falling back to mock: " . $e->getMessage());
        }
    }

    return ['sent' => false, 'is_mock' => true, 'message' => $message];
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

        // Pedido Exprés / Pedido de Grupo are order rounds with a hard,
        // shared payment cutoff (set in admin/settings.php) instead of the
        // usual N-days-from-purchase default -- any order touching either
        // section must be paid before that section's own date/time. If an
        // order touches both (each with a different deadline), the earlier
        // one governs, since both sections' rules apply independently.
        $sectionDeadlines = [
            'Pedido Exprés' => $settingsRepo->get('deadline_pedido_expres', ''),
            'Pedido de Grupo' => $settingsRepo->get('deadline_pedido_grupo', ''),
        ];
        $applicableDeadlines = [];
        foreach ($order['items'] as $item) {
            $sectionName = $item['section_name'] ?? null;
            if ($sectionName && !empty($sectionDeadlines[$sectionName])) {
                $applicableDeadlines[] = $sectionDeadlines[$sectionName];
            }
        }

        if (!empty($applicableDeadlines)) {
            $dueDate = min($applicableDeadlines);
        } else {
            $dueDays = (int) $settingsRepo->get('invoice_due_days', '7');
            $dueDate = date('Y-m-d', strtotime("+{$dueDays} days")) . ' 23:59:59';
        }

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

        $ticketUrl = buildTicketUrl($result['token'], $baseUrl);
        $sms = sendInvoiceSms($result['invoice_id'], $memberId, $result['ticket_number'], $order['cart']['total_price'], $ticketUrl);

        return [
            'success' => true,
            'invoice_id' => $result['invoice_id'],
            'token' => $result['token'],
            'ticket_number' => $result['ticket_number'],
            'total_amount' => $order['cart']['total_price'],
            'payment_url' => $payment['url'],
            'payment_is_mock' => $payment['is_mock'],
            'sms_sent' => $sms['sent'],
            'sms_is_mock' => $sms['is_mock'],
        ];
    } catch (Exception $e) {
        error_log("Error creating invoice from cart: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
