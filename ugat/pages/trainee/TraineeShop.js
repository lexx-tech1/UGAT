/* TraineeShop.js — UGAT TrainTrack
   Features:
   - Products from DB (inventory)
   - Shopee-style address selector (auto-fill default, modal to change/add)
   - Weight-based + zone shipping calculation
   - COD / GCash payment
   - Real order saving to DB
   - My Orders tab with live status from DB
================================================================= */

/* ── STATE ─────────────────────────────────────────────────── */
let PRODUCTS      = [];
let cart          = JSON.parse(localStorage.getItem('ugat_cart') || '[]');
let shopFilter    = 'all';
let userAddresses = [];
let selectedAddr  = null;   // the address object selected for this order
let shippingFee   = 50;
let shippingZone  = '';
let shippingFree  = false;
let UGAT_GCASH        = '09XX XXX XXXX';
let UGAT_GCASH_NAME   = 'UGAT Integrated Farm';
let UGAT_GCASH_QR     = '';

/* ── PRODUCT RATINGS CACHE ─────────────────────────────────── */
let PRODUCT_RATINGS = {}; // keyed by numeric product id

/* ── INIT ──────────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', async function () {
    await loadProducts();
    await loadUserAddresses();
    updateCartBadge();
});

/* ── PERSIST CART ──────────────────────────────────────────── */
function saveCart() {
    localStorage.setItem('ugat_cart', JSON.stringify(cart));
}

/* ── LOAD PRODUCTS ─────────────────────────────────────────── */
async function loadProducts() {
    try {
        const r = await fetch('get_shop_products.php?t=' + Date.now());
        const d = await r.json();
        PRODUCTS = d.success && Array.isArray(d.products) ? d.products : [];
        if (d.gcash_number)       UGAT_GCASH      = d.gcash_number;
        if (d.gcash_account_name) UGAT_GCASH_NAME = d.gcash_account_name;
        if (d.gcash_qr_path)      UGAT_GCASH_QR   = d.gcash_qr_path;
    } catch (e) {
        console.error('Failed to load products:', e);
        PRODUCTS = [];
    }
    renderShop();
}

/* ── LOAD USER ADDRESSES ───────────────────────────────────── */
async function loadUserAddresses() {
    try {
        const r = await fetch('get_user_addresses.php?t=' + Date.now());
        const d = await r.json();
        if (d.success && d.addresses.length > 0) {
            userAddresses = d.addresses;
            selectedAddr  = userAddresses.find(a => a.is_default) || userAddresses[0];
        }
    } catch (e) {
        console.error('Failed to load addresses:', e);
    }
}

/* ── TAB SWITCHING ─────────────────────────────────────────── */
function switchShopTab(tab, btn) {
    document.querySelectorAll('.pm-tab').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    ['shop', 'cart', 'orders'].forEach(t => {
        const el = document.getElementById('stab-' + t);
        if (el) el.style.display = t === tab ? '' : 'none';
    });
    if (tab === 'cart')   renderCart();
    if (tab === 'orders') loadAndRenderOrders();

    const titles = { shop: 'Farm Shop', cart: 'My Cart', orders: 'My Orders' };
    const subs   = {
        shop:   'Browse and order farm products and training kits from UGAT Integrated Farm',
        cart:   'Review your items before placing an order',
        orders: 'Track your orders from UGAT Integrated Farm',
    };
    document.getElementById('shop-page-title').textContent = titles[tab] || '';
    document.getElementById('shop-page-sub').textContent   = subs[tab]   || '';
}

/* ── SHOP TAB ──────────────────────────────────────────────── */
function filterShop(cat, btn) {
    shopFilter = cat;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    renderShop();
}

function getProductImage(p) {
    if (p.image) return '../../' + p.image;
    return 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400&q=80';
}

function renderShop() {
    const q    = (document.getElementById('shop-search')?.value || '').toLowerCase();
    const sort = document.getElementById('shop-sort')?.value || 'newest';
    let data   = [...PRODUCTS];

    if (shopFilter !== 'all') data = data.filter(p => p.cat === shopFilter);
    if (q) data = data.filter(p =>
        p.name.toLowerCase().includes(q) || (p.desc || '').toLowerCase().includes(q)
    );

    data.sort((a, b) => {
        if (sort === 'price-asc')  return a.price - b.price;
        if (sort === 'price-desc') return b.price - a.price;
        if (sort === 'name')       return a.name.localeCompare(b.name);
        return 0;
    });

    const farm = data.filter(p => p.cat === 'farm');
    const kits = data.filter(p => p.cat === 'kit');

    let html = '';
    if (farm.length && shopFilter !== 'kit') {
        html += '<h3 class="shop-section-title">Farm products</h3><div class="shop-grid">' + farm.map(productCard).join('') + '</div>';
    }
    if (kits.length && shopFilter !== 'farm') {
        html += '<h3 class="shop-section-title">Training Kits</h3><div class="shop-grid">' + kits.map(productCard).join('') + '</div>';
    }
    if (!html) {
        html = '<p class="light-txt" style="padding:2rem;text-align:center">No products found.</p>';
    }
    document.getElementById('shop-product-list').innerHTML = html;
}

function ratingStars(avg, reviewCount, totalSold) {
    const parts = [];
    if (avg && reviewCount) {
        const filled = Math.round(avg);
        const stars  = '★'.repeat(filled) + '☆'.repeat(5 - filled);
        parts.push(`<span style="color:#f4a523;font-size:0.85rem;line-height:1">${stars}</span>`);
        parts.push(`<span style="font-size:var(--text-tiny);color:#888">${avg} (${reviewCount})</span>`);
    }
    if (totalSold > 0) {
        parts.push(`<span style="font-size:var(--text-tiny);color:#aaa">${totalSold} sold</span>`);
    }
    if (!parts.length) return '';
    return `<div style="display:flex;align-items:center;gap:0.25rem;flex-wrap:wrap;margin-bottom:0.35rem">${parts.join('')}</div>`;
}

