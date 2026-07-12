<?php
require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';
$previewRows = [];
$previewColumns = [];
$summary = [];
$reportTitle = '';

$reportType = trim((string)($_GET['report_type'] ?? 'violations'));
$dateFrom = trim((string)($_GET['date_from'] ?? date('Y-m-01')));
$dateTo = trim((string)($_GET['date_to'] ?? date('Y-m-d')));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$locationFilter = trim((string)($_GET['location'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));

$allowedReportTypes = ['violations', 'payments', 'monitoring'];
if (!in_array($reportType, $allowedReportTypes, true)) {
    $reportType = 'violations';
}

function report_safe_date(string $value, string $fallback): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : $fallback;
}

function build_report(
    mysqli $conn,
    string $reportType,
    string $dateFrom,
    string $dateTo,
    string $statusFilter,
    string $locationFilter
): array {
    $rows = [];
    $columns = [];
    $summary = [];
    $title = '';

    if ($reportType === 'violations') {
        $title = 'Traffic Violation Report';
        $columns = [
            'ticket_number' => 'Ticket Number',
            'driver_name' => 'Driver Name',
            'plate_number' => 'Plate Number',
            'vehicle_type' => 'Vehicle Type',
            'violation_type' => 'Violation Type',
            'violation_location' => 'Location',
            'violation_date' => 'Date',
            'violation_time' => 'Time',
            'penalty_amount' => 'Penalty Amount',
            'status' => 'Status',
        ];

        $where = ['v.violation_date BETWEEN ? AND ?'];
        $params = [$dateFrom, $dateTo];
        $types = 'ss';

        if ($statusFilter !== '') {
            $where[] = 'v.status = ?';
            $params[] = $statusFilter;
            $types .= 's';
        }

        if ($locationFilter !== '') {
            $where[] = 'v.violation_location LIKE ?';
            $params[] = '%' . $locationFilter . '%';
            $types .= 's';
        }

        $sql = "
            SELECT v.ticket_number, v.driver_name, v.plate_number,
                   v.vehicle_type, v.violation_type, v.violation_location,
                   v.violation_date, v.violation_time, v.penalty_amount, v.status
            FROM violations v
            WHERE " . implode(' AND ', $where) . "
            ORDER BY v.violation_date DESC, v.violation_time DESC
            LIMIT 1000
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $totalPenalty = 0.0;
        $paid = 0;
        $pending = 0;
        foreach ($rows as $row) {
            $totalPenalty += (float)$row['penalty_amount'];
            $state = strtolower((string)$row['status']);
            if ($state === 'paid') $paid++;
            if (in_array($state, ['pending', 'overdue', 'unpaid'], true)) $pending++;
        }

        $summary = [
            'Total Records' => count($rows),
            'Paid Violations' => $paid,
            'Pending / Unpaid' => $pending,
            'Total Penalties' => '₱' . number_format($totalPenalty, 2),
        ];
    }

    if ($reportType === 'payments') {
        $title = 'Payment Collection Report';
        $columns = [
            'payment_reference' => 'Payment Reference',
            'ticket_number' => 'Ticket Number',
            'driver_name' => 'Driver Name',
            'plate_number' => 'Plate Number',
            'violation_type' => 'Violation Type',
            'amount_paid' => 'Amount Paid',
            'payment_method' => 'Payment Method',
            'payment_status' => 'Status',
            'payment_date' => 'Payment Date',
            'received_by_name' => 'Received By',
        ];

        $where = ['DATE(p.payment_date) BETWEEN ? AND ?'];
        $params = [$dateFrom, $dateTo];
        $types = 'ss';

        if ($statusFilter !== '') {
            $where[] = 'p.payment_status = ?';
            $params[] = $statusFilter;
            $types .= 's';
        }

        $sql = "
            SELECT CONCAT('PAY-', LPAD(p.payment_id, 6, '0')) AS payment_reference,
                   v.ticket_number, v.driver_name, v.plate_number, v.violation_type,
                   p.amount_paid, p.payment_method, p.payment_status, p.payment_date,
                   COALESCE(u.full_name, 'Not recorded') AS received_by_name
            FROM payments p
            JOIN violations v ON v.violation_id = p.violation_id
            LEFT JOIN users u ON u.user_id = p.received_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.payment_date DESC, p.payment_id DESC
            LIMIT 1000
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $totalCollected = 0.0;
        $completed = 0;
        foreach ($rows as $row) {
            if (strtolower((string)$row['payment_status']) === 'completed') {
                $completed++;
                $totalCollected += (float)$row['amount_paid'];
            }
        }

        $summary = [
            'Total Transactions' => count($rows),
            'Completed Payments' => $completed,
            'Other Statuses' => count($rows) - $completed,
            'Total Collected' => '₱' . number_format($totalCollected, 2),
        ];
    }

    if ($reportType === 'monitoring') {
        $title = 'Traffic Monitoring Report';
        $columns = [
            'camera_name' => 'Camera',
            'location' => 'Location',
            'vehicle_count' => 'Vehicle Count',
            'inbound_count' => 'Inbound',
            'outbound_count' => 'Outbound',
            'congestion_level' => 'Congestion',
            'officer_presence' => 'Officer Presence',
            'potential_collision' => 'Potential Collision',
            'recorded_at' => 'Recorded At',
        ];

        $where = ['DATE(l.recorded_at) BETWEEN ? AND ?'];
        $params = [$dateFrom, $dateTo];
        $types = 'ss';

        if ($statusFilter !== '') {
            $where[] = 'l.congestion_level = ?';
            $params[] = $statusFilter;
            $types .= 's';
        }

        if ($locationFilter !== '') {
            $where[] = 'c.location LIKE ?';
            $params[] = '%' . $locationFilter . '%';
            $types .= 's';
        }

        $sql = "
            SELECT COALESCE(c.camera_name, 'Unnamed Camera') AS camera_name,
                   COALESCE(c.location, 'Unknown Location') AS location,
                   l.vehicle_count, l.inbound_count, l.outbound_count,
                   l.congestion_level, l.officer_presence,
                   l.potential_collision, l.recorded_at
            FROM camera_monitoring_logs l
            LEFT JOIN cameras c ON c.camera_id = l.camera_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.recorded_at DESC
            LIMIT 1000
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $vehicleObservations = 0;
        $highCongestion = 0;
        $collisionFlags = 0;
        foreach ($rows as $row) {
            $vehicleObservations += (int)$row['vehicle_count'];
            if (in_array(strtolower((string)$row['congestion_level']), ['high', 'heavy', 'critical', 'severe'], true)) {
                $highCongestion++;
            }
            if (!in_array(strtolower((string)$row['potential_collision']), ['none', 'no', 'false', '0', ''], true)) {
                $collisionFlags++;
            }
        }

        $summary = [
            'Monitoring Records' => count($rows),
            'Vehicle Observations' => $vehicleObservations,
            'High Congestion Records' => $highCongestion,
            'Potential Collision Flags' => $collisionFlags,
        ];
    }

    return [$rows, $columns, $summary, $title];
}

