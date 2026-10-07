<?php
require_once __DIR__ . '/layout.php';

$period = strtolower(trim((string)($_GET['period'] ?? 'day')));
$selectedDay = trim((string)($_GET['dashboard_day'] ?? date('Y-m-d')));
$selectedMonth = trim((string)($_GET['dashboard_month'] ?? date('Y-m')));
$selectedYear = trim((string)($_GET['dashboard_year'] ?? date('Y')));
$parsedDay = DateTime::createFromFormat('!Y-m-d', $selectedDay);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDay) || !$parsedDay || $parsedDay->format('Y-m-d') !== $selectedDay) $selectedDay = date('Y-m-d');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) $selectedMonth = date('Y-m');
if (!preg_match('/^\d{4}$/', $selectedYear) || (int)$selectedYear < 2000 || (int)$selectedYear > 2100) $selectedYear = date('Y');
$periodLabels = ['day' => 'Specific day', 'month' => 'Specific month', 'year' => 'Specific year', 'all' => 'All records'];
if (!isset($periodLabels[$period])) $period = 'all';
$periodDisplay = match ($period) {
    'day' => date('F j, Y', strtotime($selectedDay)),
    'month' => date('F Y', strtotime($selectedMonth . '-01')),
    'year' => $selectedYear,
    default => 'All records',
};
$dateCondition = static function (string $column) use ($period, $selectedDay, $selectedMonth, $selectedYear): string {
    return match ($period) {
        'day' => "DATE($column) = '{$selectedDay}'",
        'month' => "DATE_FORMAT($column, '%Y-%m') = '{$selectedMonth}'",
        'year' => "YEAR($column) = {$selectedYear}",
        default => '1=1',
    };
};
$violationDateFilter = $dateCondition('violation_date');
$paymentDateFilter = $dateCondition('payment_date');

$totalViolations = scalar("SELECT COUNT(*) FROM violations WHERE $violationDateFilter", 0);
$pendingPayments = scalar("SELECT COUNT(*) FROM violations WHERE status IN ('pending', 'overdue') AND $violationDateFilter", 0);
$paidViolations = scalar("SELECT COUNT(*) FROM violations WHERE status = 'paid' AND $violationDateFilter", 0);

