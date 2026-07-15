<?php
require_once __DIR__ . '/layout.php';

$totalViolations = scalar("SELECT COUNT(*) FROM violations", 0);
$pendingPayments = scalar("SELECT COUNT(*) FROM violations WHERE status IN ('pending', 'overdue')", 0);
$paidViolations = scalar("SELECT COUNT(*) FROM violations WHERE status = 'paid'", 0);

$todaysCollections = scalar("
    SELECT COALESCE(SUM(amount_paid), 0)
    FROM payments
    WHERE payment_status = 'completed' AND DATE(payment_date) = CURDATE()
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
    ORDER BY p.payment_date DESC, p.payment_id DESC
    LIMIT 6
");

$pendingList = fetch_all("
    SELECT violation_id, ticket_number, plate_number, violation_type, penalty_amount, violation_date
    FROM violations
    WHERE status IN ('pending', 'overdue')
    ORDER BY CASE WHEN status = 'overdue' THEN 0 ELSE 1 END, violation_date ASC
    LIMIT 6
");

$statusBreakdown = payment_status_breakdown();
$dailyTrend = daily_collection_trend(7);
$monthlyTotals = monthly_collection_totals();

page_start('Dashboard', 'dashboard', 'Search violations, receipts, plates...', 'Overview of collections and violation payments');
?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl">
    <div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-cone-striped"></i></div>
      <div class="stat-label">Total Violations</div><div class="stat-value"><?= num($totalViolations) ?></div>
      <div class="stat-trend <?= $totalViolationsTrend['direction'] === 'down' ? 'down' : '' ?>"><?= esc($totalViolationsTrend['label']) ?> this week</div></div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card"><div class="stat-icon tone-warning"><i class="bi bi-hourglass-split"></i></div>
      <div class="stat-label">Pending Payments</div><div class="stat-value"><?= num($pendingPayments) ?></div>
      <div class="stat-trend <?= $pendingPaymentsTrend['direction'] === 'down' ? 'down' : '' ?>"><?= esc($pendingPaymentsTrend['label']) ?> from last week</div></div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card"><div class="stat-icon tone-success"><i class="bi bi-check2-circle"></i></div>
      <div class="stat-label">Paid Violations</div><div class="stat-value"><?= num($paidViolations) ?></div>
      <div class="stat-trend <?= $paidViolationsTrend['direction'] === 'down' ? 'down' : '' ?>"><?= esc($paidViolationsTrend['label']) ?> this month</div></div>
  </div>
  <div class="col-sm-6 col-xl">
    <div class="stat-card"><div class="stat-icon tone-navy"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-label">Today's Collections</div><div class="stat-value"><?= short_money($todaysCollections) ?></div>
      <div class="stat-trend <?= $todaysCollectionsTrend['direction'] === 'down' ? 'down' : '' ?>"><?= esc($todaysCollectionsTrend['label']) ?> vs yesterday</div></div>
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
        <a class="small fw-semibold text-decoration-none" style="color:var(--teal-dark)" href="<?= esc(app_url('history.php')) ?>">View all</a>
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
                  <td class="fw-semibold"><?= esc(payment_reference((int)$p['payment_id'])) ?></td>
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
const statusLabels = ['Paid', 'Pending', 'Overdue', 'Cancelled'];
const statusData = <?= json_encode(array_values($statusBreakdown)) ?>;
const dailyLabels = <?= json_encode($dailyTrend['labels']) ?>;
const dailyData = <?= json_encode($dailyTrend['data']) ?>;
const monthLabels = <?= json_encode(month_labels()) ?>;
const monthlyData = <?= json_encode($monthlyTotals) ?>;

Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = '#64748b';

function baseOpts() {
  return {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: {
      backgroundColor: '#0f1f47', padding: 12, cornerRadius: 8,
      callbacks: { label: c => '₱' + c.parsed.y.toLocaleString() }
    }},
    scales: {
      y: { grid: { color: 'rgba(148,163,184,.15)' }, ticks: { callback: v => '₱' + (v / 1000) + 'k', font: { size: 11 } } },
      x: { grid: { display: false }, ticks: { font: { size: 11 } } }
    }
  };
}

new Chart(document.getElementById('chartStatus'), {
  type: 'doughnut',
  data: { labels: statusLabels, datasets: [{ data: statusData, backgroundColor: ['#16a34a', '#f59e0b', '#dc2626', '#94a3b8'], borderWidth: 0, hoverOffset: 10 }] },
  options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { size: 12 } } } } }
});

const dailyCtx = document.getElementById('chartDaily').getContext('2d');
const dailyGrad = dailyCtx.createLinearGradient(0, 0, 0, 200);
dailyGrad.addColorStop(0, 'rgba(20,184,166,.35)');
dailyGrad.addColorStop(1, 'rgba(20,184,166,0)');
new Chart(dailyCtx, {
  type: 'line',
  data: { labels: dailyLabels, datasets: [{ label: 'Collection', data: dailyData, borderColor: '#14b8a6', backgroundColor: dailyGrad, fill: true, tension: .4, borderWidth: 3, pointBackgroundColor: '#14b8a6', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 4 }] },
  options: baseOpts()
});

const monthlyCtx = document.getElementById('chartMonthly').getContext('2d');
const monthlyGrad = monthlyCtx.createLinearGradient(0, 0, 0, 200);
monthlyGrad.addColorStop(0, '#0d9488');
monthlyGrad.addColorStop(1, '#14b8a6');
new Chart(monthlyCtx, {
  type: 'bar',
  data: { labels: monthLabels, datasets: [{ label: 'Collection', data: monthlyData, backgroundColor: monthlyGrad, borderRadius: 6, borderSkipped: false }] },
  options: baseOpts()
});
</script>

<?php page_end(); ?>