function productCard(p) {
    const lowThreshold = p.maxStock * 0.2;
    const stockStatus  = p.stock === 0 ? 'out' : p.stock <= lowThreshold ? 'low' : 'ok';
    const disabled     = p.stock === 0;
    const imgSrc       = getProductImage(p);
    const stockBg      = stockStatus === 'out' ? '#fff0f0' : stockStatus === 'low' ? '#fffbea' : '#f0f7ec';
    const stockColor   = stockStatus === 'out' ? '#c0392b' : stockStatus === 'low' ? '#d6901e' : '#4B8423';
    const stockBorder  = stockStatus === 'out' ? '#f5c6c6' : stockStatus === 'low' ? '#f7d98a' : '#c3e0b0';
    const stockIcon    = stockStatus === 'out' ? '✕' : stockStatus === 'low' ? '⚠' : '✓';
    const stockText    = stockStatus === 'out' ? 'Out of Stock' : stockStatus === 'low' ? 'Low Stock' : 'In Stock';
    const stockNumBg   = stockStatus === 'out' ? '#fde8e8' : stockStatus === 'low' ? '#fef3cd' : '#e8f5e9';
    const stockNumClr  = stockStatus === 'out' ? '#c0392b' : stockStatus === 'low' ? '#d6901e' : '#2e7d32';

    return '<div class="product-card" onclick="viewProduct(\'' + p.id + '\')">' +
        '<div class="product-card-img-wrap" style="position:relative">' +
            '<img src="' + imgSrc + '" class="product-card-img" alt="' + p.name + '" onerror="this.src=\'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400\'">' +
            '<div style="position:absolute;top:0.5rem;right:0.5rem;background:' + stockBg + ';border:1.5px solid ' + stockBorder + ';border-radius:20px;padding:0.2rem 0.6rem;display:flex;align-items:center;gap:0.3rem;box-shadow:0 1px 4px rgba(0,0,0,0.08)">' +
                '<span style="font-size:10px;font-weight:800;color:' + stockColor + '">' + stockIcon + ' ' + stockText + '</span>' +
            '</div>' +
        '</div>' +
        '<div class="product-card-body">' +
            '<p class="product-card-type">' + p.type + '</p>' +
            '<h4 class="product-card-name">' + p.name + '</h4>' +
            ratingStars(p.avg_rating, p.review_count, p.total_sold) +
            '<p class="product-card-desc">' + (p.desc || '') + '</p>' +
            '<div style="display:flex;align-items:center;justify-content:space-between;margin:0.5rem 0">' +
                '<p class="product-card-price" style="margin:0">₱' + p.price.toLocaleString() + '.00</p>' +
                '<div style="display:flex;align-items:center;gap:0.35rem;background:' + stockNumBg + ';border-radius:8px;padding:0.3rem 0.65rem;border:1px solid ' + stockBorder + '">' +
                    '<span style="font-size:var(--text-tiny);color:' + stockNumClr + ';font-weight:600">Stock</span>' +
                    '<span style="font-size:1rem;font-weight:800;color:' + stockNumClr + ';line-height:1">' + p.stock + '</span>' +
                    '<span style="font-size:var(--text-tiny);color:' + stockNumClr + ';font-weight:500">' + p.unit + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="product-card-actions" onclick="event.stopPropagation()">' +
                '<button class="card-btn-outline"' + (disabled ? ' disabled style="opacity:0.45;cursor:not-allowed"' : '') + ' onclick="addToCart(\'' + p.id + '\')">Add to Cart</button>' +
                '<button class="card-btn-primary"' + (disabled ? ' disabled style="opacity:0.45;cursor:not-allowed"' : '') + ' onclick="buyNow(\'' + p.id + '\')">Buy Now</button>' +
            '</div>' +
        '</div>' +
    '</div>';
}

async function viewProduct(id) {
    const p = PRODUCTS.find(x => x.id === id);
    if (!p) return;
    const lowThreshold = p.maxStock * 0.2;
    const stockStatus  = p.stock === 0 ? 'out' : p.stock <= lowThreshold ? 'low' : 'ok';
    const stockLabel   = p.stock === 0 ? 'Out of stock'
        : p.stock <= lowThreshold ? 'Low stock — ' + p.stock + ' ' + p.unit + ' remaining'
        : 'In stock — ' + p.stock + ' ' + p.unit + ' available';
    const stockCls = stockStatus === 'out' ? 'stock-out' : stockStatus === 'low' ? 'stock-low' : 'stock-ok';
    const disabled = p.stock === 0;
    const imgSrc   = getProductImage(p);

    const numericId = parseInt(p.id.replace('prod-', ''));

    // Fetch reviews
    let revData = { avg_rating: 0, total: 0, reviews: [], can_review: false, my_review: null };
    try {
        const rr = await fetch('get_product_reviews.php?product_id=' + numericId);
        revData = await rr.json();
    } catch(e) {}

    const stars = n => '★'.repeat(Math.round(n)) + '☆'.repeat(5 - Math.round(n));
    const avgStars = revData.avg_rating > 0
        ? `<span style="color:#f4a523;font-size:1rem">${stars(revData.avg_rating)}</span> <span style="font-weight:700">${revData.avg_rating}</span> <span style="color:#888">(${revData.total} review${revData.total !== 1 ? 's' : ''})</span>`
        : '<span style="color:#aaa">No reviews yet</span>';

    const reviewList = revData.reviews.length ? revData.reviews.map(r =>
        `<div style="padding:0.6rem 0;border-bottom:1px solid #f0f0f0">
            <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.2rem">
                <span style="color:#f4a523;font-size:0.9rem">${stars(r.rating)}</span>
                <span style="font-weight:600;font-size:var(--text-caption)">${r.display_name}</span>
                <span style="color:#aaa;font-size:var(--text-tiny)">${r.date}</span>
            </div>
            ${r.comment ? `<p style="font-size:var(--text-caption);color:#555;margin:0">${r.comment}</p>` : ''}
        </div>`
    ).join('') : '<p style="color:#aaa;font-size:var(--text-caption);padding:0.5rem 0">No reviews yet. Be the first to review!</p>';

    const reviewFormHtml = revData.can_review ? `
        <div style="margin-top:0.75rem;padding:0.75rem;background:#f9fbf9;border-radius:var(--radius-sm);border:1.5px solid #c3e0b0">
            <p style="font-size:var(--text-caption);font-weight:700;margin-bottom:0.5rem">${revData.my_review ? 'Update Your Review' : 'Write a Review'}</p>
            <div style="display:flex;gap:0.3rem;margin-bottom:0.5rem" id="star-picker-${numericId}">
                ${[1,2,3,4,5].map(n =>
                    `<span onclick="pickStar(${numericId},${n})" id="star-${numericId}-${n}"
                        style="font-size:1.5rem;cursor:pointer;color:${revData.my_review && n <= revData.my_review.rating ? '#f4a523' : '#ddd'}">★</span>`
                ).join('')}
            </div>
            <input type="hidden" id="rev-rating-${numericId}" value="${revData.my_review?.rating || 0}">
            <textarea id="rev-comment-${numericId}" rows="2" class="form-input" placeholder="Share your experience…" style="margin-bottom:0.5rem;resize:vertical">${revData.my_review?.comment || ''}</textarea>
            <button class="btn-primary" style="font-size:var(--text-caption);padding:0.4rem 1rem" onclick="submitReview(${numericId})">Submit Review</button>
        </div>` : '';

    document.getElementById('pm-title').textContent = p.name;
    document.getElementById('pm-body').innerHTML =
        '<img src="' + imgSrc + '" style="width:100%;height:200px;object-fit:cover;border-radius:var(--radius-sm);margin-bottom:1rem" onerror="this.src=\'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=400\'">' +
        '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem">' +
            '<div><p style="font-size:var(--text-caption);font-weight:600;color:#4B8423;text-transform:uppercase">' + p.type + '</p>' +
            '<h4 style="font-size:1.1rem;font-weight:800">' + p.name + '</h4></div>' +
            '<span style="font-size:1.3rem;font-weight:800;color:#4B8423">₱' + p.price.toLocaleString() + '</span>' +
        '</div>' +
        '<div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.6rem;flex-wrap:wrap">' +
            avgStars +
            (p.total_sold > 0 ? '<span style="font-size:var(--text-tiny);color:#888;border-left:1px solid #e0e0e0;padding-left:0.75rem">' + p.total_sold + ' sold</span>' : '') +
        '</div>' +
        '<p style="font-size:var(--text-body-sm);color:var(--color-text-mid);margin-bottom:0.75rem">' + (p.desc || '') + '</p>' +
        '<div style="display:flex;flex-direction:column;gap:0.3rem;margin-bottom:1rem;background:#f9fbf9;padding:0.75rem;border-radius:var(--radius-sm)">' +
            '<div style="display:flex;justify-content:space-between;font-size:var(--text-caption)"><span>SKU</span><strong>' + (p.sku || '—') + '</strong></div>' +
            '<div style="display:flex;justify-content:space-between;font-size:var(--text-caption)"><span>Category</span><strong>' + p.type + '</strong></div>' +
            '<div style="display:flex;justify-content:space-between;font-size:var(--text-caption)"><span>Unit</span><strong>' + p.unit + '</strong></div>' +
            '<div style="display:flex;justify-content:space-between;font-size:var(--text-caption)"><span>Weight</span><strong>' + (p.weight_kg ? p.weight_kg + ' kg' : '—') + '</strong></div>' +
            '<div style="display:flex;justify-content:space-between;font-size:var(--text-caption)"><span>Availability</span><strong class="' + stockCls + '">' + stockLabel + '</strong></div>' +
        '</div>' +
        '<div style="display:flex;gap:0.5rem;margin-bottom:1rem">' +
            '<button class="btn-outline" style="flex:1"' + (disabled ? ' disabled' : '') + ' onclick="addToCart(\'' + p.id + '\');closeModal(\'product-modal\')">Add to Cart</button>' +
            '<button class="btn-primary" style="flex:1"' + (disabled ? ' disabled' : '') + ' onclick="buyNow(\'' + p.id + '\')">Buy Now</button>' +
        '</div>' +
        '<div style="border-top:1px solid #eee;padding-top:0.75rem">' +
            '<p style="font-weight:700;font-size:var(--text-body-sm);margin-bottom:0.5rem">Customer Reviews</p>' +
            reviewList +
            reviewFormHtml +
        '</div>';
    openModal('product-modal');
}

