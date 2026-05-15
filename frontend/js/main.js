/**
 * main.js — Site-wide Initialization
 * Bootstraps: navbar, mobile menu, cart count, search, toast system.
 * Loaded on every page.
 */

(function () {
  'use strict';

  // ── Toast System ─────────────────────────────────────────────
  /**
   * Hiển thị toast notification.
   * @param {string} message
   * @param {'success'|'error'|'warning'|'info'} type
   * @param {number} duration  ms
   */
  window.showToast = function (message, type = 'info', duration = 3500) {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const icons = { success: '✅', error: '❌', warning: '⚠️', info: 'ℹ️' };
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
      <span class="toast-icon">${icons[type] || icons.info}</span>
      <span class="toast-msg">${message}</span>
      <button class="toast-close" aria-label="Đóng">✕</button>`;

    toast.querySelector('.toast-close').addEventListener('click', () => removeToast(toast));
    container.appendChild(toast);

    // Auto remove
    const timer = setTimeout(() => removeToast(toast), duration);
    toast._timer = timer;
  };

  function removeToast(toast) {
    clearTimeout(toast._timer);
    toast.style.animation = 'none';
    toast.style.opacity   = '0';
    toast.style.transform = 'translateX(120%)';
    toast.style.transition = 'all .3s ease';
    setTimeout(() => toast.remove(), 300);
  }

  // ── Nav Active State ─────────────────────────────────────────
  function initNavActiveState() {
    const currentPath = window.location.pathname;
    document.querySelectorAll('.main-nav a, .mobile-nav-links a').forEach(link => {
      const href = link.getAttribute('href') || '';
      if (!href) return;
      const isHome    = href === '/frontend/index.html' || href === '/' || href === '../index.html';
      const isOnHome  = currentPath === '/' || currentPath.endsWith('index.html');
      if ((isHome && isOnHome) || (!isHome && currentPath.includes(href.replace('../', '/frontend/')))) {
        link.classList.add('active');
      }
    });
  }

  // ── Mobile Menu ──────────────────────────────────────────────
  function initMobileMenu() {
    const hamburger   = document.querySelector('.hamburger');
    const mobileNav   = document.querySelector('.mobile-nav');
    const overlay     = mobileNav && mobileNav.querySelector('.mobile-nav-overlay');
    const closeBtn    = mobileNav && mobileNav.querySelector('.mobile-nav-close');

    if (!hamburger || !mobileNav) return;

    function openMenu() {
      mobileNav.classList.add('open');
      document.body.style.overflow = 'hidden';
      hamburger.setAttribute('aria-expanded', 'true');
    }
    function closeMenu() {
      mobileNav.classList.remove('open');
      document.body.style.overflow = '';
      hamburger.setAttribute('aria-expanded', 'false');
    }

    hamburger.addEventListener('click', openMenu);
    if (overlay) overlay.addEventListener('click', closeMenu);
    if (closeBtn) closeBtn.addEventListener('click', closeMenu);

    // Close on nav link click
    mobileNav.querySelectorAll('a').forEach(a => a.addEventListener('click', closeMenu));

    // Hamburger animation
    hamburger.addEventListener('click', () => {
      const spans = hamburger.querySelectorAll('span');
      const isOpen = mobileNav.classList.contains('open');
      if (isOpen) {
        spans[0].style.transform = '';
        spans[1].style.opacity   = '1';
        spans[2].style.transform = '';
      } else {
        spans[0].style.transform = 'translateY(7px) rotate(45deg)';
        spans[1].style.opacity   = '0';
        spans[2].style.transform = 'translateY(-7px) rotate(-45deg)';
      }
    });
  }

  // ── Header Search ────────────────────────────────────────────
  function initHeaderSearch() {
    const form   = document.querySelector('.header-search');
    const input  = form && form.querySelector('input');
    const button = form && form.querySelector('button');

    if (!input) return;

    function doSearch() {
      const q = input.value.trim();
      if (!q) return;
      window.location.href = `/frontend/pages/products.html?search=${encodeURIComponent(q)}`;
    }

    if (button) button.addEventListener('click', doSearch);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') doSearch(); });
  }

  // ── Cart Count ───────────────────────────────────────────────
  async function initCartCount() {
    // Immediate load from cache
    const cached = parseInt(localStorage.getItem('cart_count')) || 0;
    if (window.Cart) {
      Cart.updateCartCount(cached);
      // Then fetch fresh count
      try { await Cart.fetchCartCount(); } catch { /* noop */ }
    } else {
      // Fallback: just show badge
      document.querySelectorAll('.cart-count').forEach(el => {
        el.textContent = cached;
        el.style.display = cached > 0 ? 'flex' : 'none';
      });
    }
  }

  // ── Header Scroll Effect ─────────────────────────────────────
  function initScrollEffect() {
    const header = document.querySelector('.site-header');
    if (!header) return;

    let lastScroll = 0;
    window.addEventListener('scroll', () => {
      const current = window.scrollY;
      if (current > 80) {
        header.style.boxShadow = '0 4px 20px rgba(0,0,0,.12)';
      } else {
        header.style.boxShadow = '0 2px 8px rgba(0,0,0,.09)';
      }
      lastScroll = current;
    }, { passive: true });
  }

  // ── Sticky Sale Banner dismiss ───────────────────────────────
  function initSaleBanner() {
    const banner    = document.querySelector('.sale-banner');
    const dismissEl = banner && banner.querySelector('[data-dismiss]');
    if (dismissEl) {
      dismissEl.addEventListener('click', () => {
        banner.style.height   = banner.offsetHeight + 'px';
        banner.style.overflow = 'hidden';
        banner.style.transition = 'height .3s, padding .3s';
        requestAnimationFrame(() => {
          banner.style.height  = '0';
          banner.style.padding = '0';
        });
        setTimeout(() => banner.remove(), 300);
      });
    }
  }

  // ── Lazy Images ──────────────────────────────────────────────
  function initLazyImages() {
    if (!('IntersectionObserver' in window)) return;
    const imgs = document.querySelectorAll('img[loading="lazy"]');
    // Browser handles lazy natively; add fade effect
    imgs.forEach(img => {
      img.style.opacity    = '0';
      img.style.transition = 'opacity .3s';
      if (img.complete) {
        img.style.opacity = '1';
      } else {
        img.addEventListener('load', () => { img.style.opacity = '1'; });
      }
    });
  }

  // ── Back to Top ──────────────────────────────────────────────
  function initBackToTop() {
    const btn = document.getElementById('back-to-top');
    if (!btn) return;
    window.addEventListener('scroll', () => {
      btn.style.display = window.scrollY > 400 ? 'flex' : 'none';
    }, { passive: true });
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }

  // ── Dropdown menus ───────────────────────────────────────────
  function initDropdowns() {
    // Close dropdowns on outside click
    document.addEventListener('click', (e) => {
      if (!e.target.closest('.user-menu')) {
        document.querySelectorAll('.user-dropdown').forEach(d => {
          // CSS :hover handles it; nothing extra needed unless JS-driven
        });
      }
    });
  }

  // ── Form helpers: password toggle ────────────────────────────
  function initPasswordToggle() {
    document.querySelectorAll('.show-password-toggle').forEach(btn => {
      btn.addEventListener('click', () => {
        const input = btn.previousElementSibling || btn.closest('.password-field').querySelector('input');
        if (!input) return;
        const isText = input.type === 'text';
        input.type   = isText ? 'password' : 'text';
        btn.textContent = isText ? '👁️' : '🙈';
      });
    });
  }

  // ── Tab components ───────────────────────────────────────────
  function initTabs() {
    document.querySelectorAll('.tab-nav').forEach(nav => {
      nav.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          const target  = btn.dataset.tab;
          const wrapper = btn.closest('.product-tabs') || document;

          // Deactivate all
          nav.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
          wrapper.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

          // Activate clicked
          btn.classList.add('active');
          const content = wrapper.querySelector(`#tab-${target}`);
          if (content) content.classList.add('active');
        });
      });
    });
  }

  // ── Number format helper ─────────────────────────────────────
  window.formatVND = function (amount) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(amount || 0);
  };

  // ── Main Init ────────────────────────────────────────────────
  function init() {
    initNavActiveState();
    initMobileMenu();
    initHeaderSearch();
    initScrollEffect();
    initSaleBanner();
    initLazyImages();
    initBackToTop();
    initDropdowns();
    initPasswordToggle();
    initTabs();

    // Auth
    if (window.Auth) {
      Auth.init();
    }

    // Cart count (non-blocking)
    initCartCount();

    // Dispatch ready event for page-specific scripts
    document.dispatchEvent(new CustomEvent('appReady'));
  }

  // Run after DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
