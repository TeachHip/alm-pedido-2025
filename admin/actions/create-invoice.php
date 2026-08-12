<?php
// admin/actions/create-invoice.php - Manual "Crear ticket" action, for
// orders that didn't get one automatically at checkout (legacy pre-
// automation carts). Thin wrapper around includes/InvoiceHelper.php's
// createInvoiceFromCart(), the same function the automatic checkout flow
// in save-cart.php uses -- keeps both paths identical (items, IVA, due
// date, mock payment link) instead of two copies drifting apart.
include dirname(__FILE__) . '/../../includes/auth.php';
requireAdminAuth();

require_once dirname(__FILE__) . '/../../includes/repositories/InvoiceRepository-DB.php';
require_once dirname(__FILE__) . '/../../includes/InvoiceHelper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../orders.php');
    exit;
}

$cartId = (int) ($_POST['cart_id'] ?? 0);

if (!$cartId) {
    header('Location: ../orders.php?error=' . urlencode('Falta el id del pedido'));
    exit;
}

// Guard against creating a duplicate if one already exists (e.g. this page
// was reached via direct POST after the "Crear ticket" link was already
// hidden in favor of "Ver ticket" -- see admin/orders.php).
$invoiceRepo = new InvoiceRepository();
$existing = $invoiceRepo->findByCartId($cartId);
if ($existing) {
    header('Location: ../invoice-created.php?invoice_id=' . $existing['id']);
    exit;
}

$baseUrl = buildAppBaseUrl('/admin/actions');
$result = createInvoiceFromCart($cartId, $baseUrl);

if (!$result['success']) {
    header('Location: ../create-invoice.php?cart_id=' . $cartId . '&error=' . urlencode($result['error']));
    exit;
}

header('Location: ../invoice-created.php?invoice_id=' . $result['invoice_id']);
exit;
