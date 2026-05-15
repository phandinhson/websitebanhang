/**
 * products.js — Products Module
 * Handles product listing, filtering, search, pagination, and rendering.
 */

const Products = (() => {
  // ── State ────────────────────────────────────────────────────
  let state = {
    products:    [],
    categories:  [],
    page:        1,
    perPage:     12,
    total:       0,
    lastPage:    1,
    search:      '',
    categoryId:  null,
    minPrice:    null,
    maxPrice:    null,
    sort:        'created_at_desc',
    loading:     false,
  };

  // ── Init ─────────────────────────────────────────────────────
  /**
   * Khởi tạo trang danh sách sản phẩm.
   */
  async function init() {
    // Parse URL params
    const params     = new URLSearchParams(window.location.search);
    state.search     = params.get('search')   || '';
    state.categoryId = params.get('category') || null;
    state.page       = parseInt(params.get('page')) || 1;
    state.sort       = params.get('sort')     || 'created_at_desc';

    // Set search bar value
    const searchInput = document.getElementById('search-input');
    if (searchInput) searchInput.value = state.search;

    // Set sort select
    const sortSelect = document.getElementById('sort-select');
    if (sortSelect) sortSelect.value = state.sort;

    await Promise.all([loadCategories(), loadProducts()]);
    bindEvents();
  }

  /**
   * Khởi tạo lưới sản phẩm nổi bật trên trang chủ.
   */
  async function initFeatured(containerId, limit = 8) {
    const container = document.getElementById(containerId);
    if (!container) return;
    showLoadingSkeleton(container, limit);
    try {
      const data = await API.getProducts({ per_page: limit, sort: 'featured' });
      const products = data.data || data.products || [];
      renderProductGrid(container, products);
    } catch {
      container.innerHTML = `<p class="text-muted">Không thể tải sản phẩm.</p>`;
    }
  }

  // ── Load Data ─────────────────────────────────────────────────
  async function loadProducts() {
    if (state.loading) return;
    state.loading = true;

    const container = document.getElementById('products-grid');
    if (!container) { state.loading = false; return; }

    showLoadingSkeleton(container, state.perPage);
    updateResultCount('Đang tải...');

    const params = {
      page:     state.page,
      per_page: state.perPage,
      sort:     state.sort,
    };
    if (state.search)     params.search      = state.search;
    if (state.categoryId) params.category_id = state.categoryId;
    if (state.minPrice)   params.min_price   = state.minPrice;
    if (state.maxPrice)   params.max_price   = state.maxPrice;

    try {
      const data = await API.getProducts(params);
      state.products = data.data || data.products || [];
      state.total    = data.total  || data.meta?.total || state.products.length;
      state.lastPage = data.last_page || data.meta?.last_page || 1;

      renderProductGrid(container, state.products);
      updateResultCount(`Hiển thị ${state.products.length} / ${state.total} sản phẩm`);
      renderPagination();
    } catch (err) {
      container.innerHTML = `
        <div class="loading-overlay" style="flex-direction:column;gap:10px">
          <p class="text-muted">Không thể tải sản phẩm. Vui lòng thử lại.</p>
          <button class="btn btn-outline btn-sm" onclick="Products.loadProducts()">Thử lại</button>
        </div>`;
    } finally {
      state.loading = false;
    }
  }

  async function loadCategories() {
    const container = document.getElementById('category-filter-list');
    if (!container) return;

    try {
      const data = await API.getCategories();
      state.categories = data.data || data.categories || data;

      container.innerHTML = state.categories.map(cat => `
        <li>
          <label class="filter-list-label">
            <input type="checkbox" value="${cat.id}" class="cat-filter-cb"
              ${String(state.categoryId) === String(cat.id) ? 'checked' : ''}
              onchange="Products.filterByCategory(this)">
            <span>${cat.name}</span>
            <span class="filter-count">${cat.products_count || ''}</span>
          </label>
        </li>`).join('');

      // Also populate home page categories
      renderHomeCategories(state.categories);
    } catch { /* silently fail */ }
  }

  function renderHomeCategories(categories) {
    const grid = document.getElementById('categories-grid');
    if (!grid) return;

    const icons = ['👗','📱','🏠','⚽','📚','💄','🎮','🌿','👟','🔧'];
    grid.innerHTML = categories.slice(0, 8).map((cat, i) => `
      <a href="/frontend/pages/products.html?category=${cat.id}" class="category-card">
        <div class="category-icon">${icons[i] || '🛍️'}</div>
        <div class="category-name">${cat.name}</div>
        <div class="category-count">${cat.products_count || 0} sản phẩm</div>
      </a>`).join('');
  }

  // ── Render ───────────────────────────────────────────────────
  function renderProductGrid(container, products) {
    if (products.length === 0) {
      container.innerHTML = `
        <div style="grid-column:1/-1;text-align:center;padding:60px 20px">
          <div style="font-size:4rem;margin-bottom:16px;opacity:.3">🔍</div>
          <h3 style="margin-bottom:8px">Không tìm thấy sản phẩm</h3>
          <p class="text-muted">Thử tìm kiếm với từ khoá khác hoặc bỏ bộ lọc.</p>
        </div>`;
      return;
    }
    container.innerHTML = products.map(p => renderProductCard(p)).join('');
  }

  /**
   * Tạo HTML card sản phẩm.
   * @param {Object} p - product object
   * @returns {string} HTML string
   */
  function renderProductCard(p) {
    const hasDiscount  = p.sale_price && p.sale_price < p.price;
    const currentPrice = hasDiscount ? p.sale_price : p.price;
    const discountPct  = hasDiscount
      ? Math.round((1 - p.sale_price / p.price) * 100)
      : 0;
    const slug  = p.slug || p.id;
    const img   = p.image || p.thumbnail || '';
    const stars = '★'.repeat(Math.round(p.rating || 4)) + '☆'.repeat(5 - Math.round(p.rating || 4));

    let badge = '';
    if (p.is_new)      badge = `<div class="product-badge new">Mới</div>`;
    else if (p.is_hot) badge = `<div class="product-badge hot">Hot</div>`;
    else if (discountPct > 0) badge = `<div class="product-badge">-${discountPct}%</div>`;

    return `
      <div class="product-card">
        <div class="product-card-img">
          ${img
            ? `<img src="${img}" alt="${p.name}" loading="lazy">`
            : `<div class="img-placeholder">🛍️</div>`}
          ${badge}
          <div class="product-actions-hover">
            <button onclick="Cart.addProduct(${JSON.stringify(p).replace(/"/g, '&quot;')}, 1).then(() => window.showToast('Đã thêm vào giỏ hàng!','success'))" title="Thêm vào giỏ">
              🛒
            </button>
            <button onclick="window.location.href='/frontend/pages/product-detail.html?id=${slug}'" title="Xem nhanh">
              👁️
            </button>
          </div>
        </div>
        <div class="product-card-body">
          ${p.category_name ? `<div class="product-category">${p.category_name}</div>` : ''}
          <div class="product-name">
            <a href="/frontend/pages/product-detail.html?id=${slug}">${p.name}</a>
          </div>
          <div class="product-rating">
            <span class="stars">${stars}</span>
            <span class="rating-count">(${p.reviews_count || 0})</span>
          </div>
          <div class="product-price">
            <span class="price-current">${formatPrice(currentPrice)}</span>
            ${hasDiscount ? `<span class="price-original">${formatPrice(p.price)}</span>` : ''}
            ${discountPct > 0 ? `<span class="price-discount">-${discountPct}%</span>` : ''}
          </div>
          <button class="btn btn-primary btn-sm"
            onclick="Cart.addProduct(${JSON.stringify(p).replace(/"/g, '&quot;')}, 1).then(() => window.showToast('Đã thêm vào giỏ hàng!','success'))">
            🛒 Thêm vào giỏ
          </button>
        </div>
      </div>`;
  }

  // ── Filtering & Search ────────────────────────────────────────
  function filterByCategory(checkbox) {
    // Uncheck others
    if (checkbox.checked) {
      document.querySelectorAll('.cat-filter-cb').forEach(cb => {
        if (cb !== checkbox) cb.checked = false;
      });
      state.categoryId = checkbox.value;
    } else {
      state.categoryId = null;
    }
    state.page = 1;
    loadProducts();
    updateURL();
  }

  function filterProducts() {
    const minInput = document.getElementById('min-price');
    const maxInput = document.getElementById('max-price');
    state.minPrice = minInput && minInput.value ? parseInt(minInput.value) : null;
    state.maxPrice = maxInput && maxInput.value ? parseInt(maxInput.value) : null;
    state.page = 1;
    loadProducts();
  }

  function searchProducts(query) {
    state.search = query.trim();
    state.page   = 1;
    loadProducts();
    updateURL();
  }

  function sortProducts(sortValue) {
    state.sort = sortValue;
    state.page = 1;
    loadProducts();
    updateURL();
  }

  function resetFilters() {
    state.search     = '';
    state.categoryId = null;
    state.minPrice   = null;
    state.maxPrice   = null;
    state.page       = 1;

    document.querySelectorAll('.cat-filter-cb').forEach(cb => cb.checked = false);
    const minInput = document.getElementById('min-price');
    const maxInput = document.getElementById('max-price');
    if (minInput) minInput.value = '';
    if (maxInput) maxInput.value = '';
    const searchInput = document.getElementById('search-input');
    if (searchInput) searchInput.value = '';

    loadProducts();
    updateURL();
  }

  // ── Pagination ───────────────────────────────────────────────
  function renderPagination() {
    const container = document.getElementById('pagination');
    if (!container) return;

    if (state.lastPage <= 1) { container.innerHTML = ''; return; }

    const pages = [];
    const cur   = state.page;
    const last  = state.lastPage;

    // Prev
    pages.push(`<button ${cur===1?'disabled':''} onclick="Products.goToPage(${cur-1})">&lsaquo;</button>`);

    // Page numbers with ellipsis
    let pageNums = [];
    if (last <= 7) {
      pageNums = Array.from({length: last}, (_, i) => i + 1);
    } else {
      pageNums = [1];
      if (cur > 3)      pageNums.push('...');
      for (let p = Math.max(2, cur-1); p <= Math.min(last-1, cur+1); p++) pageNums.push(p);
      if (cur < last-2) pageNums.push('...');
      pageNums.push(last);
    }

    pageNums.forEach(p => {
      if (p === '...') {
        pages.push(`<span>…</span>`);
      } else {
        pages.push(`<button class="${p===cur?'active':''}" onclick="Products.goToPage(${p})">${p}</button>`);
      }
    });

    // Next
    pages.push(`<button ${cur===last?'disabled':''} onclick="Products.goToPage(${cur+1})">&rsaquo;</button>`);

    container.innerHTML = pages.join('');
  }

  function goToPage(page) {
    if (page < 1 || page > state.lastPage) return;
    state.page = page;
    loadProducts();
    updateURL();
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  // ── URL Sync ─────────────────────────────────────────────────
  function updateURL() {
    const params = new URLSearchParams();
    if (state.search)     params.set('search',   state.search);
    if (state.categoryId) params.set('category', state.categoryId);
    if (state.page > 1)   params.set('page',     state.page);
    if (state.sort !== 'created_at_desc') params.set('sort', state.sort);
    const qs = params.toString();
    history.replaceState(null, '', qs ? `?${qs}` : window.location.pathname);
  }

  // ── Events ───────────────────────────────────────────────────
  function bindEvents() {
    // Search input
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
      let timer;
      searchInput.addEventListener('input', e => {
        clearTimeout(timer);
        timer = setTimeout(() => searchProducts(e.target.value), 450);
      });
      searchInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') { clearTimeout(timer); searchProducts(e.target.value); }
      });
    }

    // Sort select
    const sortSelect = document.getElementById('sort-select');
    if (sortSelect) {
      sortSelect.addEventListener('change', e => sortProducts(e.target.value));
    }

    // Filter apply button
    const filterBtn = document.getElementById('apply-filter-btn');
    if (filterBtn) filterBtn.addEventListener('click', filterProducts);

    // Reset filter
    const resetBtn = document.getElementById('reset-filter-btn');
    if (resetBtn) resetBtn.addEventListener('click', resetFilters);
  }

  // ── Helpers ──────────────────────────────────────────────────
  function showLoadingSkeleton(container, count) {
    container.innerHTML = Array.from({length: Math.min(count, 12)}).map(() => `
      <div class="product-card">
        <div class="product-card-img skeleton" style="aspect-ratio:1"></div>
        <div class="product-card-body">
          <div class="skeleton" style="height:12px;width:60%;margin-bottom:8px"></div>
          <div class="skeleton" style="height:16px;width:90%;margin-bottom:6px"></div>
          <div class="skeleton" style="height:16px;width:75%;margin-bottom:12px"></div>
          <div class="skeleton" style="height:20px;width:50%"></div>
        </div>
      </div>`).join('');
  }

  function updateResultCount(text) {
    const el = document.getElementById('result-count');
    if (el) el.textContent = text;
  }

  function formatPrice(amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
  }

  // ── Public ───────────────────────────────────────────────────
  return {
    init,
    initFeatured,
    loadProducts,
    loadCategories,
    filterByCategory,
    filterProducts,
    searchProducts,
    sortProducts,
    resetFilters,
    renderProductCard,
    renderProductGrid,
    goToPage,
    handlePagination: renderPagination,
    formatPrice,
    getState: () => ({ ...state }),
  };
})();

window.Products = Products;
