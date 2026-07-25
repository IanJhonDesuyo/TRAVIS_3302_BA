<?php
require_once defined('TRAVIS_PORTAL_LAYOUT') ? TRAVIS_PORTAL_LAYOUT : __DIR__ . '/layout.php';

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
        if (defined('TRAVIS_AUTO_PRINT_PAYMENT_RECEIPT') && TRAVIS_AUTO_PRINT_PAYMENT_RECEIPT) {
            $autoPrintPaymentId = (int)$paymentId;
        }
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
$requestedReceiptId = max(0, (int)($_GET['receipt_id'] ?? 0));
if ($requestedReceiptId > 0) {
    $autoPrintPaymentId = $requestedReceiptId;
}

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

<style>
/* ============================================================
   TRAVIS PAYMENTS — NAVY GLASS THEME
   ============================================================ */

@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

:root{
    --navy-950:#060f1e;
    --navy-900:#0a1a30;
    --navy-800:#0f2544;
    --border-glass:rgba(255,255,255,.10);
    --blue-accent:#38bdf8;
    --blue-accent-2:#2563eb;
    --cyan-glow:#4fc3f7;
    --text-soft:#c9d8ea;
}

body{
    font-family:'Poppins', sans-serif !important;
    background:
        radial-gradient(circle at 10% 10%, rgba(56,189,248,.08), transparent 30%),
        radial-gradient(circle at 90% 80%, rgba(37,99,235,.08), transparent 35%),
        linear-gradient(160deg, var(--navy-950) 0%, var(--navy-900) 45%, var(--navy-800) 100%) !important;
    color:#fff !important;
}

/* ==== Topbar alignment to navy theme ==== */
.topbar,
.app-topbar,
.top-header,
.dashboard-topbar,
header.topbar,
.navbar-top{
    background:var(--navy-900) !important;
    border-bottom:1px solid var(--border-glass) !important;
    box-shadow:none !important;
}

.topbar input,
.app-topbar input,
.top-header input,
.dashboard-topbar input,
.navbar-top input{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
    box-shadow:none !important;
}

.topbar input::placeholder,
.app-topbar input::placeholder,
.top-header input::placeholder,
.dashboard-topbar input::placeholder,
.navbar-top input::placeholder{
    color:var(--text-soft) !important;
}

.topbar .bi-search,
.app-topbar .bi-search,
.top-header .bi-search,
.dashboard-topbar .bi-search,
.navbar-top .bi-search{
    color:var(--text-soft) !important;
}

.topbar .bi-bell,
.app-topbar .bi-bell,
.top-header .bi-bell,
.dashboard-topbar .bi-bell,
.navbar-top .bi-bell,
.topbar .notif-icon,
.app-topbar .notif-icon{
    color:var(--text-soft) !important;
}

.topbar .btn-icon,
.app-topbar .btn-icon,
.top-header .btn-icon,
.dashboard-topbar .btn-icon{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
}

.topbar .datetime,
.app-topbar .datetime,
.top-header .datetime,
.dashboard-topbar .datetime{
    color:var(--text-soft) !important;
}

.topbar .user-avatar,
.app-topbar .user-avatar,
.top-header .user-avatar,
.dashboard-topbar .user-avatar{
    background:var(--blue-accent-2) !important;
    color:#fff !important;
}

.topbar .user-name,
.app-topbar .user-name,
.top-header .user-name,
.dashboard-topbar .user-name{
    color:#fff !important;
}

/* ==== Reports / Open Monitoring buttons: exact size fit ==== */
.btn-light,
.btn-primary{
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:4px;
    width:auto !important;
    height:32px !important;
    min-width:0 !important;
    padding:0 12px !important;
    font-size:.75rem !important;
    font-weight:600 !important;
    line-height:1 !important;
    white-space:nowrap !important;
    border-radius:6px !important;
}

.btn-light i,
.btn-primary i{
    font-size:.80rem;
    margin:0 !important;
    line-height:1;
    display:inline-flex;
    align-items:center;
}

