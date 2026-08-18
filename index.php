<?php
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

<!-- Order Confirmation Banner -->
<div id="order-confirmation-banner" style="display: none; background: #4CAF50; color: white; padding: 20px; margin: 20px auto; max-width: 600px; border-radius: 8px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.2);">
    <h3 style="margin: 0 0 10px 0; font-size: 20px;">✅ Pedido realizado<br><span style="font-weight: normal;">(si enviaste el whatsapp)</span></h3>
    <p style="margin: 0 0 5px 0; font-size: 16px;">Pedido: <strong><span id="order-ticket"></span></strong></p>
    <p style="margin: 0 0 5px 0; font-size: 14px;">Recibirás confirmación por WhatsApp</p>
    <p id="order-ticket-link-wrap" style="display: none; margin: 0 0 15px 0; font-size: 14px;">
        <a id="order-ticket-link" href="#" style="color: white; font-weight: bold;">Ver ticket de compra</a>
    </p>
    <button onclick="dismissOrderConfirmation()" style="background: white; color: #4CAF50; border: none; padding: 10px 25px; border-radius: 5px; cursor: pointer; font-weight: bold; font-size: 14px;">
        Cerrar y vaciar carrito
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

                    // Ticket link -- points at the ticket de compra page,
                    // which has the payment link (SMS deliberately doesn't
                    // carry it directly, see includes/InvoiceHelper.php).
                    if (order.ticketUrl) {
                        const linkWrap = document.getElementById('order-ticket-link-wrap');
                        const link = document.getElementById('order-ticket-link');
                        if (linkWrap && link) {
                            link.href = order.ticketUrl;
                            linkWrap.style.display = 'block';
                        }
                    }

                    // Hide "Vaciar carrito" link when banner is showing
                    const emptyCartLink = document.querySelector('.empty-cart-link');
                    if (emptyCartLink) {
                        emptyCartLink.style.display = 'none';
                    }
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

    // Clear cart silently (no confirmation needed since order already placed)
    if (typeof cart !== 'undefined') {
        cart = [];
        const cartData = {
            items: [],
            lastUpdated: Date.now()
        };
        localStorage.setItem('cart', JSON.stringify(cartData));

        // Update cookie
        const expirationDate = new Date();
        expirationDate.setDate(expirationDate.getDate() + 1);
        document.cookie = `cart=${encodeURIComponent(JSON.stringify(cartData))}; expires=${expirationDate.toUTCString()}; path=/; samesite=lax`;

        // Update cart count
        if (typeof updateCartCount === 'function') {
            updateCartCount();
        }

        // Reload page to hide "Vaciar carrito" link
        window.location.reload();
    }
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
<p>
    <strong id="instructions-toggle" class="instructions-toggle" role="button" tabindex="0" aria-expanded="false" aria-controls="instructions-content">INSTRUCCIONES <span id="instructions-arrow">▸</span></strong>
</p>
<div id="instructions-content" style="display: none;">
<p>Selecciona qué producto quieres, indica qué cantidad deseas y pulsa '<strong>Al carro!</strong>'. Cuando acabes de pedir cada producto, ve al carro (abajo a la derecha), revisa la lista del pedido y, si está correcto, da a '<strong>Enviar por whatsapp</strong>' y acaba de enviar el mensaje y hacer el pago en el enlace que recibirás en tu teléfono.</p>
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