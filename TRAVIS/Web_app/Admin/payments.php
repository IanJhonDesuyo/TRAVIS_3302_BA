<?php
require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';

function payment_post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_payment') {
    $violationId = (int)($_POST['violation_id'] ?? 0);
    $amountPaid = (float)payment_post('amount_paid', '0');
    $paymentMethod = payment_post('payment_method', 'cash');

    $allowedMethods = ['cash', 'gcash', 'bank_transfer', 'other'];
    if (!in_array($paymentMethod, $allowedMethods, true)) {
        $paymentMethod = 'cash';
    }

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
            SELECT payment_id
            FROM payments
            WHERE violation_id = ?
              AND payment_status = 'completed'
            LIMIT 1
        ");
        $stmt->bind_param('i', $violationId);
        $stmt->execute();

        if ($stmt->get_result()->fetch_assoc()) {
            throw new RuntimeException('A completed payment already exists for this violation.');
        }

        $stmt = $conn->prepare("
            INSERT INTO payments (violation_id, amount_paid, payment_status, payment_method)
            VALUES (?, ?, 'completed', ?)
        ");
        $stmt->bind_param('ids', $violationId, $amountPaid, $paymentMethod);
        $stmt->execute();

        $paymentId = $conn->insert_id;

        $stmt = $conn->prepare("UPDATE violations SET status = 'paid' WHERE violation_id = ?");
        $stmt->bind_param('i', $violationId);
        $stmt->execute();

        $conn->commit();

        $message = 'Payment recorded successfully. Payment reference: PAY-' . str_pad((string)$paymentId, 6, '0', STR_PAD_LEFT);
        $messageType = 'success';
    } catch (Throwable $e) {
        $conn->rollback();
        $message = $e->getMessage() ?: 'Failed to record the payment.';
        $messageType = 'danger';
    }
}

$selectedViolationId = (int)($_GET['violation_id'] ?? 0);
$selectedViolation = null;

if ($selectedViolationId > 0) {
    $stmt = $conn->prepare("SELECT * FROM violations WHERE violation_id = ? LIMIT 1");
    $stmt->bind_param('i', $selectedViolationId);
    $stmt->execute();
    $selectedViolation = $stmt->get_result()->fetch_assoc();
}

$pendingSearch = trim((string)($_GET['pending_search'] ?? ''));
$paymentSearch = trim((string)($_GET['payment_search'] ?? ''));
$methodFilter = trim((string)($_GET['method'] ?? ''));

$pendingWhere = "WHERE v.status IN ('pending', 'overdue')";
$pendingParams = [];
$pendingTypes = '';

if ($pendingSearch !== '') {
    $pendingWhere .= " AND (v.ticket_number LIKE ? OR v.driver_name LIKE ? OR v.plate_number LIKE ? OR v.violation_type LIKE ?)";
    $like = "%{$pendingSearch}%";
    array_push($pendingParams, $like, $like, $like, $like);
    $pendingTypes .= 'ssss';
}

$pendingSql = "
    SELECT v.*
    FROM violations v
    {$pendingWhere}
    ORDER BY CASE WHEN v.status = 'overdue' THEN 0 ELSE 1 END,
             v.violation_date ASC,
             v.created_at ASC
    LIMIT 50
";

if ($pendingParams) {
    $stmt = $conn->prepare($pendingSql);
    $stmt->bind_param($pendingTypes, ...$pendingParams);
    $stmt->execute();
    $pendingViolations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $pendingViolations = fetch_all($pendingSql);
}

$paymentWhere = [];
$paymentParams = [];
$paymentTypes = '';

