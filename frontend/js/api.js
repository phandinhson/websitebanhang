/**
 * api.js — API Service Module
 * Wraps all HTTP calls to the Laravel REST API backend.
 * Base URL: http://localhost:8000/api
 */

const API = (() => {
  const BASE_URL = 'http://localhost:8000/api';

  // ── Token helpers ──────────────────────────────────────────
  function getToken() {
    return localStorage.getItem('auth_token');
  }
  function setToken(token) {
    localStorage.setItem('auth_token', token);
  }
  function removeToken() {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('auth_user');
  }

  // ── Base fetch wrapper ─────────────────────────────────────
  async function request(endpoint, options = {}) {
    const token = getToken();
    const headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...(token ? { 'Authorization': `Bearer ${token}` } : {}),
      ...(options.headers || {}),
    };

    const config = {
      method: options.method || 'GET',
      headers,
    };

    if (options.body) {
      config.body = JSON.stringify(options.body);
    }

    // FormData support (file uploads — no Content-Type override)
    if (options.formData) {
      delete config.headers['Content-Type'];
      config.body = options.formData;
    }

    try {
      const response = await fetch(`${BASE_URL}${endpoint}`, config);
      const data = await response.json().catch(() => ({}));

      if (!response.ok) {
        const err = new Error(data.message || `Lỗi ${response.status}`);
        err.status = response.status;
        err.data = data;
        throw err;
      }
      return data;
    } catch (error) {
      if (error.status === 401) {
        removeToken();
        window.location.href = '/frontend/pages/login.html';
      }
      throw error;
    }
  }

  // Convenience methods
  const get    = (url, params = {}) => {
    const qs = new URLSearchParams(params).toString();
    return request(qs ? `${url}?${qs}` : url);
  };
  const post   = (url, body)    => request(url, { method: 'POST',   body });
  const put    = (url, body)    => request(url, { method: 'PUT',    body });
  const patch  = (url, body)    => request(url, { method: 'PATCH',  body });
  const del    = (url)          => request(url, { method: 'DELETE' });
  const upload = (url, fd, m)   => request(url, { method: m||'POST', formData: fd });

  // ══════════════════════════════════════════════════════════
  // PRODUCTS
  // ══════════════════════════════════════════════════════════
  /**
   * Lấy danh sách sản phẩm
   * @param {Object} params - { page, per_page, category_id, search, sort, min_price, max_price }
   */
  function getProducts(params = {}) {
    return get('/products', params);
  }

  /**
   * Lấy chi tiết sản phẩm theo ID
   * @param {number|string} id
   */
  function getProduct(id) {
    return get(`/products/${id}`);
  }

  /**
   * Tạo sản phẩm mới (Admin)
   * @param {FormData} formData
   */
  function createProduct(formData) {
    return upload('/products', formData, 'POST');
  }

  /**
   * Cập nhật sản phẩm (Admin)
   * @param {number|string} id
   * @param {FormData} formData
   */
  function updateProduct(id, formData) {
    // Laravel _method spoofing for PUT with FormData
    formData.append('_method', 'PUT');
    return upload(`/products/${id}`, formData, 'POST');
  }

  /**
   * Xoá sản phẩm (Admin)
   * @param {number|string} id
   */
  function deleteProduct(id) {
    return del(`/products/${id}`);
  }

  // ══════════════════════════════════════════════════════════
  // CATEGORIES
  // ══════════════════════════════════════════════════════════
  /**
   * Lấy danh sách danh mục
   */
  function getCategories() {
    return get('/categories');
  }

  /**
   * Tạo danh mục mới (Admin)
   */
  function createCategory(body) {
    return post('/categories', body);
  }

  /**
   * Cập nhật danh mục (Admin)
   */
  function updateCategory(id, body) {
    return put(`/categories/${id}`, body);
  }

  /**
   * Xoá danh mục (Admin)
   */
  function deleteCategory(id) {
    return del(`/categories/${id}`);
  }

  // ══════════════════════════════════════════════════════════
  // CART
  // ══════════════════════════════════════════════════════════
  /**
   * Lấy giỏ hàng hiện tại (từ server nếu đã đăng nhập)
   */
  function getCart() {
    return get('/cart');
  }

  /**
   * Thêm sản phẩm vào giỏ hàng
   * @param {number} productId
   * @param {number} quantity
   */
  function addToCart(productId, quantity = 1) {
    return post('/cart', { product_id: productId, quantity });
  }

  /**
   * Cập nhật số lượng sản phẩm trong giỏ
   * @param {number} cartItemId
   * @param {number} quantity
   */
  function updateCart(cartItemId, quantity) {
    return put(`/cart/${cartItemId}`, { quantity });
  }

  /**
   * Xoá sản phẩm khỏi giỏ hàng
   * @param {number} cartItemId
   */
  function removeFromCart(cartItemId) {
    return del(`/cart/${cartItemId}`);
  }

  /**
   * Xoá toàn bộ giỏ hàng
   */
  function clearCart() {
    return del('/cart');
  }

  // ══════════════════════════════════════════════════════════
  // ORDERS
  // ══════════════════════════════════════════════════════════
  /**
   * Tạo đơn hàng mới
   * @param {Object} body - { shipping_name, shipping_phone, shipping_address, payment_method, note }
   */
  function createOrder(body) {
    return post('/orders', body);
  }

  /**
   * Lấy danh sách đơn hàng (của user hiện tại)
   * @param {Object} params - { page, status }
   */
  function getOrders(params = {}) {
    return get('/orders', params);
  }

  /**
   * Lấy chi tiết đơn hàng
   * @param {number|string} id
   */
  function getOrder(id) {
    return get(`/orders/${id}`);
  }

  /**
   * Admin: Lấy tất cả đơn hàng
   * @param {Object} params
   */
  function getAllOrders(params = {}) {
    return get('/admin/orders', params);
  }

  /**
   * Admin: Cập nhật trạng thái đơn hàng
   * @param {number|string} id
   * @param {string} status
   */
  function updateOrderStatus(id, status) {
    return patch(`/admin/orders/${id}/status`, { status });
  }

  /**
   * Huỷ đơn hàng (user)
   * @param {number|string} id
   */
  function cancelOrder(id) {
    return patch(`/orders/${id}/cancel`, {});
  }

  // ══════════════════════════════════════════════════════════
  // AUTH
  // ══════════════════════════════════════════════════════════
  /**
   * Đăng nhập
   * @param {string} email
   * @param {string} password
   */
  async function login(email, password) {
    const data = await post('/auth/login', { email, password });
    if (data.token) {
      setToken(data.token);
      localStorage.setItem('auth_user', JSON.stringify(data.user));
    }
    return data;
  }

  /**
   * Đăng ký tài khoản mới
   * @param {Object} body - { name, email, phone, password, password_confirmation }
   */
  async function register(body) {
    const data = await post('/auth/register', body);
    if (data.token) {
      setToken(data.token);
      localStorage.setItem('auth_user', JSON.stringify(data.user));
    }
    return data;
  }

  /**
   * Đăng xuất
   */
  async function logout() {
    try {
      await post('/auth/logout', {});
    } finally {
      removeToken();
    }
  }

  /**
   * Lấy thông tin người dùng hiện tại
   */
  function getProfile() {
    return get('/auth/profile');
  }

  /**
   * Cập nhật thông tin cá nhân
   * @param {Object} body - { name, phone, address, ... }
   */
  function updateProfile(body) {
    return put('/auth/profile', body);
  }

  /**
   * Đổi mật khẩu
   * @param {Object} body - { current_password, password, password_confirmation }
   */
  function changePassword(body) {
    return put('/auth/password', body);
  }

  // ══════════════════════════════════════════════════════════
  // ADMIN
  // ══════════════════════════════════════════════════════════
  /**
   * Admin: Lấy thống kê tổng quan
   */
  function getAdminStats() {
    return get('/admin/stats');
  }

  /**
   * Admin: Lấy danh sách người dùng
   */
  function getUsers(params = {}) {
    return get('/admin/users', params);
  }

  /**
   * Admin: Cập nhật trạng thái người dùng
   */
  function updateUserStatus(id, status) {
    return patch(`/admin/users/${id}/status`, { status });
  }

  // ── Public API ─────────────────────────────────────────────
  return {
    // Utils
    getToken,
    setToken,
    removeToken,
    request,

    // Products
    getProducts,
    getProduct,
    createProduct,
    updateProduct,
    deleteProduct,

    // Categories
    getCategories,
    createCategory,
    updateCategory,
    deleteCategory,

    // Cart
    getCart,
    addToCart,
    updateCart,
    removeFromCart,
    clearCart,

    // Orders
    createOrder,
    getOrders,
    getOrder,
    getAllOrders,
    updateOrderStatus,
    cancelOrder,

    // Auth
    login,
    register,
    logout,
    getProfile,
    updateProfile,
    changePassword,

    // Admin
    getAdminStats,
    getUsers,
    updateUserStatus,
  };
})();

// Make available globally
window.API = API;
