<?php
define('TRAVIS_PORTAL_LAYOUT', __DIR__ . '/layout.php');
define('TRAVIS_EMBEDDED_ADMIN_PAGE', true);
require dirname(__DIR__) . '/Admin/violations.php';
exit;

require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';

function legacy_treasurer_violation_post(string $key, string $default = ''): string {
    return trim((string)($_POST[$key] ?? $default));
}

function generate_treasurer_ticket_number(mysqli $conn): string {
    $prefix = 'TRV-' . date('Ymd') . '-';
    $stmt = $conn->prepare("SELECT ticket_number FROM violations WHERE ticket_number LIKE CONCAT(?, '%') ORDER BY violation_id DESC LIMIT 1");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $next = $row ? ((int)substr((string)$row['ticket_number'], -6)) + 1 : 1;
    return $prefix . str_pad((string)$next, 6, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''));
    $action = (string)($_POST['action'] ?? '');

    if (!$csrfOk) {
        $message = 'Your session expired. Please try again.';
        $messageType = 'danger';
    } elseif ($action === 'add_violation') {
        $driver = legacy_treasurer_violation_post('driver_name');
        $license = legacy_treasurer_violation_post('license_number');
        $plate = strtoupper(legacy_treasurer_violation_post('plate_number'));
        $vehicle = legacy_treasurer_violation_post('vehicle_type');
        $type = legacy_treasurer_violation_post('violation_type');
        $location = legacy_treasurer_violation_post('violation_location');
        $date = legacy_treasurer_violation_post('violation_date');
        $time = legacy_treasurer_violation_post('violation_time');
        $amount = (float)legacy_treasurer_violation_post('penalty_amount', '0');
        $allowedViolations = traffic_violation_types();
        $allowedFees = array_map('floatval', traffic_penalty_fees());

        if ($driver === '' || $license === '' || $plate === '' || $vehicle === '' || $type === '' || $location === '' || $date === '' || $time === '' || $amount <= 0) {
            $message = 'Please complete all required fields and enter a valid penalty amount.';
            $messageType = 'danger';
        } elseif (!in_array($type, $allowedViolations, true)) {
            $message = 'Please select a valid violation from the traffic ticket list.';
            $messageType = 'danger';
        } elseif (!in_array($amount, $allowedFees, true)) {
            $message = 'Please select a valid penalty fee.';
            $messageType = 'danger';
        } else {
            $ticket = generate_treasurer_ticket_number($conn);
            $encodedBy = (int)($_SESSION['user']['id'] ?? 0);
            $stmt = $conn->prepare("INSERT INTO violations (ticket_number,driver_name,license_number,plate_number,vehicle_type,violation_type,violation_location,violation_date,violation_time,penalty_amount,input_method,status,encoded_by) VALUES (?,?,?,?,?,?,?,?,?,?,'manual','pending',?)");
            $stmt->bind_param('sssssssssdi', $ticket, $driver, $license, $plate, $vehicle, $type, $location, $date, $time, $amount, $encodedBy);
            if ($stmt->execute()) {
                $message = 'Violation added successfully. Ticket No.: ' . $ticket;
                $messageType = 'success';
            } else {
                $message = 'Failed to add the violation record.';
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'cancel_violation') {
        $id = (int)($_POST['violation_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE violations SET status='cancelled' WHERE violation_id=? AND status IN ('pending','overdue')");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $message = 'Violation cancelled. The record remains available for audit purposes.';
            $messageType = 'success';
        } else {
            $message = 'Only pending or overdue violations can be cancelled.';
            $messageType = 'warning';
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$dateFilter = trim((string)($_GET['date'] ?? ''));
$violationTypeFilter = trim((string)($_GET['violation_type'] ?? ''));

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

if ($dateFilter !== '') {
    $where[] = 'v.violation_date = ?';
    $params[] = $dateFilter;
    $types .= 's';
}

if ($violationTypeFilter !== '') {
    $where[] = 'v.violation_type = ?';
    $params[] = $violationTypeFilter;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Handle Excel/CSV export in this same file (?export=1), before any HTML is echoed.
if (($_GET['export'] ?? '') === '1') {
    $exportSql = "
        SELECT v.*
        FROM violations v
        {$whereSql}
        ORDER BY v.violation_date DESC, v.violation_time DESC
    ";

    if ($params) {
        $stmt = $conn->prepare($exportSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $exportRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $exportRows = fetch_all($exportSql);
    }

    $filename = 'violation_records_' . date('Y-m-d_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders the peso sign correctly

    fputcsv($out, ['Violation ID', 'Plate', 'Vehicle', 'Violation', 'Location', 'Date', 'Time', 'Fine (PHP)', 'Status']);
    foreach ($exportRows as $v) {
        fputcsv($out, [
            $v['ticket_number'],
            $v['plate_number'],
            $v['vehicle_type'],
            $v['violation_type'],
            $v['violation_location'],
            $v['violation_date'],
            $v['violation_time'],
            number_format((float)($v['penalty_amount'] ?? 0), 2, '.', ''),
            ucfirst((string)$v['status']),
        ]);
    }
    fclose($out);
    exit;
}

$sql = "
    SELECT v.*
    FROM violations v
    {$whereSql}
    ORDER BY v.violation_date DESC, v.violation_time DESC
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

$violationTypes = fetch_all("SELECT DISTINCT violation_type FROM violations ORDER BY violation_type ASC");
$hasFilters = $search !== '' || $status !== '' || $dateFilter !== '' || $violationTypeFilter !== '';
$totalToday = scalar("SELECT COUNT(*) FROM violations WHERE violation_date = CURDATE()", 0);
$unpaid = scalar("SELECT COUNT(*) FROM violations WHERE status IN ('pending','overdue')", 0);
$paid = scalar("SELECT COUNT(*) FROM violations WHERE status = 'paid'", 0);
$cancelled = scalar("SELECT COUNT(*) FROM violations WHERE status = 'cancelled'", 0);

page_start('Traffic Violation Records', 'violations', 'Search violations, receipts, plates...', 'All AI-captured violations', false);
?>

<style>.treasurer-page-heading{display:none}</style>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
  <div>
    <span class="text-info small fw-semibold text-uppercase">Enforcement workflow</span>
    <h3 class="page-title mt-1">Violation Records</h3>
    <p class="page-sub">Record, review, and route unpaid violations to payment processing.</p>
  </div>
  <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addViolationModal"><i class="bi bi-plus-lg me-1"></i>Add Violation</button>
</div>

<?php if ($message !== ''): ?>
  <div class="alert alert-<?= esc($messageType) ?> alert-dismissible fade show" role="alert">
    <?= esc($message) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-calendar-day"></i></div><div class="stat-label">Recorded Today</div><div class="stat-value"><?= num($totalToday) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-warning"><i class="bi bi-hourglass-split"></i></div><div class="stat-label">Awaiting Payment</div><div class="stat-value"><?= num($unpaid) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-success"><i class="bi bi-check2-circle"></i></div><div class="stat-label">Paid</div><div class="stat-value"><?= num($paid) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-danger"><i class="bi bi-slash-circle"></i></div><div class="stat-label">Cancelled</div><div class="stat-value"><?= num($cancelled) ?></div></div></div>
</div>

<div class="section-card">
  <form method="get" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <input class="form-control form-control-sm flex-grow-1" style="min-width:220px" id="liveSearch" name="search" value="<?= esc($search) ?>" placeholder="Search ticket, driver, plate, violation, location...">
    <input class="form-control form-control-sm" style="max-width:170px" type="date" name="date" value="<?= esc($dateFilter) ?>">
    <select class="form-select form-select-sm" style="max-width:160px" name="status">
      <option value="">All Statuses</option>
      <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
      <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
      <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
      <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
    </select>
    <select class="form-select form-select-sm" style="max-width:190px" name="violation_type">
      <option value="">All Violation Types</option>
      <?php foreach ($violationTypes as $vt): ?>
        <option value="<?= esc($vt['violation_type']) ?>" <?= $violationTypeFilter === $vt['violation_type'] ? 'selected' : '' ?>><?= esc($vt['violation_type']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-light"><i class="bi bi-funnel me-1"></i>Apply Filters</button>
    <?php if ($hasFilters): ?>
      <a class="btn btn-sm btn-light" href="<?= esc(app_url('violations.php')) ?>">Clear</a>
    <?php endif; ?>
    <a class="btn btn-sm btn-dark ms-auto" href="<?= esc(app_url('violations.php?' . http_build_query([
      'search' => $search,
      'date' => $dateFilter,
      'status' => $status,
      'violation_type' => $violationTypeFilter,
      'export' => '1',
    ]))) ?>"><i class="bi bi-download me-1"></i>Export</a>
  </form>

  <?php if (!$violations): ?>
    <?php empty_state('No violation records matched your search or filter.'); ?>
  <?php else: ?>
    <div class="table-responsive table-scroll">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Violation ID</th><th>Plate</th><th>Vehicle</th><th>Violation</th><th>Location</th>
            <th>Date & Time</th><th>Fine</th><th>Status</th><th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody id="violationsTableBody">
          <?php foreach ($violations as $v): ?>
            <tr>
              <td class="fw-semibold"><?= esc($v['ticket_number']) ?></td>
              <td class="fw-semibold"><?= esc($v['plate_number']) ?></td>
              <td><?= esc($v['vehicle_type']) ?></td>
              <td><?= esc($v['violation_type']) ?></td>
              <td><?= esc($v['violation_location']) ?></td>
              <td><?= esc($v['violation_date']) ?><br><small class="text-muted"><?= esc($v['violation_time']) ?></small></td>
              <td class="fw-semibold"><?= peso($v['penalty_amount']) ?></td>
              <td><span class="tag <?= tag_class($v['status']) ?>"><?= esc(ucfirst($v['status'])) ?></span></td>
              <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#view<?= (int)$v['violation_id'] ?>" title="View details"><i class="bi bi-eye"></i></button>
                <button class="btn btn-sm btn-light" type="button" title="Print"
                  onclick="printViolation(this)"
                  data-ticket="<?= esc($v['ticket_number']) ?>"
                  data-status="<?= esc(ucfirst($v['status'])) ?>"
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
                ><i class="bi bi-printer"></i></button>
                <?php if (in_array($v['status'], ['pending', 'overdue'], true)): ?>
                  <a class="btn btn-sm btn-success" href="<?= esc(app_url('payments.php?violation_id=' . (int)$v['violation_id'])) ?>" title="Process payment"><i class="bi bi-cash-coin"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Cancel this violation? The record will remain available for audit.');">
                    <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
                    <input type="hidden" name="action" value="cancel_violation">
                    <input type="hidden" name="violation_id" value="<?= (int)$v['violation_id'] ?>">
                    <button class="btn btn-sm btn-light text-danger" title="Cancel violation"><i class="bi bi-slash-circle"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          <tr id="noLiveResultsRow" style="display:none">
            <td colspan="9" class="text-center text-muted py-4">No matching records</td>
          </tr>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
(function () {
  var searchInput = document.getElementById('liveSearch');
  var tbody = document.getElementById('violationsTableBody');
  if (!searchInput || !tbody) return;

  var noResultsRow = document.getElementById('noLiveResultsRow');
  var rows = Array.prototype.filter.call(tbody.querySelectorAll('tr'), function (row) {
    return row.id !== 'noLiveResultsRow';
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

<div id="printSheet" class="print-sheet-overlay">
  <div class="ps-title">Violation Details</div>
  <div class="ps-subtitle" id="ps_ticket"></div>
  <hr>
  <div class="ps-row">
    <div><div class="ps-label">Status</div><div class="ps-value"><span class="tag" id="ps_status_tag"></span></div></div>
    <div><div class="ps-label">Penalty</div><div class="ps-value" id="ps_penalty"></div></div>
    <div><div class="ps-label">Driver</div><div class="ps-value" id="ps_driver"></div></div>
    <div><div class="ps-label">License Number</div><div class="ps-value" id="ps_license"></div></div>
    <div><div class="ps-label">Plate Number</div><div class="ps-value" id="ps_plate"></div></div>
    <div><div class="ps-label">Vehicle Type</div><div class="ps-value" id="ps_vehicle"></div></div>
    <div><div class="ps-label">Violation Type</div><div class="ps-value" id="ps_violation_type"></div></div>
    <div><div class="ps-label">Location</div><div class="ps-value" id="ps_location"></div></div>
    <div><div class="ps-label">Date &amp; Time</div><div class="ps-value" id="ps_datetime"></div></div>
    <div><div class="ps-label">Input Method</div><div class="ps-value" id="ps_input_method"></div></div>
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
  body.printing-violation * { visibility: hidden !important; }
  body.printing-violation .print-sheet-overlay,
  body.printing-violation .print-sheet-overlay * { visibility: visible !important; }
  body.printing-violation .print-sheet-overlay {
    position: absolute;
    top: 0; left: 0;
    z-index: 99999;
  }
}
</style>

<script>
function printViolation(btn) {
  var d = btn.dataset;
  document.getElementById('ps_ticket').textContent = d.ticket || '';
  var tag = document.getElementById('ps_status_tag');
  tag.textContent = d.status || '';
  tag.className = 'tag ' + (d.statusClass || '');
  document.getElementById('ps_penalty').textContent = d.penalty || '';
  document.getElementById('ps_driver').textContent = d.driver || '';
  document.getElementById('ps_license').textContent = d.license || '';
  document.getElementById('ps_plate').textContent = d.plate || '';
  document.getElementById('ps_vehicle').textContent = d.vehicle || '';
  document.getElementById('ps_violation_type').textContent = d.violationType || '';
  document.getElementById('ps_location').textContent = d.location || '';
  document.getElementById('ps_datetime').textContent = d.datetime || '';
  document.getElementById('ps_input_method').textContent = d.inputMethod || '';

  document.body.classList.add('printing-violation');

  var cleanup = function () {
    document.body.classList.remove('printing-violation');
    window.removeEventListener('afterprint', cleanup);
  };
  window.addEventListener('afterprint', cleanup);

  window.print();

  // Fallback cleanup for browsers that don't reliably fire afterprint.
  setTimeout(cleanup, 2000);
}
</script>

<?php foreach ($violations as $v): ?>
  <div class="modal fade" id="view<?= (int)$v['violation_id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div><h5 class="modal-title">Violation Details</h5><small class="text-muted"><?= esc($v['ticket_number']) ?></small></div>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
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
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
          <?php if (in_array($v['status'], ['pending', 'overdue'], true)): ?>
            <a class="btn btn-success" href="<?= esc(app_url('payments.php?violation_id=' . (int)$v['violation_id'])) ?>"><i class="bi bi-cash-coin me-1"></i>Process Payment</a>
            <form method="post" class="d-inline" onsubmit="return confirm('Cancel this violation? The record will remain available for audit.');">
              <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
              <input type="hidden" name="action" value="cancel_violation">
              <input type="hidden" name="violation_id" value="<?= (int)$v['violation_id'] ?>">
              <button class="btn btn-light text-danger" title="Cancel violation"><i class="bi bi-slash-circle me-1"></i>Cancel</button>
            </form>
          <?php endif; ?>
          <button class="btn btn-primary" type="button" title="Print"
            onclick="printViolation(this)"
            data-ticket="<?= esc($v['ticket_number']) ?>"
            data-status="<?= esc(ucfirst($v['status'])) ?>"
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
          ><i class="bi bi-printer me-1"></i>Print</button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<div class="modal fade" id="addViolationModal" tabindex="-1" aria-labelledby="addViolationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <div><h5 class="modal-title" id="addViolationModalLabel">Manual Violation Input</h5><small class="text-muted">Encode a paper-issued traffic ticket</small></div>
          <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
          <input type="hidden" name="action" value="add_violation">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Ticket Number</label><input class="form-control" value="Automatically generated" readonly><small class="text-muted">TRV-YYYYMMDD-000001</small></div>
            <div class="col-md-6"><label class="form-label">Driver Name</label><input class="form-control" name="driver_name" required></div>
            <div class="col-md-6"><label class="form-label">License Number</label><input class="form-control" name="license_number" required></div>
            <div class="col-md-6"><label class="form-label">Plate Number</label><input class="form-control text-uppercase" name="plate_number" required></div>
            <div class="col-md-6"><label class="form-label">Vehicle Type</label><select class="form-select" name="vehicle_type" required><option value="">Select vehicle type</option><option>Motorcycle</option><option>Car</option><option>SUV</option><option>Jeepney</option><option>Tricycle</option><option>Van</option><option>Truck</option><option>Bus</option><option>Other</option></select></div>
            <div class="col-md-6"><label class="form-label">Violation Type</label><input class="form-control" name="violation_type" list="manualViolationTypes" placeholder="Type to search..." required><datalist id="manualViolationTypes"><?php foreach (traffic_violation_types() as $item): ?><option value="<?= esc($item) ?>"><?php endforeach; ?></datalist></div>
            <div class="col-md-6"><label class="form-label">Violation Location</label><input class="form-control" name="violation_location" required></div>
            <div class="col-md-3"><label class="form-label">Date</label><input class="form-control" type="date" name="violation_date" value="<?= esc(date('Y-m-d')) ?>" required></div>
            <div class="col-md-3"><label class="form-label">Time</label><input class="form-control" type="time" name="violation_time" value="<?= esc(date('H:i')) ?>" required></div>
            <div class="col-md-6"><label class="form-label">Penalty Fee</label><input class="form-control" type="number" name="penalty_amount" list="manualPenaltyFees" min="100" step="100" required><datalist id="manualPenaltyFees"><?php foreach (traffic_penalty_fees() as $fee): ?><option value="<?= esc($fee) ?>" label="<?= esc(peso($fee)) ?>"><?php endforeach; ?></datalist></div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Save Violation</button></div>
      </form>
    </div>
  </div>
</div>

<?php page_end(); ?>