function pickStar(productId, n) {
    document.getElementById('rev-rating-' + productId).value = n;
    for (let i = 1; i <= 5; i++) {
        const s = document.getElementById('star-' + productId + '-' + i);
        if (s) s.style.color = i <= n ? '#f4a523' : '#ddd';
    }
}

async function submitReview(productId) {
    const rating  = parseInt(document.getElementById('rev-rating-' + productId)?.value || '0');
    const comment = document.getElementById('rev-comment-' + productId)?.value.trim() || '';
    if (!rating) { showToast('Please select a star rating.'); return; }
    try {
        const r = await fetch('submit_review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId, rating, comment }),
        });
        const d = await r.json();
        showToast(d.success ? '✅ ' + d.message : '❌ ' + d.message);
        if (d.success) closeModal('product-modal');
    } catch(e) {
        showToast('❌ Network error. Please try again.');
    }
}

/* ── CART ──────────────────────────────────────────────────── */
function addToCart(id) {
    const p = PRODUCTS.find(x => x.id === id);
    if (!p || p.stock === 0) return;
    const existing = cart.find(c => c.id === id);
    if (existing) {
        if (existing.qty < p.stock) existing.qty++;
        else { showToast('⚠ Maximum available stock reached'); return; }
    } else {
        cart.push({ id, qty: 1 });
    }
    saveCart();
    updateCartBadge();
    showToast('✅ ' + p.name + ' added to cart');
}

function buyNow(id) {
    addToCart(id);
    switchShopTab('cart', document.querySelector('[data-stab=cart]'));
}

function removeFromCart(id) {
    cart = cart.filter(c => c.id !== id);
    saveCart();
    updateCartBadge();
    renderCart();
}

function updateQty(id, delta) {
    const item = cart.find(c => c.id === id);
    const prod = PRODUCTS.find(p => p.id === id);
    if (!item || !prod) return;
    item.qty = Math.max(1, Math.min(item.qty + delta, prod.stock));
    saveCart();
    renderCart();
    updateCartBadge();
}

function updateCartBadge() {
    const total = cart.reduce((s, c) => s + c.qty, 0);
    const badge = document.getElementById('cart-count');
    const tabCt = document.getElementById('cart-tab-count');
    if (badge) badge.textContent = total;
    if (tabCt) tabCt.textContent = total > 0 ? ' (' + total + ')' : '';
}

function cartSubtotal() {
    return cart.reduce((s, c) => {
        const p = PRODUCTS.find(x => x.id === c.id);
        return s + (p ? p.price * c.qty : 0);
    }, 0);
}

function cartTotalWeight() {
    return cart.reduce((s, c) => {
        const p = PRODUCTS.find(x => x.id === c.id);
        return s + (p && p.weight_kg ? p.weight_kg * c.qty : 0);
    }, 0);
}
async function placeOrder() {
    if (!selectedAddr) {
        showToast('⚠ Please add a delivery address first.');
        openAddressModal();
        return;
    }
    if (!cart.length) {
        showToast('⚠ Your cart is empty.');
        return;
    }

    const payment = getSelectedPayment();

    const codSelected   = document.getElementById('pay-cod')?.classList.contains('selected');
    const gcashSelected = document.getElementById('pay-gcash')?.classList.contains('selected');
    if (!codSelected && !gcashSelected) {
        showToast('⚠ Please select a payment method (COD or GCash).');
        return;
    }

    const btn = document.getElementById('place-order-btn');
    if (btn) { btn.disabled = true; btn.textContent = 'Placing order…'; }

    const items = cart.map(c => ({
        product_id: parseInt(c.id.replace('prod-', '')),
        quantity:   c.qty,
    }));
    const orderPayload = {
        address_id: selectedAddr.id,
        items:      items,
        notes:      document.getElementById('co-notes')?.value.trim() || '',
    };

    // ── GCash ─────────────────────────────────────────────────
    if (payment === 'gcash') {
        const gcashMode = getGCashMode();

        // QR manual payment
        if (gcashMode === 'qr') {
            const gcashRef   = document.getElementById('gcash-ref-input')?.value.trim();
            const receiptFile = document.getElementById('gcash-receipt-file')?.files?.[0];

            if (!gcashRef) {
                showToast('⚠ Please enter the GCash reference number.');
                if (btn) { btn.disabled = false; btn.textContent = 'Place Order'; }
                return;
            }
            if (!receiptFile) {
                showToast('⚠ Please upload your GCash receipt.');
                if (btn) { btn.disabled = false; btn.textContent = 'Place Order'; }
                return;
            }

            if (btn) btn.textContent = 'Submitting…';
            const fd = new FormData();
            fd.append('address_id', selectedAddr.id);
            fd.append('items',      JSON.stringify(items));
            fd.append('notes',      document.getElementById('co-notes')?.value.trim() || '');
            fd.append('gcash_ref',  gcashRef);
            fd.append('receipt',    receiptFile);

            try {
                const r = await fetch('place_order_gcash_qr.php', { method: 'POST', body: fd });
                const d = await r.json();
                if (d.success) {
                    cart = []; saveCart(); updateCartBadge();
                    showOrderSuccess(d, 'gcash_qr');
                } else {
                    showToast('❌ ' + (d.message || 'Order failed.'));
                    if (btn) { btn.disabled = false; btn.textContent = 'Place Order'; }
                }
            } catch(e) {
                showToast('❌ Network error. Please try again.');
                if (btn) { btn.disabled = false; btn.textContent = 'Place Order'; }
            }
            return;
        }

        // Link mode → PayMongo
        if (btn) btn.textContent = 'Redirecting to GCash…';
        try {
            const r = await fetch('create_gcash_payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(orderPayload),
            });
            const d = await r.json();
            if (d.success && d.checkout_url) {
                cart = []; saveCart(); updateCartBadge();
                window.location.href = d.checkout_url;
            } else {
                showToast('❌ ' + (d.message || 'Could not start GCash payment.'));
                if (btn) { btn.disabled = false; btn.textContent = 'Place Order'; }
            }
        } catch (e) {
            showToast('❌ Network error. Please try again.');
            if (btn) { btn.disabled = false; btn.textContent = 'Place Order'; }
        }
        return;
    }

    // ── COD: original flow ────────────────────────────────────
    try {
        const r = await fetch('place_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...orderPayload, payment_method: 'cod' }),
        });
        const d = await r.json();

        if (d.success) {
            showOrderSuccess(d, 'cod');
        } else {
            showToast('❌ ' + (d.message || 'Order failed.'));
            if (btn) { btn.disabled = false; btn.textContent = 'Place Order'; }
        }
    } catch (e) {
        showToast('❌ Network error. Please try again.');
        if (btn) { btn.disabled = false; btn.textContent = 'Place Order'; }
    }
}

