<?php
require_once __DIR__ . '/layout.php';

// Only violations recorded within the last 2 days show up as "New Violation" notifications.
$newViolations = fetch_all("
    SELECT violation_id, ticket_number, plate_number, driver_name, license_number, vehicle_type,
           violation_type, violation_location, violation_date, violation_time, penalty_amount, status, input_method, created_at
    FROM violations
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 DAY)
      AND status != 'paid'
    ORDER BY created_at DESC
    LIMIT 50
");

$recentCompletedPayments = fetch_all("
    SELECT p.payment_id, p.amount_paid, p.payment_date, v.plate_number
    FROM payments p
    JOIN violations v ON v.violation_id = p.violation_id
    WHERE p.payment_status = 'completed'
    ORDER BY p.payment_date DESC
    LIMIT 5
");

$systemAlerts = fetch_all("
    SELECT alert_id, message, severity, generated_at
    FROM monitoring_alerts
    WHERE status = 'active'
    ORDER BY generated_at DESC
    LIMIT 5
");

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}

// Build a flat list of notification items, each with a stable unique key.
// Read/deleted state for these keys is tracked client-side (localStorage),
// so it survives logging out and back in on the same browser.
$items = [];

foreach ($newViolations as $v) {
    $items[] = [
        'key' => 'violation:' . $v['violation_id'],
        'tone' => 'tone-info',
        'title' => 'New Violation Recorded',
        'time' => time_ago($v['created_at']),
        'body' => 'Ticket ' . esc($v['ticket_number']) . ' &mdash; plate ' . esc($v['plate_number']) . ' flagged for ' . esc($v['violation_type']) . ' at ' . esc($v['violation_location']) . '.',
        'violation' => $v,
    ];
}

foreach ($recentCompletedPayments as $p) {
    $items[] = [
        'key' => 'payment:' . $p['payment_id'],
        'tone' => 'tone-success',
        'title' => 'Payment Completed',
        'time' => time_ago($p['payment_date']),
        'body' => esc(payment_reference((int)$p['payment_id'])) . ' processed successfully for ' . peso($p['amount_paid']) . ' (plate ' . esc($p['plate_number']) . ').',
        'violation' => null,
    ];
}

foreach ($systemAlerts as $a) {
    $items[] = [
        'key' => 'alert:' . $a['alert_id'],
        'tone' => 'tone-danger',
        'title' => 'System Alert',
        'time' => time_ago($a['generated_at']),
        'body' => esc($a['message']),
        'violation' => null,
    ];
}

page_start('Notifications', 'notifications', 'Search notifications...', 'System alerts and updates relevant to collections', false);
?>

<style>
.notif-card{position:relative}
.notif-card.is-unread{background:#f5f8ff;border-left-width:4px}
.notif-card.is-unread strong::before{content:"";display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--primary,#1e3a8a);margin-right:8px;vertical-align:middle}
.notif-actions{display:flex;gap:.5rem;flex-shrink:0}
.notif-actions button{border:none;background:transparent;color:#94a3b8;padding:2px 4px;line-height:1}
.notif-actions button:hover{color:#1e3a8a}
.notif-actions button.notif-delete:hover{color:#dc2626}
.notif-actions button.notif-view:hover{color:#0d9488}
</style>

<div id="notifMarkAllWrap" class="d-flex justify-content-end mb-3 d-none">
  <button type="button" id="notifMarkAllBtn" class="btn btn-sm btn-light"><i class="bi bi-check2-all me-1"></i>Mark all as read</button>
</div>

<div id="notifList">
  <?php foreach ($items as $item): ?>
    <div class="notif-card <?= esc($item['tone']) ?>" data-notif-key="<?= esc($item['key']) ?>">
      <div class="d-flex justify-content-between align-items-start gap-2">
        <strong><?= esc($item['title']) ?></strong>
        <div class="d-flex align-items-center gap-2">
          <small class="text-muted"><?= esc($item['time']) ?></small>
          <div class="notif-actions">
            <?php if ($item['violation']): $v = $item['violation']; ?>
              <button type="button" class="notif-view" title="View details"
                data-violation-id="<?= (int)$v['violation_id'] ?>"
                data-ticket="<?= esc($v['ticket_number']) ?>"
                data-status="<?= esc(ucfirst($v['status'])) ?>"
                data-status-raw="<?= esc($v['status']) ?>"
                data-status-class="<?= esc(tag_class($v['status'])) ?>"
                data-penalty="<?= esc(peso($v['penalty_amount'])) ?>"
                data-driver="<?= esc($v['driver_name']) ?>"
                data-license="<?= esc($v['license_number']) ?>"
                data-plate="<?= esc($v['plate_number']) ?>"
                data-vehicle="<?= esc($v['vehicle_type']) ?>"
                data-violation-type="<?= esc($v['violation_type']) ?>"
                data-location="<?= esc($v['violation_location']) ?>"
                data-datetime="<?= esc($v['violation_date'] . ' ' . $v['violation_time']) ?>"
                data-input-method="<?= esc($v['input_method']) ?>"
                onclick="viewViolationNotif(this)"
              ><i class="bi bi-eye"></i></button>
            <?php endif; ?>
            <button type="button" class="notif-mark-read" title="Mark as read" onclick="markNotifRead(this)"><i class="bi bi-check2"></i></button>
            <button type="button" class="notif-delete" title="Delete" onclick="deleteNotif(this)"><i class="bi bi-trash"></i></button>
          </div>
        </div>
      </div>
      <div class="text-muted small mt-1"><?= $item['body'] ?></div>
    </div>
  <?php endforeach; ?>
</div>

<?php if (!$items): ?>
  <?php empty_state('You are all caught up. No new notifications.'); ?>
<?php endif; ?>

<div id="notifEmptyState" class="d-none"><?php empty_state('You are all caught up. No new notifications.'); ?></div>

<div class="modal fade" id="violationDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <div><h5 class="modal-title">Violation Details</h5><small class="text-muted" id="vd_ticket"></small></div>
        <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-6"><strong>Status</strong><br><span class="tag" id="vd_status_tag"></span></div>
          <div class="col-6"><strong>Penalty</strong><br><span id="vd_penalty"></span></div>
          <div class="col-6"><strong>Driver</strong><br><span id="vd_driver"></span></div>
          <div class="col-6"><strong>License Number</strong><br><span id="vd_license"></span></div>
          <div class="col-6"><strong>Plate Number</strong><br><span id="vd_plate"></span></div>
          <div class="col-6"><strong>Vehicle Type</strong><br><span id="vd_vehicle"></span></div>
          <div class="col-6"><strong>Violation Type</strong><br><span id="vd_violation_type"></span></div>
          <div class="col-6"><strong>Location</strong><br><span id="vd_location"></span></div>
          <div class="col-6"><strong>Date &amp; Time</strong><br><span id="vd_datetime"></span></div>
          <div class="col-6"><strong>Input Method</strong><br><span id="vd_input_method"></span></div>
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
        <a class="btn btn-outline-secondary" href="<?= esc(app_url('violations.php')) ?>">Go to Violation Records</a>
        <a class="btn btn-teal d-none" id="vd_pay_btn" href="#"><i class="bi bi-cash-coin me-1"></i>Pay Now</a>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="confirmDeleteNotifModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-4 text-center">
        <div class="mb-2" style="font-size:2.2rem;color:#dc2626;"><i class="bi bi-trash3"></i></div>
        <h5 class="mb-1">Delete this notification?</h5>
        <p class="text-muted small mb-0">This will remove it from your notifications list. This can't be undone.</p>
      </div>
      <div class="modal-footer border-top-0 pt-0 justify-content-center">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" id="confirmDeleteNotifBtn"><i class="bi bi-trash me-1"></i>Delete</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var READ_KEY = 'travis_notif_read';
  var DELETED_KEY = 'travis_notif_deleted';

  function loadSet(storageKey) {
    try { return new Set(JSON.parse(localStorage.getItem(storageKey) || '[]')); }
    catch (e) { return new Set(); }
  }
  function saveSet(storageKey, set) {
    try { localStorage.setItem(storageKey, JSON.stringify(Array.from(set))); }
    catch (e) { /* storage unavailable, fail silently */ }
  }

  var readSet = loadSet(READ_KEY);
  var deletedSet = loadSet(DELETED_KEY);

  function updateMarkAllVisibility() {
    var anyUnread = document.querySelector('#notifList .notif-card.is-unread') !== null;
    document.getElementById('notifMarkAllWrap').classList.toggle('d-none', !anyUnread);
  }

  function updateEmptyState() {
    var anyLeft = document.querySelector('#notifList .notif-card') !== null;
    document.getElementById('notifEmptyState').classList.toggle('d-none', anyLeft);
  }

  function applyState() {
    document.querySelectorAll('#notifList .notif-card').forEach(function (card) {
      var key = card.dataset.notifKey;
      if (deletedSet.has(key)) {
        card.remove();
        return;
      }
      if (readSet.has(key)) {
        card.classList.remove('is-unread');
        var readBtn = card.querySelector('.notif-mark-read');
        if (readBtn) readBtn.style.display = 'none';
      } else {
        card.classList.add('is-unread');
      }
    });
    updateMarkAllVisibility();
    updateEmptyState();
  }

  window.markNotifRead = function (btn) {
    var card = btn.closest('.notif-card');
    var key = card.dataset.notifKey;
    readSet.add(key);
    saveSet(READ_KEY, readSet);
    card.classList.remove('is-unread');
    btn.style.display = 'none';
    updateMarkAllVisibility();
  };

  var pendingDeleteBtn = null;

  window.deleteNotif = function (btn) {
    pendingDeleteBtn = btn;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmDeleteNotifModal')).show();
  };

  document.getElementById('confirmDeleteNotifBtn').addEventListener('click', function () {
    if (!pendingDeleteBtn) return;
    var card = pendingDeleteBtn.closest('.notif-card');
    var key = card.dataset.notifKey;
    deletedSet.add(key);
    saveSet(DELETED_KEY, deletedSet);
    card.remove();
    pendingDeleteBtn = null;
    updateMarkAllVisibility();
    updateEmptyState();
    bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmDeleteNotifModal')).hide();
  });

  window.viewViolationNotif = function (btn) {
    var d = btn.dataset;
    document.getElementById('vd_ticket').textContent = d.ticket || '';
    var tag = document.getElementById('vd_status_tag');
    tag.textContent = d.status || '';
    tag.className = 'tag ' + (d.statusClass || '');
    document.getElementById('vd_penalty').textContent = d.penalty || '';
    document.getElementById('vd_driver').textContent = d.driver || '';
    document.getElementById('vd_license').textContent = d.license || '';
    document.getElementById('vd_plate').textContent = d.plate || '';
    document.getElementById('vd_vehicle').textContent = d.vehicle || '';
    document.getElementById('vd_violation_type').textContent = d.violationType || '';
    document.getElementById('vd_location').textContent = d.location || '';
    document.getElementById('vd_datetime').textContent = d.datetime || '';
    document.getElementById('vd_input_method').textContent = d.inputMethod || '';

    var payBtn = document.getElementById('vd_pay_btn');
    if (d.statusRaw === 'pending' || d.statusRaw === 'overdue') {
      payBtn.href = '<?= esc(app_url('payments.php')) ?>?violation_id=' + encodeURIComponent(d.violationId || '');
      payBtn.classList.remove('d-none');
    } else {
      payBtn.classList.add('d-none');
    }

    markNotifRead(btn);

    bootstrap.Modal.getOrCreateInstance(document.getElementById('violationDetailModal')).show();
  };

  document.getElementById('notifMarkAllBtn').addEventListener('click', function () {
    document.querySelectorAll('#notifList .notif-card.is-unread').forEach(function (card) {
      readSet.add(card.dataset.notifKey);
      card.classList.remove('is-unread');
      var readBtn = card.querySelector('.notif-mark-read');
      if (readBtn) readBtn.style.display = 'none';
    });
    saveSet(READ_KEY, readSet);
    updateMarkAllVisibility();
  });

  applyState();
})();
</script>

<?php page_end(); ?>