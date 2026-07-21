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

page_start('Dashboard', 'dashboard', 'Search violations, receipts, plates...', 'Overview of collections and violation payments', false);
?>

<style id="treasurer-dashboard-theme">
body.admin-dashboard {
  --treasury-navy-950: #060f1e;
  --treasury-navy-900: #0a1a30;
  --treasury-navy-800: #0f2544;
  --treasury-border: rgba(255,255,255,.10);
  --treasury-blue: #38bdf8;
  --treasury-cyan: #4fc3f7;
  --treasury-soft: #c9d8ea;
  font-family: 'Poppins', sans-serif;
  background:
    radial-gradient(circle at 12% 8%, rgba(56,189,248,.09), transparent 28%),
    radial-gradient(circle at 88% 82%, rgba(37,99,235,.10), transparent 34%),
    linear-gradient(160deg, var(--treasury-navy-950), var(--treasury-navy-900) 48%, var(--treasury-navy-800));
  color: #fff;
}
.treasurer-page-heading { display: none; }
.topbar { background:var(--treasury-navy-900) !important; border-bottom:1px solid var(--treasury-border) !important; box-shadow:none; }
.topbar .search .form-control { color:#fff; background:rgba(255,255,255,.06); border:1px solid var(--treasury-border); }
.topbar .search .form-control::placeholder { color:#94a3b8; }
.topbar .search i, .topbar #liveClock { color:var(--treasury-soft) !important; }
.topbar .btn-light { color:#fff; background:rgba(255,255,255,.06); border:1px solid var(--treasury-border); }
.topbar .btn-light:hover { background:rgba(255,255,255,.12); }
.topbar .role-pill { color:#94a3b8; }
.treasury-hero { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap; margin-bottom:24px; }
.treasury-eyebrow { display:inline-flex; align-items:center; gap:7px; color:var(--treasury-cyan); font-size:.72rem; font-weight:700; letter-spacing:.09em; text-transform:uppercase; margin-bottom:8px; }
.treasury-hero h1 { color:#fff; font-size:1.8rem; font-weight:800; margin:0 0 6px; }
.treasury-hero p { color:var(--treasury-soft); margin:0; }
.treasury-online { display:inline-flex; align-items:center; gap:8px; padding:8px 14px; border:1px solid rgba(52,211,153,.3); border-radius:999px; background:rgba(52,211,153,.12); color:#34d399; font-size:.76rem; font-weight:600; }
.treasury-online span { width:8px; height:8px; border-radius:50%; background:#34d399; box-shadow:0 0 0 3px rgba(52,211,153,.22); }
.treasury-actions { display:flex; gap:10px; flex-wrap:wrap; }
.treasury-actions .btn { border-radius:10px; padding:.58rem .9rem; font-weight:600; }
.treasury-actions .btn-light { color:#fff; background:rgba(255,255,255,.06); border:1px solid var(--treasury-border); }
.treasury-actions .btn-primary { border:0; background:linear-gradient(90deg,#2563eb,var(--treasury-cyan)); box-shadow:0 12px 26px rgba(37,99,235,.28); }
.stat-card, .section-card { color:#fff; background:rgba(255,255,255,.035); border:1px solid var(--treasury-border); border-radius:18px; box-shadow:0 14px 30px rgba(0,0,0,.25); backdrop-filter:blur(8px); }
.stat-card { padding:20px; gap:.55rem; overflow:hidden; position:relative; }
.stat-card::after { content:""; position:absolute; width:85px; height:85px; right:-30px; top:-35px; border-radius:50%; background:rgba(56,189,248,.07); }
.stat-card .stat-icon { width:44px; height:44px; border-radius:12px; margin-bottom:7px; }
.stat-card .stat-label { color:var(--treasury-soft); }
.stat-card .stat-value { color:#fff; font-size:1.75rem; font-weight:800; }
.stat-card .stat-trend { display:inline-flex; align-items:center; width:max-content; padding:4px 8px; border-radius:999px; color:#34d399; background:rgba(52,211,153,.10); }
.stat-card .stat-trend.down { color:#f87171; background:rgba(248,113,113,.10); }
.tone-navy { background:rgba(56,189,248,.14); color:var(--treasury-cyan); }
.tone-teal { background:rgba(45,212,191,.14); color:#2dd4bf; }
.section-card { padding:20px; }
.section-head h6 { color:#fff; font-weight:700; }
.section-card small, .section-card .text-muted { color:var(--treasury-soft) !important; }
.section-head a { color:var(--treasury-cyan) !important; }
.table { --bs-table-bg:transparent; --bs-table-color:var(--treasury-soft); --bs-table-border-color:var(--treasury-border); color:var(--treasury-soft); }
.table thead th { color:#94a3b8; border-color:var(--treasury-border); }
.table tbody td { color:var(--treasury-soft); border-color:rgba(255,255,255,.07); }
.table tbody tr:hover td { background:rgba(56,189,248,.05) !important; color:#fff; }
.table .fw-semibold { color:#fff; }
.icon-link { display:inline-grid; place-items:center; width:30px; height:30px; border:1px solid var(--treasury-border); border-radius:8px; color:var(--treasury-cyan); margin-left:4px; }
.icon-link:hover { color:#fff; background:rgba(56,189,248,.14); }
.empty-state { color:var(--treasury-soft) !important; background:rgba(255,255,255,.025) !important; border-color:var(--treasury-border) !important; }
.tag { border:1px solid var(--treasury-border); }
@media (max-width:767.98px) {
  .treasury-hero h1 { font-size:1.5rem; }
  .treasury-actions { width:100%; }
  .treasury-actions .btn { flex:1; }
}
</style>

<div class="treasury-hero">
  <div>
    <span class="treasury-eyebrow"><i class="bi bi-bank2"></i> TRAVIS TREASURY CENTER</span>
    <h1>Collection Dashboard</h1>
    <p>Monitor violation payments, daily collections, and outstanding balances.</p>
  </div>
  <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="treasury-online"><span></span>Collection system online</span>
    <div class="treasury-actions">
      <a class="btn btn-light" href="<?= esc(app_url('reports.php')) ?>"><i class="bi bi-file-earmark-bar-graph me-1"></i> Reports</a>
      <a class="btn btn-primary" href="<?= esc(app_url('payments.php')) ?>"><i class="bi bi-credit-card me-1"></i> Process Payment</a>
    </div>
  </div>
</div>

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

Chart.defaults.font.family = "'Poppins', sans-serif";
Chart.defaults.color = '#c9d8ea';

function baseOpts() {
  return {
    responsive: true, maintainAspectRatio: false,
    plugins: { legend: { display: false }, tooltip: {
      backgroundColor: '#0f1f47', padding: 12, cornerRadius: 8,
      callbacks: { label: c => '₱' + c.parsed.y.toLocaleString() }
    }},
    scales: {
      y: { grid: { color: 'rgba(148,163,184,.15)' }, ticks: { callback: v => '₱' + (v / 1000) + 'k', font: { size: 11 } } },
      x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } }
    }
  };
}

new Chart(document.getElementById('chartStatus'), {
  type: 'doughnut',
  data: { labels: statusLabels, datasets: [{ data: statusData, backgroundColor: ['#16a34a', '#f59e0b', '#dc2626', '#94a3b8'], borderWidth: 0, hoverOffset: 10 }] },
  options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { position: 'bottom', labels: { color: '#c9d8ea', usePointStyle: true, padding: 16, font: { size: 12 } } } } }
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
