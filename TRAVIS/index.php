<?php
require_once __DIR__ . '/layout.php';

$vehiclesToday = scalar("SELECT COALESCE(SUM(vehicle_count),0) FROM camera_monitoring_logs WHERE DATE(recorded_at)=CURDATE()", 0);
$currentVehicles = scalar("SELECT vehicle_count FROM camera_monitoring_logs ORDER BY recorded_at DESC LIMIT 1", 0);
$violationsToday = scalar("SELECT COUNT(*) FROM violations WHERE violation_date=CURDATE()", 0);
$paymentsToday = scalar("SELECT COALESCE(SUM(amount_paid),0) FROM payments WHERE DATE(payment_date)=CURDATE() AND payment_status='completed'", 0);
$activeAlerts = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE status='active'", 0);
$cameraStatus = fetch_one("SELECT status FROM cameras ORDER BY camera_id ASC LIMIT 1");
$latestLog = fetch_one("SELECT c.location, l.congestion_level, l.officer_presence, l.potential_collision, l.recorded_at FROM camera_monitoring_logs l JOIN cameras c ON c.camera_id=l.camera_id ORDER BY l.recorded_at DESC LIMIT 1");
$latestPrediction = fetch_one("SELECT predicted_result, risk_level, confidence_score, prediction_date FROM ml_predictions ORDER BY prediction_date DESC LIMIT 1");
$latestHotspot = fetch_one("SELECT location, risk_level, frequency_count FROM violation_hotspots ORDER BY generated_at DESC LIMIT 1");
$trendData = monthly_violation_counts();
$vehicleDist = vehicle_distribution();
$trafficVol = daily_traffic_volume();
$recentAlerts = fetch_all("SELECT alert_type, severity, message, generated_at FROM monitoring_alerts ORDER BY generated_at DESC LIMIT 4");
$recentViolations = fetch_all("SELECT ticket_number, plate_number, violation_type, created_at FROM violations ORDER BY created_at DESC LIMIT 3");
$recentPayments = fetch_all("SELECT p.amount_paid, p.payment_date, v.ticket_number FROM payments p JOIN violations v ON v.violation_id=p.violation_id ORDER BY p.payment_date DESC LIMIT 3");

page_start('Dashboard', 'dashboard', 'Search violations, plates, officers...');
?>
<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div><h3 class="page-title">Operations Dashboard</h3><p class="page-sub">Database-driven overview of traffic monitoring, violation analytics, and enforcement</p></div>
  <div class="d-flex gap-2"><a class="btn btn-light" href="reports.php"><i class="bi bi-download me-1"></i>Reports</a><a class="btn btn-primary" href="monitoring.php"><i class="bi bi-camera-video me-1"></i>Open Monitoring</a></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="d-flex justify-content-between"><div class="stat-icon tone-primary"><i class="bi bi-car-front"></i></div></div><div class="stat-label">Vehicles Today</div><div class="stat-value"><?= num($vehiclesToday) ?></div><small class="text-muted">from camera monitoring logs</small></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="d-flex justify-content-between"><div class="stat-icon tone-warning"><i class="bi bi-cone-striped"></i></div></div><div class="stat-label">Violations Today</div><div class="stat-value"><?= num($violationsToday) ?></div><small class="text-muted">from treasury records</small></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="d-flex justify-content-between"><div class="stat-icon tone-success"><i class="bi bi-cash-stack"></i></div></div><div class="stat-label">Collected Today</div><div class="stat-value"><?= short_money($paymentsToday) ?></div><small class="text-muted">completed payments</small></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="d-flex justify-content-between"><div class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></div></div><div class="stat-label">Active CV Alerts</div><div class="stat-value"><?= num($activeAlerts) ?></div><small class="text-muted">congestion/collision/system</small></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8"><div class="section-card"><div class="section-head"><div><h6>Monthly Violation Trends</h6><small class="text-muted">Current year</small></div></div><canvas id="trendChart" height="110"></canvas><div id="trendEmpty" class="mt-3"></div></div></div>
  <div class="col-lg-4"><div class="section-card h-100"><div class="section-head"><h6>Vehicle Type Distribution</h6></div><canvas id="vehicleChart" height="180"></canvas><div id="vehicleEmpty" class="mt-3"></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7"><div class="section-card"><div class="section-head"><h6>Daily Traffic Volume</h6><span class="tag tag-info">Today</span></div><canvas id="volumeChart" height="120"></canvas><div id="volumeEmpty" class="mt-3"></div></div></div>
  <div class="col-lg-5"><div class="section-card h-100"><div class="section-head"><h6>Current Monitoring Status</h6></div>
    <div class="metric-grid">
      <div class="mini-metric"><small>Camera</small><strong><?= esc($cameraStatus['status'] ?? 'offline') ?></strong></div>
      <div class="mini-metric"><small>Current Vehicles</small><strong><?= num($currentVehicles) ?></strong></div>
      <div class="mini-metric"><small>Congestion</small><strong><?= esc($latestLog['congestion_level'] ?? 'none') ?></strong></div>
      <div class="mini-metric"><small>Officer</small><strong><?= esc($latestLog['officer_presence'] ?? 'unknown') ?></strong></div>
      <div class="mini-metric"><small>Collision</small><strong><?= esc($latestLog['potential_collision'] ?? 'none') ?></strong></div>
      <div class="mini-metric"><small>Location</small><strong><?= esc($latestLog['location'] ?? 'Not set') ?></strong></div>
    </div>
  </div></div>
