<?php
/**
 * partials/invoice-card.php - Shared "ticket de compra" card markup, used by
 * ticket.php (full review page) and my-orders.php (latest-order summary) so
 * the receipt looks and behaves identically everywhere it appears -- one
 * markup source instead of two pages drifting apart over time.
 *
 * Expects in scope: $invoice, $items, $businessName, $associationName,
 * $businessAddress, $businessNif. $forwardToken (superseded-ticket redirect)
 * is optional -- only ticket.php ever sets it. $showCancelInCard (bool) is
 * also optional -- only ticket.php sets it true, to render its own cancel
 * link inside the card; my-orders.php renders its equivalent separately,
 * outside the card, so leaving this unset there avoids a duplicate.
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
        <div class="invoice-ticket">
            <?php if ($associationName): ?><?php echo htmlspecialchars($associationName); ?><br><?php endif; ?>
            <?php if ($businessAddress || $businessNif): ?>
            <span class="invoice-header-line">
                <?php if ($businessAddress): ?><span><?php echo htmlspecialchars($businessAddress); ?></span><?php endif; ?>
                <?php if ($businessAddress && $businessNif): ?><span class="invoice-header-sep">|</span><?php endif; ?>
                <?php if ($businessNif): ?><span>NIF <?php echo htmlspecialchars($businessNif); ?></span><?php endif; ?>
            </span>
            <?php endif; ?>
            <hr class="invoice-header-divider">
            <div class="invoice-header-line">
                <strong><?php echo $invoice['payment_status'] === 'paid' ? 'Ticket de compra' : 'Pedido'; ?> <?php echo htmlspecialchars($invoice['ticket_number']); ?></strong>
                <span class="invoice-header-sep">|</span>
                <span>AM<?php echo MemberRepository::formatMemberNumber($invoice['member_number']); ?> | <?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></span>
            </div>
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
            // Under 48h left to pay -- flag it so the customer doesn't miss
            // the deadline. Not shown once already past due (payment_status
            // 'expired' already gets its own "Vencido" banner above).
            $hoursUntilDue = ($dueDateTimestamp - time()) / 3600;
            $isDueSoon = $hoursUntilDue > 0 && $hoursUntilDue < 48;
        ?>
        <p style="text-align: center;"><?php if ($isDueSoon): ?><span title="Menos de 48h para pagar">⚠️</span> <?php endif; ?><strong>Fecha límite de pago:</strong> <?php echo $dueDateDisplay; ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($showCancelInCard) && $invoice['status'] === 'active' && $invoice['payment_status'] === 'pending'): ?>
    <form method="POST" action="cancel-order.php" onsubmit="return confirm('¿Seguro que quieres cancelar este pedido? Esta acción no se puede deshacer.');">
        <input type="hidden" name="invoice_id" value="<?php echo $invoice['id']; ?>">
        <button type="submit" class="empty-cart-link link-button"><i class="fas fa-trash-alt"></i> Cancelar pedido</button>
    </form>
    <?php endif; ?>
</div>
