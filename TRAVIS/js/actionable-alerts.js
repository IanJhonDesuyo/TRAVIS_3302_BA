(() => {
  const modalElement = document.getElementById('actionableAlertModal');
  if (!modalElement || typeof bootstrap === 'undefined') return;

  const modal = bootstrap.Modal.getOrCreateInstance(modalElement, {
    backdrop: false,
    keyboard: true,
  });
  const typeElement = document.getElementById('actionableAlertType');
  const messageElement = document.getElementById('actionableAlertMessage');
  const timeElement = document.getElementById('actionableAlertTime');
  const iconElement = document.getElementById('actionableAlertIcon');
  const acknowledgeButton = document.getElementById('acknowledgeActionableAlert');
  const noteElement = document.getElementById('actionableAlertNote');
  let currentAlert = null;
  let cooldownSeconds = 300;
  let polling = false;

  const storageKey = alert => `travis-alert-modal:${alert.alert_id}`;
  const lastShownAt = alert => Number(localStorage.getItem(storageKey(alert)) || 0);

  function isDue(alert) {
    const lastShown = lastShownAt(alert);
    if (!lastShown) return true;
    if (alert.alert_type !== 'officer_absence') return false;
    return Date.now() - lastShown >= cooldownSeconds * 1000;
  }

  function showAlert(alert) {
    currentAlert = alert;
    const officerAbsence = alert.alert_type === 'officer_absence';
    modalElement.classList.toggle('officer-alert-modal', officerAbsence);
    modalElement.classList.toggle('critical-alert-modal', !officerAbsence);
    typeElement.textContent = officerAbsence ? 'Officer absence detected' : 'Critical traffic alert';
    messageElement.textContent = alert.message;
    timeElement.textContent = `Detected ${new Date(String(alert.generated_at).replace(' ', 'T')).toLocaleString()}`;
    iconElement.className = officerAbsence
      ? 'bi bi-person-x-fill'
      : 'bi bi-exclamation-octagon-fill';
    if (noteElement) {
      noteElement.innerHTML = '<i class="bi bi-clock-history me-1"></i>Officer-absence reminders follow the configured alert cooldown.';
    }
    localStorage.setItem(storageKey(alert), String(Date.now()));
    modal.show();
  }

  async function pollAlerts() {
    if (polling || document.hidden || document.querySelector('.modal.show')) return;
    polling = true;
    try {
      const response = await fetch('/TRAVIS/api/get_actionable_alerts.php', {
        cache: 'no-store',
        credentials: 'same-origin',
      });
      if (!response.ok) return;
      const payload = await response.json();
      cooldownSeconds = Math.max(60, Number(payload.cooldown_seconds || 300));
      const nextAlert = (payload.data || []).find(isDue);
      if (nextAlert) showAlert(nextAlert);
    } catch (_) {
      // Keep monitoring non-blocking when the notification endpoint is offline.
    } finally {
      polling = false;
    }
  }

  acknowledgeButton?.addEventListener('click', async () => {
    if (!currentAlert) return;
    acknowledgeButton.disabled = true;
    acknowledgeButton.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>Saving...</span>';
    try {
      const response = await fetch('/TRAVIS/api/acknowledge_alert.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ alert_id: Number(currentAlert.alert_id) }),
      });
      if (!response.ok) throw new Error('Unable to acknowledge alert');
      acknowledgeButton.innerHTML = '<i class="bi bi-check2"></i><span>Acknowledged</span>';
      window.setTimeout(() => modal.hide(), 350);
    } catch (error) {
      acknowledgeButton.disabled = false;
      acknowledgeButton.innerHTML = '<i class="bi bi-arrow-clockwise"></i><span>Try again</span>';
      if (noteElement) {
        noteElement.innerHTML = '<i class="bi bi-exclamation-circle me-1"></i>Unable to acknowledge this alert. Check your session and try again.';
      }
    }
  });

  modalElement.addEventListener('hidden.bs.modal', () => {
    acknowledgeButton.disabled = false;
    acknowledgeButton.innerHTML = '<i class="bi bi-check2-circle"></i><span>Acknowledge</span>';
    currentAlert = null;
  });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) pollAlerts();
  });

  window.setTimeout(pollAlerts, 1200);
  window.setInterval(pollAlerts, 5000);
})();