</div>

<div class="row g-3">
  <div class="col-lg-6"><div class="section-card h-100"><div class="section-head"><h6>Prediction Status</h6><a href="analytics.php" class="small fw-semibold text-decoration-none">View analytics</a></div>
    <?php if ($latestPrediction): ?>
      <div class="d-flex justify-content-between align-items-center"><div><h5><?= esc($latestPrediction['predicted_result']) ?></h5><small class="text-muted">Generated <?= esc($latestPrediction['prediction_date']) ?></small></div><span class="tag <?= tag_class($latestPrediction['risk_level']) ?>"><?= esc($latestPrediction['risk_level']) ?></span></div>
      <p class="mb-0 mt-2 text-muted small">Confidence: <?= number_format((float)$latestPrediction['confidence_score'] * 100, 2) ?>%</p>
    <?php else: empty_state('Prediction not generated yet. The Random Forest result will appear here after training and saving to ml_predictions.'); endif; ?>
  </div></div>
  <div class="col-lg-6"><div class="section-card h-100"><div class="section-head"><h6>Location Hotspot Status</h6><a href="analytics.php" class="small fw-semibold text-decoration-none">View hotspots</a></div>
    <?php if ($latestHotspot): ?>
      <div class="d-flex justify-content-between align-items-center"><div><h5><?= esc($latestHotspot['location']) ?></h5><small class="text-muted">Frequency: <?= num($latestHotspot['frequency_count']) ?></small></div><span class="tag <?= tag_class($latestHotspot['risk_level']) ?>"><?= esc($latestHotspot['risk_level']) ?></span></div>
    <?php else: empty_state('No hotspot analysis available. K-Means results will appear here after saving to violation_hotspots.'); endif; ?>
  </div></div>
</div>

<div class="row g-3 mt-1">
  <div class="col-lg-4"><div class="section-card h-100"><div class="section-head"><h6>Recent Alerts</h6></div><?php if (!$recentAlerts) empty_state('No recent alerts found.'); else foreach ($recentAlerts as $a): ?><div class="border-bottom py-2"><strong><?= esc(ucfirst($a['alert_type'])) ?></strong><br><small class="text-muted"><?= esc($a['message']) ?> • <?= esc($a['generated_at']) ?></small></div><?php endforeach; ?></div></div>
  <div class="col-lg-4"><div class="section-card h-100"><div class="section-head"><h6>Recent Violations</h6></div><?php if (!$recentViolations) empty_state('No violation records found.'); else foreach ($recentViolations as $v): ?><div class="border-bottom py-2"><strong><?= esc($v['ticket_number']) ?></strong><br><small class="text-muted"><?= esc($v['plate_number']) ?> • <?= esc($v['violation_type']) ?></small></div><?php endforeach; ?></div></div>
  <div class="col-lg-4"><div class="section-card h-100"><div class="section-head"><h6>Recent Payments</h6></div><?php if (!$recentPayments) empty_state('No payment records found.'); else foreach ($recentPayments as $p): ?><div class="border-bottom py-2"><strong><?= peso($p['amount_paid']) ?></strong><br><small class="text-muted">Ticket <?= esc($p['ticket_number']) ?> • <?= esc($p['payment_date']) ?></small></div><?php endforeach; ?></div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const months = <?= json_encode(month_labels()) ?>;
const trendData = <?= json_encode($trendData) ?>;
const vehicleLabels = <?= json_encode($vehicleDist['labels']) ?>;
const vehicleData = <?= json_encode($vehicleDist['data']) ?>;
const volumeLabels = <?= json_encode($trafficVol['labels']) ?>;
const volumeData = <?= json_encode($trafficVol['data']) ?>;
function empty(id, msg){document.getElementById(id).innerHTML = '<div class="empty-state">'+msg+'</div>';}
if (trendData.reduce((a,b)=>a+b,0) > 0) new Chart(document.getElementById('trendChart'), {type:'line',data:{labels:months,datasets:[{label:'Violations',data:trendData,borderColor:'#1e3a8a',backgroundColor:'rgba(30,58,138,.15)',fill:true,tension:.4,borderWidth:2,pointRadius:2}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}}); else empty('trendEmpty','No violation trend data yet.');
if (vehicleData.length > 0) new Chart(document.getElementById('vehicleChart'), {type:'doughnut',data:{labels:vehicleLabels,datasets:[{data:vehicleData,borderWidth:0}]},options:{cutout:'68%',plugins:{legend:{position:'bottom'}}}}); else empty('vehicleEmpty','No vehicle distribution data yet.');
if (volumeData.length > 0) new Chart(document.getElementById('volumeChart'), {type:'bar',data:{labels:volumeLabels,datasets:[{label:'Vehicles',data:volumeData,backgroundColor:'#16a34a',borderRadius:6}]},options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}}); else empty('volumeEmpty','No traffic volume data for today.');
</script>
<?php page_end(false); ?>