function showOrderSuccess(d, mode) {
    cart.forEach(c => {
        const p = PRODUCTS.find(x => x.id === c.id);
        if (p) p.stock = Math.max(0, p.stock - c.qty);
    });
    const orderNum = 'Order #' + String(d.order_id).padStart(6, '0');
    let msg;
    if (mode === 'gcash_qr') {
        msg = orderNum + ' submitted! Total: ₱' + Number(d.total).toLocaleString() + '. Your receipt has been uploaded — we\'ll confirm your payment shortly.';
    } else {
        msg = orderNum + ' placed! Pay ₱' + Number(d.total).toLocaleString() + ' upon delivery.';
    }
    document.getElementById('order-success-msg').textContent = msg;
    openModal('order-success-modal');
}


/* ── RENDER CART ───────────────────────────────────────────── */
async function renderCart() {
    const el = document.getElementById('cart-content');
    if (!el) return;

    if (!cart.length) {
        el.innerHTML = '<div style="text-align:center;padding:3rem"><div style="font-size:3rem;margin-bottom:1rem">🛒</div><p style="color:var(--color-text-mid)">Your cart is empty.</p><button class="btn-primary" style="margin-top:1rem" onclick="switchShopTab(\'shop\',document.querySelector(\'[data-stab=shop]\'))">Browse Products</button></div>';
        return;
    }

    // Calculate shipping
    await recalcShipping();

    const subtotal = cartSubtotal();
    const total    = subtotal + shippingFee;

    const items = cart.map(c => {
        const p = PRODUCTS.find(x => x.id === c.id);
        if (!p) return '';
        const imgSrc = getProductImage(p);
        return '<div class="cart-item">' +
            '<img src="' + imgSrc + '" class="cart-item-img" onerror="this.src=\'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=100\'">' +
            '<div class="cart-item-info">' +
                '<div class="cart-item-name">' + p.name + '</div>' +
                '<div class="cart-item-price">₱' + p.price.toLocaleString() + ' each' + (p.weight_kg ? ' · ' + (p.weight_kg * c.qty).toFixed(2) + 'kg' : '') + '</div>' +
            '</div>' +
            '<div class="qty-ctrl">' +
                '<button class="qty-btn" onclick="updateQty(\'' + p.id + '\',-1)">−</button>' +
                '<span class="qty-num">' + c.qty + '</span>' +
                '<button class="qty-btn" onclick="updateQty(\'' + p.id + '\',1)">+</button>' +
            '</div>' +
            '<span class="cart-item-total">₱' + (p.price * c.qty).toLocaleString() + '</span>' +
            '<button class="cart-remove" onclick="removeFromCart(\'' + p.id + '\')">✕</button>' +
        '</div>';
    }).join('');

    // Address block
    const addrBlock = renderAddressBlock();

    // Shipping info
    const shippingLabel = shippingFree
        ? '<span style="color:#4B8423;font-weight:700">FREE</span> <span style="text-decoration:line-through;color:#aaa;font-size:0.8em">₱' + shippingFee + '</span>'
        : '₱' + shippingFee.toLocaleString();
    const shippingNote = shippingZone ? '<span style="font-size:var(--text-tiny);color:#888;margin-left:0.5rem">(' + shippingZone + ' · ' + cartTotalWeight().toFixed(2) + 'kg)</span>' : '';

    const orderTotal = subtotal + (shippingFree ? 0 : shippingFee);

    // Payment method
    const qrSrc = UGAT_GCASH_QR ? '../../' + UGAT_GCASH_QR : '';
    const qrBlock = qrSrc
        ? `<img src="${qrSrc}" alt="GCash QR" style="width:200px;height:200px;object-fit:contain;border-radius:8px;display:block;margin:0 auto 0.6rem">`
        : `<div style="width:200px;height:200px;border:2px dashed #aaa;border-radius:8px;display:flex;align-items:center;justify-content:center;margin:0 auto 0.6rem;color:#aaa;font-size:0.78rem;text-align:center">QR code not yet<br>uploaded by admin</div>`;

const paymentBlock = `
    <div class="form-group" style="margin-top:0.75rem">
        <label style="font-size:var(--text-body-sm);font-weight:700;margin-bottom:0.5rem;display:block">Payment Method *</label>
        <div style="display:flex;gap:0.75rem;flex-wrap:wrap">
            <label class="pay-opt" id="pay-cod" onclick="selectPayment('cod')">
                <input type="radio" name="payment" value="cod" checked style="display:none">
                <span class="pay-icon">🚚</span>
                <span>Cash on Delivery</span>
            </label>
            <label class="pay-opt" id="pay-gcash" onclick="selectPayment('gcash')">
                <input type="radio" name="payment" value="gcash" style="display:none">
                <span class="pay-icon" style="background:#00b0e6;border-radius:6px;padding:2px 5px;color:#fff;font-weight:800;font-size:0.8rem">G</span>
                <span>GCash</span>
            </label>
        </div>

        <!-- GCash details panel -->
        <div id="gcash-details" style="display:none;margin-top:0.75rem">

            <!-- Sub-mode tabs -->
            <div style="display:flex;gap:0.5rem;margin-bottom:0.85rem">
                <button id="gcash-tab-link" onclick="selectGCashMode('link')"
                    style="flex:1;padding:0.5rem;border-radius:8px;border:1.5px solid #1a73e8;background:#1a73e8;color:#fff;font-weight:600;font-size:0.82rem;cursor:pointer">
                    🔗 Pay via Link
                </button>
                <button id="gcash-tab-qr" onclick="selectGCashMode('qr')"
                    style="flex:1;padding:0.5rem;border-radius:8px;border:1.5px solid #c5def8;background:#fff;color:#1a73e8;font-weight:600;font-size:0.82rem;cursor:pointer">
                    📷 Scan QR Code
                </button>
            </div>

            <!-- Link mode -->
            <div id="gcash-mode-link" style="background:linear-gradient(135deg,#e8f4ff,#f0f8ff);border:1.5px solid #c5def8;border-radius:var(--radius-sm);padding:1rem">
                <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.75rem">
                    <span style="background:#00b0e6;color:#fff;font-weight:800;font-size:1rem;padding:0.2rem 0.55rem;border-radius:6px">G</span>
                    <span style="font-weight:700;color:#1a73e8;font-size:0.95rem">GCash Payment via PayMongo</span>
                </div>
                <div style="background:#fff;border:1px solid #c5def8;border-radius:8px;padding:0.85rem;margin-bottom:0.75rem">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="font-size:var(--text-caption);color:#888">Amount to pay</span>
                        <span style="font-size:1.15rem;font-weight:800;color:#1a73e8">₱${orderTotal.toLocaleString()}.00</span>
                    </div>
                </div>
                <div style="font-size:var(--text-tiny);color:#555;line-height:1.6">
                    Clicking <strong>Place Order</strong> will redirect you to GCash to complete payment securely. Your order is confirmed automatically once payment is received.
                </div>
            </div>

            <!-- QR mode -->
            <div id="gcash-mode-qr" style="display:none">
                <div style="background:#f2f2f2;border-radius:12px;padding:1.25rem;text-align:center;margin-bottom:0.85rem">
                    ${qrBlock}
                    <div style="color:#666;font-size:0.82rem;font-style:italic;margin-bottom:0.4rem">Transfer fees may apply.</div>
                    <div style="color:#1a73e8;font-size:1.05rem;font-weight:700">${UGAT_GCASH_NAME}</div>
                </div>
                <div style="background:#fff8e1;border:1.5px solid #f7d98a;border-radius:8px;padding:0.75rem;margin-bottom:0.85rem;font-size:0.8rem;color:#7a5800;line-height:1.55">
                    📌 Scan the QR code above using your <strong>GCash app</strong>, pay <strong>₱${orderTotal.toLocaleString()}.00</strong>, then upload your receipt and enter the reference number below.
                </div>

                <div class="form-group" style="margin-bottom:0.75rem">
                    <label style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.35rem">GCash Reference Number <span style="color:red">*</span></label>
                    <input type="text" id="gcash-ref-input" class="form-input" placeholder="e.g. 1234567890" style="font-family:monospace">
                </div>

                <div class="form-group" style="margin-bottom:0">
                    <label style="font-size:0.82rem;font-weight:600;display:block;margin-bottom:0.35rem">Upload GCash Receipt <span style="color:red">*</span></label>
                    <div id="receipt-drop-zone" onclick="document.getElementById('gcash-receipt-file').click()"
                        style="border:2px dashed #c5def8;border-radius:8px;padding:1.25rem;text-align:center;cursor:pointer;background:#fafcff;transition:background 0.2s">
                        <div id="receipt-preview-wrap" style="display:none">
                            <img id="receipt-preview-img" style="max-width:100%;max-height:160px;border-radius:6px;object-fit:contain">
                            <div id="receipt-filename" style="font-size:0.75rem;color:#666;margin-top:0.4rem"></div>
                        </div>
                        <div id="receipt-placeholder">
                            <div style="font-size:1.5rem;margin-bottom:0.3rem">🖼</div>
                            <div style="font-size:0.82rem;color:#666">Click to upload receipt image</div>
                            <div style="font-size:0.72rem;color:#aaa">JPG, PNG, WebP · Max 8 MB</div>
                        </div>
                    </div>
                    <input type="file" id="gcash-receipt-file" accept="image/*" style="display:none" onchange="previewReceipt(this)">
                </div>
            </div>

        </div>
    </div>`;
    el.innerHTML = '<div class="cart-wrap">' +
        items +
        addrBlock +
        '<div class="cart-summary">' +
            '<div class="cart-summary-row"><span>Subtotal (' + cart.reduce((s,c)=>s+c.qty,0) + ' items)</span><span>₱' + subtotal.toLocaleString() + '</span></div>' +
            '<div class="cart-summary-row"><span>Shipping ' + shippingNote + '</span><span>' + shippingLabel + '</span></div>' +
            '<div class="cart-summary-row total-row"><span>Total</span><span>₱' + (subtotal + (shippingFree ? 0 : shippingFee)).toLocaleString() + '</span></div>' +
        '</div>' +
        '<div class="checkout-form">' +
            paymentBlock +
            '<div class="form-group" style="margin-top:0.75rem"><label>Notes (optional)</label><input type="text" id="co-notes" class="form-input" placeholder="Special instructions…"></div>' +
            '<div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem">' +
                '<button class="btn-outline" onclick="switchShopTab(\'shop\',document.querySelector(\'[data-stab=shop]\'))">← Continue Shopping</button>' +
                '<button class="btn-primary" id="place-order-btn" onclick="placeOrder()">Place Order — ₱' + (subtotal + (shippingFree ? 0 : shippingFee)).toLocaleString() + '</button>' +
            '</div>' +
        '</div>' +
    '</div>';
}

