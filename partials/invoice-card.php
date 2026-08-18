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
 */
$isOverdue = $invoice['payment_status'] === 'pending' && strtotime($invoice['due_date']) < time();
$cardClass = 'invoice-card';
if ($invoice['status'] === 'superseded') $cardClass .= ' invoice-superseded';
if ($invoice['status'] === 'cancelled') $cardClass .= ' invoice-cancelled';
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
<?php elseif ($isOverdue): ?>
<div class="invoice-banner invoice-banner-warning">⚠️ Plazo de pago vencido — contacta con AlMercáu.</div>
<?php endif; ?>

<div class="<?php echo $cardClass; ?>">
    <div class="invoice-header">
        <h2><?php echo htmlspecialchars($businessName); ?></h2>
        <?php if ($businessNif): ?><p>NIF <?php echo htmlspecialchars($businessNif); ?></p><?php endif; ?>
        <?php if ($associationName): ?><p><?php echo htmlspecialchars($associationName); ?></p><?php endif; ?>
        <?php if ($businessAddress): ?><p><?php echo htmlspecialchars($businessAddress); ?></p><?php endif; ?>
        <p class="invoice-ticket">Ticket de compra <?php echo htmlspecialchars($invoice['ticket_number']); ?></p>
    </div>

    <div class="invoice-meta">
        <p><strong>Miembro:</strong> AM<?php echo MemberRepository::formatMemberNumber($invoice['member_number']); ?></p>
        <p><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></p>
        <p><strong>Fecha límite de pago:</strong> <?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></p>
    </div>

    <ul class="invoice-items-list">
        <?php foreach ($items as $item): ?>
        <li>
            <?php echo (int) $item['quantity']; ?>x
            <?php echo htmlspecialchars($item['product_name']); ?>
            <?php if ($item['option_label']): ?> (<?php echo htmlspecialchars($item['option_label']); ?>)<?php endif; ?>
            - <?php echo number_format($item['line_total'], 2); ?>€
            <?php if ($item['iva_rate']): ?> <small>(IVA <?php echo htmlspecialchars($item['iva_rate']); ?>% inc)</small><?php endif; ?>
        </li>
        <?php endforeach; ?>
        <?php if ($invoice['surcharge_amount']): ?>
        <li><?php echo htmlspecialchars($invoice['surcharge_label']); ?> - <?php echo number_format($invoice['surcharge_amount'], 2); ?>€</li>
        <?php endif; ?>
    </ul>

    <div class="invoice-total">
        Total: <?php echo number_format($invoice['total_amount'], 2); ?>€, impuestos incluidos
    </div>

    <?php if ($invoice['status'] === 'active' && $invoice['payment_status'] === 'pending' && !$isOverdue): ?>
        <?php // Not paid yet -- pay link only, no WhatsApp link. ?>
        <?php if ($invoice['paygold_payment_url']): ?>
            <p class="invoice-pay-line">Haz el abono en el siguiente enlace: <a href="<?php echo htmlspecialchars($invoice['paygold_payment_url']); ?>"><?php echo htmlspecialchars($invoice['paygold_payment_url']); ?></a></p>
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
        <p class="invoice-pay-line"><a href="<?php echo htmlspecialchars($whatsappUrl); ?>">📱 Avisar por WhatsApp</a></p>
    <?php endif; ?>
</div>
