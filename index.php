<?php
// Load database repository
require_once 'includes/SectionRepository-DB.php';

try {
    $sectionRepo = new SectionRepository();
    $sections = $sectionRepo->getAllVisibleActive();
} catch (Exception $e) {
    error_log("Error loading sections: " . $e->getMessage());
    $sections = [];
}

// Include 00.php for cart functionality - cookie
include 'assets/00.php';

$pageTitle = 'AlMercáu - Carro de la compra para mercantes';

//START HTML
?>
<?php include 'assets/head.php'; ?>
<?php include 'assets/header.php'; ?>


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
<p><strong>INSTRUCCIONES</strong>. Selecciona qué producto quieres, indica qué cantidad deseas y pulsa '<strong>Al carro!</strong>'. Cuando acabes de pedir cada producto, ve al carro (abajo a la derecha), revisa la lista del pedido y, si está correcto, da a '<strong>Enviar por whatsapp</strong>'.</p>
<p><em>La presente aplicación sólo gestiona los pedidos de los miembros de AlMercáu. Uso exclusivo de mercantes (socias).</em></p>
</div>

<?php
    include 'assets/cart-component.php';
    include 'assets/footer.php';
?>
<script src="assets/script.js"></script>
</body>

</html>