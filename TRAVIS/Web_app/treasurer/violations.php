<?php
require_once __DIR__ . '/layout.php';

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$dateFilter = trim((string)($_GET['date'] ?? ''));
$violationTypeFilter = trim((string)($_GET['violation_type'] ?? ''));

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(v.ticket_number LIKE ? OR v.plate_number LIKE ? OR v.violation_type LIKE ? OR v.violation_location LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
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

page_start('Traffic Violation Records', 'violations', 'Search violations, receipts, plates...', 'All AI-captured violations');
?>

<div class="section-card">
  <form method="get" class="d-flex flex-wrap align-items-center gap-2 mb-3">
    <input class="form-control form-control-sm flex-grow-1" style="min-width:220px" id="liveSearch" name="search" value="<?= esc($search) ?>" placeholder="Search plate, ID, location...">
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
                <button class="btn btn-sm btn-light" type="button" onclick="window.print()" title="Print"><i class="bi bi-printer"></i></button>
                <?php if (in_array($v['status'], ['pending', 'overdue'], true)): ?>
                  <a class="btn btn-sm btn-success" href="<?= esc(app_url('payments.php?violation_id=' . (int)$v['violation_id'])) ?>" title="Process payment"><i class="bi bi-cash-coin"></i></a>
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
          <?php endif; ?>
          <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php page_end(); ?>