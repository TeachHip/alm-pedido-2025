<?php
/**
 * partials/invoice-card.php - Shared "ticket de compra" card markup, used by
 * ticket.php (full review page) and my-orders.php (latest-order summary) so
 * the receipt looks and behaves identically everywhere it appears -- one
 * markup source instead of two pages drifting apart over time.
 *
 * Expects in scope: $invoice, $items, $businessName, $associationName,
 * $businessAddress, $businessNif. $forwardToken (superseded-ticket redirect)
 * is optional -- only ticket.php ever sets it.
 *
 * Vencido (payment_status='expired', set lazily by
 * InvoiceRepository::autoExpireIfOverdue() -- called by every page that
 * fetches an invoice before including this partial) is deliberately
 * distinct from Cancelado (status='cancelled') -- one is automatic, nobody
 * acted; the other means a person actually cancelled it.
 */
$cardClass = 'invoice-card';
if ($invoice['status'] === 'superseded') $cardClass .= ' invoice-superseded';
if ($invoice['status'] === 'cancelled' || $invoice['payment_status'] === 'expired') $cardClass .= ' invoice-cancelled';
?>

<?php if ($invoice['status'] === 'superseded'): ?>
<div class="invoice-banner invoice-banner-warning">
    ⚠️ Este ticket ha sido sustituido por uno nuevo.
    <?php if (!empty($forwardToken)): ?>
        <a href="ticket.php?token=<?php echo htmlspecialchars($forwardToken); ?>">Ver ticket actual →</a>
    <?php endif; ?>
</div>
<?php elseif ($invoice['status'] === 'cancelled'): ?>
<div class="invoice-banner invoice-banner-warning">❌ Este ticket ha sido cancelado.</div>
<?php elseif ($invoice['payment_status'] === 'paid'): ?>
<div class="invoice-banner invoice-banner-success">✅ Pagado</div>
<?php elseif ($invoice['payment_status'] === 'expired'): ?>
<div class="invoice-banner invoice-banner-warning">⚠️ Vencido — el plazo de pago ha pasado. Contacta con AlMercáu.</div>
<?php endif; ?>

<div class="<?php echo $cardClass; ?>">
    <div class="invoice-header">
        <h2><?php echo htmlspecialchars($businessName); ?></h2>
        <?php
            $nifAddressParts = [];
            if ($businessNif) $nifAddressParts[] = 'NIF ' . htmlspecialchars($businessNif);
            if ($businessAddress) $nifAddressParts[] = htmlspecialchars($businessAddress);
        ?>
        <div class="invoice-ticket">
            <?php if ($associationName): ?><?php echo htmlspecialchars($associationName); ?><br><?php endif; ?>
            <?php if ($nifAddressParts): ?><?php echo implode(' | ', $nifAddressParts); ?><?php endif; ?>
            <hr class="invoice-header-divider">
            <strong>Ticket de compra <?php echo htmlspecialchars($invoice['ticket_number']); ?></strong>
            | AM<?php echo MemberRepository::formatMemberNumber($invoice['member_number']); ?>
            | <?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?>
        </div>
    </div>

    <ul class="invoice-items-list">
        <?php foreach ($items as $item): ?>
        <li class="invoice-item-row">
            <span>
                <?php echo (int) $item['quantity']; ?>x
                <?php echo htmlspecialchars($item['product_name']); ?>
                <?php if ($item['option_label']): ?> (<?php echo htmlspecialchars($item['option_label']); ?>)<?php endif; ?>
                <?php if ($item['iva_rate']): ?> <small>(IVA <?php echo htmlspecialchars($item['iva_rate']); ?>% inc)</small><?php endif; ?>
            </span>
            <span class="invoice-item-price"><?php echo number_format($item['line_total'], 2); ?>€</span>
        </li>
        <?php endforeach; ?>
        <?php if ($invoice['surcharge_amount']): ?>
        <li class="invoice-item-row">
            <span><?php echo htmlspecialchars($invoice['surcharge_label']); ?></span>
            <span class="invoice-item-price"><?php echo number_format($invoice['surcharge_amount'], 2); ?>€</span>
        </li>
        <?php endif; ?>
    </ul>

    <div class="invoice-total">
        Total: <?php echo number_format($invoice['total_amount'], 2); ?>€
        <span class="invoice-total-note">impuestos incluidos</span>
    </div>

    <?php if ($invoice['status'] === 'active' && $invoice['payment_status'] === 'pending'): ?>
        <?php // Not paid yet -- pay link only, no WhatsApp link. ?>
        <?php if ($invoice['paygold_payment_url']): ?>
            <a href="<?php echo htmlspecialchars($invoice['paygold_payment_url']); ?>" class="whatsapp-btn">2. Ir al pago <small>(2/3)</small></a>
        <?php else: ?>
            <p class="invoice-pending-note">Pago pendiente — te contactaremos con las instrucciones.</p>
        <?php endif; ?>
    <?php elseif ($invoice['status'] === 'active' && $invoice['payment_status'] === 'paid'): ?>
        <?php
            // Paid -- WhatsApp link only, no pay link (nothing left to pay).
            // Decorative/warmth touch only, built fresh from the invoice
            // itself so it works no matter how the customer arrived here
            // (first landing, a later revisit, or the post-payment redirect).
            $whatsappMessage = "✅ Pedido " . $invoice['ticket_number'] . " pagado. ¡Gracias!";
            $whatsappUrl = "https://api.whatsapp.com/send?phone=34611183123&text=" . urlencode($whatsappMessage);
        ?>
        <a href="<?php echo htmlspecialchars($whatsappUrl); ?>" class="whatsapp-btn">3. Enviar pedido <small>(3/3)</small></a>
    <?php endif; ?>

    <?php if ($invoice['payment_status'] !== 'paid'): ?>
    <div class="invoice-meta">
        <?php
            // Flat 7-day default always stores '23:59:59' as a pure
            // end-of-day sentinel (see InvoiceHelper::createInvoiceFromCart());
            // a real Pedido Exprés/Grupo section deadline is an admin-chosen
            // date/time and never lands on that exact second. Only show the
            // time when it's a genuine chosen one, not the artificial sentinel.
            $dueDateTimestamp = strtotime($invoice['due_date']);
            $dueDateDisplay = date('d/m/Y', $dueDateTimestamp);
            if (date('H:i:s', $dueDateTimestamp) !== '23:59:59') {
                $dueDateDisplay .= ' ' . date('H:i', $dueDateTimestamp);
            }
        ?>
        <p style="text-align: center;"><strong>Fecha límite de pago:</strong> <?php echo $dueDateDisplay; ?></p>
    </div>
    <?php endif; ?>
</div>
