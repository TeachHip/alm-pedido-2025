<?php
/**
 * Price Helper
 * Shared price-selection logic for the show_dual_pricing setting (admin/settings.php).
 * Previously duplicated between product.php and section.php — that duplication
 * caused a real bug (displayed price didn't match cart/checkout price), fixed
 * 2026-07-06. Single source of truth from here on.
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
function resolveCartLines($product, $options, $showDualPricing) {
    $image = !empty($product['image']) ? 'primgs/' . $product['image'] : '';
    $lines = [];
    foreach ($options as $option) {
        $lines[] = [
            'id' => 'product-' . $product['id'] . '-option-' . $option['id'],
            'label' => $option['label'],
            'name' => $product['name'] . ' (' . $option['label'] . ')',
            'price' => (float) getCartPrice($option, $showDualPricing),
            'priceHtml' => renderPriceHtml($option, $showDualPricing),
            'image' => $image,
        ];
    }
    return $lines;
}
