<?php
// admin/actions/create-invoice.php - Build a ticket de compra from a
// completed cart. Deliberately dumb: reads the cart's already-finalized
// numbers (member, items, fee) and hands them to InvoiceRepository as-is --
// no pricing/membership logic here, per the invoice/SMS decoupling
// principle (see AI/plans v10). Member and due date are derived server-side
// (not trusted from the client) -- the cart's own member_id and the
// invoice_due_days setting are the only sources of truth for those.
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/CartRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/repositories/InvoiceRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/repositories/SettingsRepository-DB.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../orders.php');
    exit;
}

$cartId = (int) ($_POST['cart_id'] ?? 0);

if (!$cartId) {
    header('Location: ../orders.php?error=' . urlencode('Falta el id del pedido'));
    exit;
}

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

    header('Location: ../invoice-created.php?invoice_id=' . $result['invoice_id']);
} catch (Exception $e) {
    error_log("Error creating invoice: " . $e->getMessage());
    header('Location: ../create-invoice.php?cart_id=' . $cartId . '&error=' . urlencode($e->getMessage()));
}
exit;