if ($paymentSearch !== '') {
    $paymentWhere[] = "(v.ticket_number LIKE ? OR v.driver_name LIKE ? OR v.plate_number LIKE ?)";
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
    SELECT p.*, v.ticket_number, v.plate_number, v.driver_name,
           v.violation_type, u.full_name AS received_by_name
    FROM payments p
    JOIN violations v ON v.violation_id = p.violation_id
    LEFT JOIN users u ON u.user_id = p.received_by
    {$paymentWhereSql}
    ORDER BY p.payment_date DESC, p.payment_id DESC
    LIMIT 100
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

page_start('Payments', 'payments', 'Search payments...');
?>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <h3 class="page-title">Payment Management</h3>
    <p class="page-sub">Process unpaid violations, record collections, and review completed payment transactions.</p>
  </div>
  <button class="btn btn-light" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print Ledger</button>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-success"><i class="bi bi-cash-stack"></i></div><div class="stat-label">Collected Today</div><div class="stat-value"><?= short_money($collectedToday) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-calendar-week"></i></div><div class="stat-label">This Week</div><div class="stat-value"><?= short_money($thisWeek) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-calendar3"></i></div><div class="stat-label">This Month</div><div class="stat-value"><?= short_money($thisMonth) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-warning"><i class="bi bi-hourglass-split"></i></div><div class="stat-label">Pending Settlement</div><div class="stat-value"><?= short_money($pendingAmount) ?></div><small class="text-muted"><?= num($pendingCount) ?> unpaid violations</small></div></div>
</div>

<?php if ($selectedViolationId > 0): ?>
  <div class="section-card mb-4">
    <div class="section-head">
      <div><h6 class="mb-0">Process Selected Violation</h6><small class="text-muted">Review the violation before recording payment.</small></div>
      <a class="btn btn-sm btn-light" href="<?= esc(app_url('payments.php')) ?>"><i class="bi bi-x-lg me-1"></i>Clear Selection</a>
    </div>

    <?php if (!$selectedViolation): ?>
      <?php empty_state('The selected violation record could not be found.'); ?>
    <?php elseif ($selectedViolation['status'] === 'paid'): ?>
      <div class="alert alert-success mb-0">This violation has already been paid.</div>
    <?php elseif ($selectedViolation['status'] === 'cancelled'): ?>
      <div class="alert alert-warning mb-0">This violation was cancelled and cannot be paid.</div>
    <?php else: ?>
      <div class="row g-4">
        <div class="col-lg-7">
          <div class="row g-3">
            <div class="col-md-6"><strong>Ticket Number</strong><br><?= esc($selectedViolation['ticket_number']) ?></div>
            <div class="col-md-6"><strong>Status</strong><br><span class="tag <?= tag_class($selectedViolation['status']) ?>"><?= esc(ucfirst($selectedViolation['status'])) ?></span></div>
            <div class="col-md-6"><strong>Driver</strong><br><?= esc($selectedViolation['driver_name']) ?></div>
            <div class="col-md-6"><strong>Plate Number</strong><br><?= esc($selectedViolation['plate_number']) ?></div>
            <div class="col-md-6"><strong>Violation</strong><br><?= esc($selectedViolation['violation_type']) ?></div>
            <div class="col-md-6"><strong>Location</strong><br><?= esc($selectedViolation['violation_location']) ?></div>
            <div class="col-md-6"><strong>Date & Time</strong><br><?= esc($selectedViolation['violation_date'] . ' ' . $selectedViolation['violation_time']) ?></div>
            <div class="col-md-6"><strong>Penalty</strong><br><span class="fs-5 fw-semibold"><?= peso($selectedViolation['penalty_amount']) ?></span></div>
          </div>
        </div>

        <div class="col-lg-5">
          <form method="post" class="border rounded-3 p-3">
            <input type="hidden" name="action" value="record_payment">
            <input type="hidden" name="violation_id" value="<?= (int)$selectedViolation['violation_id'] ?>">

            <div class="mb-3">
              <label class="form-label">Violation Fee / Amount Paid</label>
              <select name="amount_paid" class="form-select" required>
                <option value="<?= esc($selectedViolation['penalty_amount']) ?>"><?= peso($selectedViolation['penalty_amount']) ?> &mdash; <?= esc($selectedViolation['violation_type']) ?></option>
              </select>
              <small class="text-muted">The fee is taken from the selected violation and cannot be changed during payment.</small>
            </div>

            <div class="mb-3">
              <label class="form-label">Payment Method</label>
              <select name="payment_method" class="form-select" required>
                <option value="cash">Cash</option>
                <option value="gcash">GCash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="other">Other</option>
              </select>
            </div>

            <div class="alert alert-light border small">A payment reference will be generated from the saved payment ID.</div>
            <button class="btn btn-success w-100" onclick="return confirm('Confirm and record this payment?');"><i class="bi bi-check2-circle me-1"></i>Confirm Payment</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="section-card mb-4">
  <div class="section-head flex-wrap gap-2">
    <div><h6 class="mb-0">Pending Violations</h6><small class="text-muted">Select a violation to begin payment processing.</small></div>
    <form method="get" class="d-flex gap-2">
      <input type="hidden" name="payment_search" value="<?= esc($paymentSearch) ?>">
      <?php if ($methodFilter !== ''): ?><input type="hidden" name="method" value="<?= esc($methodFilter) ?>"><?php endif; ?>
      <input class="form-control form-control-sm" name="pending_search" value="<?= esc($pendingSearch) ?>" placeholder="Ticket, driver, plate, violation...">
      <button class="btn btn-sm btn-primary">Search</button>
    </form>
  </div>

  <?php if (!$pendingViolations): ?>
    <?php empty_state('No pending or overdue violations were found.'); ?>
  <?php else: ?>
    <div class="table-responsive table-scroll">
      <table class="table align-middle">
        <thead><tr><th>Ticket</th><th>Driver / Plate</th><th>Violation</th><th>Location</th><th>Date</th><th>Penalty</th><th>Status</th><th class="text-end">Action</th></tr></thead>
        <tbody>
          <?php foreach ($pendingViolations as $v): ?>
            <tr class="<?= $selectedViolationId === (int)$v['violation_id'] ? 'table-active' : '' ?>">
              <td class="fw-semibold"><?= esc($v['ticket_number']) ?></td>
              <td><?= esc($v['driver_name']) ?><br><small class="text-muted"><?= esc($v['plate_number']) ?></small></td>
              <td><?= esc($v['violation_type']) ?></td>
              <td><?= esc($v['violation_location']) ?></td>
              <td><?= esc($v['violation_date']) ?></td>
              <td class="fw-semibold"><?= peso($v['penalty_amount']) ?></td>
              <td><span class="tag <?= tag_class($v['status']) ?>"><?= esc(ucfirst($v['status'])) ?></span></td>
              <td class="text-end"><a class="btn btn-sm btn-success" href="<?= esc(app_url('payments.php?violation_id=' . (int)$v['violation_id'])) ?>"><i class="bi bi-cash-coin me-1"></i>Process</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="section-card">
  <div class="section-head flex-wrap gap-2">
    <div><h6 class="mb-0">Payment Transactions</h6><small class="text-muted">Completed and recorded collection history.</small></div>

    <form method="get" class="d-flex flex-wrap gap-2">
      <?php if ($selectedViolationId > 0): ?><input type="hidden" name="violation_id" value="<?= $selectedViolationId ?>"><?php endif; ?>
      <input type="hidden" name="pending_search" value="<?= esc($pendingSearch) ?>">
      <input class="form-control form-control-sm" name="payment_search" value="<?= esc($paymentSearch) ?>" placeholder="Ticket, driver, or plate...">
      <select class="form-select form-select-sm" name="method">
        <option value="">All Methods</option>
        <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
        <option value="gcash" <?= $methodFilter === 'gcash' ? 'selected' : '' ?>>GCash</option>
        <option value="bank_transfer" <?= $methodFilter === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
        <option value="other" <?= $methodFilter === 'other' ? 'selected' : '' ?>>Other</option>
      </select>
      <button class="btn btn-sm btn-primary">Filter</button>
    </form>
  </div>

  <?php if (!$payments): ?>
    <?php empty_state('No payment transactions matched your current filters.'); ?>
  <?php else: ?>
    <div class="table-responsive table-scroll">
      <table class="table align-middle">
        <thead><tr><th>Reference</th><th>Ticket</th><th>Driver / Plate</th><th>Violation</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th><th>Received By</th></tr></thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td class="fw-semibold">PAY-<?= str_pad((string)$p['payment_id'], 6, '0', STR_PAD_LEFT) ?></td>
              <td><?= esc($p['ticket_number']) ?></td>
              <td><?= esc($p['driver_name']) ?><br><small class="text-muted"><?= esc($p['plate_number']) ?></small></td>
              <td><?= esc($p['violation_type']) ?></td>
              <td class="fw-semibold"><?= peso($p['amount_paid']) ?></td>
              <td><?= esc(ucwords(str_replace('_', ' ', $p['payment_method']))) ?></td>
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

<?php page_end(); ?>
