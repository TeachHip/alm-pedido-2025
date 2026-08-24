<?php
// partials/out-of-stock-badge.php - "Fin de stock" overlay badge, shared by
// product.php (detail page) and section.php (grid card). Caller must set
// $product before including.
?>
<?php if ($product['almost_out_of_stock']): ?>
<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: #FFFF00; color: black; padding: 5px 8px 8px 8px; border-radius: 5px; font-size: 13px; font-weight: bold; z-index: 10; text-align: center; line-height: 1.3; white-space: nowrap;">
    ⚠️ Fin de stock
</div>
<?php endif; ?>