/* ── ADDRESS BLOCK (Shopee-style) ──────────────────────────── */
function renderAddressBlock() {
    if (!selectedAddr) {
        return `<div class="address-block no-addr">
            <div class="address-block-label">📍 Delivery Address</div>
            <p style="color:#888;font-size:var(--text-body-sm);margin:0.5rem 0">No address saved yet.</p>
            <button class="btn-outline" style="margin-top:0.5rem" onclick="openAddressModal()">+ Add Address</button>
        </div>`;
    }

    const a = selectedAddr;
    const full = [a.address_line, a.barangay_name, a.city_name, a.province_name, a.region_name].filter(Boolean).join(', ');

    return `<div class="address-block">
        <div class="address-block-label">📍 Delivery Address</div>
        <div class="address-block-body">
            <div class="address-block-name">
                <strong>${a.full_name}</strong>
                <span class="address-phone">(${a.contact_number})</span>
                ${a.is_default ? '<span class="addr-default-badge">Default</span>' : ''}
            </div>
            <div class="address-block-addr">${full}</div>
        </div>
        <button class="btn-link-green" onclick="openAddressModal()">Change</button>
    </div>`;
}

/* ── RECALC SHIPPING ───────────────────────────────────────── */
async function recalcShipping() {
    if (!selectedAddr || !selectedAddr.city_id) {
        shippingFee  = 50;
        shippingZone = '';
        shippingFree = false;
        return;
    }
    try {
        const r = await fetch('get_shipping_fee.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                city_id:          selectedAddr.city_id,
                total_weight_kg:  cartTotalWeight(),
                subtotal:         cartSubtotal(),
            }),
        });
        const d = await r.json();
        if (d.success) {
            shippingFee  = d.is_free ? 0 : d.shipping_fee;
            shippingZone = d.zone;
            shippingFree = d.is_free;
        }
    } catch (e) {
        shippingFee = 50;
    }
}
function enforcePhonePrefix(input) {
    const prefix = '+63 9';
    // Always keep the prefix
    if (!input.value.startsWith(prefix)) {
        input.value = prefix;
    }
    // Remove non-numeric chars after prefix
    const digits = input.value.slice(prefix.length).replace(/\D/g, '');
    // Limit to 9 digits after prefix (total PH mobile = +63 9XX XXX XXXX)
    const limited = digits.slice(0, 9);
    // Format as XXX XXX XXX
    let formatted = '';
    if (limited.length > 0) formatted += limited.slice(0, 3);
    if (limited.length > 3) formatted += ' ' + limited.slice(3, 6);
    if (limited.length > 6) formatted += ' ' + limited.slice(6, 9);
    input.value = prefix + formatted;
}


/* ── PAYMENT METHOD ────────────────────────────────────────── */
function selectPayment(method) {
    document.querySelectorAll('.pay-opt').forEach(el => el.classList.remove('selected'));
    const el = document.getElementById('pay-' + method);
    if (el) el.classList.add('selected');
    const gcashDetails = document.getElementById('gcash-details');
    if (gcashDetails) gcashDetails.style.display = method === 'gcash' ? 'block' : 'none';
}

