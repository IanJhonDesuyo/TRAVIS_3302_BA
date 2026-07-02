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
    Chart.defaults.color = '#6b7280';
    Chart.defaults.borderColor = '#e5e7eb';
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
