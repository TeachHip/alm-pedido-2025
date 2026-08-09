<?php
// partials/product-option-select.php - Variant dropdown, shared by product.php
// (detail page) and section.php (grid card). Caller must set $product and
// $cartLines (from PriceHelper::resolveCartLines()) before including.
?>
<div class="product-option-select">
    <select id="option-select-<?php echo $product['id']; ?>" onchange="updateOptionPriceDisplay('<?php echo $product['id']; ?>')">
        <?php foreach ($cartLines as $line): ?>
        <option value="<?php echo htmlspecialchars($line['id']); ?>" data-line='<?php echo htmlspecialchars(json_encode($line), ENT_QUOTES); ?>'>
            <?php echo htmlspecialchars($line['label']); ?> — <?php echo number_format($line['price'], 2); ?>€
        </option>
        <?php endforeach; ?>
    </select>
</div>