$todaysCollections = scalar("
    SELECT COALESCE(SUM(amount_paid), 0)
    FROM payments
    WHERE payment_status = 'completed' AND $paymentDateFilter
", 0);

$monthlyCollections = scalar("
    SELECT COALESCE(SUM(amount_paid), 0)
    FROM payments
    WHERE payment_status = 'completed'
      AND YEAR(payment_date) = YEAR(CURDATE())
      AND MONTH(payment_date) = MONTH(CURDATE())
", 0);

// --- Trend comparisons for stat card chips ---

$violationsThisWeek = (float)scalar("
    SELECT COUNT(*) FROM violations WHERE YEARWEEK(violation_date, 1) = YEARWEEK(CURDATE(), 1)
", 0);
$violationsLastWeek = (float)scalar("
    SELECT COUNT(*) FROM violations
    WHERE YEARWEEK(violation_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)
", 0);
$totalViolationsTrend = trend_label($violationsThisWeek, $violationsLastWeek);

$pendingThisWeek = (float)scalar("
    SELECT COUNT(*) FROM violations
    WHERE status IN ('pending', 'overdue') AND YEARWEEK(violation_date, 1) = YEARWEEK(CURDATE(), 1)
", 0);
$pendingLastWeek = (float)scalar("
    SELECT COUNT(*) FROM violations
    WHERE status IN ('pending', 'overdue') AND YEARWEEK(violation_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)
", 0);
$pendingPaymentsTrend = trend_label($pendingThisWeek, $pendingLastWeek);

$paidThisMonth = (float)scalar("
    SELECT COUNT(*) FROM violations
    WHERE status = 'paid' AND YEAR(violation_date) = YEAR(CURDATE()) AND MONTH(violation_date) = MONTH(CURDATE())
", 0);
$paidLastMonth = (float)scalar("
    SELECT COUNT(*) FROM violations
    WHERE status = 'paid'
      AND YEAR(violation_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND MONTH(violation_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
", 0);
$paidViolationsTrend = trend_label($paidThisMonth, $paidLastMonth);

$yesterdaysCollections = (float)scalar("
    SELECT COALESCE(SUM(amount_paid), 0) FROM payments
    WHERE payment_status = 'completed' AND DATE(payment_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
", 0);
$todaysCollectionsTrend = trend_label((float)$todaysCollections, $yesterdaysCollections);

$lastMonthCollections = (float)scalar("
    SELECT COALESCE(SUM(amount_paid), 0) FROM payments
    WHERE payment_status = 'completed'
      AND YEAR(payment_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
      AND MONTH(payment_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))
", 0);
$monthlyCollectionsTrend = trend_label((float)$monthlyCollections, $lastMonthCollections);

$recentPayments = fetch_all("
    SELECT p.payment_id, p.amount_paid, p.payment_date, p.payment_status,
           v.plate_number, v.violation_type
    FROM payments p
    JOIN violations v ON v.violation_id = p.violation_id
    WHERE p.payment_status = 'completed' AND " . $dateCondition('p.payment_date') . "
    ORDER BY p.payment_date DESC, p.payment_id DESC
    LIMIT 6
");

$pendingList = fetch_all("
    SELECT violation_id, ticket_number, plate_number, violation_type, penalty_amount, violation_date
    FROM violations
    WHERE status IN ('pending', 'overdue') AND " . $dateCondition('violation_date') . "
    ORDER BY CASE WHEN status = 'overdue' THEN 0 ELSE 1 END, violation_date ASC
    LIMIT 6
");

$statusBreakdown = ['paid' => 0, 'pending' => 0, 'overdue' => 0, 'cancelled' => 0];
foreach (fetch_all("SELECT LOWER(status) status, COUNT(*) total FROM violations WHERE $violationDateFilter GROUP BY LOWER(status)") as $statusRow) {
    $statusKey = (string)($statusRow['status'] ?? '');
    if (array_key_exists($statusKey, $statusBreakdown)) $statusBreakdown[$statusKey] = (int)$statusRow['total'];
}
$dailyTrend = daily_collection_trend(7);
$monthlyTotals = monthly_collection_totals();

page_start('Dashboard', 'dashboard', 'Search violations, receipts, plates...', 'Overview of collections and violation payments', false);
?>

<div class="dashboard-period-toolbar mb-3">
  <div class="dashboard-period-summary">
    <span class="dashboard-period-icon"><i class="bi bi-calendar3"></i></span>
    <span><small>Currently showing</small><strong><?= esc($periodDisplay) ?></strong></span>
  </div>
  <form method="get" class="dashboard-period-form" id="dashboardFilterForm">
    <div class="dashboard-filter-field dashboard-filter-period">
      <label for="dashboardPeriod">Collection period</label>
      <select id="dashboardPeriod" name="period" class="form-select">
        <?php foreach ($periodLabels as $value => $label): ?>
          <option value="<?= esc($value) ?>" <?= $period === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="dashboard-filter-field dashboard-filter-date">
      <label for="dashboardDay" id="dashboardValueLabel">Select date</label>
      <input class="form-control dashboard-filter-value" type="date" name="dashboard_day" id="dashboardDay" value="<?= esc($selectedDay) ?>">
      <input class="form-control dashboard-filter-value" type="month" name="dashboard_month" id="dashboardMonth" value="<?= esc($selectedMonth) ?>">
      <input class="form-control dashboard-filter-value" type="number" name="dashboard_year" id="dashboardYear" value="<?= esc($selectedYear) ?>" min="2000" max="2100" inputmode="numeric">
    </div>
    <button class="btn btn-primary dashboard-filter-submit" type="submit"><i class="bi bi-funnel"></i><span>Apply filter</span></button>
  </form>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl">
    <div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-cone-striped"></i></div>
      <div class="stat-label">Total Violations</div><div class="stat-value"><?= num($totalViolations) ?></div>
      <div class="stat-trend"><?= esc($periodDisplay) ?></div></div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card"><div class="stat-icon tone-warning"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-label">Pending Payments</div><div class="stat-value"><?= num($pendingPayments) ?></div>
      <div class="stat-trend"><?= esc($periodDisplay) ?></div></div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card"><div class="stat-icon tone-success"><i class="bi bi-check2-circle"></i></div>
      <div class="stat-label">Paid Violations</div><div class="stat-value"><?= num($paidViolations) ?></div>
      <div class="stat-trend"><?= esc($periodDisplay) ?></div></div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card"><div class="stat-icon tone-navy"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-label">Selected Collections</div><div class="stat-value"><?= short_money($todaysCollections) ?></div>
      <div class="stat-trend"><?= esc($periodDisplay) ?></div></div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card"><div class="stat-icon tone-teal"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="stat-label">Monthly Collections</div><div class="stat-value"><?= short_money($monthlyCollections) ?></div>
      <div class="stat-trend <?= $monthlyCollectionsTrend['direction'] === 'down' ? 'down' : '' ?>"><?= esc($monthlyCollectionsTrend['label']) ?> MoM</div></div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-4">
    <div class="section-card h-100">
      <div class="section-head"><div><h6 class="mb-0">Daily Collection</h6><small class="text-muted">Last 7 days</small></div></div>
      <div style="height:200px"><canvas id="chartDaily"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="section-card h-100">
      <div class="section-head"><div><h6 class="mb-0">Monthly Collection</h6><small class="text-muted">Calendar year overview</small></div></div>
      <div style="height:200px"><canvas id="chartMonthly"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="section-card h-100">
      <div class="section-head"><h6 class="mb-0">Payment Status Distribution</h6></div>
      <div style="height:200px"><canvas id="chartStatus"></canvas></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6 class="mb-0">Recent Payments</h6>
        <a class="small fw-semibold text-decoration-none" style="color:var(--teal-dark)" href="<?= esc(app_url('payments.php')) ?>">View all</a>
      </div>
      <?php if (!$recentPayments): ?>
        <?php empty_state('No payments have been recorded yet.'); ?>
      <?php else: ?>
        <div class="table-responsive table-scroll">
          <table class="table table-sm align-middle">
            <thead><tr><th>Receipt No.</th><th>Plate</th><th>Violation</th><th>Amount</th><th>Date Paid</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($recentPayments as $p): ?>
                <tr>
                  <td class="fw-semibold"><?= esc(($p['official_receipt_number'] ?? '') ?: payment_reference((int)$p['payment_id'])) ?></td>
                  <td><?= esc($p['plate_number']) ?></td>
                  <td><?= esc($p['violation_type']) ?></td>
                  <td class="fw-semibold"><?= peso($p['amount_paid']) ?></td>
                  <td><?= esc($p['payment_date']) ?></td>
                  <td><span class="tag <?= tag_class($p['payment_status']) ?>"><?= esc(ucfirst($p['payment_status'])) ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6 class="mb-0">Pending Payments</h6>
        <a class="small fw-semibold text-decoration-none" style="color:var(--teal-dark)" href="<?= esc(app_url('violations.php?status=pending')) ?>">View all</a>
      </div>
      <?php if (!$pendingList): ?>
        <?php empty_state('There are no pending or overdue violations right now.'); ?>
      <?php else: ?>
        <div class="table-responsive table-scroll">
          <table class="table table-sm align-middle">
            <thead><tr><th>Violation ID</th><th>Plate</th><th>Type</th><th>Fine</th><th>Due Date</th><th class="text-end">Action</th></tr></thead>
            <tbody>
              <?php foreach ($pendingList as $v): ?>
                <tr>
                  <td class="fw-semibold"><?= esc($v['ticket_number']) ?></td>
                  <td><?= esc($v['plate_number']) ?></td>
                  <td><?= esc($v['violation_type']) ?></td>
                  <td class="fw-semibold"><?= peso($v['penalty_amount']) ?></td>
                  <td><?= esc($v['violation_date']) ?></td>
                  <td class="text-end text-nowrap">
                    <a class="icon-link" title="View" href="<?= esc(app_url('violations.php?search=' . urlencode($v['ticket_number']))) ?>"><i class="bi bi-eye"></i></a>
                    <a class="icon-link" title="Process payment" href="<?= esc(app_url('payments.php?violation_id=' . (int)$v['violation_id'])) ?>"><i class="bi bi-credit-card"></i></a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
function updateDashboardFilterInput() {
  const period = document.getElementById('dashboardPeriod')?.value || 'day';
  const valueLabel = document.getElementById('dashboardValueLabel');
  const inputs = {
    day: document.getElementById('dashboardDay'),
    month: document.getElementById('dashboardMonth'),
    year: document.getElementById('dashboardYear')
  };
  Object.entries(inputs).forEach(([key, input]) => {
    if (!input) return;
    input.classList.toggle('d-none', period !== key);
    input.disabled = period !== key;
  });
  if (valueLabel) {
    valueLabel.textContent = ({day: 'Select date', month: 'Select month', year: 'Enter year'})[period] || 'Date range';
    valueLabel.closest('.dashboard-filter-field')?.classList.toggle('d-none', period === 'all');
  }
}
document.getElementById('dashboardPeriod')?.addEventListener('change', updateDashboardFilterInput);
updateDashboardFilterInput();

const statusLabels = ['Paid', 'Pending', 'Overdue', 'Cancelled'];
const statusData = <?= json_encode(array_values($statusBreakdown)) ?>;
const dailyLabels = <?= json_encode($dailyTrend['labels']) ?>;
const dailyData = <?= json_encode($dailyTrend['data']) ?>;
const monthLabels = <?= json_encode(month_labels()) ?>;
const monthlyData = <?= json_encode($monthlyTotals) ?>;

Chart.defaults.font.family = "'Poppins', sans-serif";
Chart.defaults.color = '#526b64';

function baseOpts() {
  return {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: {
      backgroundColor: '#102f49', titleColor: '#ffffff', bodyColor: '#e8f0ed', padding: 12, cornerRadius: 8,
      callbacks: { label: c => '₱' + c.parsed.y.toLocaleString() }
    }},
    scales: {
      y: { grid: { color: 'rgba(148,163,184,.15)' }, ticks: { callback: v => '₱' + (v / 1000) + 'k', font: { size: 11 } } },
      x: { grid: { display: false }, ticks: { color: '#526b64', font: { size: 11 } } }
    }
  };
}

new Chart(document.getElementById('chartStatus'), {
  type: 'doughnut',
  data: { labels: statusLabels, datasets: [{ data: statusData, backgroundColor: ['#15966f', '#eb941f', '#c84b45', '#8b9b96'], borderWidth: 3, borderColor: '#fffdf7', hoverOffset: 10 }] },
  options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { color: '#526b64', usePointStyle: true, padding: 16, font: { size: 12 } } } } }
});

const dailyCtx = document.getElementById('chartDaily').getContext('2d');
const dailyGrad = dailyCtx.createLinearGradient(0, 0, 0, 200);
dailyGrad.addColorStop(0, 'rgba(8,125,120,.30)');
dailyGrad.addColorStop(1, 'rgba(8,125,120,0)');
new Chart(dailyCtx, {
  type: 'line',
  data: { labels: dailyLabels, datasets: [{ label: 'Collection', data: dailyData, borderColor: '#087d78', backgroundColor: dailyGrad, fill: true, tension: .4, borderWidth: 3, pointBackgroundColor: '#eb941f', pointBorderColor: '#fffdf7', pointBorderWidth: 2, pointRadius: 4 }] },
  options: baseOpts()
});

const monthlyCtx = document.getElementById('chartMonthly').getContext('2d');
const monthlyGrad = monthlyCtx.createLinearGradient(0, 0, 0, 200);
monthlyGrad.addColorStop(0, '#087d78');
monthlyGrad.addColorStop(1, '#eb941f');
new Chart(monthlyCtx, {
  type: 'bar',
  data: { labels: monthLabels, datasets: [{ label: 'Collection', data: monthlyData, backgroundColor: monthlyGrad, borderRadius: 6, borderSkipped: false }] },
  options: baseOpts()
});
</script>

<?php page_end(); ?>
