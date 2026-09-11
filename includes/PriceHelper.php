<?php
/**
 * Price Helper
 * Shared price-selection logic. Single source of truth for both what a
 * viewer sees and what they're actually charged -- previously duplicated
 * between product.php and section.php, which caused a real bug (displayed
 * price didn't match cart/checkout price), fixed 2026-07-06.
 *
 * Pricing is based on the *viewer's actual membership status*, not the
 * show_dual_pricing admin toggle alone (that was the bug fixed 2026-09-11 --
 * previously, when the toggle was on, EVERYONE was charged the member price
 * price_member, including non-paying members and anonymous visitors):
 * - Paying member ("Mercante colaborador"): charged price_member, always
 *   sees both prices (the struck-through public price is their visible
 *   discount).
 * - Non-paying member ("Mercante") or not logged in: charged price_public.
 *   Non-paying members additionally see both prices only when the
 *   show_dual_pricing toggle is on (informational -- they don't get the
 *   discount, so this is the admin's call, not automatic); a visitor who
 *   isn't logged in always sees just the one price they'll actually pay.
 *
 * $member throughout is whatever includes/member-auth.php's
 * getLoggedInMember() returns (session-only, not re-validated against the
 * DB) -- fine for display; save-cart.php uses the fully-validated member
 * from getValidatedMember() for the actual charge, since checkout requires
 * a real login there anyway.
 */

function isPayingMember($member) {
    return $member && ($member['membership_type'] ?? null) === 'paying';
}

/**
 * The price actually charged/added to the cart for this product.
 */
function getCartPrice($product, $member) {
    return isPayingMember($member) ? $product['price_member'] : $product['price_public'];
}

/**
 * Whether to show both prices (struck-through public + the charged price)
 * rather than just the one price the viewer will actually pay.
 */
function shouldShowDualPricing($member, $showDualPricingSetting) {
    if (isPayingMember($member)) {
        return true; // always shows their real discount
    }
    if ($member) {
        return $showDualPricingSetting; // non-paying member: admin's call
    }
    return false; // not logged in: always just the one price they'd pay
}

/**
 * HTML for the price block: the charged price, plus a struck-through
 * "other" price when dual pricing applies and they differ. Which price is
 * struck depends on who's looking -- it's always whichever one ISN'T the
 * charge, so the plain (non-struck) number is always what they'll actually
 * pay:
 * - Paying member: struck public price, plain member price -- their real
 *   discount.
 * - Non-paying member (dual pricing on): struck member price, plain public
 *   price -- an upgrade nudge ("paying members get this, you pay this"),
 *   never the charge itself struck through.
 */
function renderPriceHtml($product, $member, $showDualPricingSetting) {
    $charge = getCartPrice($product, $member);
    if (!shouldShowDualPricing($member, $showDualPricingSetting) || $product['price_public'] == $product['price_member']) {
        return number_format($charge, 2) . '€';
    }
    $struckPrice = isPayingMember($member) ? $product['price_public'] : $product['price_member'];
    return '<del class="greyed">' . number_format($struckPrice, 2) . '€</del> | ' . number_format($charge, 2) . '€';
}

/**
 * Resolve a product's options into purchasable cart lines: one entry per
 * option, each carrying everything the cart needs (id, display name, price,
 * image) plus the price HTML for display. This is the single place that
 * translates "product + chosen variant" into a cart-line identity — the
 * dropdown on product.php/section.php renders from this, and the client JS
 * only looks entries up here rather than recomputing price/name itself.
 * Assumes $options is non-empty (callers only invoke this when a product
 * has options; products without options keep using getCartPrice/renderPriceHtml
 * directly, unchanged).
 */
function resolveCartLines($product, $options, $member, $showDualPricingSetting) {
    $image = !empty($product['image']) ? 'primgs/' . $product['image'] : '';
    $lines = [];
    foreach ($options as $option) {
        $lines[] = [
            'id' => 'product-' . $product['id'] . '-option-' . $option['id'],
            'label' => $option['label'],
            'name' => $product['name'] . ' (' . $option['label'] . ')',
            'price' => (float) getCartPrice($option, $member),
            'priceHtml' => renderPriceHtml($option, $member, $showDualPricingSetting),
            'image' => $image,
        ];
    }
    return $lines;
}
