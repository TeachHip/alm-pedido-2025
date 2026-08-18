<?php
/**
 * Save Cart Endpoint
 * Saves cart to database and returns ticket number
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/member-auth.php';
require_once __DIR__ . '/includes/repositories/CartRepository-DB.php';
require_once __DIR__ . '/includes/repositories/ProductRepository-DB.php';
require_once __DIR__ . '/includes/repositories/SettingsRepository-DB.php';
require_once __DIR__ . '/includes/CartHelper.php';
require_once __DIR__ . '/includes/InvoiceHelper.php';

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
    
    // Prepare cart items with proper structure
    $cartItems = [];
    foreach ($data['items'] as $item) {
        // Extract numeric ID from formats like 'product-7', 'product-7-option-12', or just '7'
        $productId = extractProductId($item['id'] ?? $item['product_id'] ?? null);
        $optionId = extractOptionId($item['id'] ?? null);

        $cartItems[] = [
            'product_id' => $productId,
            'product_option_id' => $optionId,
            'quantity' => $item['quantity'] ?? 1,
            'price' => $item['price'] ?? 0,
            'name' => $item['name'] ?? ''
        ];
    }
    
    // Pedido Expres cart fee
    $settingsRepo = new SettingsRepository();
    $feeAmount = (float) $settingsRepo->get('pedido_expres_fee_amount', '0');
    $feeLabel = $settingsRepo->get('pedido_expres_fee_label', '');
    if ($feeAmount > 0) {
        $productRepo = new ProductRepository();
        $productIds = array_column($cartItems, 'product_id');
        if (!$productRepo->anyInSectionKey($productIds, 'flash')) {
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
