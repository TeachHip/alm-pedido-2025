// ===== CART MANAGEMENT SYSTEM =====

// Cart state
let cart = [];
let productQuantities = {};

// Load cart from localStorage (with timestamp check)
const loadCartFromStorage = () => {
    try {
        const stored = localStorage.getItem('cart');
        if (!stored) return [];

        const cartData = JSON.parse(stored);

        // Check if new format with timestamp
        if (cartData.items && cartData.lastUpdated) {
            // Check if cart is older than 48 hours (172800000 ms)
            const age = Date.now() - cartData.lastUpdated;
            if (age > 172800000) {
                // Cart expired, clear it
                localStorage.removeItem('cart');
                return [];
            }
            return cartData.items;
        }

        // Old format (array), migrate it
        if (Array.isArray(cartData)) {
            return cartData;
        }

        return [];
    } catch (e) {
        console.error('Error loading cart:', e);
        return [];
    }
};

cart = loadCartFromStorage();

// ===== INITIALIZATION =====

/**
 * Clean up function - remove invalid cart items
 */
function cleanupCart() {
    // Remove items with invalid data
    cart = cart.filter(item => item.id && item.name && item.price && item.quantity > 0);
    saveCart();
}

/**
 * Get cart from cookie
 */
function getCookieCart() {
    const cookies = document.cookie.split(';');
    for (let cookie of cookies) {
        const [name, value] = cookie.trim().split('=');
        if (name === 'cart') {
            try {
                return JSON.parse(decodeURIComponent(value));
            } catch(e) {
                return [];
            }
        }
    }
    return [];
}

/**
 * Initialize the application
 */
function initializeApp() {
    // Check if cookie and localStorage are in sync
    const cookieCart = getCookieCart();
    const wasInSync = JSON.stringify(cart) === JSON.stringify(cookieCart);
    if (!wasInSync) {
        // Cookie is out of sync, resync it (localStorage wins -- it has the
        // longer 48h staleness window vs. the cookie's 24h expiry, so an
        // empty/expired cookie must never overwrite a still-valid cart).
        syncCartWithPHP();
    }

    cleanupCart();
    updateCartCount();
    const whatsappBtn = document.querySelector('.whatsapp-btn');
    if (whatsappBtn) whatsappBtn.style.display = 'block';
    refreshCartCookie();

    // cart-page.php already server-renders the cart from the cookie (with
    // equivalent onclick handlers baked in) -- only re-render client-side
    // when there was an actual mismatch to correct. Re-rendering
    // unconditionally on every load meant the customer could see the cart
    // visibly change right after the page finished painting, even when
    // cookie and localStorage already agreed (the common case).
    if (document.getElementById('cart-items') && !wasInSync) {
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
    const cartData = {
        items: cart,
        lastUpdated: Date.now()
    };
    const expirationDate = new Date();
    expirationDate.setDate(expirationDate.getDate() + 1);
    document.cookie = `cart=${encodeURIComponent(JSON.stringify(cartData))}; expires=${expirationDate.toUTCString()}; path=/; samesite=lax`;
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
    showAddedToCartToast(name, quantity);
}

/**
 * Brief fading confirmation shown above the page (product.php and
 * section.php both funnel through addToCart(), so one call here covers
 * every add-to-cart path -- no separate toast trigger needed per page).
 */
function showAddedToCartToast(name, quantity) {
    let toast = document.getElementById('add-to-cart-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'add-to-cart-toast';
        toast.className = 'add-to-cart-toast';
        document.body.appendChild(toast);
    }

    toast.textContent = `✓ ${quantity}x ${name} añadido al carrito`;

    // Restart the fade-in even on rapid repeated clicks (force a reflow
    // between removing and re-adding the visible class).
    toast.classList.remove('add-to-cart-toast-visible');
    void toast.offsetWidth;
    toast.classList.add('add-to-cart-toast-visible');

    clearTimeout(showAddedToCartToast._timer);
    showAddedToCartToast._timer = setTimeout(() => {
        toast.classList.remove('add-to-cart-toast-visible');
    }, 2000);
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

        // Hide "Vaciar carrito" link without reload
        const emptyCartLink = document.querySelector('.empty-cart-link');
        if (emptyCartLink) {
            emptyCartLink.style.display = 'none';
        }
        // Removed the second alert here
    }
}

/**
 * Save cart to localStorage and sync with PHP
 */
function saveCart() {
    const cartData = {
        items: cart,
        lastUpdated: Date.now()
    };
    localStorage.setItem('cart', JSON.stringify(cartData));
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

    if (!cartItems) return;
    document.querySelector('.whatsapp-btn').style.display = 'block';

    if (cart.length === 0) {
        showEmptyCart(cartItems, cartTotal);
        return;
    }

    showCartItems(cartItems, cartTotal);
}

/**
 * Display empty cart state
 */
function showEmptyCart(cartItems, cartTotal) {
    cartItems.innerHTML = `
        <div class="empty-cart">
            <p>Tu carrito está vacío</p>
            <a href="index.php" class="btn">Continuar comprando</a>
        </div>
    `;

    if (cartTotal) cartTotal.textContent = '0.00';
}

/**
 * Display cart items and totals
 */
