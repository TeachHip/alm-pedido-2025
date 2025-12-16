<?php
/**
 * Save Cart Endpoint
 * Saves cart to database and returns ticket number
 */

header('Content-Type: application/json');

require_once __DIR__ . '/includes/CartRepository-DB.php';

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || !isset($data['items']) || empty($data['items'])) {
        throw new Exception('Carrito vacío');
    }
    
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $cartRepo = new CartRepository();
    
    // Prepare cart items with proper structure
    $cartItems = [];
    foreach ($data['items'] as $item) {
        // Extract numeric ID from formats like 'product-7' or just '7'
        $productId = $item['id'] ?? $item['product_id'] ?? null;
        if (is_string($productId) && strpos($productId, 'product-') === 0) {
            $productId = (int)str_replace('product-', '', $productId);
        } else {
            $productId = (int)$productId;
        }
        
        $cartItems[] = [
            'product_id' => $productId,
            'quantity' => $item['quantity'] ?? 1,
            'price' => $item['price'] ?? 0,
            'name' => $item['name'] ?? ''
        ];
    }
    
    // Create cart in database
    $result = $cartRepo->createCart(
        $cartItems,
        null, // client_id (guest for now)
        session_id()
    );
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'cart_id' => $result['cart_id'],
            'ticket' => $result['ticket'],
            'total' => $result['total']
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
