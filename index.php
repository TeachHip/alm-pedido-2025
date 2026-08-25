<?php
require_once 'includes/maintenance.php';
enforceMaintenanceMode();

// Load database repository
require_once 'includes/repositories/SectionRepository-DB.php';

try {
    $sectionRepo = new SectionRepository();
    $sections = $sectionRepo->getAllVisibleActive();
} catch (Exception $e) {
    error_log("Error loading sections: " . $e->getMessage());
    $sections = [];
}

// Include 00.php for cart functionality - cookie
include 'partials/00.php';

$pageTitle = 'AlMercáu - Carro de la compra para mercantes';

//START HTML
?>
<?php include 'partials/head.php'; ?>
<?php include 'partials/header.php'; ?>

<?php if (!empty($cart)): ?>
    <a href="#" onclick="clearCart(); return false;" class="empty-cart-link">
        ¿Nueva compra? >>>
        <i class="fas fa-trash-alt"></i> Vaciar carrito
    </a>
<?php endif; ?>


<div class="container">
    <div class="menu-grid">
        <?php foreach ($sections as $section): ?>
        <a href="section.php?section=<?php echo $section['id']; ?>" class="menu-item">
            <img src="<?php echo !empty($section['image']) ? htmlspecialchars($section['image']) : 'https://placehold.co/300x200/25D366/ffffff?text=' . urlencode($section['name']); ?>"
                alt="<?php echo htmlspecialchars($section['name']); ?>"
                onerror="this.src='https://placehold.co/300x200/25D366/ffffff?text=<?php echo urlencode($section['name']); ?>'">
            <h3><?php echo htmlspecialchars($section['name']); ?></h3>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<div class="container page-desc">
<p>
    <strong id="instructions-toggle" class="instructions-toggle" role="button" tabindex="0" aria-expanded="false" aria-controls="instructions-content">INSTRUCCIONES <span id="instructions-arrow">▸</span></strong>
</p>
<div id="instructions-content" style="display: none;">
<p>Selecciona qué producto quieres, indica qué cantidad deseas y pulsa '<strong>Al carro!</strong>'. Cuando acabes de pedir cada producto, ve al carro (abajo a la derecha), revisa la lista del pedido y, si está correcto, da a '<strong>Hacer pedido</strong>'. Verás tu ticket de compra con el enlace de pago.</p>
</div>
<p><em>La presente aplicación es de uso exclusivo para pedidos de mercantes (usuarios de AlMercáu con alta presencial).</em></p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('instructions-toggle');
    const content = document.getElementById('instructions-content');
    const arrow = document.getElementById('instructions-arrow');

    function toggleInstructions() {
        const isHidden = content.style.display === 'none';
        content.style.display = isHidden ? 'block' : 'none';
        arrow.textContent = isHidden ? '▾' : '▸';
        toggle.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
    }

    if (toggle) {
        toggle.addEventListener('click', toggleInstructions);
        toggle.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                toggleInstructions();
            }
        });
    }
});
</script>

<?php
    include 'partials/cart-component.php';
    include 'partials/footer.php';
?>
</body>

</html>