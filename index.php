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

<!-- Order Confirmation Banner -->
<div id="order-confirmation-banner" style="display: none; background: #4CAF50; color: white; padding: 20px; margin: 20px auto; max-width: 600px; border-radius: 8px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
    <h3 style="margin: 0 0 10px 0; font-size: 20px;">✅ Pedido realizado</h3>
    <p style="margin: 0 0 5px 0; font-size: 16px;">Ticket: <strong><span id="order-ticket"></span></strong></p>
    <p style="margin: 0 0 15px 0; font-size: 14px;">Recibirás confirmación por WhatsApp</p>
    <button onclick="dismissOrderConfirmation()" style="background: white; color: #4CAF50; border: none; padding: 10px 25px; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 14px;">
        Cerrar
    </button>
</div>

<script>
// Check for recent order confirmation on page load
document.addEventListener('DOMContentLoaded', function() {
    const lastOrder = localStorage.getItem('last_order');
    
    if (lastOrder) {
        try {
            const order = JSON.parse(lastOrder);
            const ageMinutes = (Date.now() - order.timestamp) / 1000 / 60;
            
            // If order was placed in last 5 minutes, show confirmation
            if (ageMinutes < 5) {
                const banner = document.getElementById('order-confirmation-banner');
                const ticketSpan = document.getElementById('order-ticket');
                if (banner && ticketSpan) {
                    ticketSpan.textContent = order.ticket;
                    banner.style.display = 'block';
                }
            }
            
            // Clean up old order reference
            if (ageMinutes > 60) {
                localStorage.removeItem('last_order');
            }
        } catch (e) {
            console.error('Error parsing last order:', e);
            localStorage.removeItem('last_order');
        }
    }
});

function dismissOrderConfirmation() {
    const banner = document.getElementById('order-confirmation-banner');
    if (banner) {
        banner.style.display = 'none';
    }
    localStorage.removeItem('last_order');
}
</script>


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