<?php
require_once __DIR__ . '/layout.php';


$todaySummary = fetch_one("
    SELECT
        COALESCE(SUM(vehicle_count), 0) AS vehicle_observations,
        COALESCE(MAX(inbound_count), 0) AS inbound_total,
        COALESCE(MAX(outbound_count), 0) AS outbound_total,
        SUM(CASE
            WHEN LOWER(congestion_level) IN ('heavy', 'high', 'severe', 'critical')
            THEN 1 ELSE 0
        END) AS congestion_events,
        SUM(CASE
            WHEN LOWER(potential_collision) NOT IN ('none', 'no', 'false', '0', '')
            THEN 1 ELSE 0
        END) AS collision_events
    FROM camera_monitoring_logs
    WHERE DATE(recorded_at) = CURDATE()
") ?: [];

$violationSummary = fetch_one("
    SELECT
        COUNT(*) AS total_today,
        SUM(CASE WHEN LOWER(status) = 'paid' THEN 1 ELSE 0 END) AS paid_today,
        SUM(CASE WHEN LOWER(status) IN ('pending', 'unpaid', 'overdue') THEN 1 ELSE 0 END) AS unpaid_today
    FROM violations
    WHERE violation_date = CURDATE()
") ?: [];

$allPendingViolations = scalar("
    SELECT COUNT(*)
    FROM violations
    WHERE LOWER(status) IN ('pending', 'unpaid', 'overdue')
", 0);

$paymentSummary = fetch_one("
    SELECT
        COALESCE(SUM(amount_paid), 0) AS collection_today,
        COUNT(*) AS completed_payments_today
    FROM payments
    WHERE DATE(payment_date) = CURDATE()
      AND LOWER(payment_status) = 'completed'
") ?: [];

$alertSummary = fetch_one("
    SELECT
        COUNT(*) AS alerts_today,
        SUM(CASE WHEN LOWER(status) = 'active' THEN 1 ELSE 0 END) AS active_alerts
    FROM monitoring_alerts
    WHERE DATE(generated_at) = CURDATE()
") ?: [];

$latestMonitoring = fetch_one("
    SELECT
        l.vehicle_count,
        l.inbound_count,
        l.outbound_count,
        l.congestion_level,
        l.officer_presence,
        l.potential_collision,
        l.recorded_at,
        c.camera_name,
        c.location,
        c.status AS camera_status
    FROM camera_monitoring_logs l
    LEFT JOIN cameras c ON c.camera_id = l.camera_id
    ORDER BY l.recorded_at DESC
    LIMIT 1
");

$onlineCameras = scalar("
    SELECT COUNT(*)
    FROM cameras
    WHERE LOWER(status) = 'online'
", 0);

$totalCameras = scalar("SELECT COUNT(*) FROM cameras", 0);

$latestPrediction = fetch_one("
    SELECT
        prediction_type,
        predicted_result,
        location,
        violation_type,
        risk_level,
        confidence_score,
        prediction_date
    FROM ml_predictions
    ORDER BY prediction_date DESC
    LIMIT 1
");

$latestHotspot = fetch_one("
    SELECT
        cluster_label,
        location,
        violation_type,
        risk_level,
        frequency_count,
        generated_at
    FROM violation_hotspots
    ORDER BY
        FIELD(LOWER(risk_level), 'critical', 'high', 'medium', 'low'),
        frequency_count DESC,
        generated_at DESC
    LIMIT 1
");

$trendData = monthly_violation_counts();
$vehicleDist = vehicle_distribution();
$trafficVol = daily_traffic_volume();

$topViolationRows = fetch_all("
    SELECT violation_type, COUNT(*) AS total
    FROM violations
    WHERE YEAR(violation_date) = YEAR(CURDATE())
    GROUP BY violation_type
    ORDER BY total DESC
    LIMIT 6
");

$topViolationLabels = [];
$topViolationData = [];
foreach ($topViolationRows as $row) {
    $topViolationLabels[] = (string)$row['violation_type'];
    $topViolationData[] = (int)$row['total'];
}

$topLocationRows = fetch_all("
    SELECT violation_location, COUNT(*) AS total
    FROM violations
    WHERE YEAR(violation_date) = YEAR(CURDATE())
    GROUP BY violation_location
    ORDER BY total DESC
    LIMIT 5
");

$recentAlerts = fetch_all("
    SELECT alert_type, severity, message, status, generated_at
    FROM monitoring_alerts
    ORDER BY generated_at DESC
    LIMIT 5
");

$recentViolations = fetch_all("
    SELECT
        ticket_number,
        plate_number,
        violation_type,
        violation_location,
        penalty_amount,
        status,
        created_at
    FROM violations
    ORDER BY created_at DESC
    LIMIT 5
");

$recentPayments = fetch_all("
    SELECT
        p.amount_paid,
        p.payment_date,
        p.payment_status,
        v.ticket_number,
        v.plate_number
    FROM payments p
    JOIN violations v ON v.violation_id = p.violation_id
    ORDER BY p.payment_date DESC
    LIMIT 5
");

$recentMonitoringLogs = fetch_all("
    SELECT
        l.recorded_at,
        l.vehicle_count,
        l.inbound_count,
        l.outbound_count,
        l.congestion_level,
        l.officer_presence,
        l.potential_collision,
        c.camera_name,
        c.location
    FROM camera_monitoring_logs l
    LEFT JOIN cameras c ON c.camera_id = l.camera_id
    ORDER BY l.recorded_at DESC
    LIMIT 5
");

$vehiclesToday = (int)($todaySummary['vehicle_observations'] ?? 0);
$inboundToday = (int)($todaySummary['inbound_total'] ?? 0);
$outboundToday = (int)($todaySummary['outbound_total'] ?? 0);
$congestionEvents = (int)($todaySummary['congestion_events'] ?? 0);
$collisionEvents = (int)($todaySummary['collision_events'] ?? 0);

$violationsToday = (int)($violationSummary['total_today'] ?? 0);
$paidViolationsToday = (int)($violationSummary['paid_today'] ?? 0);
$unpaidViolationsToday = (int)($violationSummary['unpaid_today'] ?? 0);

$paymentsToday = (float)($paymentSummary['collection_today'] ?? 0);
$completedPaymentsToday = (int)($paymentSummary['completed_payments_today'] ?? 0);

$alertsToday = (int)($alertSummary['alerts_today'] ?? 0);
$activeAlerts = (int)($alertSummary['active_alerts'] ?? 0);

$currentVehicles = (int)($latestMonitoring['vehicle_count'] ?? 0);
$currentInbound = (int)($latestMonitoring['inbound_count'] ?? 0);
$currentOutbound = (int)($latestMonitoring['outbound_count'] ?? 0);

page_start('Dashboard', 'dashboard', 'Search violations, plates, locations...');
?>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <h3 class="page-title">Operations Dashboard</h3>
    <p class="page-sub">Live traffic operations, violations, collections, alerts, and prediction overview</p>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-light" href="<?= esc(app_url('reports.php')) ?>">
      <i class="bi bi-download me-1"></i>Reports
    </a>
    <a class="btn btn-primary" href="<?= esc(app_url('monitoring.php')) ?>">
      <i class="bi bi-camera-video me-1"></i>Open Monitoring
    </a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-icon tone-primary"><i class="bi bi-car-front"></i></div>
      <div class="stat-label">Vehicle Observations Today</div>
      <div class="stat-value"><?= num($vehiclesToday) ?></div>
      <small class="text-muted"><?= num($inboundToday) ?> inbound • <?= num($outboundToday) ?> outbound</small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-icon tone-warning"><i class="bi bi-cone-striped"></i></div>
      <div class="stat-label">Violations Today</div>
      <div class="stat-value"><?= num($violationsToday) ?></div>
      <small class="text-muted"><?= num($paidViolationsToday) ?> paid • <?= num($unpaidViolationsToday) ?> unpaid today</small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-icon tone-success"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-label">Collected Today</div>
      <div class="stat-value"><?= short_money($paymentsToday) ?></div>
      <small class="text-muted"><?= num($completedPaymentsToday) ?> completed payments</small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="stat-label">Active Alerts</div>
      <div class="stat-value"><?= num($activeAlerts) ?></div>
      <small class="text-muted"><?= num($alertsToday) ?> generated today</small>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3"><div class="section-card h-100"><small class="text-muted">Pending Violations</small><h4 class="mb-0 mt-1"><?= num($allPendingViolations) ?></h4></div></div>
  <div class="col-6 col-md-3"><div class="section-card h-100"><small class="text-muted">Congestion Events Today</small><h4 class="mb-0 mt-1"><?= num($congestionEvents) ?></h4></div></div>
  <div class="col-6 col-md-3"><div class="section-card h-100"><small class="text-muted">Potential Collisions Today</small><h4 class="mb-0 mt-1"><?= num($collisionEvents) ?></h4></div></div>
  <div class="col-6 col-md-3"><div class="section-card h-100"><small class="text-muted">Online Cameras</small><h4 class="mb-0 mt-1"><?= num($onlineCameras) ?> / <?= num($totalCameras) ?></h4></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-xl-8">
    <div class="section-card h-100">
      <div class="section-head">
        <div><h6>Current Monitoring Status</h6><small class="text-muted">Latest camera monitoring record</small></div>
        <span class="tag <?= tag_class($latestMonitoring['camera_status'] ?? 'offline') ?>"><?= esc($latestMonitoring['camera_status'] ?? 'offline') ?></span>
      </div>

      <?php if ($latestMonitoring): ?>
        <div class="metric-grid">
          <div class="mini-metric"><small>Camera</small><strong><?= esc($latestMonitoring['camera_name'] ?? 'Unnamed camera') ?></strong></div>
          <div class="mini-metric"><small>Location</small><strong><?= esc($latestMonitoring['location'] ?? 'Not set') ?></strong></div>
          <div class="mini-metric"><small>Visible Vehicles</small><strong><?= num($currentVehicles) ?></strong></div>
          <div class="mini-metric"><small>Inbound</small><strong><?= num($currentInbound) ?></strong></div>
          <div class="mini-metric"><small>Outbound</small><strong><?= num($currentOutbound) ?></strong></div>
          <div class="mini-metric"><small>Congestion</small><strong><?= esc($latestMonitoring['congestion_level'] ?? 'none') ?></strong></div>
          <div class="mini-metric"><small>Traffic Officer</small><strong><?= esc($latestMonitoring['officer_presence'] ?? 'unknown') ?></strong></div>
          <div class="mini-metric"><small>Potential Collision</small><strong><?= esc($latestMonitoring['potential_collision'] ?? 'none') ?></strong></div>
        </div>
        <div class="mt-3 small text-muted">Last updated: <?= esc($latestMonitoring['recorded_at'] ?? 'No timestamp') ?></div>
      <?php else: ?>
        <?php empty_state('No camera monitoring data is available yet. Start an analysis to populate this section.'); ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="section-card h-100">
      <div class="section-head"><h6>Latest Monitoring Records</h6><a href="<?= esc(app_url('monitoring.php')) ?>" class="small fw-semibold text-decoration-none">View monitoring</a></div>
      <?php if (!$recentMonitoringLogs): ?>
        <?php empty_state('No monitoring records found.'); ?>
      <?php else: ?>
        <?php foreach ($recentMonitoringLogs as $log): ?>
          <div class="border-bottom py-2">
            <div class="d-flex justify-content-between gap-2">
              <strong><?= esc($log['camera_name'] ?? 'Camera') ?></strong>
              <span class="tag <?= tag_class($log['congestion_level'] ?? 'none') ?>"><?= esc($log['congestion_level'] ?? 'none') ?></span>
            </div>
            <small class="text-muted"><?= esc($log['location'] ?? 'Unknown location') ?> • <?= num($log['vehicle_count'] ?? 0) ?> vehicles • <?= esc($log['recorded_at']) ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8"><div class="section-card"><div class="section-head"><div><h6>Monthly Violation Trends</h6><small class="text-muted">Current year</small></div></div><canvas id="trendChart" height="110"></canvas><div id="trendEmpty" class="mt-3"></div></div></div>
  <div class="col-lg-4"><div class="section-card h-100"><div class="section-head"><h6>Vehicle Type Distribution</h6></div><canvas id="vehicleChart" height="180"></canvas><div id="vehicleEmpty" class="mt-3"></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7"><div class="section-card"><div class="section-head"><h6>Daily Traffic Volume</h6><span class="tag tag-info">Today</span></div><canvas id="volumeChart" height="120"></canvas><div id="volumeEmpty" class="mt-3"></div></div></div>
  <div class="col-lg-5"><div class="section-card h-100"><div class="section-head"><h6>Top Violation Types</h6></div><canvas id="topViolationChart" height="150"></canvas><div id="topViolationEmpty" class="mt-3"></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head"><h6>Prediction Status</h6><span class="tag tag-info">ML Output</span></div>
      <?php if ($latestPrediction): ?>
        <div class="d-flex justify-content-between align-items-start gap-3">
          <div><h5 class="mb-1"><?= esc($latestPrediction['predicted_result']) ?></h5><small class="text-muted"><?= esc($latestPrediction['prediction_type'] ?? 'Prediction') ?><?php if (!empty($latestPrediction['location'])): ?> • <?= esc($latestPrediction['location']) ?><?php endif; ?></small></div>
          <span class="tag <?= tag_class($latestPrediction['risk_level']) ?>"><?= esc($latestPrediction['risk_level']) ?></span>
        </div>
        <div class="mt-3"><small class="text-muted d-block">Confidence</small><strong><?= number_format((float)$latestPrediction['confidence_score'] * 100, 2) ?>%</strong></div>
        <small class="text-muted d-block mt-2">Generated: <?= esc($latestPrediction['prediction_date']) ?></small>
      <?php else: ?>
        <?php empty_state('Prediction not generated yet. Random Forest results will appear here after training and saving to ml_predictions.'); ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head"><h6>Location Hotspot Status</h6><span class="tag tag-info">K-Means Output</span></div>
      <?php if ($latestHotspot): ?>
        <div class="d-flex justify-content-between align-items-start gap-3">
          <div><h5 class="mb-1"><?= esc($latestHotspot['location']) ?></h5><small class="text-muted"><?= esc($latestHotspot['violation_type'] ?? 'All violations') ?> • Frequency: <?= num($latestHotspot['frequency_count']) ?></small></div>
          <span class="tag <?= tag_class($latestHotspot['risk_level']) ?>"><?= esc($latestHotspot['risk_level']) ?></span>
        </div>
        <small class="text-muted d-block mt-3">Cluster: <?= esc($latestHotspot['cluster_label'] ?? 'Not assigned') ?> • Generated: <?= esc($latestHotspot['generated_at']) ?></small>
      <?php else: ?>
        <?php empty_state('No hotspot analysis is available. K-Means results will appear here after saving to violation_hotspots.'); ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="section-card">
      <div class="section-head"><h6>Top Violation Locations</h6><small class="text-muted">Current year</small></div>
      <?php if (!$topLocationRows): ?>
        <?php empty_state('No location-based violation data found.'); ?>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Rank</th><th>Location</th><th class="text-end">Recorded Violations</th></tr></thead>
            <tbody>
              <?php foreach ($topLocationRows as $index => $location): ?>
                <tr><td>#<?= $index + 1 ?></td><td><?= esc($location['violation_location']) ?></td><td class="text-end fw-semibold"><?= num($location['total']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-4">
    <div class="section-card h-100">
      <div class="section-head"><h6>Recent Alerts</h6><a href="<?= esc(app_url('alerts.php')) ?>" class="small fw-semibold text-decoration-none">View all</a></div>
      <?php if (!$recentAlerts): ?>
        <?php empty_state('No recent alerts found.'); ?>
      <?php else: ?>
        <?php foreach ($recentAlerts as $alert): ?>
          <div class="border-bottom py-2">
            <div class="d-flex justify-content-between gap-2"><strong><?= esc(ucfirst($alert['alert_type'])) ?></strong><span class="tag <?= tag_class($alert['severity']) ?>"><?= esc($alert['severity']) ?></span></div>
            <small class="text-muted d-block"><?= esc($alert['message']) ?></small><small class="text-muted"><?= esc($alert['generated_at']) ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="section-card h-100">
      <div class="section-head"><h6>Recent Violations</h6><a href="<?= esc(app_url('violations.php')) ?>" class="small fw-semibold text-decoration-none">View all</a></div>
      <?php if (!$recentViolations): ?>
        <?php empty_state('No violation records found.'); ?>
      <?php else: ?>
        <?php foreach ($recentViolations as $violation): ?>
          <div class="border-bottom py-2">
            <div class="d-flex justify-content-between gap-2"><strong><?= esc($violation['ticket_number']) ?></strong><span class="tag <?= tag_class($violation['status']) ?>"><?= esc($violation['status']) ?></span></div>
            <small class="text-muted d-block"><?= esc($violation['plate_number']) ?> • <?= esc($violation['violation_type']) ?></small>
            <small class="text-muted"><?= esc($violation['violation_location']) ?> • <?= peso($violation['penalty_amount']) ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="section-card h-100">
      <div class="section-head"><h6>Recent Payments</h6><a href="<?= esc(app_url('payments.php')) ?>" class="small fw-semibold text-decoration-none">View all</a></div>
      <?php if (!$recentPayments): ?>
        <?php empty_state('No payment records found.'); ?>
      <?php else: ?>
        <?php foreach ($recentPayments as $payment): ?>
          <div class="border-bottom py-2">
            <div class="d-flex justify-content-between gap-2"><strong><?= peso($payment['amount_paid']) ?></strong><span class="tag <?= tag_class($payment['payment_status']) ?>"><?= esc($payment['payment_status']) ?></span></div>
            <small class="text-muted d-block">Ticket <?= esc($payment['ticket_number']) ?> • <?= esc($payment['plate_number']) ?></small><small class="text-muted"><?= esc($payment['payment_date']) ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const months = <?= json_encode(month_labels()) ?>;
const trendData = <?= json_encode($trendData) ?>;
const vehicleLabels = <?= json_encode($vehicleDist['labels']) ?>;
const vehicleData = <?= json_encode($vehicleDist['data']) ?>;
const volumeLabels = <?= json_encode($trafficVol['labels']) ?>;
const volumeData = <?= json_encode($trafficVol['data']) ?>;
const topViolationLabels = <?= json_encode($topViolationLabels) ?>;
const topViolationData = <?= json_encode($topViolationData) ?>;

function showEmpty(id, message) {
  const target = document.getElementById(id);
  if (target) target.innerHTML = '<div class="empty-state">' + message + '</div>';
}

if (trendData.reduce((sum, value) => sum + Number(value || 0), 0) > 0) {
  new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {labels: months, datasets: [{label: 'Violations', data: trendData, borderColor: '#1e3a8a', backgroundColor: 'rgba(30,58,138,.15)', fill: true, tension: .4, borderWidth: 2, pointRadius: 2}]},
    options: {responsive: true, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {precision: 0}}}}
  });
} else showEmpty('trendEmpty', 'No violation trend data yet.');

if (vehicleData.length > 0 && vehicleData.reduce((sum, value) => sum + Number(value || 0), 0) > 0) {
  new Chart(document.getElementById('vehicleChart'), {
    type: 'doughnut',
    data: {labels: vehicleLabels, datasets: [{data: vehicleData, borderWidth: 0}]},
    options: {responsive: true, cutout: '68%', plugins: {legend: {position: 'bottom'}}}
  });
} else showEmpty('vehicleEmpty', 'No vehicle distribution data yet.');

if (volumeData.length > 0 && volumeData.reduce((sum, value) => sum + Number(value || 0), 0) > 0) {
  new Chart(document.getElementById('volumeChart'), {
    type: 'bar',
    data: {labels: volumeLabels, datasets: [{label: 'Vehicles', data: volumeData, backgroundColor: '#16a34a', borderRadius: 6}]},
    options: {responsive: true, plugins: {legend: {display: false}}, scales: {y: {beginAtZero: true, ticks: {precision: 0}}}}
  });
} else showEmpty('volumeEmpty', 'No traffic volume data for today.');

if (topViolationData.length > 0 && topViolationData.reduce((sum, value) => sum + Number(value || 0), 0) > 0) {
  new Chart(document.getElementById('topViolationChart'), {
    type: 'bar',
    data: {labels: topViolationLabels, datasets: [{label: 'Recorded Violations', data: topViolationData, backgroundColor: '#f59e0b', borderRadius: 6}]},
    options: {responsive: true, indexAxis: 'y', plugins: {legend: {display: false}}, scales: {x: {beginAtZero: true, ticks: {precision: 0}}}}
  });
} else showEmpty('topViolationEmpty', 'No violation type data available.');
</script>

<?php page_end(false); ?>