function selectGCashMode(mode) {
    const linkPanel = document.getElementById('gcash-mode-link');
    const qrPanel   = document.getElementById('gcash-mode-qr');
    const tabLink   = document.getElementById('gcash-tab-link');
    const tabQR     = document.getElementById('gcash-tab-qr');
    if (!linkPanel || !qrPanel) return;

    if (mode === 'link') {
        linkPanel.style.display = 'block'; qrPanel.style.display = 'none';
        tabLink.style.background = '#1a73e8'; tabLink.style.color = '#fff'; tabLink.style.borderColor = '#1a73e8';
        tabQR.style.background   = '#fff';    tabQR.style.color   = '#1a73e8'; tabQR.style.borderColor = '#c5def8';
    } else {
        linkPanel.style.display = 'none'; qrPanel.style.display = 'block';
        tabQR.style.background   = '#1a73e8'; tabQR.style.color   = '#fff'; tabQR.style.borderColor = '#1a73e8';
        tabLink.style.background = '#fff';    tabLink.style.color = '#1a73e8'; tabLink.style.borderColor = '#c5def8';
    }
}

function previewReceipt(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];
    const reader = new FileReader();
    reader.onload = function(e) {
        const img  = document.getElementById('receipt-preview-img');
        const wrap = document.getElementById('receipt-preview-wrap');
        const ph   = document.getElementById('receipt-placeholder');
        const fn   = document.getElementById('receipt-filename');
        if (img)  { img.src = e.target.result; }
        if (wrap) wrap.style.display = 'block';
        if (ph)   ph.style.display   = 'none';
        if (fn)   fn.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
    };
    reader.readAsDataURL(file);
}

function getSelectedPayment() {
    return document.getElementById('pay-gcash')?.classList.contains('selected') ? 'gcash' : 'cod';
}

function getGCashMode() {
    const tabQR = document.getElementById('gcash-tab-qr');
    return tabQR && tabQR.style.background.includes('rgb(26') ? 'qr' : 'link';
}

/* ── ADDRESS MODAL ─────────────────────────────────────────── */
function openAddressModal() {
    const modal = document.getElementById('address-modal');
    if (!modal) return;
    renderAddressList();
    openModal('address-modal');
}

function renderAddressList() {
    const body = document.getElementById('addr-modal-body');
    if (!body) return;

    if (!userAddresses.length) {
        body.innerHTML = '<p style="color:#888;text-align:center;padding:1rem">No saved addresses.</p>';
        return;
    }

    body.innerHTML = userAddresses.map(a => {
        const full = [a.address_line, a.barangay_name, a.city_name, a.province_name].filter(Boolean).join(', ');
        const isSelected = selectedAddr && selectedAddr.id === a.id;
        return `<div class="addr-list-item ${isSelected ? 'selected' : ''}" onclick="selectAddress(${a.id})">
            <div class="addr-radio">
                <div class="addr-radio-dot ${isSelected ? 'active' : ''}"></div>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-weight:700;font-size:var(--text-body-sm)">${a.full_name} <span style="font-weight:400;color:#666">| ${a.contact_number}</span></div>
                <div style="font-size:var(--text-caption);color:#555;margin-top:0.2rem">${full}</div>
                ${a.is_default ? '<span class="addr-default-badge" style="margin-top:0.3rem;display:inline-block">Default</span>' : ''}
            </div>
            <button class="btn-link-green" style="flex-shrink:0;font-size:var(--text-caption)" onclick="event.stopPropagation();openAddressForm(${a.id})">Edit</button>
        </div>`;
    }).join('');
}

async function selectAddress(addrId) {
    selectedAddr = userAddresses.find(a => a.id === addrId);
    closeModal('address-modal');
    await renderCart(); // re-render with new address + recalc shipping
}

/* ── ADD / EDIT ADDRESS FORM ───────────────────────────────── */
function openAddressForm(addrId = null) {
    const addr = addrId ? userAddresses.find(a => a.id === addrId) : null;
    const modal = document.getElementById('address-form-modal');
    if (!modal) return;

    document.getElementById('addr-form-title').textContent = addr ? 'Edit Address' : 'Add New Address';

    // Pre-fill if editing
    document.getElementById('af-fullname').value    = addr?.full_name      || '';
    document.getElementById('af-contact').value     = addr?.contact_number || '';
    document.getElementById('af-line').value        = addr?.address_line   || '';
    document.getElementById('af-default').checked   = addr?.is_default     || false;
    document.getElementById('af-id').value          = addr?.id             || '';

    // Load region dropdown, then cascade
    loadRegionOptions(addr);
    closeModal('address-modal');
    openModal('address-form-modal');
}

async function loadRegionOptions(addr = null) {
    const sel = document.getElementById('af-region');
    sel.innerHTML = '<option value="">Select Region</option>';
    try {
const r = await fetch('../admin/get_address.php?type=regions');
        const d = await r.json();
        if (d.success) {
            d.regions.forEach(reg => {
                const opt = document.createElement('option');
                opt.value = reg.id;
                opt.textContent = reg.name;
                if (addr && addr.region_id == reg.id) opt.selected = true;
                sel.appendChild(opt);
            });
        }
    } catch (e) {}
    if (addr && addr.region_id) {
        await loadProvinceOptions(addr.region_id, addr);
    }
}

async function loadProvinceOptions(regionId, addr = null) {
    const sel = document.getElementById('af-province');
    sel.innerHTML = '<option value="">Select Province</option>';
    document.getElementById('af-city').innerHTML     = '<option value="">Select City/Municipality</option>';
    document.getElementById('af-barangay').innerHTML = '<option value="">Select Barangay</option>';
    if (!regionId) return;
    try {
const r = await fetch('../admin/get_address.php?type=provinces&region_id=' + regionId);
        const d = await r.json();
        if (d.success) {
            d.provinces.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.name;
                if (addr && addr.province_id == p.id) opt.selected = true;
                sel.appendChild(opt);
            });
        }
    } catch (e) {}
    if (addr && addr.province_id) await loadCityOptions(addr.province_id, addr);
}

async function loadCityOptions(provinceId, addr = null) {
    const sel = document.getElementById('af-city');
    sel.innerHTML = '<option value="">Select City/Municipality</option>';
    document.getElementById('af-barangay').innerHTML = '<option value="">Select Barangay</option>';
    if (!provinceId) return;
    try {
const r = await fetch('../admin/get_address.php?type=cities&province_id=' + provinceId);
        const d = await r.json();
        if (d.success) {
            d.cities.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                if (addr && addr.city_id == c.id) opt.selected = true;
                sel.appendChild(opt);
            });
        }
    } catch (e) {}
    if (addr && addr.city_id) await loadBarangayOptions(addr.city_id, addr);
}

async function loadBarangayOptions(cityId, addr = null) {
    const sel = document.getElementById('af-barangay');
    sel.innerHTML = '<option value="">Select Barangay</option>';
    if (!cityId) return;
    try {
const r = await fetch('../admin/get_address.php?type=barangays&city_id=' + cityId);
        const d = await r.json();
        if (d.success) {
            d.barangays.forEach(b => {
                const opt = document.createElement('option');
                opt.value = b.id;
                opt.textContent = b.name;
                if (addr && addr.barangay_id == b.id) opt.selected = true;
                sel.appendChild(opt);
            });
        }
    } catch (e) {}
}

