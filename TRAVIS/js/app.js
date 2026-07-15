// Sidebar toggle + active link + chart defaults
(function () {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const backdrop = document.getElementById('backdrop');
  if (toggle && sidebar && backdrop) {
    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('show');
      backdrop.classList.toggle('show');
    });
    backdrop.addEventListener('click', () => {
      sidebar.classList.remove('show');
      backdrop.classList.remove('show');
    });
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

  // Shared system feedback and programmatic notifications
  document.querySelectorAll('[data-system-feedback]').forEach(feedback => {
    feedback.querySelector('[data-feedback-dismiss]')?.addEventListener('click', () => {
      feedback.classList.add('is-hiding');
      window.setTimeout(() => feedback.remove(), 280);
    });

    if (feedback.classList.contains('system-feedback-success')) {
      window.setTimeout(() => {
        if (!feedback.isConnected) return;
        feedback.classList.add('is-hiding');
        window.setTimeout(() => feedback.remove(), 280);
      }, 7000);
    }
  });

  window.travisNotify = (title, message, type = 'info') => {
    const region = document.getElementById('systemNotificationRegion');
    if (!region) return;
    const allowed = ['success', 'danger', 'warning', 'info'];
    const safeType = allowed.includes(type) ? type : 'info';
    const icons = {
      success: 'bi-check-circle-fill',
      danger: 'bi-x-octagon-fill',
      warning: 'bi-exclamation-triangle-fill',
      info: 'bi-info-circle-fill'
    };
    const notification = document.createElement('div');
    notification.className = `system-feedback system-feedback-${safeType} system-feedback-floating`;

    const icon = document.createElement('span');
    icon.className = 'system-feedback-icon';
    icon.innerHTML = `<i class="bi ${icons[safeType]}"></i>`;
    const copy = document.createElement('span');
    copy.className = 'system-feedback-copy';
    const heading = document.createElement('strong');
    heading.textContent = title;
    const body = document.createElement('span');
    body.textContent = message;
    copy.append(heading, body);
    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.setAttribute('aria-label', 'Dismiss notification');
    dismiss.innerHTML = '<i class="bi bi-x-lg"></i>';
    dismiss.addEventListener('click', () => notification.remove());
    notification.append(icon, copy, dismiss);
    region.appendChild(notification);

    window.setTimeout(() => {
      notification.classList.add('is-hiding');
      window.setTimeout(() => notification.remove(), 280);
    }, safeType === 'danger' ? 10000 : 7000);
  };

  // Reusable replacement for native browser confirmation prompts
  const confirmElement = document.getElementById('systemConfirmModal');
  const confirmButton = document.getElementById('systemConfirmSubmit');
  if (confirmElement && confirmButton && window.bootstrap) {
    const confirmModal = bootstrap.Modal.getOrCreateInstance(confirmElement);
    let pendingForm = null;
    let pendingSubmitter = null;

    document.addEventListener('submit', event => {
      const form = event.target;
      if (!(form instanceof HTMLFormElement) || !form.matches('[data-confirm]')) return;
      if (form.dataset.confirmed === 'true') {
        delete form.dataset.confirmed;
        return;
      }

      event.preventDefault();
      pendingForm = form;
      pendingSubmitter = event.submitter || null;
      const tone = form.dataset.confirmTone || 'primary';
      confirmElement.dataset.tone = tone;
      const toneIcons = {
        danger: 'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        success: 'bi-check-lg',
        primary: 'bi-question-lg'
      };
      document.getElementById('systemConfirmIcon').innerHTML = `<i class="bi ${toneIcons[tone] || toneIcons.primary}"></i>`;
      document.getElementById('systemConfirmTitle').textContent = form.dataset.confirmTitle || 'Continue with this action?';
      document.getElementById('systemConfirmMessage').textContent = form.dataset.confirm || 'Please review this operation before continuing.';
      document.getElementById('systemConfirmSubmitLabel').textContent = form.dataset.confirmLabel || 'Continue';
      document.getElementById('systemConfirmEyebrow').textContent = form.dataset.confirmEyebrow || 'Confirm action';
      confirmModal.show();
    });

    confirmButton.addEventListener('click', () => {
      if (!pendingForm) return;
      const form = pendingForm;
      const submitter = pendingSubmitter;
      pendingForm = null;
      pendingSubmitter = null;
      form.dataset.confirmed = 'true';
      confirmModal.hide();
      if (typeof form.requestSubmit === 'function') form.requestSubmit(submitter);
      else form.submit();
    });

    confirmElement.addEventListener('hidden.bs.modal', () => {
      pendingForm = null;
      pendingSubmitter = null;
    });
  }
})();
