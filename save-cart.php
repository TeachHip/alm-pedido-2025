<?php
/**
 * Save Cart Endpoint
 * Saves cart to database and returns ticket number
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/member-auth.php';
require_once __DIR__ . '/includes/repositories/CartRepository-DB.php';
require_once __DIR__ . '/includes/repositories/ProductRepository-DB.php';
require_once __DIR__ . '/includes/repositories/ProductOptionRepository-DB.php';
require_once __DIR__ . '/includes/repositories/SettingsRepository-DB.php';
require_once __DIR__ . '/includes/CartHelper.php';
require_once __DIR__ . '/includes/InvoiceHelper.php';
require_once __DIR__ . '/includes/PriceHelper.php';

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['items']) || empty($data['items'])) {
        throw new Exception('Carrito vacío');
    }

    // Checkout requires a logged-in member (browsing does not) -- the ticket
    // de compra needs a real, verified member on the cart.
    $member = getValidatedMember();
    if (!$member) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'requires_login' => true,
            'error' => 'Debes iniciar sesión para completar el pedido'
        ]);
        exit;
    }

    $cartRepo = new CartRepository();
    $productRepo = new ProductRepository();
    $optionRepo = new ProductOptionRepository();
    $settingsRepo = new SettingsRepository();
    $showDualPricing = $settingsRepo->getBool('show_dual_pricing', false);

    // Prepare cart items with proper structure. Price is NEVER taken from
    // the client -- it's fully attacker-controlled (cart lives in a browser
    // cookie/localStorage) and flows untouched into the invoice and the
    // amount actually charged via PayGold if trusted. Re-derive it
    // server-side from the product/option row instead, the same way
    // product.php/section.php compute it for display (PriceHelper::getCartPrice()).
    $productIds = array_values(array_unique(array_filter(array_map(
        function ($item) { return extractProductId($item['id'] ?? $item['product_id'] ?? null); },
        $data['items']
    ))));
    $optionsById = [];
    foreach ($optionRepo->getByProductIds($productIds) as $productOptions) {
        foreach ($productOptions as $option) {
            $optionsById[$option['id']] = $option;
        }
    }

    $cartItems = [];
    foreach ($data['items'] as $item) {
        // Extract numeric ID from formats like 'product-7', 'product-7-option-12', or just '7'
        $productId = extractProductId($item['id'] ?? $item['product_id'] ?? null);
        $optionId = extractOptionId($item['id'] ?? null);

        if ($optionId && isset($optionsById[$optionId])) {
            $price = getCartPrice($optionsById[$optionId], $showDualPricing);
        } else {
            $product = $productRepo->getById($productId);
            if (!$product) {
                throw new Exception('Producto no encontrado');
            }
            $price = getCartPrice($product, $showDualPricing);
        }

        $cartItems[] = [
            'product_id' => $productId,
            'product_option_id' => $optionId,
            'quantity' => $item['quantity'] ?? 1,
            'price' => $price,
            'name' => $item['name'] ?? ''
        ];
    }
    
    // Pedido Expres cart fee
    $feeAmount = (float) $settingsRepo->get('pedido_expres_fee_amount', '0');
    $feeLabel = $settingsRepo->get('pedido_expres_fee_label', '');
    if ($feeAmount > 0) {
        $cartProductIds = array_column($cartItems, 'product_id');
        if (!$productRepo->anyInSectionKey($cartProductIds, 'flash')) {
            $feeAmount = 0;
        }
    }

    // Create cart in database
    $result = $cartRepo->createCart(
        $cartItems,
        $member['id'],
        session_id(),
        $feeAmount,
        $feeLabel
    );

    if ($result['success']) {
        // Auto-create the ticket de compra + payment link (real PayGold if
        // configured, mock fallback otherwise) right away instead of
        // waiting for Hop to do it manually later. Fail-soft: the WhatsApp
        // order must go through regardless of whether this succeeds
        // (see AI/plans v10 Stage 2 -- confirmed 2026-08-12).
        $mock = null;
        try {
            $baseUrl = buildAppBaseUrl('');
            $invoiceResult = createInvoiceFromCart($result['cart_id'], $baseUrl);
            if ($invoiceResult['success']) {
                $ticketUrl = buildTicketUrl($invoiceResult['token'], $baseUrl);
                $mock = [
                    'payment_url' => $invoiceResult['payment_url'],
                    'is_mock' => $invoiceResult['payment_is_mock'],
                    'ticket_url' => $ticketUrl,
                ];
            } else {
                error_log("Error auto-creating invoice at checkout: " . $invoiceResult['error']);
            }
        } catch (Exception $e) {
            error_log("Error auto-creating invoice at checkout: " . $e->getMessage());
        }

        echo json_encode([
            'success' => true,
            'cart_id' => $result['cart_id'],
            'ticket' => $result['ticket'],
            'total' => $result['total'],
            'fee_amount' => $result['fee_amount'],
            'fee_label' => $result['fee_label'],
            // The cart itself always saves successfully even when ticket
            // creation below fails (deliberately fail-soft -- see comment
            // above). But my-orders.php only ever reads from `invoices`, so
            // if this is false the customer would land on a page showing
            // nothing at all with no clue their order was actually received.
            // The caller needs to be able to tell the two outcomes apart.
            'ticket_created' => $mock !== null,
            'mock' => $mock
        ]);
    } else {
        throw new Exception($result['error'] ?? 'Error desconocido');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