async function saveAddressForm() {
    const payload = {
        id:             document.getElementById('af-id').value     || null,
        full_name:      document.getElementById('af-fullname').value.trim(),
        contact_number: document.getElementById('af-contact').value.trim(),
        address_line:   document.getElementById('af-line').value.trim(),
        region_id:      document.getElementById('af-region').value   || null,
        province_id:    document.getElementById('af-province').value || null,
        city_id:        document.getElementById('af-city').value     || null,
        barangay_id:    document.getElementById('af-barangay').value || null,
        is_default:     document.getElementById('af-default').checked,
    };

    if (!payload.full_name || !payload.contact_number || !payload.address_line) {
        showToast('Please fill in all required fields.');
        return;
    }

    try {
        const r = await fetch('save_user_address.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const d = await r.json();
        if (d.success) {
            showToast('✅ Address saved!');
            closeModal('address-form-modal');
            await loadUserAddresses();
            if (!selectedAddr) selectedAddr = userAddresses[0];
            await renderCart();
        } else {
            showToast('❌ ' + d.message);
        }
    } catch (e) {
        showToast('❌ Failed to save address.');
    }
}


/* ── MY ORDERS TAB ─────────────────────────────────────────── */
async function loadAndRenderOrders() {
    const el = document.getElementById('orders-content');
    if (!el) return;
    el.innerHTML = '<div style="text-align:center;padding:2rem;color:#888">Loading orders…</div>';

    try {
        const r = await fetch('get_my_orders.php?t=' + Date.now());
        const d = await r.json();
        if (!d.success) throw new Error(d.message);
        renderOrders(d.orders);
    } catch (e) {
        el.innerHTML = '<div style="text-align:center;padding:2rem;color:#c0392b">Failed to load orders.</div>';
    }
}

const STATUS_LABELS = {
    pending:          { label: 'Pending',           cls: 'badge-pending'   },
    confirmed:        { label: 'Confirmed',          cls: 'badge-upcoming'  },
    preparing:        { label: 'Preparing',          cls: 'badge-ongoing'   },
    out_for_delivery: { label: 'Out for Delivery',   cls: 'badge-ongoing'   },
    delivered:        { label: 'Delivered',          cls: 'badge-completed' },
    cancelled:        { label: 'Cancelled',          cls: 'badge-outstock'  },
};

const STATUS_STEPS = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'delivered'];