.dashboard-title-row{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:4px}
.dashboard-eyebrow{
    display:inline-block;color:var(--cyan-glow) !important;font-weight:700;
    letter-spacing:.06em;font-size:.72rem;text-transform:uppercase;margin-bottom:8px;
}
.page-title{color:#fff !important;font-weight:800 !important;margin-bottom:6px}
.page-sub{color:var(--text-soft) !important;margin-bottom:0}

.btn-light{background:rgba(255,255,255,.06) !important;border:1px solid var(--border-glass) !important;color:#fff !important;}
.btn-light:hover{background:rgba(255,255,255,.14) !important;color:#fff !important}
.btn-primary{
    background:linear-gradient(90deg,var(--blue-accent-2),var(--cyan-glow)) !important;
    border:none !important;color:#fff !important;
    box-shadow:0 12px 26px rgba(37,99,235,.32) !important;
}
.btn-primary:hover{filter:brightness(1.08)}
.btn-success{
    background:linear-gradient(90deg,#15803d,#34d399) !important;
    border:none !important;color:#fff !important;
    box-shadow:0 12px 26px rgba(21,128,61,.32) !important;
}
.btn-success:hover{filter:brightness(1.08);color:#fff !important}
.btn-sm{height:28px !important;padding:0 10px !important;font-size:.70rem !important;border-radius:5px !important;}
.btn-sm i{font-size:.75rem !important;}

.stat-card,.dashboard-stat-card{
    background:rgba(255,255,255,.03) !important;
    border:1px solid var(--border-glass) !important;
    border-radius:18px !important;
    padding:20px !important;
    box-shadow:0 14px 30px rgba(0,0,0,.28) !important;
    color:#fff !important;
}
.stat-icon{
    width:44px;height:44px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    margin-bottom:14px;font-size:18px;
}
.stat-icon.tone-primary{background:rgba(56,189,248,.14) !important;color:var(--cyan-glow) !important}
.stat-icon.tone-warning{background:rgba(251,191,36,.14) !important;color:#fbbf24 !important}
.stat-icon.tone-success{background:rgba(52,211,153,.14) !important;color:#34d399 !important}
.stat-icon.tone-danger{background:rgba(248,113,113,.14) !important;color:#f87171 !important}
.stat-label{color:var(--text-soft) !important;font-size:.8rem;margin-bottom:4px}
.stat-value{color:#fff !important;font-size:1.7rem;font-weight:800;line-height:1.2}

.section-card{
    background:rgba(255,255,255,.03) !important;
    border:1px solid var(--border-glass) !important;
    border-radius:18px !important;
    padding:20px !important;
    box-shadow:0 14px 30px rgba(0,0,0,.28) !important;
    color:#fff !important;
}
.section-head{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:16px}
.section-head h6{color:#fff !important;font-weight:700;margin:0}
.section-card small,.section-card .text-muted{color:var(--text-soft) !important}
.section-head a{color:var(--cyan-glow) !important}

.tag{
    display:inline-block;padding:4px 12px;border-radius:999px;
    font-size:.72rem;font-weight:700;text-transform:capitalize;
    background:rgba(255,255,255,.08);color:var(--text-soft);
    border:1px solid var(--border-glass);
}
.tag-success,.tag-online,.tag-paid,.tag-completed,.tag-active,.tag-low{
    background:rgba(52,211,153,.14) !important;color:#34d399 !important;border-color:rgba(52,211,153,.3) !important;
}
.tag-danger,.tag-offline,.tag-overdue,.tag-high,.tag-critical{
    background:rgba(248,113,113,.14) !important;color:#f87171 !important;border-color:rgba(248,113,113,.3) !important;
}
.tag-warning,.tag-pending,.tag-unpaid,.tag-medium{
    background:rgba(251,191,36,.14) !important;color:#fbbf24 !important;border-color:rgba(251,191,36,.3) !important;
}
.tag-info{
    background:rgba(56,189,248,.14) !important;color:var(--cyan-glow) !important;border-color:rgba(56,189,248,.3) !important;
}
.tag-muted{
    background:rgba(255,255,255,.06) !important;color:var(--text-soft) !important;
}

.empty-state{
    background:rgba(255,255,255,.03) !important;
    border:1px solid var(--border-glass) !important;
    border-radius:14px;
    color:var(--text-soft) !important;
    text-align:center;
    padding:26px 10px;
    font-size:.9rem;
}
.empty-state i,.empty-state svg{color:var(--text-soft) !important;fill:var(--text-soft) !important;opacity:.7}

.border-bottom{border-color:var(--border-glass) !important}
.alert-light{background:rgba(255,255,255,.03) !important;border:1px solid var(--border-glass) !important;color:var(--text-soft) !important}
.alert-success{background:rgba(52,211,153,.12) !important;border:1px solid rgba(52,211,153,.3) !important;color:#34d399 !important}
.alert-danger{background:rgba(248,113,113,.12) !important;border:1px solid rgba(248,113,113,.3) !important;color:#f87171 !important}
.alert-warning{background:rgba(251,191,36,.12) !important;border:1px solid rgba(251,191,36,.3) !important;color:#fbbf24 !important}

a{color:var(--cyan-glow)}
a:hover{color:#fff}

.table{color:#fff !important}
.table thead th{color:var(--text-soft) !important;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;border-color:var(--border-glass) !important;font-weight:600}
.table td,.table th{border-color:var(--border-glass) !important;vertical-align:middle}
.table-responsive{border-radius:12px}
.table-active{background:rgba(56,189,248,.08) !important;}

.form-control{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
}
.form-control:focus{
    background:rgba(255,255,255,.09) !important;
    border-color:var(--blue-accent) !important;
    color:#fff !important;
    box-shadow:0 0 0 .2rem rgba(56,189,248,.18) !important;
}
.form-control::placeholder{color:var(--text-soft) !important;}
.form-select{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
}
.form-select:focus{
    background:rgba(255,255,255,.09) !important;
    border-color:var(--blue-accent) !important;
    color:#fff !important;
    box-shadow:0 0 0 .2rem rgba(56,189,248,.18) !important;
}
.form-select option{background:var(--navy-800);color:#fff;}
.form-label{color:var(--text-soft) !important}

/* Table scroll */
.table-scroll{
    max-height:600px;
    overflow-y:auto;
}
.table-scroll::-webkit-scrollbar{width:7px;}
.table-scroll::-webkit-scrollbar-track{background:rgba(255,255,255,.04);border-radius:20px;}
.table-scroll::-webkit-scrollbar-thumb{background:rgba(56,189,248,.35);border-radius:20px;}
.table-scroll::-webkit-scrollbar-thumb:hover{background:rgba(56,189,248,.65);}

/* Border rounded */
.border{ border-color:var(--border-glass) !important; }
.rounded-3{ border-radius:12px !important; }

/* ==== Catch-all: any remaining white cards ==== */
.card,
.badge,
.rounded-pill,
.bg-white,
.bg-light,
[class*="card"]{
    background-color:rgba(255,255,255,.03) !important;
    color:#fff !important;
    border-color:var(--border-glass) !important;
}

.card *:not(.tag),
[class*="card"] *:not(.tag){
    color:inherit;
}

.card small,
[class*="card"] small,
.card .text-muted,
[class*="card"] .text-muted{
    color:var(--text-soft) !important;
}

.rounded-pill:not(.tag),
span[style*="border-radius:999px"]:not(.tag),
span[style*="border-radius: 999px"]:not(.tag),
div[style*="border-radius:999px"]:not(.tag),
div[style*="border-radius: 999px"]:not(.tag){
    background:rgba(255,255,255,.05) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
}

.progress{
    background:rgba(255,255,255,.08) !important;
}

.dropdown-menu,
.popover,
.tooltip-inner{
    background:var(--navy-800) !important;
    color:#fff !important;
    border:1px solid var(--border-glass) !important;
}

.dropdown-item{
    color:var(--text-soft) !important;
}

.dropdown-item:hover,
.dropdown-item:focus{
    background:rgba(255,255,255,.06) !important;
    color:#fff !important;
}
</style>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <span class="dashboard-eyebrow">TRAVIS PAYMENTS MODULE</span>
    <h3 class="page-title">Payment Management</h3>
    <p class="page-sub">Process unpaid violations, record collections, and review completed payment transactions.</p>
  </div>
  <button class="btn btn-light" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print Ledger</button>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-label">Collected Today</div>
      <div class="stat-value"><?= short_money($collectedToday) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-calendar-week"></i></div>
      <div class="stat-label">This Week</div>
      <div class="stat-value"><?= short_money($thisWeek) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-calendar3"></i></div>
      <div class="stat-label">This Month</div>
      <div class="stat-value"><?= short_money($thisMonth) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-label">Pending Settlement</div>
      <div class="stat-value"><?= short_money($pendingAmount) ?></div>
      <small class="text-muted"><?= num($pendingCount) ?> unpaid violations</small>
    </div>
  </div>
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
      <input class="form-control form-control-sm" name="pending_search" value="<?= esc($pendingSearch) ?>" placeholder="Ticket, driver, plate, violation..." style="width:220px;">
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
      <input class="form-control form-control-sm" name="payment_search" value="<?= esc($paymentSearch) ?>" placeholder="Ticket, driver, or plate..." style="width:200px;">
      <select class="form-select form-select-sm" name="method" style="width:140px;">
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
        <thead><tr><th>Reference</th><th>Ticket</th><th>Driver / Plate</th><th>Violation</th><th>Amount</th><th>Method</th><th>Date</th><th>Status</th><th>Received By</th><th class="text-end no-print">Receipt</th></tr></thead>
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
              <td class="text-end no-print">
                <?php if (strtolower((string)$p['payment_status']) === 'completed'): ?>
                  <a class="btn btn-sm btn-light" href="<?= esc(app_url('payments.php?' . http_build_query(['receipt_id' => (int)$p['payment_id'], 'payment_search' => $paymentSearch, 'method' => $methodFilter]))) ?>">
                    <i class="bi bi-receipt me-1"></i>Print
                  </a>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if (!empty($autoPrintPaymentId)): ?>
  <?php
  $printReceipt = fetch_one("
      SELECT p.*, v.ticket_number, v.plate_number, v.driver_name, v.violation_type,
             u.full_name AS received_by_name
      FROM payments p
      JOIN violations v ON v.violation_id = p.violation_id
      LEFT JOIN users u ON u.user_id = p.received_by
      WHERE p.payment_id = ?
      LIMIT 1
  ", [(string)$autoPrintPaymentId]);
  ?>
  <?php if ($printReceipt): ?>
    <div id="treasurerReceiptSheet" class="treasurer-receipt-sheet" aria-hidden="true">
      <header class="receipt-header">
        <div class="receipt-republic">Republic of the Philippines</div>
        <div class="receipt-office">Municipality of Nasugbu</div>
        <div class="receipt-department">Traffic Management Office</div>
        <h2>Official Payment Receipt</h2>
        <div class="receipt-reference">Receipt No. <?= esc(payment_reference((int)$printReceipt['payment_id'])) ?></div>
      </header>
      <div class="receipt-grid">
        <div><strong>Ticket Number</strong><span><?= esc($printReceipt['ticket_number']) ?></span></div>
        <div><strong>Plate Number</strong><span><?= esc($printReceipt['plate_number']) ?></span></div>
        <div><strong>Driver</strong><span><?= esc($printReceipt['driver_name']) ?></span></div>
        <div><strong>Violation</strong><span><?= esc($printReceipt['violation_type']) ?></span></div>
        <div><strong>Amount Paid</strong><span><?= peso($printReceipt['amount_paid']) ?></span></div>
        <div><strong>Payment Method</strong><span><?= esc(payment_method_label($printReceipt['payment_method'])) ?></span></div>
        <div><strong>Payment Date</strong><span><?= esc($printReceipt['payment_date']) ?></span></div>
        <div><strong>Received By</strong><span><?= esc($printReceipt['received_by_name'] ?? 'Treasury Personnel') ?></span></div>
      </div>
      <div class="receipt-total"><span>Total Amount Paid</span><strong><?= peso($printReceipt['amount_paid']) ?></strong></div>
      <p class="receipt-certification">Payment received in settlement of the traffic violation stated above. This computer-generated receipt is valid subject to verification in the official TRAVIS payment ledger.</p>
      <div class="receipt-signatures"><div><span><?= esc($printReceipt['received_by_name'] ?? 'Treasury Personnel') ?></span><small>Collecting Officer</small></div><div><span>&nbsp;</span><small>Payor's Signature</small></div></div>
      <footer>TRAVIS · Traffic Violation Recognition and AI Surveillance</footer>
    </div>
    <style>
      .treasurer-receipt-sheet{position:fixed;left:0;top:0;width:720px;max-width:100%;padding:2.5rem;background:#fff;color:#111827;visibility:hidden;z-index:-1;font-family:Arial,sans-serif}
      .receipt-header{text-align:center;border-bottom:2px solid #102f49;padding-bottom:1rem;margin-bottom:1.4rem}
      .receipt-republic{font-family:Georgia,serif;font-size:.78rem;letter-spacing:.08em}
      .treasurer-receipt-sheet .receipt-office{text-transform:uppercase;letter-spacing:.1em;color:#102f49;font:700 1.25rem Georgia,serif;margin-top:.2rem}
      .receipt-department{font-size:.82rem;margin-top:.2rem}
      .treasurer-receipt-sheet h2{margin:1rem 0 .25rem;color:#102f49;font:700 1.45rem Georgia,serif;text-transform:uppercase;letter-spacing:.05em}
      .treasurer-receipt-sheet .receipt-reference{color:#526b64;font-size:.85rem}
      .treasurer-receipt-sheet .receipt-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem 2rem}
      .treasurer-receipt-sheet .receipt-grid div{display:flex;flex-direction:column;gap:.25rem;border-bottom:1px solid #d1d5db;padding-bottom:.55rem}
      .treasurer-receipt-sheet .receipt-grid strong{color:#52606d;font-size:.7rem;text-transform:uppercase;letter-spacing:.05em}
      .receipt-total{display:flex;justify-content:space-between;align-items:center;margin-top:1.5rem;padding:1rem;border:2px solid #102f49;background:#f8fafc}
      .receipt-total strong{font-size:1.35rem;color:#102f49}
      .receipt-certification{font-size:.75rem;line-height:1.5;color:#4b5563;margin:1.25rem 0}
      .receipt-signatures{display:grid;grid-template-columns:1fr 1fr;gap:4rem;margin-top:2.5rem;text-align:center}
      .receipt-signatures span{display:block;border-bottom:1px solid #111827;padding-bottom:.25rem;font-weight:700}
      .receipt-signatures small{display:block;margin-top:.35rem;color:#4b5563}
      .treasurer-receipt-sheet footer{text-align:center;border-top:1px solid #d1d5db;margin-top:2rem;padding-top:.75rem;font-size:.68rem;color:#6b7280;letter-spacing:.06em}
      @media print{
        @page{size:A4 portrait;margin:16mm}
        body.printing-treasurer-receipt *{visibility:hidden!important}
        body.printing-treasurer-receipt .treasurer-receipt-sheet,
        body.printing-treasurer-receipt .treasurer-receipt-sheet *{visibility:visible!important}
        body.printing-treasurer-receipt .treasurer-receipt-sheet{position:absolute;z-index:99999;left:50%;transform:translateX(-50%);width:180mm;padding:10mm}
      }
    </style>
    <script>
      document.addEventListener('DOMContentLoaded', function () {
        document.body.classList.add('printing-treasurer-receipt');
        var cleanup = function () {
          document.body.classList.remove('printing-treasurer-receipt');
          window.removeEventListener('afterprint', cleanup);
        };
        window.addEventListener('afterprint', cleanup);
        window.print();
        window.setTimeout(cleanup, 2000);
      });
    </script>
  <?php endif; ?>
<?php endif; ?>

<?php page_end(); ?>