function showCartItems(cartItems, cartTotal) {
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

    // Pedido Expres cart fee
    const feeAmount = window.pedidoExpresFeeAmount || 0;
    const feeProductIds = window.pedidoExpresProductIds || [];
    if (feeAmount > 0 && feeProductIds.length > 0) {
        const cartHasFeeProduct = cart.some(item => {
            const numericId = parseInt(String(item.id).replace('product-', ''), 10);
            return feeProductIds.includes(numericId);
        });
        if (cartHasFeeProduct) {
            total += feeAmount;
            itemsHtml += `
                    <div class="cart-item-name">${window.pedidoExpresFeeLabel || ''}</div>
            <div class="cart-item cart-fee-item">
                <div class="cart-item-info">
                    <div class="cart-item-total">${feeAmount.toFixed(2)}€</div>
                </div>
            </div>
        `;
        }
    }

    cartItems.innerHTML = itemsHtml;
    document.querySelector('.whatsapp-btn').style.display = 'block';

    if (cartTotal) cartTotal.textContent = total.toFixed(2);
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
    resetProductQuantity(productId);
}

/**
 * Reset product quantity to 1 after adding to cart
 */
function resetProductQuantity(productId) {
    productQuantities[productId] = 1;
    const quantityElement = document.getElementById('quantity-' + productId);
    if (quantityElement) quantityElement.textContent = '1';
}

// ===== PRODUCT OPTIONS (variants) =====
// The server resolves every option into a purchasable "cart line" (id, name,
// price, image, priceHtml) embedded as data-line on each <option> — see
// includes/PriceHelper.php's resolveCartLines(). These two functions just
// read that data; no price/name logic is duplicated here.

/**
 * Update the visible price block to match the currently selected option.
 */
function updateOptionPriceDisplay(productId) {
    const select = document.getElementById('option-select-' + productId);
    if (!select) return;
    const line = JSON.parse(select.options[select.selectedIndex].dataset.line);
    const display = document.getElementById('price-display-' + productId);
    if (display) display.innerHTML = line.priceHtml;
}

/**
 * Add to cart using whichever option is currently selected in the product's
 * dropdown. Mirrors addToCartFromProduct/addToCartFromSection; resetQuantity
 * matches addToCartFromSection's behavior (true) vs addToCartFromProduct's (false).
 */
function addToCartFromOptions(productId, selectId, resetQuantity) {
    const select = document.getElementById(selectId);
    if (!select) return;
    const line = JSON.parse(select.options[select.selectedIndex].dataset.line);
    const quantityKey = 'product-' + productId;
    const quantity = productQuantities[quantityKey] || 1;
    addToCart(line.id, line.name, line.price, line.image, quantity);
    if (resetQuantity) resetProductQuantity(quantityKey);
}

// Also add this separate function for the button click
// Submit the cart as an order and redirect to my-orders.php
async function sendWhatsAppMessage() {
    // Get cart from localStorage
    const cartJson = localStorage.getItem('cart');
    if (!cartJson) {
        alert('Tu carrito está vacío');
        return false;
    }

    let cartData;
    let cartItems;
    try {
        cartData = JSON.parse(cartJson);
        // Handle new format with timestamp
        if (cartData.items) {
            cartItems = cartData.items;
        } else {
            // Old format
            cartItems = cartData;
        }
    } catch (e) {
        alert('Error al leer el carrito');
        return false;
    }

    if (!cartItems || cartItems.length === 0) {
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
                body: JSON.stringify({ items: cartItems })
            });

            const result = await response.json();

            if (!result.success) {
                if (result.requires_login) {
                    const returnTo = window.location.pathname + window.location.search;
                    window.location.href = 'member-login.php?return_to=' + encodeURIComponent(returnTo);
                    return false;
                }
                throw new Error(result.error || 'Error al guardar el pedido');
            }

            // The cart itself saved, but ticket/invoice creation can still
            // fail server-side (fail-soft by design). If it did, my-orders.php
            // would show nothing at all (it only reads from `invoices`) --
            // don't silently clear the cart and send the customer to a page
            // that looks like their order vanished. Leave the cart intact so
            // they still have something to fall back on (retry, or contact
            // AlMercáu directly) and re-enable the button instead.
            if (!result.ticket_created) {
                alert('Tu pedido se ha recibido, pero hubo un problema generando el ticket. Contacta con AlMercáu para confirmar tu pedido.');
                btn.textContent = originalText;
                btn.disabled = false;
                return false;
            }

            // The cart's job is done once the order is submitted -- it's now
            // an invoice, tracked server-side and shown on my-orders.php, not
            // a cart concern anymore. Clear it immediately (not gated on
            // payment) so a later visit to index.php/cart-page.php shows a
            // genuinely empty cart instead of the already-submitted items
            // sitting there inviting a duplicate order.
            cart = [];
            saveCart();

            // Order/payment confirmation comes first now, not WhatsApp --
            // WhatsApp is a decorative, optional touch, only offered once
            // the invoice is actually paid (partials/invoice-card.php builds
            // that link itself from the paid invoice, not from anything
            // passed here -- see my-orders.php / ticket.php).
            window.location.href = 'my-orders.php';

        } catch (error) {
            console.error('Error:', error);
            alert('Error al procesar el pedido: ' + error.message);
            btn.textContent = originalText;
            btn.disabled = false;
        }
    }

    return false;
}


// Initialize application when DOM is loaded
document.addEventListener('DOMContentLoaded', initializeApp);