function renderOrders(orders) {
    const el = document.getElementById('orders-content');
    if (!el) return;

    if (!orders.length) {
        el.innerHTML = '<div style="text-align:center;padding:3rem"><div style="font-size:3rem;margin-bottom:1rem">📦</div><p style="color:var(--color-text-mid)">You have no orders yet.</p><button class="btn-primary" style="margin-top:1rem" onclick="switchShopTab(\'shop\',document.querySelector(\'[data-stab=shop]\'))">Start Shopping</button></div>';
        return;
    }

    el.innerHTML = orders.map(o => {
        const st          = STATUS_LABELS[o.status] || { label: o.status, cls: 'badge-upcoming' };
        const dateStr     = new Date(o.created_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        const addrStr     = o.address ? [o.address.address_line].filter(Boolean).join(', ') : '—';
        const isCancelled = o.status === 'cancelled';
        const stepIdx     = STATUS_STEPS.indexOf(o.status);

        const tracker = isCancelled ? `
            <div style="
                background:#fff5f5;
                border:1.5px solid #ffd0d0;
                border-radius:var(--radius-sm);
                padding:0.65rem 1rem;
                margin:0.75rem 0;
                display:flex;
                align-items:flex-start;
                gap:0.5rem;
                font-size:var(--text-body-sm)">
                <span style="font-size:1rem;flex-shrink:0"></span>
                <div>
                    <div style="font-weight:700;color:var(--color-danger);margin-bottom:0.15rem">Order Cancelled</div>
                    <div style="color:#888;font-size:var(--text-caption)">
                        ${o.cancel_reason
                            ? 'Reason: <strong style="color:#555">' + o.cancel_reason + '</strong>'
                            : 'This order has been cancelled.'}
                    </div>
                </div>
            </div>` : `
            <div class="order-tracker">
                ${STATUS_STEPS.map((s, i) => `
                    <div class="tracker-step ${i <= stepIdx ? 'done' : ''} ${i === stepIdx ? 'current' : ''}">
                        <div class="tracker-dot"></div>
                        <div class="tracker-label">${STATUS_LABELS[s]?.label || s}</div>
                    </div>
                    ${i < STATUS_STEPS.length - 1 ? '<div class="tracker-line ' + (i < stepIdx ? 'done' : '') + '"></div>' : ''}
                `).join('')}
            </div>`;

        const payBadge = o.payment_method === 'gcash'
            ? '<span style="background:#e8f4ff;color:#1a73e8;border:1px solid #c5def8;border-radius:10px;padding:0.15rem 0.5rem;font-size:10px;font-weight:700">💳 GCash</span>'
            : '<span style="background:#f0f7ec;color:#4B8423;border:1px solid #c3e0b0;border-radius:10px;padding:0.15rem 0.5rem;font-size:10px;font-weight:700">🚚 Cash on Delivery</span>';

        return `<div class="order-card">
            <div class="order-card-header">
                <div>
                    <div class="order-id">${o.order_code}</div>
                    <div class="order-date">${dateStr} · ${addrStr}</div>
                </div>
                <div style="display:flex;align-items:center;gap:0.5rem">
                    ${payBadge}
                    <span class="badge ${st.cls}">${st.label}</span>
                </div>
            </div>
            ${tracker}
            ${o.items.map(item => `
                <div class="order-item-row">
                    <img src="${item.image ? '../../' + item.image : 'https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=100'}"
                         class="order-item-img" onerror="this.src='https://images.unsplash.com/photo-1416879595882-3373a0480b5b?w=100'">
                    <span style="flex:1">${item.name}</span>
                    <span style="color:var(--color-text-mid)">x${item.quantity}</span>
                    <strong>₱${item.subtotal.toLocaleString()}</strong>
                </div>
                ${o.status === 'delivered' ? `
                <div style="padding:0.3rem 0 0.5rem 44px">
                    <button onclick="viewProduct('prod-${item.product_id}')"
                        style="background:none;border:1.5px solid #f4a523;color:#d6901e;border-radius:6px;padding:0.25rem 0.75rem;font-size:var(--text-tiny);font-weight:700;cursor:pointer;font-family:var(--font-body)">
                        ★ Write a Review
                    </button>
                </div>` : ''}`).join('')}
            <div class="order-total-row">
                <span>Subtotal</span><span>₱${o.subtotal.toLocaleString()}</span>
            </div>
            <div class="order-total-row" style="font-weight:400;font-size:var(--text-caption);color:#666">
                <span>Shipping</span><span>₱${o.shipping_fee.toLocaleString()}</span>
            </div>
            <div class="order-total-row" style="font-size:1rem">
                <span>Total</span><span>₱${o.total.toLocaleString()}</span>
            </div>
            ${o.notes ? '<div style="margin-top:0.5rem;font-size:var(--text-caption);color:#888">📝 ' + o.notes + '</div>' : ''}
            ${o.status === 'pending' ? `
                <div style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--color-border);display:flex;justify-content:flex-end">
                    <button onclick="cancelOrder(${o.id})"
                            style="background:none;border:1.5px solid var(--color-danger);color:var(--color-danger);border-radius:var(--radius-sm);padding:0.4rem 1rem;font-size:var(--text-caption);font-weight:600;cursor:pointer;font-family:var(--font-body)"
                            onmouseover="this.style.background='var(--color-danger)';this.style.color='#fff'"
                            onmouseout="this.style.background='none';this.style.color='var(--color-danger)'">
                        ✕ Cancel Order
                    </button>
                </div>` : ''}
        </div>`;
    }).join('');
}
async function cancelOrder(orderId) {
    const existing = document.getElementById('trainee-cancel-modal');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.id = 'trainee-cancel-modal';
    overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center';
    overlay.innerHTML = `
        <div style="background:#fff;border-radius:14px;padding:2rem;max-width:420px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.2)">
            <h3 style="font-size:1.05rem;font-weight:700;margin-bottom:0.25rem">Cancel Order</h3>
            <p style="font-size:0.83rem;color:#888;margin-bottom:1.25rem">Please tell us why you want to cancel this order.</p>

            <div style="margin-bottom:1rem">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:0.4rem">Reason *</label>
                <div id="trainee-cancel-reasons" style="display:flex;flex-direction:column;gap:0.5rem">
                    <label style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.85rem;border:1.5px solid #eee;border-radius:8px;cursor:pointer;font-size:0.88rem" onclick="selectCancelReason(this)">
                        <span class="cancel-radio" style="width:16px;height:16px;border-radius:50%;border:2px solid #ddd;flex-shrink:0;display:inline-block"></span>
                        I changed my mind
                    </label>
                    <label style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.85rem;border:1.5px solid #eee;border-radius:8px;cursor:pointer;font-size:0.88rem" onclick="selectCancelReason(this)">
                        <span class="cancel-radio" style="width:16px;height:16px;border-radius:50%;border:2px solid #ddd;flex-shrink:0;display:inline-block"></span>
                        I ordered the wrong item
                    </label>
                    <label style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.85rem;border:1.5px solid #eee;border-radius:8px;cursor:pointer;font-size:0.88rem" onclick="selectCancelReason(this)">
                        <span class="cancel-radio" style="width:16px;height:16px;border-radius:50%;border:2px solid #ddd;flex-shrink:0;display:inline-block"></span>
                        I found a cheaper price elsewhere
                    </label>
                    <label style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.85rem;border:1.5px solid #eee;border-radius:8px;cursor:pointer;font-size:0.88rem" onclick="selectCancelReason(this)">
                        <span class="cancel-radio" style="width:16px;height:16px;border-radius:50%;border:2px solid #ddd;flex-shrink:0;display:inline-block"></span>
                        I need to change my delivery address
                    </label>
                    <label style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.85rem;border:1.5px solid #eee;border-radius:8px;cursor:pointer;font-size:0.88rem" onclick="selectCancelReason(this)">
                        <span class="cancel-radio" style="width:16px;height:16px;border-radius:50%;border:2px solid #ddd;flex-shrink:0;display:inline-block"></span>
                        I want to change my payment method
                    </label>
                    <label style="display:flex;align-items:center;gap:0.6rem;padding:0.6rem 0.85rem;border:1.5px solid #eee;border-radius:8px;cursor:pointer;font-size:0.88rem" onclick="selectCancelReason(this)">
                        <span class="cancel-radio" style="width:16px;height:16px;border-radius:50%;border:2px solid #ddd;flex-shrink:0;display:inline-block"></span>
                        Other
                    </label>
                </div>
            </div>

            <div id="trainee-cancel-other-wrap" style="display:none;margin-bottom:1rem">
                <label style="font-size:0.85rem;font-weight:600;display:block;margin-bottom:0.4rem">Please specify *</label>
                <input type="text" id="trainee-cancel-other"
                       style="width:100%;padding:0.6rem;border:1.5px solid #ddd;border-radius:8px;font-size:0.9rem;font-family:inherit;box-sizing:border-box"
                       placeholder="Describe your reason…">
            </div>

            <div id="trainee-cancel-error" style="display:none;color:#e53e3e;font-size:0.82rem;margin-bottom:0.75rem"></div>

            <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:0.5rem">
                <button onclick="document.getElementById('trainee-cancel-modal').remove()"
                        style="padding:0.6rem 1.2rem;border:1.5px solid #ddd;background:#fff;border-radius:8px;cursor:pointer;font-size:0.88rem;font-family:inherit">
                    Keep Order
                </button>
                <button onclick="confirmTraineeCancel(${orderId})"
                        style="padding:0.6rem 1.2rem;background:#e53e3e;color:#fff;border:none;border-radius:8px;cursor:pointer;font-size:0.88rem;font-weight:600;font-family:inherit">
                    Confirm Cancellation
                </button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    overlay.addEventListener('click', e => { if (e.target === overlay) overlay.remove(); });
}

function selectCancelReason(label) {
    // Reset all
    document.querySelectorAll('#trainee-cancel-reasons label').forEach(l => {
        l.style.borderColor = '#eee';
        l.style.background  = '#fff';
        l.querySelector('.cancel-radio').style.borderColor = '#ddd';
        l.querySelector('.cancel-radio').style.background  = '#fff';
    });
    // Highlight selected
    label.style.borderColor = '#4B8423';
    label.style.background  = '#f0f7ec';
    label.querySelector('.cancel-radio').style.borderColor = '#4B8423';
    label.querySelector('.cancel-radio').style.background  = '#4B8423';
    label.querySelector('.cancel-radio').style.boxShadow   = '0 0 0 3px rgba(75,132,35,0.15)';

    // Show/hide other input
    const text = label.textContent.trim();
    document.getElementById('trainee-cancel-other-wrap').style.display = text === 'Other' ? 'block' : 'none';
}

async function confirmTraineeCancel(orderId) {
    const errEl = document.getElementById('trainee-cancel-error');

    // Get selected reason
    let reason = '';
    document.querySelectorAll('#trainee-cancel-reasons label').forEach(l => {
        if (l.style.borderColor === 'rgb(75, 132, 35)') {
            reason = l.textContent.trim();
        }
    });

    if (!reason) {
        errEl.textContent = 'Please select a reason.';
        errEl.style.display = 'block';
        return;
    }

    if (reason === 'Other') {
        const other = document.getElementById('trainee-cancel-other')?.value.trim();
        if (!other) {
            errEl.textContent = 'Please specify your reason.';
            errEl.style.display = 'block';
            return;
        }
        reason = other;
    }

    errEl.style.display = 'none';

    try {
        const r = await fetch('cancel_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, reason }),
        });
        const d = await r.json();
        document.getElementById('trainee-cancel-modal')?.remove();
        if (d.success) {
            showToast('✅ Order cancelled.');
            await loadAndRenderOrders();
        } else {
            showToast('❌ ' + (d.message || 'Could not cancel order.'));
        }
    } catch (e) {
        showToast('❌ Network error. Please try again.');
    }
}
/* ── MODAL / TOAST ─────────────────────────────────────────── */
function openModal(id) {
    const el = document.getElementById(id);
    if (el) { el.style.display = 'flex'; requestAnimationFrame(() => el.classList.add('open')); }
}
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) { el.classList.remove('open'); setTimeout(() => el.style.display = 'none', 200); }
}
function closeModalOutside(e, id) { if (e.target.id === id) closeModal(id); }

function showToast(msg) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
}