$dateFrom = report_safe_date($dateFrom, date('Y-m-01'));
$dateTo = report_safe_date($dateTo, date('Y-m-d'));

if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    $message = 'The date range was corrected because the start date was later than the end date.';
    $messageType = 'warning';
}

if (isset($_GET['generate']) || $action === 'csv' || $action === 'print') {
    try {
        [$previewRows, $previewColumns, $summary, $reportTitle] = build_report(
            $conn,
            $reportType,
            $dateFrom,
            $dateTo,
            $statusFilter,
            $locationFilter
        );
    } catch (Throwable $e) {
        $message = 'Unable to generate the report: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

if ($action === 'csv' && $reportTitle !== '') {
    $filename = strtolower(str_replace(' ', '_', $reportTitle)) . '_' . $dateFrom . '_to_' . $dateTo . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, array_values($previewColumns));

    foreach ($previewRows as $row) {
        $line = [];
        foreach (array_keys($previewColumns) as $key) {
            $value = $row[$key] ?? '';
            if (in_array($key, ['penalty_amount', 'amount_paid'], true)) {
                $value = number_format((float)$value, 2, '.', '');
            }
            $line[] = $value;
        }
        fputcsv($output, $line);
    }

    fclose($output);
    exit;
}

$reportHistory = fetch_all("
    SELECT r.*, u.full_name AS generated_by_name
    FROM reports r
    LEFT JOIN users u ON u.user_id = r.generated_by
    ORDER BY r.generated_at DESC
    LIMIT 50
");

$totalReports = scalar("SELECT COUNT(*) FROM reports", 0);
$reportsToday = scalar("SELECT COUNT(*) FROM reports WHERE DATE(generated_at) = CURDATE()", 0);
$reportsThisMonth = scalar("
    SELECT COUNT(*) FROM reports
    WHERE YEAR(generated_at) = YEAR(CURDATE())
      AND MONTH(generated_at) = MONTH(CURDATE())
", 0);
$lastGenerated = fetch_one("SELECT generated_at FROM reports ORDER BY generated_at DESC LIMIT 1");

$statusOptions = [
    'violations' => [
        '' => 'All Statuses',
        'pending' => 'Pending',
        'overdue' => 'Overdue',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ],
    'payments' => [
        '' => 'All Statuses',
        'completed' => 'Completed',
        'pending' => 'Pending',
        'failed' => 'Failed',
    ],
    'monitoring' => [
        '' => 'All Congestion Levels',
        'none' => 'None',
        'low' => 'Low',
        'moderate' => 'Moderate',
        'high' => 'High',
        'heavy' => 'Heavy',
        'critical' => 'Critical',
    ],
];

$queryBase = [
    'report_type' => $reportType,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'status' => $statusFilter,
    'location' => $locationFilter,
    'generate' => '1',
];

page_start('Reports', 'reports', 'Search reports...');
?>

<style>
.report-table-scroll {
  max-height: 560px;
  overflow: auto;
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: .75rem;
}
.report-table-scroll thead th {
  position: sticky;
  top: 0;
  z-index: 5;
  background: #fff;
  box-shadow: inset 0 -1px 0 #dee2e6;
  white-space: nowrap;
}
.report-table-scroll::-webkit-scrollbar { width: 9px; height: 9px; }
.report-table-scroll::-webkit-scrollbar-thumb { background: #b8c0cc; border-radius: 10px; }
.report-table-scroll::-webkit-scrollbar-track { background: #f1f3f5; }
@media print {
  .sidebar, .topbar, .btn, .report-generator, .report-history, .no-print { display: none !important; }
  .report-table-scroll { max-height: none; overflow: visible; border: 0; }
  .report-table-scroll thead th { position: static; }
  body { background: #fff !important; }
}
</style>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <h3 class="page-title">Reports</h3>
    <p class="page-sub">Generate, preview, print, and export operational records.</p>
  </div>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-files"></i></div><div class="stat-label">Total Saved Reports</div><div class="stat-value"><?= num($totalReports) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-success"><i class="bi bi-calendar-check"></i></div><div class="stat-label">Generated Today</div><div class="stat-value"><?= num($reportsToday) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-warning"><i class="bi bi-calendar3"></i></div><div class="stat-label">This Month</div><div class="stat-value"><?= num($reportsThisMonth) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-clock-history"></i></div><div class="stat-label">Last Generated</div><div class="fs-6 fw-semibold mt-2"><?= esc($lastGenerated['generated_at'] ?? 'No saved report') ?></div></div></div>
</div>

<div class="section-card mb-4 report-generator">
  <div class="section-head">
    <div><h6 class="mb-0">Report Generator</h6><small class="text-muted">Choose a report type and date range, then generate a preview.</small></div>
  </div>

  <form method="get" class="row g-3">
    <div class="col-md-6 col-xl-3">
      <label class="form-label">Report Type</label>
      <select class="form-select" name="report_type" id="reportType" required>
        <option value="violations" <?= $reportType === 'violations' ? 'selected' : '' ?>>Violation Report</option>
        <option value="payments" <?= $reportType === 'payments' ? 'selected' : '' ?>>Payment Collection Report</option>
        <option value="monitoring" <?= $reportType === 'monitoring' ? 'selected' : '' ?>>Traffic Monitoring Report</option>
      </select>
    </div>

    <div class="col-md-6 col-xl-2"><label class="form-label">Start Date</label><input type="date" class="form-control" name="date_from" value="<?= esc($dateFrom) ?>" required></div>
    <div class="col-md-6 col-xl-2"><label class="form-label">End Date</label><input type="date" class="form-control" name="date_to" value="<?= esc($dateTo) ?>" required></div>

    <div class="col-md-6 col-xl-2">
      <label class="form-label" id="statusLabel"><?= $reportType === 'monitoring' ? 'Congestion Level' : 'Status' ?></label>
      <select class="form-select" name="status" id="statusFilter">
        <?php foreach ($statusOptions[$reportType] as $value => $label): ?>
          <option value="<?= esc($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6 col-xl-3">
      <label class="form-label">Location Contains</label>
      <input type="text" class="form-control" name="location" value="<?= esc($locationFilter) ?>" placeholder="Example: J.P. Laurel Street" <?= $reportType === 'payments' ? 'disabled' : '' ?>>
    </div>

    <div class="col-12 d-flex flex-wrap gap-2">
      <button class="btn btn-primary" name="generate" value="1"><i class="bi bi-bar-chart-line me-1"></i>Generate Preview</button>
      <a class="btn btn-light" href="<?= esc(app_url('reports.php')) ?>"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
    </div>
  </form>
</div>

<?php if ($reportTitle !== ''): ?>
  <div class="section-card mb-4" id="reportPreview">
    <div class="section-head flex-wrap gap-2">
      <div>
        <h5 class="mb-1"><?= esc($reportTitle) ?></h5>
        <small class="text-muted">
          Period: <?= esc($dateFrom) ?> to <?= esc($dateTo) ?>
          <?php if ($statusFilter !== ''): ?> • Filter: <?= esc(ucwords(str_replace('_', ' ', $statusFilter))) ?><?php endif; ?>
          <?php if ($locationFilter !== '' && $reportType !== 'payments'): ?> • Location contains “<?= esc($locationFilter) ?>”<?php endif; ?>
        </small>
      </div>

      <div class="d-flex flex-wrap gap-2 no-print">
        <a class="btn btn-success btn-sm" href="<?= esc(app_url('reports.php?' . http_build_query(array_merge($queryBase, ['action' => 'csv'])))) ?>"><i class="bi bi-filetype-csv me-1"></i>Download CSV</a>
        <button class="btn btn-primary btn-sm" type="button" onclick="window.print()"><i class="bi bi-file-earmark-pdf me-1"></i>Print / Save as PDF</button>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <?php foreach ($summary as $label => $value): ?>
        <div class="col-sm-6 col-xl-3"><div class="border rounded-3 p-3 h-100"><small class="text-muted"><?= esc($label) ?></small><div class="fs-5 fw-semibold mt-1"><?= esc((string)$value) ?></div></div></div>
      <?php endforeach; ?>
    </div>

    <?php if (!$previewRows): ?>
      <?php empty_state('No records matched the selected report filters.'); ?>
    <?php else: ?>
      <div class="report-table-scroll">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><?php foreach ($previewColumns as $label): ?><th><?= esc($label) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($previewRows as $row): ?>
              <tr>
                <?php foreach ($previewColumns as $key => $label): ?>
                  <td>
                    <?php if (in_array($key, ['penalty_amount', 'amount_paid'], true)): ?>
                      <?= peso($row[$key] ?? 0) ?>
                    <?php elseif (in_array($key, ['status', 'payment_status', 'congestion_level'], true)): ?>
                      <span class="tag <?= tag_class($row[$key] ?? '') ?>"><?= esc(ucwords(str_replace('_', ' ', (string)($row[$key] ?? '')))) ?></span>
                    <?php elseif ($key === 'payment_method'): ?>
                      <?= esc(ucwords(str_replace('_', ' ', (string)($row[$key] ?? '')))) ?>
                    <?php else: ?>
                      <?= esc((string)($row[$key] ?? '')) ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <small class="text-muted d-block mt-2">Showing <?= num(count($previewRows)) ?> record(s). Preview and export are limited to 1,000 records.</small>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="section-card report-history">
  <div class="section-head"><div><h6 class="mb-0">Saved Report History</h6><small class="text-muted">Metadata previously stored in the reports table.</small></div></div>

  <?php if (!$reportHistory): ?>
    <?php empty_state('No saved report metadata was found. Generated previews are not automatically saved to the reports table.'); ?>
  <?php else: ?>
    <div class="report-table-scroll">
      <table class="table align-middle mb-0">
        <thead><tr><th>Title</th><th>Type</th><th>Period</th><th>Status</th><th>Generated By</th><th>Generated At</th></tr></thead>
        <tbody>
          <?php foreach ($reportHistory as $report): ?>
            <tr>
              <td class="fw-semibold"><?= esc($report['title']) ?></td>
              <td><?= esc(ucwords(str_replace('_', ' ', $report['report_type']))) ?></td>
              <td><?= esc(($report['period_start'] ?? 'N/A') . ' - ' . ($report['period_end'] ?? 'N/A')) ?></td>
              <td><span class="tag <?= tag_class($report['status']) ?>"><?= esc(ucfirst($report['status'])) ?></span></td>
              <td><?= esc($report['generated_by_name'] ?? 'N/A') ?></td>
              <td><?= esc($report['generated_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
const statusOptions = <?= json_encode($statusOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const reportTypeSelect = document.getElementById('reportType');
const statusSelect = document.getElementById('statusFilter');
const statusLabel = document.getElementById('statusLabel');
const locationInput = document.querySelector('input[name="location"]');

function rebuildStatusOptions() {
  const reportType = reportTypeSelect.value;
  const options = statusOptions[reportType] || {};
  statusSelect.innerHTML = '';

  Object.entries(options).forEach(([value, label]) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = label;
    statusSelect.appendChild(option);
  });

  statusLabel.textContent = reportType === 'monitoring' ? 'Congestion Level' : 'Status';

  if (locationInput) {
    locationInput.disabled = reportType === 'payments';
    if (reportType === 'payments') locationInput.value = '';
  }
}

reportTypeSelect.addEventListener('change', rebuildStatusOptions);
</script>

<?php page_end(); ?>
