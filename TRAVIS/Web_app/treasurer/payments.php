<?php
require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';

function payment_post(string $key, string $default = ''): string {
    return trim((string)($_POST[$key] ?? $default));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_payment') {
    $csrfOk = hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''));
    $violationId = (int)($_POST['violation_id'] ?? 0);
    $amountPaid = (float)payment_post('amount_paid', '0');
    $paymentMethod = payment_post('payment_method', 'cash');
    $notes = payment_post('notes');
    $paymentDateInput = payment_post('payment_date');
    $paymentDateTime = ($paymentDateInput !== '' && strtotime($paymentDateInput) !== false)
        ? date('Y-m-d H:i:s', strtotime($paymentDateInput))
        : date('Y-m-d H:i:s');

    $allowedMethods = array_keys(payment_method_options());
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $paymentMethod = 'cash';
    }

    if (!$csrfOk) {
        $message = 'Your session expired. Please try again.';
        $messageType = 'danger';
    } else {
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("
                SELECT violation_id, ticket_number, penalty_amount, status
                FROM violations
                WHERE violation_id = ?
                FOR UPDATE
            ");
            $stmt->bind_param('i', $violationId);
            $stmt->execute();
            $violation = $stmt->get_result()->fetch_assoc();

            if (!$violation) throw new RuntimeException('Violation record not found.');
            if ($violation['status'] === 'paid') throw new RuntimeException('This violation has already been paid.');
            if ($violation['status'] === 'cancelled') throw new RuntimeException('Cancelled violations cannot be paid.');
            if ($amountPaid <= 0) throw new RuntimeException('Enter a valid payment amount.');

            $penalty = (float)$violation['penalty_amount'];
            if (abs($amountPaid - $penalty) > 0.009) {
                throw new RuntimeException('The amount paid must match the recorded penalty amount.');
            }

            $stmt = $conn->prepare("
                SELECT payment_id FROM payments
                WHERE violation_id = ? AND payment_status = 'completed'
                LIMIT 1
            ");
            $stmt->bind_param('i', $violationId);
            $stmt->execute();

            if ($stmt->get_result()->fetch_assoc()) {
                throw new RuntimeException('A completed payment already exists for this violation.');
            }

            $receivedBy = (int)($_SESSION['user']['id'] ?? 0);
            $stmt = $conn->prepare("
                INSERT INTO payments (violation_id, amount_paid, payment_status, payment_date, received_by, payment_method, notes)
                VALUES (?, ?, 'completed', ?, ?, ?, ?)
            ");
            if ($stmt) {
                $stmt->bind_param('idsiss', $violationId, $amountPaid, $paymentDateTime, $receivedBy, $paymentMethod, $notes);
                $stmt->execute();
            } else {
                // Fallback for schemas without a "notes" column on payments.
                $stmt = $conn->prepare("
                    INSERT INTO payments (violation_id, amount_paid, payment_status, payment_date, received_by, payment_method)
                    VALUES (?, ?, 'completed', ?, ?, ?)
                ");
                $stmt->bind_param('idsis', $violationId, $amountPaid, $paymentDateTime, $receivedBy, $paymentMethod);
                $stmt->execute();
            }

            $paymentId = $conn->insert_id;

            // Auto-generate the official receipt number now that the payment_id is known.
            $receiptRef = payment_reference((int)$paymentId);
            $stmt = $conn->prepare("UPDATE payments SET receipt_reference = ? WHERE payment_id = ?");
            $stmt->bind_param('si', $receiptRef, $paymentId);
            $stmt->execute();

            $stmt = $conn->prepare("UPDATE violations SET status = 'paid' WHERE violation_id = ?");
            $stmt->bind_param('i', $violationId);
            $stmt->execute();

            $conn->commit();

            $_SESSION['payment_flash'] = 'Payment recorded successfully. Reference: ' . payment_reference((int)$paymentId);
            $_SESSION['payment_flash_type'] = 'success';
            $_SESSION['payment_flash_id'] = (int)$paymentId;
            header('Location: ' . app_url('payments.php'));
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $message = $e->getMessage() ?: 'Failed to record the payment.';
            $messageType = 'danger';
        }
    }
}

if (!empty($_SESSION['payment_flash'])) {
    $message = $_SESSION['payment_flash'];
    $messageType = $_SESSION['payment_flash_type'] ?? 'info';
    unset($_SESSION['payment_flash'], $_SESSION['payment_flash_type']);
}

$justPaidReceipt = null;
if (!empty($_SESSION['payment_flash_id'])) {
    $justPaidId = (int)$_SESSION['payment_flash_id'];
    unset($_SESSION['payment_flash_id']);
    $justPaidReceipt = fetch_one("
        SELECT p.*, v.ticket_number, v.plate_number, v.violation_type, v.driver_name
        FROM payments p
        JOIN violations v ON v.violation_id = p.violation_id
        WHERE p.payment_id = ?
        LIMIT 1
    ", [(string)$justPaidId]);
}

$selectedViolationId = (int)($_GET['violation_id'] ?? 0);
$selectedViolation = null;

if ($selectedViolationId > 0) {
    $stmt = $conn->prepare("SELECT * FROM violations WHERE violation_id = ? LIMIT 1");
    $stmt->bind_param('i', $selectedViolationId);
    $stmt->execute();
    $selectedViolation = $stmt->get_result()->fetch_assoc();
}

$paymentSearch = trim((string)($_GET['payment_search'] ?? ''));
$methodFilter = trim((string)($_GET['method'] ?? ''));

$pendingSql = "
    SELECT v.*
    FROM violations v
    WHERE v.status IN ('pending', 'overdue')
    ORDER BY CASE WHEN v.status = 'overdue' THEN 0 ELSE 1 END, v.violation_date ASC
    LIMIT 200
";
$pendingViolations = fetch_all($pendingSql);

$paymentWhere = [];
$paymentParams = [];
$paymentTypes = '';

if ($paymentSearch !== '') {
    $paymentWhere[] = "(v.ticket_number LIKE ? OR v.plate_number LIKE ? OR p.receipt_reference LIKE ?)";
    $like = "%{$paymentSearch}%";
    array_push($paymentParams, $like, $like, $like);
    $paymentTypes .= 'sss';
}

if ($methodFilter !== '') {
    $paymentWhere[] = 'p.payment_method = ?';
    $paymentParams[] = $methodFilter;
    $paymentTypes .= 's';
}

$paymentWhereSql = $paymentWhere ? 'WHERE ' . implode(' AND ', $paymentWhere) : '';

$paymentSql = "
    SELECT p.*, v.ticket_number, v.plate_number, v.violation_type, u.full_name AS received_by_name
    FROM payments p
    JOIN violations v ON v.violation_id = p.violation_id
    LEFT JOIN users u ON u.user_id = p.received_by
    {$paymentWhereSql}
    ORDER BY p.payment_date DESC, p.payment_id DESC
    LIMIT 50
";

if ($paymentParams) {
    $stmt = $conn->prepare($paymentSql);
    $stmt->bind_param($paymentTypes, ...$paymentParams);
    $stmt->execute();
    $payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $payments = fetch_all($paymentSql);
}

$collectedToday = scalar("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE DATE(payment_date) = CURDATE() AND payment_status = 'completed'", 0);
$thisWeek = scalar("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE YEARWEEK(payment_date, 1) = YEARWEEK(CURDATE(), 1) AND payment_status = 'completed'", 0);
$thisMonth = scalar("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE()) AND payment_status = 'completed'", 0);
$pendingAmount = scalar("SELECT COALESCE(SUM(penalty_amount), 0) FROM violations WHERE status IN ('pending', 'overdue')", 0);
$pendingCount = scalar("SELECT COUNT(*) FROM violations WHERE status IN ('pending', 'overdue')", 0);

page_start('Payments', 'payments', 'Search payments...', 'Process unpaid violations and record collections', false);
?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-success"><i class="bi bi-cash-stack"></i></div><div class="stat-label">Collected Today</div><div class="stat-value"><?= short_money($collectedToday) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-calendar-week"></i></div><div class="stat-label">This Week</div><div class="stat-value"><?= short_money($thisWeek) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-calendar3"></i></div><div class="stat-label">This Month</div><div class="stat-value"><?= short_money($thisMonth) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-warning"><i class="bi bi-hourglass-split"></i></div><div class="stat-label">Pending Settlement</div><div class="stat-value"><?= short_money($pendingAmount) ?></div><small class="text-muted"><?= num($pendingCount) ?> unpaid violations</small></div></div>
</div>

<style>
.pp-card { display: flex; flex-direction: column; height: 100%; }
.pp-card .form-label { font-weight: 600; font-size: .85rem; color: #374151; }
.pp-card .form-control[readonly] { background: #fff; color: #111827; }
.pp-card textarea.form-control { font-family: 'SFMono-Regular', Consolas, monospace; font-size: .85rem; }
.plate-chip {
  display: inline-block;
  font-family: 'SFMono-Regular', Consolas, monospace;
  font-weight: 700;
  background: #fde68a;
  color: #78350f;
  padding: .2rem .55rem;
  border-radius: 6px;
  font-size: .82rem;
}
.pv-id { font-family: 'SFMono-Regular', Consolas, monospace; font-weight: 600; color: #111827; }
.btn-save-payment {
  background: var(--teal-dark, #0d9488);
  color: #fff;
  font-weight: 600;
}
.btn-save-payment:hover { background: #0f766e; color: #fff; }
.filter-toolbar {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: .65rem;
}
.filter-toolbar .form-control,
.filter-toolbar .form-select {
  border: 1px solid var(--border);
  border-radius: 999px;
  padding: .5rem 1.1rem;
  font-size: .85rem;
  background-color: #fff;
}
.payment-summary-card {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: .25rem 1rem;
}
.payment-summary-card .ps-row {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  padding: .55rem 0;
}
.payment-summary-card .ps-row:not(:last-child) {
  border-bottom: 1px dashed #e2e8f0;
}
.payment-summary-card .ps-label { color: #64748b; font-size: .85rem; }
.payment-summary-card .ps-value { font-weight: 600; color: #111827; text-align: right; }
.payment-summary-card .ps-value.text-teal { color: var(--teal-dark, #0d9488); font-size: 1.05rem; font-weight: 700; }
</style>

<div class="row g-3 mb-4 align-items-stretch">
  <div class="col-lg-6">
    <div class="section-card pp-card">
      <h6 class="mb-1 fw-bold">Process Payment</h6>
      <p class="text-muted small mb-3">Fill in the details to record a violation payment</p>

      <?php if ($message): ?>
        <div class="alert alert-<?= esc($messageType) ?> d-flex justify-content-between align-items-center flex-wrap gap-2">
          <span><?= esc($message) ?></span>
          <?php if ($justPaidReceipt): ?>
            <button type="button" class="btn btn-sm btn-teal" onclick="printReceiptNow()"><i class="bi bi-printer me-1"></i>Print Receipt</button>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($selectedViolationId > 0 && !$selectedViolation): ?>
        <div class="alert alert-warning">The selected violation record could not be found.</div>
      <?php elseif ($selectedViolation && $selectedViolation['status'] === 'paid'): ?>
        <div class="alert alert-success">This violation has already been paid.</div>
      <?php elseif ($selectedViolation && $selectedViolation['status'] === 'cancelled'): ?>
        <div class="alert alert-warning">This violation was cancelled and cannot be paid.</div>
      <?php endif; ?>

      <div id="ppSelectWarning" class="alert alert-warning d-none d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div class="flex-grow-1">Please select a violation from the Pending Violations list first.</div>
        <button type="button" class="btn-close" aria-label="Close" onclick="document.getElementById('ppSelectWarning').classList.add('d-none')"></button>
      </div>

      <form method="post" id="processPaymentForm" class="flex-grow-1 d-flex flex-column">
        <input type="hidden" name="action" value="record_payment">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
        <input type="hidden" name="violation_id" id="pp_violation_id" value="<?= (int)($selectedViolation['violation_id'] ?? 0) ?>">

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Violation ID</label>
            <input type="text" class="form-control" id="pp_ticket_display" placeholder="VIO-2026-01193" value="<?= esc($selectedViolation['ticket_number'] ?? '') ?>" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Plate Number</label>
            <input type="text" class="form-control" id="pp_plate_display" placeholder="ABC 1234" value="<?= esc($selectedViolation['plate_number'] ?? '') ?>" readonly>
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Fine Amount (&#8369;)</label>
            <input type="number" step="0.01" min="0" class="form-control" name="amount_paid" id="pp_amount" value="<?= esc($selectedViolation['penalty_amount'] ?? '') ?>" readonly required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Official Receipt Number</label>
            <input type="text" class="form-control" value="Auto-generated on save" readonly tabindex="-1" style="color:#94a3b8;">
          </div>
        </div>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Payment Date</label>
            <input type="date" class="form-control" name="payment_date" value="<?= esc(date('Y-m-d')) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Payment Method</label>
            <select class="form-select" name="payment_method">
              <?php foreach (payment_method_options() as $value => $label): ?>
                <option value="<?= esc($value) ?>" <?= $value === 'cash' ? 'selected' : '' ?>><?= esc($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="mb-3 flex-grow-1">
          <label class="form-label">Notes</label>
          <textarea class="form-control" name="notes" rows="3" placeholder="Optional remarks..."></textarea>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-auto">
          <a class="btn btn-light" href="<?= esc(app_url('payments.php')) ?>"><i class="bi bi-x-lg me-1"></i>Cancel</a>
          <button type="button" class="btn btn-save-payment" id="btnOpenPaymentSummary"><i class="bi bi-check2 me-1"></i>Save Payment</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="section-card pp-card">
      <h6 class="mb-1 fw-bold">Pending Violations</h6>
      <p class="text-muted small mb-3">Click to auto-fill</p>

      <div class="mb-3">
        <div class="position-relative">
          <input class="form-control form-control-sm ps-4" id="pendingLiveSearch" placeholder="Search ticket, plate, violation...">
          <i class="bi bi-search position-absolute" style="left:.6rem; top:50%; transform:translateY(-50%); color:#94a3b8;"></i>
        </div>
      </div>

      <?php if (!$pendingViolations): ?>
        <?php empty_state('No pending or overdue violations were found.'); ?>
      <?php else: ?>
        <div class="table-responsive" style="max-height: 420px; overflow-y: auto;">
          <table class="table align-middle mb-0">
            <thead><tr><th>ID</th><th>Plate</th><th class="text-end">Fine</th></tr></thead>
            <tbody id="pendingTableBody">
              <?php foreach ($pendingViolations as $v): ?>
                <tr class="pending-row" data-violation-id="<?= (int)$v['violation_id'] ?>" data-ticket="<?= esc($v['ticket_number']) ?>" data-plate="<?= esc($v['plate_number']) ?>" data-fine="<?= esc($v['penalty_amount']) ?>">
                  <td class="pv-id"><?= esc($v['ticket_number']) ?></td>
                  <td><span class="plate-chip"><?= esc($v['plate_number']) ?></span></td>
                  <td class="text-end fw-semibold"><?= peso($v['penalty_amount']) ?></td>
                </tr>
              <?php endforeach; ?>
              <tr id="noPendingLiveResultsRow" style="display:none;">
                <td colspan="3" class="text-center text-muted py-3">No matching violations found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.pending-row').forEach(function (row) {
  row.addEventListener('click', function () {
    document.getElementById('pp_violation_id').value = row.dataset.violationId;
    document.getElementById('pp_ticket_display').value = row.dataset.ticket;
    document.getElementById('pp_plate_display').value = row.dataset.plate;
    document.getElementById('pp_amount').value = row.dataset.fine;
    document.querySelectorAll('.pending-row').forEach(function (r) { r.classList.remove('table-active'); });
    row.classList.add('table-active');
    var warning = document.getElementById('ppSelectWarning');
    if (warning) warning.classList.add('d-none');
  });
});

(function () {
  var searchInput = document.getElementById('pendingLiveSearch');
  var tbody = document.getElementById('pendingTableBody');
  if (!searchInput || !tbody) return;

  var noResultsRow = document.getElementById('noPendingLiveResultsRow');
  var rows = Array.prototype.filter.call(tbody.querySelectorAll('tr'), function (row) {
    return row.id !== 'noPendingLiveResultsRow';
  });

  searchInput.addEventListener('input', function () {
    var query = searchInput.value.trim().toLowerCase();
    var anyVisible = false;

    rows.forEach(function (row) {
      var matches = row.textContent.toLowerCase().indexOf(query) !== -1;
      row.style.display = matches ? '' : 'none';
      if (matches) anyVisible = true;
    });

    if (noResultsRow) {
      noResultsRow.style.display = anyVisible ? 'none' : '';
    }
  });
})();
</script>

<div class="modal fade" id="confirmPaymentModal" tabindex="-1" aria-labelledby="confirmPaymentModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-4">
        <div class="text-center mb-3">
          <div style="font-size:2.2rem;color:#0d9488;"><i class="bi bi-receipt-cutoff"></i></div>
          <h5 class="mt-2 mb-1" id="confirmPaymentModalLabel">Confirm Payment</h5>
          <p class="text-muted small mb-0">Please review the details below before saving.</p>
        </div>
        <div class="payment-summary-card">
          <div class="ps-row"><span class="ps-label">Ticket / Violation ID</span><span class="ps-value" id="sum_ticket">—</span></div>
          <div class="ps-row"><span class="ps-label">Plate Number</span><span class="ps-value" id="sum_plate">—</span></div>
          <div class="ps-row"><span class="ps-label">Amount</span><span class="ps-value text-teal" id="sum_amount">—</span></div>
          <div class="ps-row"><span class="ps-label">Payment Method</span><span class="ps-value" id="sum_method">—</span></div>
          <div class="ps-row"><span class="ps-label">Payment Date</span><span class="ps-value" id="sum_date">—</span></div>
          <div class="ps-row"><span class="ps-label">Receipt Number</span><span class="ps-value" id="sum_receipt">—</span></div>
          <div class="ps-row"><span class="ps-label">Notes</span><span class="ps-value" id="sum_notes" style="max-width:60%;">—</span></div>
        </div>
      </div>
      <div class="modal-footer border-top-0 pt-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal"><i class="bi bi-x-lg me-1"></i>Cancel</button>
        <button type="button" class="btn btn-save-payment" id="confirmSavePaymentBtn"><i class="bi bi-check2 me-1"></i>Confirm &amp; Save</button>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var openBtn = document.getElementById('btnOpenPaymentSummary');
  var form = document.getElementById('processPaymentForm');
  if (!openBtn || !form) return;

  openBtn.addEventListener('click', function () {
    if (!form.reportValidity()) return;

    var warning = document.getElementById('ppSelectWarning');
    var violationId = document.getElementById('pp_violation_id').value;
    if (!violationId || violationId === '0') {
      if (warning) {
        warning.classList.remove('d-none');
        warning.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      }
      return;
    }
    if (warning) warning.classList.add('d-none');

    var methodSelect = form.querySelector('[name="payment_method"]');
    var amount = parseFloat(document.getElementById('pp_amount').value || '0');

    document.getElementById('sum_ticket').textContent = document.getElementById('pp_ticket_display').value || '—';
    document.getElementById('sum_plate').textContent = document.getElementById('pp_plate_display').value || '—';
    document.getElementById('sum_amount').textContent = '\u20b1' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('sum_method').textContent = methodSelect.options[methodSelect.selectedIndex].text;
    document.getElementById('sum_date').textContent = form.querySelector('[name="payment_date"]').value || '—';
    document.getElementById('sum_receipt').textContent = 'Auto-generated on save';
    document.getElementById('sum_notes').textContent = form.querySelector('[name="notes"]').value.trim() || '—';

    bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmPaymentModal')).show();
  });

  document.getElementById('confirmSavePaymentBtn').addEventListener('click', function () {
    this.disabled = true;
    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
    form.submit();
  });
})();
</script>

<div class="section-card">
  <div class="section-head flex-wrap gap-2">
    <div><h6 class="mb-0">Payment Transactions</h6><small class="text-muted">Completed and recorded collection history.</small></div>
    <form method="get" class="filter-toolbar" id="paymentFilterForm">
      <?php if ($selectedViolationId > 0): ?><input type="hidden" name="violation_id" value="<?= $selectedViolationId ?>"><?php endif; ?>
      <input class="form-control" style="min-width:220px" id="paymentSearchInput" name="payment_search" value="<?= esc($paymentSearch) ?>" placeholder="Ticket, plate, or receipt no...">
      <select class="form-select" style="max-width:190px" id="paymentMethodSelect" name="method">
        <option value="">All Methods</option>
        <?php foreach (payment_method_options() as $value => $label): ?>
          <option value="<?= esc($value) ?>" <?= $methodFilter === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>

  <script>
  (function () {
    var form = document.getElementById('paymentFilterForm');
    var search = document.getElementById('paymentSearchInput');
    var method = document.getElementById('paymentMethodSelect');
    if (!form) return;

    var debounceTimer;
    search.addEventListener('input', function () {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () { form.submit(); }, 500);
    });

    method.addEventListener('change', function () { form.submit(); });
  })();
  </script>

  <?php if (!$payments): ?>
    <?php empty_state('No payment transactions matched your current filters.'); ?>
  <?php else: ?>
    <div class="table-responsive table-scroll">
      <table class="table align-middle">
        <thead><tr><th>Reference</th><th>Ticket</th><th>Plate</th><th>Violation</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th><th>Received By</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td class="fw-semibold"><?= esc(payment_reference((int)$p['payment_id'])) ?></td>
              <td><?= esc($p['ticket_number']) ?></td>
              <td><?= esc($p['plate_number']) ?></td>
              <td><?= esc($p['violation_type']) ?></td>
              <td class="fw-semibold"><?= peso($p['amount_paid']) ?></td>
              <td><?= esc(payment_method_label($p['payment_method'])) ?></td>
              <td class="text-muted"><?= esc($p['payment_date']) ?></td>
              <td><span class="tag <?= tag_class($p['payment_status']) ?>"><?= esc(ucfirst($p['payment_status'])) ?></span></td>
              <td><?= esc($p['received_by_name'] ?? 'Not recorded') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($justPaidReceipt): ?>
<div id="receiptSheet" class="print-sheet-overlay">
  <div class="ps-title">Official Receipt</div>
  <div class="ps-subtitle"><?= esc(payment_reference((int)$justPaidReceipt['payment_id'])) ?></div>
  <hr>
  <div class="ps-row">
    <div><div class="ps-label">Ticket Number</div><div class="ps-value"><?= esc($justPaidReceipt['ticket_number']) ?></div></div>
    <div><div class="ps-label">Plate Number</div><div class="ps-value"><?= esc($justPaidReceipt['plate_number']) ?></div></div>
    <div><div class="ps-label">Driver</div><div class="ps-value"><?= esc($justPaidReceipt['driver_name']) ?></div></div>
    <div><div class="ps-label">Violation</div><div class="ps-value"><?= esc($justPaidReceipt['violation_type']) ?></div></div>
    <div><div class="ps-label">Amount Paid</div><div class="ps-value"><?= peso($justPaidReceipt['amount_paid']) ?></div></div>
    <div><div class="ps-label">Payment Method</div><div class="ps-value"><?= esc(payment_method_label($justPaidReceipt['payment_method'])) ?></div></div>
    <div><div class="ps-label">Payment Date</div><div class="ps-value"><?= esc($justPaidReceipt['payment_date']) ?></div></div>
    <div><div class="ps-label">Received By</div><div class="ps-value"><?= esc($_SESSION['user']['name'] ?? 'Treasury Personnel') ?></div></div>
    <?php if (!empty($justPaidReceipt['notes'])): ?>
      <div class="col-12" style="grid-column:1/-1;"><div class="ps-label">Notes</div><div class="ps-value"><?= nl2br(esc($justPaidReceipt['notes'])) ?></div></div>
    <?php endif; ?>
  </div>
</div>

<style>
.print-sheet-overlay {
  position: fixed;
  top: 0; left: 0;
  width: 800px;
  max-width: 100%;
  background: #fff;
  padding: 2.5rem;
  visibility: hidden;
  z-index: -1;
}
.print-sheet-overlay .ps-title { font-size: 1.6rem; font-weight: 700; margin-bottom: .15rem; color: #111827; }
.print-sheet-overlay .ps-subtitle { color: #64748b; font-size: .9rem; margin-bottom: 1rem; }
.print-sheet-overlay hr { margin: 0 0 1.75rem; }
.print-sheet-overlay .ps-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem 2rem; }
.print-sheet-overlay .ps-label { font-weight: 700; color: #111827; margin-bottom: .2rem; }
.print-sheet-overlay .ps-value { color: #111827; }

@media print {
  body.printing-receipt * { visibility: hidden !important; }
  body.printing-receipt .print-sheet-overlay,
  body.printing-receipt .print-sheet-overlay * { visibility: visible !important; }
  body.printing-receipt .print-sheet-overlay {
    position: absolute;
    top: 0; left: 0;
    z-index: 99999;
  }
}
</style>

<script>
function printReceiptNow() {
  document.body.classList.add('printing-receipt');
  var cleanup = function () {
    document.body.classList.remove('printing-receipt');
    window.removeEventListener('afterprint', cleanup);
  };
  window.addEventListener('afterprint', cleanup);
  window.print();
  setTimeout(cleanup, 2000);
}
</script>

<div class="modal fade" id="printReceiptPromptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body py-4">
        <div class="mb-3" style="font-size:2.5rem;color:#0d9488;"><i class="bi bi-receipt-cutoff"></i></div>
        <h5 class="mb-2">Payment Saved</h5>
        <p class="text-muted mb-0">Would you like to print the official receipt now?</p>
      </div>
      <div class="modal-footer border-top-0 pt-0 justify-content-center">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Not Now</button>
        <button type="button" class="btn btn-teal" id="printReceiptOkBtn"><i class="bi bi-printer me-1"></i>OK, Print</button>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('printReceiptPromptModal');
  if (!el) return;
  var modal = bootstrap.Modal.getOrCreateInstance(el);
  modal.show();
  document.getElementById('printReceiptOkBtn').addEventListener('click', function () {
    modal.hide();
    printReceiptNow();
  });
});
</script>
<?php endif; ?>


<?php page_end(); ?>