// Bootstrap modal fallback. The municipal portals must remain usable when the
// CDN-hosted Bootstrap bundle is unavailable (for example, on an offline LGU
// workstation). Bootstrap itself is still preferred whenever it loaded.
(function () {
  if (window.bootstrap && window.bootstrap.Modal) return;

  const instances = new WeakMap();
  let openModal = null;
  let backdrop = null;

  class ModalFallback {
    constructor(element) {
      this.element = element;
      instances.set(element, this);
    }

    show() {
      if (!this.element || this.element.classList.contains('show')) return;
      if (openModal && openModal !== this) openModal.hide();

      openModal = this;
      this.element.style.display = 'block';
      this.element.removeAttribute('aria-hidden');
      this.element.setAttribute('aria-modal', 'true');
      this.element.setAttribute('role', 'dialog');
      this.element.classList.add('show');
      document.body.classList.add('modal-open');
      document.body.style.overflow = 'hidden';

      backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show';
      document.body.appendChild(backdrop);

      this.element.dispatchEvent(new CustomEvent('shown.bs.modal', { bubbles: true }));
      const focusTarget = this.element.querySelector('[autofocus], input:not([type="hidden"]), select, textarea, button');
      if (focusTarget) focusTarget.focus();
    }

    hide() {
      if (!this.element || !this.element.classList.contains('show')) return;
      this.element.classList.remove('show');
      this.element.style.display = 'none';
      this.element.setAttribute('aria-hidden', 'true');
      this.element.removeAttribute('aria-modal');
      this.element.removeAttribute('role');
      if (backdrop) backdrop.remove();
      backdrop = null;
      openModal = null;
      document.body.classList.remove('modal-open');
      document.body.style.removeProperty('overflow');
      this.element.dispatchEvent(new CustomEvent('hidden.bs.modal', { bubbles: true }));
    }

    static getOrCreateInstance(element) {
      return instances.get(element) || new ModalFallback(element);
    }
  }

  window.bootstrap = window.bootstrap || {};
  window.bootstrap.Modal = ModalFallback;

  document.addEventListener('click', event => {
    const trigger = event.target.closest('[data-bs-toggle="modal"]');
    if (trigger) {
      const selector = trigger.getAttribute('data-bs-target') || trigger.getAttribute('href');
      const modal = selector && selector.startsWith('#') ? document.querySelector(selector) : null;
      if (modal) {
        event.preventDefault();
        ModalFallback.getOrCreateInstance(modal).show();
      }
      return;
    }

    const dismiss = event.target.closest('[data-bs-dismiss="modal"]');
    if (dismiss) {
      const modal = dismiss.closest('.modal');
      if (modal) ModalFallback.getOrCreateInstance(modal).hide();
      return;
    }

    if (openModal && event.target === openModal.element) openModal.hide();
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && openModal) openModal.hide();
  });
})();

// Sidebar toggle + active link + chart defaults
(function () {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('backdrop');
  if (toggle && sidebar && backdrop) {
    toggle.addEventListener('click', () => {
      if (window.innerWidth >= 992) {
        document.body.classList.toggle('sidebar-collapsed');
        const collapsed = document.body.classList.contains('sidebar-collapsed');
        localStorage.setItem('travisSidebarCollapsed', collapsed ? '1' : '0');
        toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      } else {
        sidebar.classList.toggle('show');
        backdrop.classList.toggle('show');
        toggle.setAttribute('aria-expanded', sidebar.classList.contains('show') ? 'true' : 'false');
      }
    });
    backdrop.addEventListener('click', () => {
      sidebar.classList.remove('show');
      backdrop.classList.remove('show');
      toggle.setAttribute('aria-expanded', 'false');
    });

    if (window.innerWidth >= 992 && localStorage.getItem('travisSidebarCollapsed') === '1') {
      document.body.classList.add('sidebar-collapsed');
      toggle.setAttribute('aria-expanded', 'false');
    }
  }

  // Highlight current nav
  const path = location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.sidebar .nav-link').forEach(a => {
    const href = a.getAttribute('href');
    if (href && href.endsWith(path)) a.classList.add('active');
  });

  // Chart.js global defaults
  if (window.Chart) {
    Chart.defaults.font.family = "'Inter', system-ui, sans-serif";
    Chart.defaults.color = '#49657f';
    Chart.defaults.borderColor = 'rgba(25, 118, 210, .12)';
  }

  // Live clock
  const clock = document.getElementById('liveClock');
  if (clock) {
    const tick = () => {
      const d = new Date();
      clock.textContent = d.toLocaleString([], { dateStyle: 'medium', timeStyle: 'medium' });
    };
    tick();
    setInterval(tick, 1000);
  }
})();
