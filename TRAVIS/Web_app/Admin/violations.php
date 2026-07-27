<?php
require_once defined('TRAVIS_PORTAL_LAYOUT') ? TRAVIS_PORTAL_LAYOUT : __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';

function violation_post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function generateTicketNumber(mysqli $conn): string
{
    $prefix = 'TRV-' . date('Ymd') . '-';
    $stmt = $conn->prepare("
        SELECT ticket_number
        FROM violations
        WHERE ticket_number LIKE CONCAT(?, '%')
        ORDER BY violation_id DESC
        LIMIT 1
    ");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $result = $stmt->get_result();

    $nextNumber = 1;
    if ($row = $result->fetch_assoc()) {
        $nextNumber = ((int)substr((string)$row['ticket_number'], -6)) + 1;
    }

    return $prefix . str_pad((string)$nextNumber, 6, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_violation') {
        $driver = violation_post('driver_name');
        $hasNoLicense = isset($_POST['has_no_license']);
        $license = $hasNoLicense ? 'NO LICENSE' : strtoupper(violation_post('license_number'));
        $plate = strtoupper(violation_post('plate_number'));
        $vehicle = violation_post('vehicle_type');
        $submittedTypes = is_array($_POST['violation_type'] ?? null) ? $_POST['violation_type'] : [violation_post('violation_type')];
        $submittedAmounts = is_array($_POST['penalty_amount'] ?? null) ? $_POST['penalty_amount'] : [violation_post('penalty_amount', '0')];
        $location = violation_post('violation_location');
        $date = violation_post('violation_date');
        $time = violation_post('violation_time');

        $allowedViolations = traffic_violation_types();
        $allowedFees = array_map('floatval', traffic_penalty_fees());
        $allowedVehicles = ['Motorcycle', 'Car', 'SUV', 'Truck', 'Bus', 'Other'];
        $items = [];
        foreach ($submittedTypes as $index => $submittedType) {
            $itemType = trim((string)$submittedType);
            $itemAmount = (float)($submittedAmounts[$index] ?? 0);
            if ($itemType !== '') $items[$itemType] = $itemAmount;
        }
        $type = implode(', ', array_keys($items));
        $amount = array_sum($items);

        if (
            $driver === '' || $license === '' || $plate === '' ||
            $vehicle === '' || $type === '' || $location === '' ||
            $date === '' || $time === '' || $amount <= 0
        ) {
            $message = 'Please complete all required fields and enter a valid penalty amount.';
            $messageType = 'danger';
        } elseif (!$items || array_diff(array_keys($items), $allowedViolations)) {
            $message = 'Please select one or more valid violations from the traffic ticket list.';
            $messageType = 'danger';
        } elseif (!in_array($vehicle, $allowedVehicles, true)) {
            $message = 'Please select a valid vehicle type.';
            $messageType = 'danger';
        } elseif (array_filter($items, static fn(float $fee): bool => !in_array($fee, $allowedFees, true))) {
            $message = 'Please select a valid penalty fee for every violation.';
            $messageType = 'danger';
        } else {
            $ticket = generateTicketNumber($conn);

            $stmt = $conn->prepare("
                INSERT INTO violations (
                    ticket_number, driver_name, license_number, has_no_license, plate_number,
                    vehicle_type, violation_type, violation_location,
                    violation_date, violation_time, penalty_amount,
                    encoded_by, input_method, status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', 'pending')
            ");

            $encodedBy = (int)($_SESSION['user']['id'] ?? 0);

            $stmt->bind_param(
                'sssissssssdi',
                $ticket, $driver, $license, $hasNoLicense, $plate, $vehicle,
                $type, $location, $date, $time, $amount, $encodedBy
            );

            try {
                $conn->begin_transaction();
                if ($stmt->execute()) {
                    $violationId = (int)$conn->insert_id;
                    $itemStmt = $conn->prepare('INSERT INTO violation_items (violation_id, violation_type, penalty_amount) VALUES (?, ?, ?)');
                    foreach ($items as $itemType => $itemAmount) {
                        $itemStmt->bind_param('isd', $violationId, $itemType, $itemAmount);
                        $itemStmt->execute();
                    }
                    $conn->commit();
                    $message = count($items) . ' violation(s) recorded successfully. Ticket No.: ' . $ticket;
                    $messageType = 'success';
                } else {
                    throw new RuntimeException($stmt->error ?: 'Insert failed');
                }
            } catch (Throwable $exception) {
                $conn->rollback();
                error_log('Web violation insert failed: ' . $exception->getMessage());
                $message = 'Failed to add the violation record. Verify the selected values and try again.';
                $messageType = 'danger';
            }
        }
    }

    if ($action === 'cancel_violation') {
        $id = (int)($_POST['violation_id'] ?? 0);

        $stmt = $conn->prepare("
            UPDATE violations
            SET status = 'cancelled'
            WHERE violation_id = ?
              AND status <> 'paid'
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = 'Violation record was cancelled. The record remains available for audit purposes.';
            $messageType = 'success';
        } else {
            $message = 'The violation could not be cancelled. Paid records cannot be cancelled.';
            $messageType = 'warning';
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(v.ticket_number LIKE ? OR v.driver_name LIKE ? OR v.plate_number LIKE ? OR v.violation_type LIKE ? OR v.violation_location LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

if ($status !== '') {
    $where[] = 'v.status = ?';
    $params[] = $status;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalToday = scalar("SELECT COUNT(*) FROM violations WHERE violation_date = CURDATE()", 0);
$unpaid = scalar("SELECT COUNT(*) FROM violations WHERE status IN ('pending', 'overdue')", 0);
$paid = scalar("SELECT COUNT(*) FROM violations WHERE status = 'paid'", 0);
$cancelled = scalar("SELECT COUNT(*) FROM violations WHERE status = 'cancelled'", 0);

$sql = "
    SELECT v.*, u.full_name AS encoded_by_name
    FROM violations v
    LEFT JOIN users u ON u.user_id = v.encoded_by
    {$whereSql}
    ORDER BY v.created_at DESC
    LIMIT 100
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $violations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $violations = fetch_all($sql);
}

page_start('Violations', 'violations', 'Search plate or ticket...');
?>

<style>
/* ============================================================
   TRAVIS VIOLATIONS — NAVY GLASS THEME
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
.btn-accent{
    background:linear-gradient(90deg,var(--blue-accent-2),var(--cyan-glow)) !important;
    border:none !important;color:#fff !important;
    box-shadow:0 12px 26px rgba(37,99,235,.32) !important;
    font-weight:600 !important;border-radius:12px !important;
}
.btn-accent:hover{filter:brightness(1.08);color:#fff !important}
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
.tag-cancelled{
    background:rgba(255,255,255,.06) !important;color:var(--text-soft) !important;border-color:var(--border-glass) !important;
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
    <span class="dashboard-eyebrow">TRAVIS VIOLATIONS MODULE</span>
    <h3 class="page-title">Violation Records</h3>
    <p class="page-sub">Record, review, and route unpaid traffic violations to the payment module.</p>
  </div>
  <a class="btn btn-primary" href="#addViolationModal" data-bs-toggle="modal" data-bs-target="#addViolationModal" role="button">
    <i class="bi bi-plus-lg me-1"></i>Add Violation
  </a>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-calendar-day"></i></div>
      <div class="stat-label">Recorded Today</div>
      <div class="stat-value"><?= num($totalToday) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-danger"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-label">Awaiting Payment</div>
      <div class="stat-value"><?= num($unpaid) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-check2-circle"></i></div>
      <div class="stat-label">Paid</div>
      <div class="stat-value"><?= num($paid) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-slash-circle"></i></div>
      <div class="stat-label">Cancelled</div>
      <div class="stat-value"><?= num($cancelled) ?></div>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-head flex-wrap gap-2">
    <div>
      <h6 class="mb-0">Violation Records</h6>
      <small class="text-muted">Payment processing is handled in the Payments page.</small>
    </div>

    <form method="get" class="d-flex flex-wrap gap-2">
      <input class="form-control form-control-sm" name="search" value="<?= esc($search) ?>" placeholder="Ticket, driver, plate, violation, location..." style="width:220px;">
      <select class="form-select form-select-sm" name="status" style="width:140px;">
        <option value="">All Statuses</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
      </select>
      <button class="btn btn-sm btn-primary">Filter</button>
      <?php if ($search !== '' || $status !== ''): ?>
        <a class="btn btn-sm btn-light" href="<?= esc(app_url('violations.php')) ?>">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$violations): ?>
    <?php empty_state('No violation records matched your search or filter.'); ?>
  <?php else: ?>
    <div class="table-responsive table-scroll">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Ticket</th><th>Driver / License</th><th>Vehicle</th><th>Violation</th>
            <th>Location</th><th>Date & Time</th><th>Fine</th><th>Status</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($violations as $v): ?>
            <tr>
              <td class="fw-semibold"><?= esc($v['ticket_number']) ?></td>
              <td><?= esc($v['driver_name']) ?><br><small class="text-muted"><?= esc($v['license_number']) ?></small></td>
              <td><?= esc($v['plate_number']) ?><br><small class="text-muted"><?= esc($v['vehicle_type']) ?></small></td>
              <td><?= esc($v['violation_type']) ?></td>
              <td><?= esc($v['violation_location']) ?></td>
              <td><?= esc($v['violation_date']) ?><br><small class="text-muted"><?= esc($v['violation_time']) ?></small></td>
              <td class="fw-semibold"><?= peso($v['penalty_amount']) ?></td>
              <td><span class="tag <?= tag_class($v['status']) ?>"><?= esc(ucfirst($v['status'])) ?></span></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-light" href="#view<?= (int)$v['violation_id'] ?>" data-bs-toggle="modal" data-bs-target="#view<?= (int)$v['violation_id'] ?>" role="button" title="View details"><i class="bi bi-eye"></i></a>

                <?php if (in_array($v['status'], ['pending', 'overdue'], true)): ?>
                  <a class="btn btn-sm btn-success" href="<?= esc(app_url('payments.php?violation_id=' . (int)$v['violation_id'])) ?>" title="Proceed to payment"><i class="bi bi-cash-coin"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Cancel this violation record? The record will not be deleted.');">
                    <input type="hidden" name="action" value="cancel_violation">
                    <input type="hidden" name="violation_id" value="<?= (int)$v['violation_id'] ?>">
                    <button class="btn btn-sm btn-light text-danger" title="Cancel record"><i class="bi bi-slash-circle"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php foreach ($violations as $v): ?>
  <div class="modal fade" id="view<?= (int)$v['violation_id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div><h5 class="modal-title">Violation Details</h5><small class="text-muted"><?= esc($v['ticket_number']) ?></small></div>
          <a class="btn-close" href="#" data-bs-dismiss="modal" aria-label="Close"></a>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><strong>Status</strong><br><span class="tag <?= tag_class($v['status']) ?>"><?= esc(ucfirst($v['status'])) ?></span></div>
            <div class="col-md-6"><strong>Penalty</strong><br><?= peso($v['penalty_amount']) ?></div>
            <div class="col-md-6"><strong>Driver</strong><br><?= esc($v['driver_name']) ?></div>
            <div class="col-md-6"><strong>License Number</strong><br><?= esc($v['license_number']) ?></div>
            <div class="col-md-6"><strong>Plate Number</strong><br><?= esc($v['plate_number']) ?></div>
            <div class="col-md-6"><strong>Vehicle Type</strong><br><?= esc($v['vehicle_type']) ?></div>
            <div class="col-md-6"><strong>Violation Type</strong><br><?= esc($v['violation_type']) ?></div>
            <div class="col-md-6"><strong>Location</strong><br><?= esc($v['violation_location']) ?></div>
            <div class="col-md-6"><strong>Date & Time</strong><br><?= esc($v['violation_date'] . ' ' . $v['violation_time']) ?></div>
            <div class="col-md-6"><strong>Input Method</strong><br><?= esc($v['input_method']) ?></div>
            <div class="col-md-6"><strong>Encoded By</strong><br><?= esc($v['encoded_by_name'] ?? 'System / Mobile App') ?></div>
            <div class="col-md-6"><strong>Created At</strong><br><?= esc($v['created_at']) ?></div>
          </div>
        </div>
        <div class="modal-footer">
          <a class="btn btn-light" href="#" data-bs-dismiss="modal">Close</a>
          <?php if (in_array($v['status'], ['pending', 'overdue'], true)): ?>
            <a class="btn btn-success" href="<?= esc(app_url('payments.php?violation_id=' . (int)$v['violation_id'])) ?>"><i class="bi bi-cash-coin me-1"></i>Proceed to Payment</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<div class="modal fade" id="addViolationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <div><h5 class="modal-title">Manual Violation Input</h5><small class="text-muted">For manually encoded paper ticket records</small></div>
          <a class="btn-close" href="#" data-bs-dismiss="modal" aria-label="Close"></a>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="add_violation">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Ticket Number</label><input type="text" class="form-control" value="Automatically generated" readonly><small class="text-muted">Format: TRV-YYYYMMDD-000001</small></div>
            <div class="col-md-6"><label class="form-label">Driver Name</label><input type="text" name="driver_name" class="form-control" required></div>
            <div class="col-md-6">
              <label class="form-label" for="licenseNumberInput">License Number</label>
              <input type="text" id="licenseNumberInput" name="license_number" class="form-control text-uppercase" required>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="has_no_license" value="1" id="noLicenseCheck">
                <label class="form-check-label" for="noLicenseCheck">Driver has no license</label>
              </div>
            </div>
            <div class="col-md-6"><label class="form-label">Plate Number</label><input type="text" name="plate_number" class="form-control text-uppercase" required></div>
            <div class="col-md-6"><label class="form-label">Vehicle Type</label><select name="vehicle_type" class="form-select" required><option value="">Select vehicle type</option><option>Motorcycle</option><option>Car</option><option>SUV</option><option>Truck</option><option>Bus</option><option>Other</option></select></div>
            <div class="col-12">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <label class="form-label mb-0">Violations and Penalties</label>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addViolationItem"><i class="bi bi-plus-lg me-1"></i>Add another</button>
              </div>
              <div id="violationItems">
                <div class="row g-2 mb-2 violation-item-row">
                  <div class="col-md-8"><select name="violation_type[]" class="form-select" required><option value="">Select violation</option><?php foreach (traffic_violation_types() as $violationType): ?><option value="<?= esc($violationType) ?>"><?= esc($violationType) ?></option><?php endforeach; ?></select></div>
                  <div class="col-md-3"><select name="penalty_amount[]" class="form-select" required><option value="">Select fee</option><?php foreach (traffic_penalty_fees() as $penaltyFee): ?><option value="<?= esc($penaltyFee) ?>"><?= peso($penaltyFee) ?></option><?php endforeach; ?></select></div>
                  <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100 remove-violation-item" aria-label="Remove violation" disabled><i class="bi bi-x-lg"></i></button></div>
                </div>
              </div>
              <small class="text-muted">Add every box checked on the paper citation and confirm its corresponding fee.</small>
            </div>
            <div class="col-md-6"><label class="form-label">Violation Location</label><input type="text" name="violation_location" class="form-control" placeholder="Nasugbu, Batangas location" required></div>
            <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="violation_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-3"><label class="form-label">Time</label><input type="time" name="violation_time" class="form-control" value="<?= date('H:i') ?>" required></div>
          </div>
          <small class="text-muted d-block mt-3">OCR scanning will be handled by the mobile application. Mobile records saved to the same database will also appear here.</small>
        </div>
        <div class="modal-footer">
          <a class="btn btn-light" href="#" data-bs-dismiss="modal">Cancel</a>
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Violation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let violationModalBackdrop = null;

function openViolationModal(modalId, event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }

  const modal = document.getElementById(modalId);
  if (!modal) return false;

  document.querySelectorAll('.modal.violation-modal-open').forEach(function (openModal) {
    closeViolationModal(openModal);
  });

  modal.style.display = 'block';
  modal.classList.add('show', 'violation-modal-open');
  modal.removeAttribute('aria-hidden');
  modal.setAttribute('aria-modal', 'true');
  modal.setAttribute('role', 'dialog');
  document.body.classList.add('modal-open');
  document.body.style.overflow = 'hidden';

  violationModalBackdrop = document.createElement('div');
  violationModalBackdrop.className = 'modal-backdrop fade show violation-modal-backdrop';
  violationModalBackdrop.addEventListener('click', function () {
    closeViolationModal(modal);
  });
  document.body.appendChild(violationModalBackdrop);

  const focusTarget = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
  if (focusTarget) focusTarget.focus();
  return false;
}

function closeViolationModal(modalOrChild) {
  const modal = modalOrChild && modalOrChild.classList && modalOrChild.classList.contains('modal')
    ? modalOrChild
    : modalOrChild?.closest('.modal');
  if (!modal) return false;

  modal.classList.remove('show', 'violation-modal-open');
  modal.style.display = 'none';
  modal.setAttribute('aria-hidden', 'true');
  modal.removeAttribute('aria-modal');
  modal.removeAttribute('role');
  document.querySelectorAll('.violation-modal-backdrop').forEach(function (item) { item.remove(); });
  violationModalBackdrop = null;
  document.body.classList.remove('modal-open');
  document.body.style.removeProperty('overflow');
  return false;
}

document.querySelectorAll('#addViolationModal [data-bs-dismiss="modal"], [id^="view"] [data-bs-dismiss="modal"]').forEach(function (button) {
  button.addEventListener('click', function (event) {
    event.preventDefault();
    event.stopPropagation();
    closeViolationModal(button);
  });
});

document.addEventListener('keydown', function (event) {
  if (event.key === 'Escape') {
    const modal = document.querySelector('.modal.violation-modal-open');
    if (modal) closeViolationModal(modal);
  }
});

document.getElementById('noLicenseCheck')?.addEventListener('change', function () {
  const input = document.getElementById('licenseNumberInput');
  input.disabled = this.checked;
  input.required = !this.checked;
  input.value = this.checked ? 'NO LICENSE' : '';
});

const violationItems = document.getElementById('violationItems');
function refreshViolationItemButtons() {
  const rows = violationItems?.querySelectorAll('.violation-item-row') || [];
  rows.forEach(function (row) { row.querySelector('.remove-violation-item').disabled = rows.length === 1; });
}
document.getElementById('addViolationItem')?.addEventListener('click', function () {
  const source = violationItems?.querySelector('.violation-item-row');
  if (!source || !violationItems) return;
  const clone = source.cloneNode(true);
  clone.querySelectorAll('select').forEach(function (select) { select.value = ''; });
  violationItems.appendChild(clone);
  refreshViolationItemButtons();
});
violationItems?.addEventListener('click', function (event) {
  const button = event.target.closest('.remove-violation-item');
  if (!button || button.disabled) return;
  button.closest('.violation-item-row')?.remove();
  refreshViolationItemButtons();
});
</script>
<?php page_end(); ?>
