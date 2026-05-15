/**
 * auth.js — Authentication Module
 * Handles JWT token management, auth checks, redirects, and user display.
 */

const Auth = (() => {
  const TOKEN_KEY = 'auth_token';
  const USER_KEY  = 'auth_user';

  // ── Token Helpers ──────────────────────────────────────────
  function getToken() {
    return localStorage.getItem(TOKEN_KEY);
  }

  function getUser() {
    const raw = localStorage.getItem(USER_KEY);
    try { return raw ? JSON.parse(raw) : null; }
    catch { return null; }
  }

  function setSession(token, user) {
    localStorage.setItem(TOKEN_KEY, token);
    localStorage.setItem(USER_KEY, JSON.stringify(user));
  }

  function clearSession() {
    localStorage.removeItem(TOKEN_KEY);
    localStorage.removeItem(USER_KEY);
    localStorage.removeItem('cart_count');
  }

  // ── Auth State ─────────────────────────────────────────────
  function isLoggedIn() {
    return !!getToken();
  }

  function isAdmin() {
    const user = getUser();
    return user && (user.role === 'admin' || user.is_admin === true);
  }

  // ── Redirect Guards ────────────────────────────────────────
  /**
   * Chuyển hướng đến trang đăng nhập nếu chưa xác thực.
   * Gọi ở đầu các trang yêu cầu đăng nhập.
   */
  function redirectIfNotAuth() {
    if (!isLoggedIn()) {
      const current = encodeURIComponent(window.location.href);
      window.location.href = `/frontend/pages/login.html?redirect=${current}`;
      return false;
    }
    return true;
  }

  /**
   * Chuyển hướng về trang chủ nếu đã đăng nhập.
   * Gọi trên trang login/register.
   */
  function redirectIfAuth() {
    if (isLoggedIn()) {
      const params = new URLSearchParams(window.location.search);
      const redirect = params.get('redirect');
      window.location.href = redirect || '/frontend/index.html';
      return true;
    }
    return false;
  }

  /**
   * Chuyển hướng nếu không phải admin.
   * Gọi ở đầu các trang admin.
   */
  function redirectIfNotAdmin() {
    if (!isLoggedIn()) {
      window.location.href = '/frontend/pages/login.html';
      return false;
    }
    if (!isAdmin()) {
      window.location.href = '/frontend/index.html';
      return false;
    }
    return true;
  }

  // ── Check Auth (async — refreshes user from server) ────────
  /**
   * Kiểm tra trạng thái đăng nhập và làm mới thông tin user.
   * @returns {Promise<Object|null>} User object or null
   */
  async function checkAuth() {
    if (!isLoggedIn()) return null;
    try {
      const data = await API.getProfile();
      const user = data.user || data;
      localStorage.setItem(USER_KEY, JSON.stringify(user));
      return user;
    } catch {
      clearSession();
      return null;
    }
  }

  // ── UI Display ─────────────────────────────────────────────
  /**
   * Hiển thị thông tin user trên thanh nav (header).
   * Ẩn link đăng nhập/đăng ký, hiện avatar menu.
   */
  function displayUserInfo() {
    const user = getUser();
    if (!user) return;

    // Hide auth links, show user menu
    const authLinks   = document.querySelector('.auth-links');
    const userMenuEl  = document.querySelector('.user-menu');
    if (authLinks)  authLinks.style.display  = 'none';
    if (userMenuEl) userMenuEl.style.display = 'block';

    // Set avatar initials
    const avatarEls = document.querySelectorAll('.user-avatar');
    avatarEls.forEach(el => {
      el.textContent = getInitials(user.name);
    });

    // Set username in dropdown
    const userNameEl = document.querySelector('.user-name');
    if (userNameEl) userNameEl.textContent = user.name;

    const userEmailEl = document.querySelector('.user-email');
    if (userEmailEl) userEmailEl.textContent = user.email;

    // Admin link visibility
    const adminLinkEl = document.querySelector('.admin-nav-link');
    if (adminLinkEl) {
      adminLinkEl.style.display = isAdmin() ? 'flex' : 'none';
    }
  }

  /**
   * Hiển thị thông tin user trong trang admin header.
   */
  function displayAdminUserInfo() {
    const user = getUser();
    if (!user) return;

    const nameEls = document.querySelectorAll('[data-admin-name]');
    nameEls.forEach(el => el.textContent = user.name);

    const avatarEls = document.querySelectorAll('[data-admin-avatar]');
    avatarEls.forEach(el => el.textContent = getInitials(user.name));

    const emailEls = document.querySelectorAll('[data-admin-email]');
    emailEls.forEach(el => el.textContent = user.email);
  }

  // ── Logout ─────────────────────────────────────────────────
  /**
   * Xử lý đăng xuất: gọi API, xoá session, chuyển hướng.
   */
  async function handleLogout() {
    try {
      await API.logout();
    } catch { /* ignore */ } finally {
      clearSession();
      window.location.href = '/frontend/pages/login.html';
    }
  }

  // ── Init ───────────────────────────────────────────────────
  /**
   * Khởi tạo auth trên mọi trang: gắn sự kiện logout button.
   */
  function init() {
    displayUserInfo();

    // Logout buttons
    document.querySelectorAll('[data-logout]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        handleLogout();
      });
    });

    // Show/hide nav items based on auth state
    const loggedInEls  = document.querySelectorAll('[data-show-auth]');
    const loggedOutEls = document.querySelectorAll('[data-show-guest]');
    if (isLoggedIn()) {
      loggedInEls.forEach(el  => el.style.display = '');
      loggedOutEls.forEach(el => el.style.display = 'none');
    } else {
      loggedInEls.forEach(el  => el.style.display = 'none');
      loggedOutEls.forEach(el => el.style.display = '');
    }
  }

  // ── Utilities ──────────────────────────────────────────────
  function getInitials(name = '') {
    return name.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2);
  }

  /**
   * Lưu token và user sau khi đăng nhập thành công từ form.
   */
  function saveSession(data) {
    if (data.token && data.user) {
      setSession(data.token, data.user);
    }
  }

  // ── Public ─────────────────────────────────────────────────
  return {
    getToken,
    getUser,
    setSession,
    clearSession,
    saveSession,
    isLoggedIn,
    isAdmin,
    checkAuth,
    redirectIfNotAuth,
    redirectIfAuth,
    redirectIfNotAdmin,
    displayUserInfo,
    displayAdminUserInfo,
    handleLogout,
    getInitials,
    init,
  };
})();

window.Auth = Auth;
