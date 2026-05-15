/**
 * cart.js — Shopping Cart Module
 * Manages cart UI: load, display, quantity update, remove, totals.
 * Works with both server-side (logged in) and localStorage (guest) cart.
 */

const Cart = (() => {
  const CART_KEY = 'guest_cart'; // localStorage key for guest cart

  // ── Local Storage Cart (Guest) ──────────────────────────────
  function getLocalCart() {
    try { return JSON.parse(localStorage.getItem(CART_KEY)) || []; }
    catch { return []; }
  }
  function saveLocalCart(items) {
    localStorage.setItem(CART_KEY, JSON.stringify(items));
  }

  // ── Cart Count Badge ────────────────────────────────────────
  /**
   * Cập nhật số lượng badge giỏ hàng trên header.
   * @param {number} count
   */
  function updateCartCount(count) {
    const badges = document.querySelectorAll('.cart-count');
    badges.forEach(badge => {
      badge.textContent = count > 99 ? '99+' : count;
      badge.style.display = count > 0 ? 'flex' : 'none';
    });
    localStorage.setItem('cart_count', count);
  }

  /**
   * Lấy số lượng item hiện tại (từ cache hoặc API).
   */
  async function fetchCartCount() {
    if (Auth.isLoggedIn()) {
      try {
        const data = await API.getCart();
        const items = data.items || data.data || [];
        const count = items.reduce((s, item) => s + (item.quantity || 1), 0);
        updateCartCount(count);
        return count;
      } catch {
        const cached = parseInt(localStorage.getItem('cart_count')) || 0;
        updateCartCount(cached);
        return cached;
      }
    } else {
      const items = getLocalCart();
      const count = items.reduce((s, item) => s + item.quantity, 0);
      updateCartCount(count);
      return count;
    }
  }

  // ── Load & Render Cart ──────────────────────────────────────
  /**
   * Tải và hiển thị giỏ hàng trên trang cart.html.
   */
  async function loadCart() {
    const container   = document.getElementById('cart-items-container');
    const emptyMsg    = document.getElementById('cart-empty');
    const cartContent = document.getElementById('cart-content');

    if (!container) return;

    showLoading(container);

    let items = [];
    try {
      if (Auth.isLoggedIn()) {
        const data = await API.getCart();
        items = data.items || data.data || [];
      } else {
        items = getLocalCart();
      }
    } catch (err) {
      showError(container, 'Không thể tải giỏ hàng. Vui lòng thử lại.');
      return;
    }

    if (items.length === 0) {
      if (cartContent) cartContent.style.display = 'none';
      if (emptyMsg)    emptyMsg.style.display    = 'flex';
      updateCartCount(0);
      return;
    }

    if (cartContent) cartContent.style.display = '';
    if (emptyMsg)    emptyMsg.style.display    = 'none';

    renderCartItems(container, items);
    updateCartCount(items.reduce((s, i) => s + i.quantity, 0));
    renderSummary(items);
  }

  function renderCartItems(container, items) {
    container.innerHTML = items.map(item => {
      const product   = item.product || item;
      const name      = product.name || item.name || 'Sản phẩm';
      const price     = item.price || product.sale_price || product.price || 0;
      const imgSrc    = product.image || product.thumbnail || '';
      const itemId    = item.id || item.cart_item_id;
      const productId = product.id || item.product_id;
      const slug      = product.slug || productId;

      return `
        <tr data-item-id="${itemId}" data-product-id="${productId}">
          <td data-label="Sản phẩm">
            <div class="cart-product">
              <div class="cart-product-img">
                ${imgSrc
                  ? `<img src="${imgSrc}" alt="${name}" loading="lazy">`
                  : `<div class="img-placeholder">🛍️</div>`}
              </div>
              <div>
                <div class="cart-product-name">
                  <a href="/frontend/pages/product-detail.html?id=${slug}">${name}</a>
                </div>
                ${product.sku ? `<div class="cart-product-sku">SKU: ${product.sku}</div>` : ''}
              </div>
            </div>
          </td>
          <td data-label="Đơn giá">
            <span class="price-current">${formatPrice(price)}</span>
          </td>
          <td data-label="Số lượng">
            <div class="quantity-selector" style="justify-content:flex-start">
              <button class="qty-btn" onclick="Cart.changeQuantity('${itemId}', -1)">−</button>
              <input class="qty-input" type="number" value="${item.quantity}"
                min="1" max="${product.stock || 99}"
                onchange="Cart.setQuantity('${itemId}', this.value)"
                style="width:48px;height:38px">
              <button class="qty-btn" onclick="Cart.changeQuantity('${itemId}', 1)">+</button>
            </div>
          </td>
          <td data-label="Thành tiền">
            <span class="price-current subtotal-${itemId}">${formatPrice(price * item.quantity)}</span>
          </td>
          <td data-label="">
            <button class="remove-btn" onclick="Cart.removeItem('${itemId}')" title="Xoá">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/>
                <path d="M10 11v6"/><path d="M14 11v6"/>
                <path d="M9 6V4h6v2"/>
              </svg>
            </button>
          </td>
        </tr>`;
    }).join('');
  }

  function renderSummary(items) {
    const subtotal = items.reduce((s, item) => {
      const price = item.price || (item.product && (item.product.sale_price || item.product.price)) || 0;
      return s + price * item.quantity;
    }, 0);
    const shipping = subtotal >= 500000 ? 0 : 30000;
    const total    = subtotal + shipping;

    const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
    setEl('summary-subtotal', formatPrice(subtotal));
    setEl('summary-shipping', shipping === 0 ? 'Miễn phí' : formatPrice(shipping));
    setEl('summary-total',    formatPrice(total));
    setEl('summary-discount', formatPrice(0));

    // Update checkout button
    const checkoutBtn = document.getElementById('checkout-btn');
    if (checkoutBtn) {
      checkoutBtn.href = Auth.isLoggedIn()
        ? '/frontend/pages/checkout.html'
        : '/frontend/pages/login.html?redirect=' + encodeURIComponent('/frontend/pages/checkout.html');
    }
  }

  // ── Quantity Controls ────────────────────────────────────────
  /**
   * Thay đổi số lượng ± delta.
   */
  async function changeQuantity(itemId, delta) {
    const row   = document.querySelector(`tr[data-item-id="${itemId}"]`);
    const input = row && row.querySelector('.qty-input');
    if (!input) return;
    const newQty = Math.max(1, parseInt(input.value) + delta);
    await setQuantity(itemId, newQty);
  }

  /**
   * Đặt số lượng cụ thể.
   */
  async function setQuantity(itemId, qty) {
    qty = Math.max(1, parseInt(qty));
    const row   = document.querySelector(`tr[data-item-id="${itemId}"]`);
    const input = row && row.querySelector('.qty-input');
    if (input) input.value = qty;

    try {
      if (Auth.isLoggedIn()) {
        await API.updateCart(itemId, qty);
      } else {
        const items = getLocalCart();
        const idx   = items.findIndex(i => String(i.id) === String(itemId));
        if (idx !== -1) { items[idx].quantity = qty; saveLocalCart(items); }
      }
      // Update subtotal cell
      if (row) {
        const priceEl = row.querySelector('.price-current');
        const price   = priceEl ? parsePriceText(priceEl.textContent) : 0;
        const subEl   = row.querySelector(`.subtotal-${itemId}`);
        if (subEl) subEl.textContent = formatPrice(price * qty);
      }
      await fetchCartCount();
      await recalcSummary();
    } catch (err) {
      showToast('Không thể cập nhật số lượng.', 'error');
    }
  }

  /**
   * Xoá một sản phẩm khỏi giỏ hàng.
   */
  async function removeItem(itemId) {
    if (!confirm('Xoá sản phẩm này khỏi giỏ hàng?')) return;
    try {
      if (Auth.isLoggedIn()) {
        await API.removeFromCart(itemId);
      } else {
        const items = getLocalCart().filter(i => String(i.id) !== String(itemId));
        saveLocalCart(items);
      }
      const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
      if (row) row.remove();

      await fetchCartCount();
      await recalcSummary();
      showToast('Đã xoá sản phẩm khỏi giỏ hàng.', 'success');

      // Show empty state if no rows left
      const remaining = document.querySelectorAll('#cart-items-container tr').length;
      if (remaining === 0) {
        const cartContent = document.getElementById('cart-content');
        const emptyMsg    = document.getElementById('cart-empty');
        if (cartContent) cartContent.style.display = 'none';
        if (emptyMsg)    emptyMsg.style.display    = 'flex';
      }
    } catch {
      showToast('Không thể xoá sản phẩm.', 'error');
    }
  }

  /**
   * Tính lại summary từ các row hiện tại trên UI.
   */
  async function recalcSummary() {
    if (Auth.isLoggedIn()) {
      try {
        const data = await API.getCart();
        renderSummary(data.items || data.data || []);
      } catch { /* noop */ }
    } else {
      renderSummary(getLocalCart());
    }
  }

  // ── Add to Cart (called from product pages) ─────────────────
  /**
   * Thêm sản phẩm vào giỏ hàng.
   * @param {Object} product - { id, name, price, image }
   * @param {number} qty
   */
  async function addProduct(product, qty = 1) {
    if (Auth.isLoggedIn()) {
      await API.addToCart(product.id, qty);
    } else {
      const items = getLocalCart();
      const idx   = items.findIndex(i => i.id === product.id);
      if (idx !== -1) {
        items[idx].quantity += qty;
      } else {
        items.push({
          id: product.id,
          product_id: product.id,
          name: product.name,
          price: product.sale_price || product.price,
          image: product.image || '',
          quantity: qty,
        });
      }
      saveLocalCart(items);
    }
    await fetchCartCount();
  }

  // ── Checkout helpers ────────────────────────────────────────
  /**
   * Lấy danh sách item cho trang checkout.
   */
  async function getCheckoutItems() {
    if (Auth.isLoggedIn()) {
      const data = await API.getCart();
      return data.items || data.data || [];
    }
    return getLocalCart();
  }

  /**
   * Tính tổng tiền thanh toán.
   */
  function calculateTotal(items) {
    const subtotal = items.reduce((s, item) => {
      const price = item.price || (item.product && (item.product.sale_price || item.product.price)) || 0;
      return s + price * item.quantity;
    }, 0);
    const shipping = subtotal >= 500000 ? 0 : 30000;
    return { subtotal, shipping, total: subtotal + shipping };
  }

  // ── Utilities ───────────────────────────────────────────────
  function formatPrice(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount);
  }

  function parsePriceText(text) {
    return parseInt(text.replace(/[^\d]/g, '')) || 0;
  }

  function showLoading(container) {
    container.innerHTML = `
      <tr><td colspan="5">
        <div class="loading-overlay"><div class="spinner"></div></div>
      </td></tr>`;
  }
  function showError(container, msg) {
    container.innerHTML = `<tr><td colspan="5"><div class="alert alert-danger">${msg}</div></td></tr>`;
  }

  function showToast(msg, type = 'info') {
    if (window.showToast) { window.showToast(msg, type); return; }
    // Fallback
    const c = document.querySelector('.toast-container') || (() => {
      const d = document.createElement('div');
      d.className = 'toast-container';
      document.body.appendChild(d);
      return d;
    })();
    const t = document.createElement('div');
    t.className = `toast ${type}`;
    t.innerHTML = `<span class="toast-msg">${msg}</span>`;
    c.appendChild(t);
    setTimeout(() => t.remove(), 3000);
  }

  function showLoading2(container) { showLoading(container); }

  // ── Public ──────────────────────────────────────────────────
  return {
    loadCart,
    fetchCartCount,
    updateCartCount,
    changeQuantity,
    setQuantity,
    removeItem,
    addProduct,
    getCheckoutItems,
    calculateTotal,
    getLocalCart,
    saveLocalCart,
    formatPrice,
  };
})();

window.Cart = Cart;
