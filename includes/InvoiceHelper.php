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

require_once __DIR__ . '/config/environment.php';
require_once __DIR__ . '/repositories/CartRepository-DB.php';
require_once __DIR__ . '/repositories/InvoiceRepository-DB.php';
require_once __DIR__ . '/repositories/SettingsRepository-DB.php';
require_once __DIR__ . '/repositories/MemberRepository-DB.php';
require_once __DIR__ . '/services/PayGoldClient.php';

/**
 * Absolute base URL of the app (scheme + host + path). Uses the hardcoded
 * SITE_URL in production (see environment.php -- a forged Host header must
 * never be able to redirect PayGold's webhook/redirect URLs to another
 * domain); falls back to deriving from the current request when SITE_URL
 * isn't set (local dev). $stripFromPath removes the calling script's own
 * subdirectory from dirname($_SERVER['PHP_SELF']) -- e.g. admin/actions/*.php
 * pass '/admin/actions', root-level scripts (save-cart.php) pass ''.
 */
function buildAppBaseUrl($stripFromPath = '') {
    // dirname() returns a bare '\' (not '/') for root-level scripts on
    // Windows -- normalize before trimming, or that backslash leaks
    // straight into the URL (found via real testing: a Windows dev box
    // produced "http://host\/mock-payment.php...", a real corrupt link).
    $dir = str_replace('\\', '/', dirname($_SERVER['PHP_SELF'] ?? ''));
    $basePath = rtrim(str_replace($stripFromPath, '', $dir), '/');

    if (defined('SITE_URL') && SITE_URL) {
        return rtrim(SITE_URL, '/') . $basePath;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'almercau.org';
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
    $client = PayGoldClient::fromConfig();

    if ($client) {
        try {
            $orderRef = PayGoldClient::generateOrderReference($invoiceId);
            $notificationUrl = rtrim($baseUrl, '/') . '/paygold-notify.php';
            $expiryDate = date('Y-m-d-H.i.s.000', strtotime($dueDate));

            // Redirect back to ticket.php (not my-orders.php) after payment --
            // it's the token-secured public page, so it works regardless of
            // which device/browser the customer actually completes payment
            // on (often not the one they were logged into when ordering).
            // from_payment=1 lets ticket.php show a brief "confirming your
            // payment" note if the webhook hasn't landed yet by the time
            // the browser redirect does (the two aren't ordered).
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

// Redsys caps a real PayGold P2F link's actual validity at ~24h,
// independent of the (often days-out) Ds_Merchant_P2F_EXPIRYDATE this app
// requests -- see refreshPaymentLinkIfStale().
const PAYGOLD_LINK_MAX_AGE_HOURS = 24;

/**
 * If $invoice is still genuinely payable (pending, due_date not yet
 * reached -- guaranteed true for any 'pending' invoice that's already been
 * through InvoiceRepository::autoExpireIfOverdue()) but its stored real
 * PayGold link has aged past Redsys's own ~24h cap, silently request a
 * fresh one and persist it. Does nothing (returns $invoice unchanged) for
 * a mock link -- those don't expire the same way -- or when PayGold isn't
 * configured at all.
 *
 * Mutates and returns $invoice with the refreshed paygold_payment_url, so
 * the caller sees the new link immediately without a second query.
 */
function refreshPaymentLinkIfStale($invoice, $baseUrl) {
    if ($invoice['status'] !== 'active' || $invoice['payment_status'] !== 'pending') {
        return $invoice;
    }

    $currentUrl = $invoice['paygold_payment_url'] ?? '';
    if ($currentUrl === '' || strpos($currentUrl, 'mock-payment.php') !== false) {
        return $invoice;
    }

    if (!PayGoldClient::fromConfig()) {
        return $invoice;
    }

    $linkAge = $invoice['paygold_link_generated_at'] ?? $invoice['created_at'];
    $ageHours = (time() - strtotime($linkAge)) / 3600;
    if ($ageHours < PAYGOLD_LINK_MAX_AGE_HOURS) {
        return $invoice;
    }

    $payment = requestPaymentLink($invoice['id'], $invoice['token'], $invoice['due_date'], $invoice['total_amount'], $baseUrl);
    if ($payment['is_mock']) {
        // PayGold request failed this time too -- don't overwrite a dead
        // real link with a mock one; leave it for the next lazy check to
        // retry, same fail-soft spirit as the rest of this file.
        return $invoice;
    }

    $invoiceRepo = new InvoiceRepository();
    $invoiceRepo->setPaymentUrl($invoice['id'], $payment['url'], $payment['reference']);

    $invoice['paygold_payment_url'] = $payment['url'];
    $invoice['paygold_link_generated_at'] = date('Y-m-d H:i:s');
    return $invoice;
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
        //
        // Matched by the section's stable `key` ('flash' / 'pedido_g'), NOT
        // its editable display `name` -- the cart fee logic already keys off
        // `flash` the same way (ProductRepository::anyInSectionKey()).
        // Matching by name would silently stop applying the deadline the
        // moment an admin renames the section (typo/accent fix) via
        // admin/edit-section.php, with no error anywhere.
        $sectionDeadlines = [
            'flash' => $settingsRepo->get('deadline_pedido_expres', ''),
            'pedido_g' => $settingsRepo->get('deadline_pedido_grupo', ''),
        ];
        $applicableDeadlines = [];
        foreach ($order['items'] as $item) {
            $sectionKey = $item['section_key'] ?? null;
            if ($sectionKey && !empty($sectionDeadlines[$sectionKey])) {
                $applicableDeadlines[] = $sectionDeadlines[$sectionKey];
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
