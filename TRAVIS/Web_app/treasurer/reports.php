<?php
require_once __DIR__ . '/layout.php';

function report_safe_date(string $value, string $fallback): string {
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : $fallback;
}

$dateFrom = report_safe_date(trim((string)($_GET['date_from'] ?? '')), date('Y-m-01'));
$dateTo = report_safe_date(trim((string)($_GET['date_to'] ?? '')), date('Y-m-d'));
$reportType = trim((string)($_GET['report_type'] ?? 'summary'));
$allowedTypes = ['summary', 'detailed', 'by_method'];
if (!in_array($reportType, $allowedTypes, true)) {
    $reportType = 'summary';
}

$dailyCollections = scalar("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'completed' AND DATE(payment_date) = CURDATE()", 0);
$weeklyCollections = scalar("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'completed' AND YEARWEEK(payment_date, 1) = YEARWEEK(CURDATE(), 1)", 0);
$monthlyCollections = scalar("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'completed' AND YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())", 0);
$annualCollections = scalar("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'completed' AND YEAR(payment_date) = YEAR(CURDATE())", 0);

// Report preview rows for the selected range.
if ($reportType === 'by_method') {
    $previewRows = fetch_all("
        SELECT payment_method, COUNT(*) AS total_transactions, COALESCE(SUM(amount_paid), 0) AS total_amount
        FROM payments
        WHERE payment_status = 'completed' AND DATE(payment_date) BETWEEN ? AND ?
        GROUP BY payment_method
        ORDER BY total_amount DESC
    ", [$dateFrom, $dateTo]);
} elseif ($reportType === 'detailed') {
    $previewRows = fetch_all("
        SELECT p.payment_id, p.amount_paid, p.payment_method, p.payment_date,
               v.ticket_number, v.plate_number, v.violation_type
        FROM payments p
        JOIN violations v ON v.violation_id = p.violation_id
        WHERE p.payment_status = 'completed' AND DATE(p.payment_date) BETWEEN ? AND ?
        ORDER BY p.payment_date DESC
        LIMIT 500
    ", [$dateFrom, $dateTo]);
} else {
    $previewRows = fetch_all("
        SELECT DATE(payment_date) AS collection_date, COUNT(*) AS total_transactions, COALESCE(SUM(amount_paid), 0) AS total_amount
        FROM payments
        WHERE payment_status = 'completed' AND DATE(payment_date) BETWEEN ? AND ?
        GROUP BY DATE(payment_date)
        ORDER BY collection_date DESC
    ", [$dateFrom, $dateTo]);
}

if (($_GET['action'] ?? '') === 'export_csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="travis-collection-report-' . $dateFrom . '-to-' . $dateTo . '.csv"');
    $out = fopen('php://output', 'w');
    if ($previewRows) {
        fputcsv($out, array_keys($previewRows[0]));
        foreach ($previewRows as $row) {
            fputcsv($out, $row);
        }
    }
    fclose($out);
    exit;
}

$violationTypeBreakdown = fetch_all("
    SELECT v.violation_type, COUNT(*) AS total
    FROM payments p
    JOIN violations v ON v.violation_id = p.violation_id
    WHERE p.payment_status = 'completed' AND DATE(p.payment_date) BETWEEN ? AND ?
    GROUP BY v.violation_type
    ORDER BY total DESC
    LIMIT 8
", [$dateFrom, $dateTo]);

$weeklyTrend = fetch_all("
    SELECT YEARWEEK(payment_date, 1) AS yw, MIN(DATE(payment_date)) AS week_start, COALESCE(SUM(amount_paid), 0) AS total
    FROM payments
    WHERE payment_status = 'completed' AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 8 WEEK)
    GROUP BY YEARWEEK(payment_date, 1)
    ORDER BY yw ASC
");

$justGenerated = ($_GET['generated'] ?? '') === '1';
$generateSuccess = $justGenerated && !empty($previewRows);
$generateFailed = $justGenerated && empty($previewRows);

page_start('Collection Reports', 'reports', 'Search reports...', 'Generate and export payment collection reports', false);
?>

<style>
.collection-print-header,.collection-print-footer{display:none}
@media print{
  @page{size:A4 landscape;margin:14mm 12mm 16mm}
  body.admin-dashboard .content>*:not(.print-area){display:none!important}
  body.admin-dashboard .print-area{display:block!important;border:0!important;padding:0!important}
  .collection-print-header,.collection-print-footer{display:block!important}
  .collection-print-header{text-align:center;border-bottom:2px solid #17324d;padding-bottom:10px;margin-bottom:16px;color:#111827!important}
  .collection-print-header .republic{font:11px Georgia,serif;letter-spacing:.08em;text-transform:uppercase}
  .collection-print-header h1{font:700 20px Georgia,serif;margin:3px 0;color:#102f49!important}
  .collection-print-header p{font:11px Arial,sans-serif;margin:2px 0;color:#374151!important}
  .collection-print-meta{display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;text-align:left;margin-top:12px;font:11px Arial,sans-serif}
  .print-area>.section-head{display:none!important}
  .print-area .table-responsive{overflow:visible!important;max-height:none!important}
  .print-area table{font-size:10px;color:#111827!important}
  .print-area thead{display:table-header-group}
  .print-area thead th{background:#e9eef2!important;color:#102f49!important;border-bottom:2px solid #102f49!important}
  .print-area tr{page-break-inside:avoid}
  .collection-print-footer{font:11px Arial,sans-serif;margin-top:24px;page-break-inside:avoid;color:#111827!important}
  .collection-signatures{display:grid;grid-template-columns:1fr 1fr;gap:80px;margin-top:38px}
  .collection-signatures div{border-top:1px solid #111827;padding-top:5px;text-align:center}
}
</style>


<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-calendar-day"></i></div><div class="stat-label">Daily Collections</div><div class="stat-value"><?= short_money($dailyCollections) ?></div><small class="text-muted">Today</small></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-calendar-week"></i></div><div class="stat-label">Weekly Collections</div><div class="stat-value"><?= short_money($weeklyCollections) ?></div><small class="text-muted">This week</small></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-calendar3"></i></div><div class="stat-label">Monthly Collections</div><div class="stat-value"><?= short_money($monthlyCollections) ?></div><small class="text-muted"><?= esc(date('F Y')) ?></small></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-graph-up"></i></div><div class="stat-label">Annual Collections</div><div class="stat-value"><?= short_money($annualCollections) ?></div><small class="text-muted">CY <?= esc(date('Y')) ?></small></div></div>
</div>

<div class="section-card mb-4">
  <div class="section-head"><div><h6 class="mb-0">Generate Report</h6><small class="text-muted">Select a date range and report type, then export or print.</small></div></div>

  <form method="get" class="row g-3 align-items-end">
    <div class="col-md-3"><label class="form-label">From Date</label><input class="form-control" type="date" name="date_from" value="<?= esc($dateFrom) ?>"></div>
    <div class="col-md-3"><label class="form-label">To Date</label><input class="form-control" type="date" name="date_to" value="<?= esc($dateTo) ?>"></div>
    <div class="col-md-3">
      <label class="form-label">Report Type</label>
      <select class="form-select" name="report_type">
        <option value="summary" <?= $reportType === 'summary' ? 'selected' : '' ?>>Daily Summary</option>
        <option value="detailed" <?= $reportType === 'detailed' ? 'selected' : '' ?>>Detailed Transactions</option>
        <option value="by_method" <?= $reportType === 'by_method' ? 'selected' : '' ?>>By Payment Method</option>
      </select>
    </div>
    <div class="col-md-3"><button class="btn btn-primary w-100" type="submit">Generate</button></div>
    <input type="hidden" name="generated" value="1">
  </form>

  <div class="d-flex gap-2 flex-wrap mt-3">
    <a class="btn btn-navy" href="<?= esc(app_url('reports.php?' . http_build_query(['date_from' => $dateFrom, 'date_to' => $dateTo, 'report_type' => $reportType, 'action' => 'export_csv']))) ?>">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
    </a>
    <button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print Report</button>
  </div>
</div>

<div class="section-card mb-4 print-area">
  <header class="collection-print-header">
    <div class="republic">Republic of the Philippines</div>
    <h1>Municipality of Nasugbu</h1>
    <p>Municipal Treasurer's Office · Traffic Management Office</p>
    <p><strong>Official Collection Report</strong></p>
    <div class="collection-print-meta">
      <div><strong>Report type:</strong> <?= esc(ucwords(str_replace('_', ' ', $reportType))) ?></div>
      <div><strong>Date generated:</strong> <?= esc(date('F j, Y g:i A')) ?></div>
      <div><strong>Covered period:</strong> <?= esc(date('F j, Y', strtotime($dateFrom))) ?> – <?= esc(date('F j, Y', strtotime($dateTo))) ?></div>
      <div><strong>Prepared by:</strong> <?= esc($_SESSION['full_name'] ?? 'Treasury Personnel') ?></div>
    </div>
  </header>
  <div class="section-head"><h6 class="mb-0">Report Preview</h6><small class="text-muted"><?= esc($dateFrom) ?> to <?= esc($dateTo) ?></small></div>
  <?php if (!$previewRows): ?>
    <?php empty_state('No completed payments were found for the selected range.'); ?>
  <?php else: ?>
    <div class="table-responsive table-scroll">
      <table class="table align-middle">
        <thead>
          <tr>
            <?php foreach (array_keys($previewRows[0]) as $col): ?>
              <th><?= esc(ucwords(str_replace('_', ' ', $col))) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($previewRows as $row): ?>
            <tr>
              <?php foreach ($row as $key => $value): ?>
                <td><?= str_contains($key, 'amount') ? peso($value) : esc((string)$value) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
  <footer class="collection-print-footer">
    <p>I certify that the collections shown above agree with the payment records maintained in TRAVIS for the stated period.</p>
    <div class="collection-signatures"><div>Prepared by / Date</div><div>Municipal Treasurer / Date</div></div>
  </footer>
</div>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="section-card">
      <div class="section-head"><h6 class="mb-0">Collection Trend</h6><small class="text-muted">Last 8 weeks</small></div>
      <div style="height:260px"><canvas id="chartTrend"></canvas></div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="section-card">
      <div class="section-head"><h6 class="mb-0">Violation Type Breakdown</h6><small class="text-muted">Paid violations in selected range</small></div>
      <div style="height:260px"><canvas id="chartTypes"></canvas></div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const trendLabels = <?= json_encode(array_map(fn($r) => date('M j', strtotime($r['week_start'])), $weeklyTrend)) ?>;
const trendData = <?= json_encode(array_map(fn($r) => (float)$r['total'], $weeklyTrend)) ?>;
const typeLabels = <?= json_encode(array_column($violationTypeBreakdown, 'violation_type')) ?>;
const typeData = <?= json_encode(array_map('intval', array_column($violationTypeBreakdown, 'total'))) ?>;

Chart.defaults.font.family = "'Poppins', sans-serif";
Chart.defaults.color = '#526b64';

const trendCtx = document.getElementById('chartTrend').getContext('2d');
const trendGrad = trendCtx.createLinearGradient(0, 0, 0, 260);
trendGrad.addColorStop(0, 'rgba(8,125,120,.30)');
trendGrad.addColorStop(1, 'rgba(8,125,120,0)');
new Chart(trendCtx, {
  type: 'line',
  data: { labels: trendLabels, datasets: [{ label: 'Weekly', data: trendData, borderColor: '#087d78', backgroundColor: trendGrad, fill: true, tension: .4, borderWidth: 3, pointBackgroundColor: '#eb941f', pointBorderColor: '#fffdf7', pointBorderWidth: 2, pointRadius: 5 }] },
  options: {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => '₱' + c.parsed.y.toLocaleString() } } },
    scales: { y: { grid: { color: 'rgba(148,163,184,.15)' }, ticks: { callback: v => '₱' + (v / 1000) + 'k' } }, x: { grid: { display: false } } }
  }
});

new Chart(document.getElementById('chartTypes'), {
  type: 'polarArea',
  data: { labels: typeLabels, datasets: [{ data: typeData, backgroundColor: ['rgba(8,125,120,.82)','rgba(235,148,31,.82)','rgba(21,150,111,.78)','rgba(200,75,69,.78)','rgba(62,124,146,.78)','rgba(120,169,159,.78)','rgba(200,120,32,.72)','rgba(80,110,103,.72)'], borderWidth: 2, borderColor: '#fffdf7' }] },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { position: 'right', labels: { color: '#526b64', usePointStyle: true, padding: 14, font: { size: 11 } } },
      tooltip: { backgroundColor: '#102f49', titleColor: '#fff', bodyColor: '#e8f0ed', borderColor: 'rgba(255,255,255,.16)', borderWidth: 1 }
    },
    scales: {
      r: {
        beginAtZero: true,
        grid: { color: 'rgba(16,47,73,.12)' },
        angleLines: { color: 'rgba(16,47,73,.12)' },
        ticks: { color: '#526b64', backdropColor: 'rgba(255,253,247,.88)', backdropPadding: 3, z: 1 },
        pointLabels: { color: '#526b64' }
      }
    }
  }
});
</script>

<div class="modal fade" id="generateResultModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content text-center">
      <div class="modal-body py-4">
        <?php if ($generateSuccess): ?>
          <div class="mb-3" style="font-size:2.5rem;color:#16a34a;"><i class="bi bi-check-circle-fill"></i></div>
          <h5 class="mb-2">Report Generated</h5>
          <p class="text-muted mb-0"><?= count($previewRows) ?> record<?= count($previewRows) === 1 ? '' : 's' ?> found for <?= esc($dateFrom) ?> to <?= esc($dateTo) ?>.</p>
        <?php elseif ($generateFailed): ?>
          <div class="mb-3" style="font-size:2.5rem;color:#dc2626;"><i class="bi bi-x-circle-fill"></i></div>
          <h5 class="mb-2">Report Generation Failed</h5>
          <p class="text-muted mb-0">No completed payments were found for the selected range. Try a different date range or report type.</p>
        <?php endif; ?>
      </div>
      <div class="modal-footer justify-content-center border-top-0 pt-0">
        <button class="btn btn-primary" type="button" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>
</div>

<?php if ($justGenerated): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('generateResultModal');
  if (el) bootstrap.Modal.getOrCreateInstance(el).show();
});
</script>
<?php endif; ?>

<?php page_end(); ?>
