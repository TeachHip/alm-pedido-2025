<?php
/**
 * Price Helper
 * Shared price-selection logic for the show_dual_pricing setting (admin/settings.php).
 * Previously duplicated between product.php and section.php — that duplication
 * caused a real bug (displayed price didn't match cart/checkout price), fixed
 * 2026-07-06, see AI/CHANGELOG.md. Single source of truth from here on.
 */

/**
 * The price actually charged/added to the cart for this product.
 */
function getCartPrice($product, $showDualPricing) {
    return $showDualPricing ? $product['price_member'] : $product['price_public'];
}

/**
 * HTML for the price block: struck-through public price + member price when
 * dual pricing is on and they differ, otherwise just the single cart price.
 */
function renderPriceHtml($product, $showDualPricing) {
    $html = '';
    if ($showDualPricing && $product['price_public'] != $product['price_member']) {
        $html .= '<del class="greyed">' . number_format($product['price_public'], 2) . '€</del> | ';
    }
    $html .= number_format(getCartPrice($product, $showDualPricing), 2) . '€';
    return $html;
}
