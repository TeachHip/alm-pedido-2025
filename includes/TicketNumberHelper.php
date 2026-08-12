<?php
/**
 * Ticket Number Helper
 * The "#ALM-YYYY-MM-####" formatting was previously duplicated verbatim in
 * three places (CartRepository::generateTicketNumber/getTicketNumber,
 * InvoiceRepository::generateTicketNumber) -- each with its own counting
 * query (they legitimately count different things: carts this month,
 * a cart's position among carts this month, invoices this month), but all
 * three re-typed the same prefix+zero-pad string building. This centralizes
 * only that formatting step.
 */
function formatTicketNumber($year, $month, $sequenceNumber) {
    $paddedNumber = str_pad($sequenceNumber, 4, '0', STR_PAD_LEFT);
    return "#ALM-{$year}-{$month}-{$paddedNumber}";
}
