// ===== CART MANAGEMENT SYSTEM =====

// Cart state
let cart = JSON.parse(localStorage.getItem('cart')) || [];
let productQuantities = {};

// ===== INITIALIZATION =====

/**
 * Initialize the application
 */
function initializeApp() {
    updateCartCount();
    document.querySelector('.whatsapp-btn').style.display = 'block'; //v11 4tres
    refreshCartCookie();

    if (document.getElementById('cart-items')) {
        updateCartDisplay();
    }
}

/**
 * Update cart count display in floating cart icon
 */
function updateCartCount() {
    const count = cart.reduce((total, item) => total + (item.quantity || 0), 0);
    const cartCount = document.getElementById('cart-count');
    if (cartCount) cartCount.textContent = count;
}

/**
 * Sync cart with PHP cookie (24-hour expiration)
 */
function syncCartWithPHP() {
    const expirationDate = new Date();
    expirationDate.setDate(expirationDate.getDate() + 1);
    document.cookie = `cart=${JSON.stringify(cart)}; expires=${expirationDate.toUTCString()}; path=/; samesite=lax`;
}

/**
 * Refresh cookie expiration on page load
 */
function refreshCartCookie() {
    if (cart.length > 0) syncCartWithPHP();
}

// ===== CART OPERATIONS =====

/**
 * Add product to cart or update quantity if already exists
 */
function addToCart(id, name, price, image, quantity = 1) {
    const existingItem = cart.find(item => item.id === id);

    if (existingItem) {
        existingItem.quantity += quantity;
    } else {
        cart.push({ id, name, price, image, quantity });
    }

    saveCart();
    //4tres
    //showNotification(`${quantity}x ${name} añadido al carrito`);
}

/**
 * Update product quantity in cart (remove if quantity < 1)
 */
function updateQuantity(id, newQuantity) {
    if (newQuantity < 1) {
        cart = cart.filter(item => item.id !== id);
    } else {
        const item = cart.find(item => item.id === id);
        if (item) item.quantity = newQuantity;
    }

    saveCart();
    updateCartDisplay();
}

/**
 * Clear entire cart with confirmation
 */
function clearCart() {
    if (cart.length === 0) {
        alert('Tu carrito ya está vacío');
        return;
    }

    if (confirm('¿Seguro que quieres vaciar tu carrito?')) {
        cart = [];
        saveCart();
        updateCartDisplay();
        // Removed the second alert here
    }
}

/**
 * Save cart to localStorage and sync with PHP
 */
function saveCart() {
    localStorage.setItem('cart', JSON.stringify(cart));
    syncCartWithPHP();
    updateCartCount();
}

// ===== CART DISPLAY =====

/**
 * Update cart display on cart page
 */
function updateCartDisplay() {
    const cartItems = document.getElementById('cart-items');
    const cartTotal = document.getElementById('cart-total');
    const whatsappLink = document.getElementById('whatsapp-link');

    if (!cartItems) return;
    document.querySelector('.whatsapp-btn').style.display = 'block'; //v11 4tres

    if (cart.length === 0) {
        showEmptyCart(cartItems, cartTotal, whatsappLink);
        return;
    }

    showCartItems(cartItems, cartTotal, whatsappLink);
}

/**
 * Display empty cart state
 */
function showEmptyCart(cartItems, cartTotal, whatsappLink) {
    cartItems.innerHTML = `
        <div class="empty-cart">
            <p>Tu carrito está vacío</p>
            <a href="index.php" class="btn">Continuar comprando</a>
        </div>
    `;

    if (cartTotal) cartTotal.textContent = '0.00';
    if (whatsappLink) whatsappLink.style.display = 'none';
}

/**
 * Display cart items and totals
 */
function showCartItems(cartItems, cartTotal, whatsappLink) {
    let total = 0;
    let itemsHtml = '';

    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        total += itemTotal;

        itemsHtml += `
                    <div class="cart-item-name">${item.name}</div>
            <div class="cart-item">
                <img src="${item.image}" alt="${item.name}" class="cart-item-image"
                     onerror="this.src='https://placehold.co/80x80/25D366/ffffff?text=Imagen'">
                <div class="cart-item-info">
                    <div class="cart-item-price">${item.price.toFixed(2)}€ unidad</div>
                    <div class="cart-item-total">Total: ${itemTotal.toFixed(2)}€</div>
                </div>
            <div class="cart-item-quantity-container">
                <button class="quantity-btn" onclick="updateQuantity('${item.id}', ${item.quantity - 1})">-</button>
                <span class="quantity-value">${item.quantity}</span>
                <button class="quantity-btn" onclick="updateQuantity('${item.id}', ${item.quantity + 1})">+</button>
            </div>
            </div>
        `;
    });

    cartItems.innerHTML = itemsHtml;
    document.querySelector('.whatsapp-btn').style.display = 'block'; //v11 4tres

    if (cartTotal) cartTotal.textContent = total.toFixed(2);
    if (whatsappLink) updateWhatsAppLink(whatsappLink, total);
}



// ===== PRODUCT QUANTITY MANAGEMENT =====

/**
 * Update product quantity before adding to cart
 */
function updateProductQuantity(productId, change) {
    if (!productQuantities[productId]) {
        productQuantities[productId] = 1;
    }

    productQuantities[productId] = Math.max(1, productQuantities[productId] + change);

    const quantityElement = document.getElementById('quantity-' + productId);
    if (quantityElement) {
        quantityElement.textContent = productQuantities[productId];
    }
}

/**
 * Add to cart from section page with current quantity
 */
function addToCartFromSection(productId, name, price, image) {
    const quantity = productQuantities[productId] || 1;
    addToCart(productId, name, price, image, quantity);
    resetProductQuantity(productId);
}

/**
 * Add to cart from product page with current quantity
 */
function addToCartFromProduct(productId, name, price, image) {
    const quantity = productQuantities[productId] || 1;
    addToCart(productId, name, price, image, quantity);
}

/**
 * Reset product quantity to 1 after adding to cart
 */
function resetProductQuantity(productId) {
    productQuantities[productId] = 1;
    const quantityElement = document.getElementById('quantity-' + productId);
    if (quantityElement) quantityElement.textContent = '1';
}

// ===== WHATSAPP FUNCTION =====
function updateWhatsAppLink(whatsappLink, total) {
    if (!whatsappLink) return;

    if (cart.length === 0) {
        whatsappLink.style.display = 'none';
        return;
    }

    // Build the WhatsApp message
    const itemsText = cart.map(item =>
        `${item.quantity}x ${item.name} - ${(item.price * item.quantity).toFixed(2)}€`
    ).join('%0A');

    const message = `¡Hola! Me interesan los siguientes productos:%0A%0A${itemsText}%0A%0ATotal: ${total.toFixed(2)}€%0A%0A¡Gracias!`;

    // Use web.whatsapp.com instead of wa.me
    const phoneNumber = "34611183123"; // Your number

    whatsappLink.href = `https://web.whatsapp.com/send?phone=${phoneNumber}&text=${message}`;
    whatsappLink.target = '_blank';
    whatsappLink.style.display = 'block';
}

// Also add this separate function for the button click
// NEW: Save cart to database and open WhatsApp with ticket number
async function sendWhatsAppMessage() {
    // Get cart from localStorage
    const cartJson = localStorage.getItem('cart');
    if (!cartJson) {
        alert('Tu carrito está vacío');
        return false;
    }

    let cart;
    try {
        cart = JSON.parse(cartJson);
    } catch (e) {
        alert('Error al leer el carrito');
        return false;
    }

    if (!cart || cart.length === 0) {
        alert('Tu carrito está vacío');
        return false;
    }

    // Show loading on button
    const btn = document.querySelector('.whatsapp-btn');
    if (btn) {
        const originalText = btn.textContent;
        btn.textContent = 'Guardando pedido...';
        btn.disabled = true;

        try {
            // Save cart to database
            const response = await fetch('save-cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ items: cart })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Error al guardar el pedido');
            }

            // Store order info in localStorage
            localStorage.setItem('last_order', JSON.stringify({
                id: result.cart_id,
                ticket: result.ticket,
                timestamp: Date.now()
            }));

            // Clear cart from localStorage BEFORE opening WhatsApp
            localStorage.removeItem('cart');

            // Generate WhatsApp message with ticket number
            let message = `🛒 Pedido ${result.ticket}%0A%0A`;
            
            cart.forEach(item => {
                const itemTotal = (item.price * item.quantity).toFixed(2);
                message += `- ${item.name} (${item.quantity}x) - ${itemTotal}€%0A`;
            });

            message += `%0ATotal: ${result.total.toFixed(2)}€`;

            const phoneNumber = "34611183123"; // AlMercáu WhatsApp

            // Create WhatsApp URL
            const whatsappURL = `https://api.whatsapp.com/send?phone=${phoneNumber}&text=${message}`;

            // Open WhatsApp in new window/tab (doesn't close current page)
            window.open(whatsappURL, '_blank');
            
            // Redirect to homepage to show confirmation
            setTimeout(() => {
                window.location.href = 'index.php';
            }, 1000);

        } catch (error) {
            console.error('Error:', error);
            alert('Error al procesar el pedido: ' + error.message);
            btn.textContent = originalText;
            btn.disabled = false;
        }
    }
    
    return false;
}


// ===== HELPER FUNCTIONS =====

/**
 * Show notification to user (simple alert)
 */
function showNotification(message) {
    alert(message);
}

// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', initializeApp);