<style id="travis-ai-v6-overrides">
/* V6 Compact Hotspot Cards */
.hotspot-risk-card{
    height:290px !important;
    display:flex !important;
    flex-direction:column !important;
}

.hotspot-card-head{
    flex:0 0 auto;
    margin-bottom:10px !important;
    padding-bottom:10px;
    border-bottom:1px solid #edf2f7;
}

.hotspot-card-list{
    flex:1 1 auto !important;
    overflow-y:auto !important;
    overflow-x:hidden;
    padding-right:6px;
    margin-top:4px;
}

.hotspot-card-list li{
    padding:8px 10px !important;
    margin-bottom:0;
}

.hotspot-card-list::-webkit-scrollbar{
    width:7px;
}

.hotspot-card-list::-webkit-scrollbar-track{
    background:#eef3f8;
    border-radius:20px;
}

.hotspot-card-list::-webkit-scrollbar-thumb{
    background:#9bb5d3;
    border-radius:20px;
}

.hotspot-card-list::-webkit-scrollbar-thumb:hover{
    background:#6e95be;
}

.hotspot-list-copy strong{
    font-size:.90rem;
}

.hotspot-list-copy small{
    font-size:.74rem;
}

.hotspot-card-head strong{
    font-size:1.5rem !important;
}

.hotspot-card-head .hotspot-card-label{
    font-size:.75rem;
}

@media (max-width:991px){
    .hotspot-risk-card{
        height:260px !important;
    }
}
</style>


<style id="travis-ai-internal-css">
/* ==== TRAVIS AI V5 ==== */
.ai-decision-card{background:#fff;border:1px solid #dbe4ee;border-radius:22px;padding:24px;box-shadow:0 10px 28px rgba(0,0,0,.06)}
.ai-kpi-card,.hotspot-risk-card{background:#fff;border:1px solid #dbe4ee;border-radius:18px;padding:20px;box-shadow:0 8px 20px rgba(0,0,0,.05);height:100%}
.ai-card-header,.hotspot-card-head{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.ai-card-icon,.hotspot-card-icon{width:48px;height:48px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:#eef5ff;color:#2563eb;font-size:20px}
.hotspot-card-high{border-top:6px solid #dc2626}
.hotspot-card-medium{border-top:6px solid #f59e0b}
.hotspot-card-low{border-top:6px solid #16a34a}
.hotspot-card-highlighted{border-color:#2563eb!important;box-shadow:0 0 0 3px rgba(37,99,235,.12),0 14px 28px rgba(37,99,235,.18)}
.deployment-kpi{display:flex;justify-content:space-between;align-items:center;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:12px 15px;margin-bottom:10px}
.hotspot-card-list{list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:10px}
.hotspot-card-list li{display:flex;gap:10px;background:#f8fafc;border:1px solid #edf2f7;border-radius:12px;padding:10px}
.hotspot-list-rank{width:26px;height:26px;border-radius:8px;background:#2563eb;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:bold}
.hotspot-list-copy{display:flex;flex-direction:column}
.hotspot-list-copy strong{font-size:.86rem}
.hotspot-list-copy small{color:#64748b}
.ai-action-grid{list-style:none;padding:0;margin:0;display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.ai-action-grid li{display:flex;gap:10px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:14px}
.ai-monthly-body{display:grid;grid-template-columns:1fr;gap:16px}
@media(min-width:992px){.ai-monthly-body{grid-template-columns:1fr 1fr}}
</style>

<?php
require_once __DIR__ . '/layout.php';

$todaySummary = fetch_one("
    SELECT
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

$chartData = violation_chart_data(
    isset($_GET['chart_range']) ? (string) $_GET['chart_range'] : null,
    isset($_GET['chart_status']) ? (string) $_GET['chart_status'] : null,
    6
);
$trendData = $chartData['trend'];
$topViolationLabels = $chartData['type_labels'];
$topViolationData = $chartData['type_data'];

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

$inboundToday = (int) ($todaySummary['inbound_total'] ?? 0);
$outboundToday = (int) ($todaySummary['outbound_total'] ?? 0);
$vehiclesToday = $inboundToday + $outboundToday;
$congestionEvents = (int) ($todaySummary['congestion_events'] ?? 0);
$collisionEvents = (int) ($todaySummary['collision_events'] ?? 0);

$violationsToday = (int) ($violationSummary['total_today'] ?? 0);
$paidViolationsToday = (int) ($violationSummary['paid_today'] ?? 0);
$unpaidViolationsToday = (int) ($violationSummary['unpaid_today'] ?? 0);

$paymentsToday = (float) ($paymentSummary['collection_today'] ?? 0);
$completedPaymentsToday = (int) ($paymentSummary['completed_payments_today'] ?? 0);

$alertsToday = (int) ($alertSummary['alerts_today'] ?? 0);
$activeAlerts = (int) ($alertSummary['active_alerts'] ?? 0);

$currentVehicles = (int) ($latestMonitoring['vehicle_count'] ?? 0);
$currentInbound = (int) ($latestMonitoring['inbound_count'] ?? 0);
$currentOutbound = (int) ($latestMonitoring['outbound_count'] ?? 0);

page_start('Dashboard', 'dashboard', 'Search violations, plates, locations...');
?>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <div class="dashboard-title-row">
      <div>
        <span class="dashboard-eyebrow">TRAVIS COMMAND CENTER</span>
        <h3 class="page-title">Operations Dashboard</h3>
        <p class="page-sub">Real-time traffic monitoring, predictive insights, and hotspot intelligence.</p>
      </div>
      <span class="system-online-badge"><span class="system-online-dot"></span>AI Services Online</span>
    </div>
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

<!-- Main statistics -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card dashboard-stat-card h-100">
      <div class="stat-icon tone-primary"><i class="bi bi-car-front"></i></div>
      <div class="stat-label">Vehicles Counted Today</div>
      <div class="stat-value"><?= num($vehiclesToday) ?></div>
      <small class="text-muted">
        <?= num($inboundToday) ?> inbound • <?= num($outboundToday) ?> outbound
      </small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card dashboard-stat-card h-100">
      <div class="stat-icon tone-warning"><i class="bi bi-cone-striped"></i></div>
      <div class="stat-label">Violations Today</div>
      <div class="stat-value"><?= num($violationsToday) ?></div>
      <small class="text-muted">
        <?= num($paidViolationsToday) ?> paid • <?= num($unpaidViolationsToday) ?> unpaid
      </small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card dashboard-stat-card h-100">
      <div class="stat-icon tone-success"><i class="bi bi-cash-stack"></i></div>
      <div class="stat-label">Collected Today</div>
      <div class="stat-value"><?= short_money($paymentsToday) ?></div>
      <small class="text-muted"><?= num($completedPaymentsToday) ?> completed payments</small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card dashboard-stat-card h-100">
      <div class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="stat-label">Active Alerts</div>
      <div class="stat-value"><?= num($activeAlerts) ?></div>
      <small class="text-muted"><?= num($alertsToday) ?> generated today</small>
    </div>
  </div>
</div>

<!-- AI Decision Support -->
<div class="section-card ai-decision-card mb-4" id="aiDecisionCard">
  <div class="section-head ai-section-head">
    <div>
      <span class="ai-kicker"><i class="bi bi-cpu me-1"></i>TRAVIS AI ENGINE</span>
      <h5 class="mb-1">AI Decision Support Center</h5>
      <small class="text-muted">Random Forest monthly risk prediction combined with K-Means hotspot intelligence</small>
    </div>

    <div class="d-flex flex-wrap align-items-end gap-2">
      <div>
        <label class="form-label small text-muted mb-1" for="predictionMonth">Forecast month</label>
        <select class="form-select form-select-sm" id="predictionMonth" aria-label="Forecast month"></select>
      </div>
      <div>
        <label class="form-label small text-muted mb-1" for="predictionYear">Year</label>
        <select class="form-select form-select-sm" id="predictionYear" aria-label="Forecast year"></select>
      </div>
      <button class="btn btn-sm btn-light" type="button" id="refreshPredictionBtn">
        <i class="bi bi-stars me-1"></i>Predict
      </button>
    </div>
  </div>

  <div id="aiPredictionLoading" class="ai-loading-state">
    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
    Loading monthly risk prediction...
  </div>

  <div id="aiPredictionError" class="alert alert-danger d-none mb-0" role="alert"></div>

  <div id="aiPredictionContent" class="d-none">
    <div class="row g-4 ai-top-row">
      <div class="col-lg-7">
        <div class="ai-kpi-card ai-monthly-card h-100">
          <div class="ai-card-header">
            <span class="ai-card-icon ai-icon-blue">
              <i class="bi bi-graph-up-arrow"></i>
            </span>
            <div>
              <h6>Monthly Risk Prediction</h6>
              <small>Random Forest risk forecast</small>
            </div>
          </div>

          <div class="ai-monthly-body">
            <div>
              <div class="ai-label">Expected monthly risk</div>
              <div class="ai-risk-badge" id="aiRiskBadge">—</div>
              <div class="ai-period" id="aiPredictionPeriod">—</div>
            </div>

            <div class="ai-confidence-card">
              <div class="d-flex justify-content-between align-items-center gap-3">
                <span>Prediction confidence</span>
                <strong id="aiConfidenceText">—</strong>
              </div>
              <div class="progress ai-confidence-progress">
                <div
                  class="progress-bar"
                  id="aiConfidenceBar"
                  role="progressbar"
                  style="width:0%"
                  aria-valuemin="0"
                  aria-valuemax="100"
                ></div>
              </div>
            </div>
          </div>

          <p class="ai-model-note mb-0">
            This prediction is based on historical TMO records and should be reviewed together with current traffic conditions.
          </p>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="ai-kpi-card ai-deployment-card h-100">
          <div class="ai-card-header">
            <span class="ai-card-icon ai-icon-teal">
              <i class="bi bi-people"></i>
            </span>
            <div>
              <h6>Deployment Guidance</h6>
              <small>Recommended operational resources</small>
            </div>
          </div>

          <div class="deployment-kpi-grid">
            <div class="deployment-kpi">
              <small>Priority</small>
              <strong id="aiDeploymentPriority">—</strong>
            </div>
            <div class="deployment-kpi">
              <small>Personnel</small>
              <strong id="aiSuggestedPersonnel">—</strong>
            </div>
            <div class="deployment-kpi">
              <small>Monitoring</small>
              <strong id="aiMonitoringIntensity">—</strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="ai-subsection-heading">
      <div>
        <h6>Historical Hotspot Classification</h6>
        <small>K-Means clustering groups all monitored locations by historical risk level.</small>
      </div>
      <span class="ai-highlight-note">
        Monthly forecast: <strong id="hotspotMonthlyHighlight">—</strong>
      </span>
    </div>

    <div id="hotspotLoading" class="hotspot-loading-card">
      <div class="spinner-border spinner-border-sm me-2" role="status"></div>
      Loading hotspot classifications...
    </div>

    <div id="hotspotError" class="alert alert-danger d-none mb-3"></div>

    <div id="hotspotContent" class="d-none">
      <div class="row g-3 hotspot-kpi-grid">
        <div class="col-lg-4">
          <div class="hotspot-risk-card hotspot-card-high" id="hotspotCardHigh">
            <div class="hotspot-card-head">
              <span class="hotspot-card-icon"><i class="bi bi-exclamation-triangle"></i></span>
              <div>
                <span class="hotspot-card-label">High Risk</span>
                <strong id="highRiskCount">0</strong>
                <small>locations</small>
              </div>
            </div>
            <ul class="hotspot-card-list" id="highRiskLocations"></ul>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="hotspot-risk-card hotspot-card-medium" id="hotspotCardMedium">
            <div class="hotspot-card-head">
              <span class="hotspot-card-icon"><i class="bi bi-exclamation-circle"></i></span>
              <div>
                <span class="hotspot-card-label">Medium Risk</span>
                <strong id="mediumRiskCount">0</strong>
                <small>locations</small>
              </div>
            </div>
            <ul class="hotspot-card-list" id="mediumRiskLocations"></ul>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="hotspot-risk-card hotspot-card-low" id="hotspotCardLow">
            <div class="hotspot-card-head">
              <span class="hotspot-card-icon"><i class="bi bi-check-circle"></i></span>
              <div>
                <span class="hotspot-card-label">Low Risk</span>
                <strong id="lowRiskCount">0</strong>
                <small>locations</small>
              </div>
            </div>
            <ul class="hotspot-card-list" id="lowRiskLocations"></ul>
          </div>
        </div>
      </div>
    </div>

    <div class="ai-kpi-card ai-actions-card mt-4">
      <div class="ai-card-header">
        <span class="ai-card-icon ai-icon-amber">
          <i class="bi bi-lightbulb"></i>
        </span>
        <div>
          <h6>Recommended Actions</h6>
          <small>Suggested operational response based on the monthly forecast</small>
        </div>
      </div>

      <ul class="ai-action-grid" id="aiRecommendations"></ul>
    </div>
  </div>
</div>

<!-- Compact operational counters -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="section-card compact-metric h-100">
      <small class="text-muted">Pending Violations</small>
      <h4 class="mb-0 mt-1"><?= num($allPendingViolations) ?></h4>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="section-card compact-metric h-100">
      <small class="text-muted">Congestion Events Today</small>
      <h4 class="mb-0 mt-1"><?= num($congestionEvents) ?></h4>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="section-card compact-metric h-100">
      <small class="text-muted">Potential Collisions Today</small>
      <h4 class="mb-0 mt-1"><?= num($collisionEvents) ?></h4>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="section-card compact-metric h-100">
      <small class="text-muted">Online Cameras</small>
      <h4 class="mb-0 mt-1"><?= num($onlineCameras) ?> / <?= num($totalCameras) ?></h4>
    </div>
  </div>
</div>

<!-- Current monitoring -->
<div class="section-card mb-4">
  <div class="section-head">
    <div>
      <h6>Current Monitoring Status</h6>
      <small class="text-muted">Latest computer-vision monitoring record</small>
    </div>

    <span class="tag <?= tag_class($latestMonitoring['camera_status'] ?? 'offline') ?>">
      <?= esc($latestMonitoring['camera_status'] ?? 'offline') ?>
    </span>
  </div>

  <?php if ($latestMonitoring): ?>
    <div class="metric-grid">
      <div class="mini-metric">
        <small>Camera</small>
        <strong><?= esc($latestMonitoring['camera_name'] ?? 'Unnamed camera') ?></strong>
      </div>

      <div class="mini-metric">
        <small>Location</small>
        <strong><?= esc($latestMonitoring['location'] ?? 'Not set') ?></strong>
      </div>

      <div class="mini-metric">
        <small>Visible Vehicles</small>
        <strong><?= num($currentVehicles) ?></strong>
      </div>

      <div class="mini-metric">
        <small>Inbound</small>
        <strong><?= num($currentInbound) ?></strong>
      </div>

      <div class="mini-metric">
        <small>Outbound</small>
        <strong><?= num($currentOutbound) ?></strong>
      </div>

      <div class="mini-metric">
        <small>Congestion</small>
        <strong><?= esc($latestMonitoring['congestion_level'] ?? 'none') ?></strong>
      </div>

      <div class="mini-metric">
        <small>Traffic Officer</small>
        <strong><?= esc($latestMonitoring['officer_presence'] ?? 'unknown') ?></strong>
      </div>

      <div class="mini-metric">
        <small>Potential Collision</small>
        <strong><?= esc($latestMonitoring['potential_collision'] ?? 'none') ?></strong>
      </div>
    </div>

    <div class="mt-3 small text-muted">
      Last updated: <?= esc($latestMonitoring['recorded_at'] ?? 'No timestamp') ?>
    </div>
  <?php else: ?>
    <?php empty_state('No camera monitoring data is available yet. Start an analysis to populate this section.'); ?>
  <?php endif; ?>
</div>

<form method="get" class="chart-filter-form mb-3" aria-label="Filter dashboard charts">
  <div class="chart-filter-heading">
    <span><i class="bi bi-sliders"></i></span>
    <div><strong>Chart Filters</strong><small>Refine the violation analytics shown below</small></div>
  </div>
  <div class="chart-filter-controls">
    <label>
      <span>Period</span>
      <select name="chart_range" class="form-select form-select-sm">
        <?php foreach ($chartData['range_options'] as $value => $label): ?>
          <option value="<?= esc($value) ?>" <?= $chartData['range'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>
      <span>Status</span>
      <select name="chart_status" class="form-select form-select-sm">
        <?php foreach ($chartData['status_options'] as $value => $label): ?>
          <option value="<?= esc($value) ?>" <?= $chartData['status'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button class="btn chart-filter-apply" type="submit"><i class="bi bi-funnel"></i><span>Apply filters</span></button>
    <?php if ($chartData['range'] !== 'year' || $chartData['status'] !== 'all'): ?>
      <a class="chart-filter-reset" href="<?= esc(app_url('dashboard.php')) ?>"><i class="bi bi-arrow-counterclockwise"></i>Reset</a>
    <?php endif; ?>
  </div>
</form>

<!-- Two essential charts only -->
<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="section-card h-100">
      <div class="section-head">
        <div>
          <h6>Violation Trend</h6>
          <small class="text-muted"><?= esc($chartData['period_label'] . ' · ' . $chartData['status_label']) ?></small>
        </div>
      </div>

      <canvas id="trendChart" height="115"></canvas>
      <div id="trendEmpty" class="mt-3"></div>
      <div id="trendInterpretation" class="alert alert-light border mt-3 mb-0 small" role="note"></div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="section-card h-100">
      <div class="section-head">
        <div><h6>Top Violation Types</h6><small class="text-muted"><?= esc($chartData['period_label'] . ' · ' . $chartData['status_label']) ?></small></div>
      </div>

      <canvas id="topViolationChart" height="155"></canvas>
      <div id="topViolationEmpty" class="mt-3"></div>
      <div id="topViolationInterpretation" class="alert alert-light border mt-3 mb-0 small" role="note"></div>
    </div>
  </div>
</div>

<!-- Recent operational activity -->
<div class="row g-3">
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Recent Alerts</h6>
        <a href="<?= esc(app_url('alerts.php')) ?>" class="small fw-semibold text-decoration-none">View all</a>
      </div>

      <?php if (!$recentAlerts): ?>
        <?php empty_state('No recent alerts found.'); ?>
      <?php else: ?>
        <?php foreach ($recentAlerts as $alert): ?>
          <div class="border-bottom py-2">
            <div class="d-flex justify-content-between gap-2">
              <strong><?= esc(ucfirst($alert['alert_type'])) ?></strong>
              <span class="tag <?= tag_class($alert['severity']) ?>"><?= esc($alert['severity']) ?></span>
            </div>
            <small class="text-muted d-block"><?= esc($alert['message']) ?></small>
            <small class="text-muted"><?= esc($alert['generated_at']) ?></small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Recent Violations</h6>
        <a href="<?= esc(app_url('violations.php')) ?>" class="small fw-semibold text-decoration-none">View all</a>
      </div>

      <?php if (!$recentViolations): ?>
        <?php empty_state('No violation records found.'); ?>
      <?php else: ?>
        <?php foreach ($recentViolations as $violation): ?>
          <div class="border-bottom py-2">
            <div class="d-flex justify-content-between gap-2">
              <strong><?= esc($violation['ticket_number']) ?></strong>
              <span class="tag <?= tag_class($violation['status']) ?>"><?= esc($violation['status']) ?></span>
            </div>
            <small class="text-muted d-block">
              <?= esc($violation['plate_number']) ?> • <?= esc($violation['violation_type']) ?>
            </small>
            <small class="text-muted">
              <?= esc($violation['violation_location']) ?> • <?= peso($violation['penalty_amount']) ?>
            </small>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const MONTHLY_PREDICTION_ENDPOINT = '../api/predict_monthly.php';
const HOTSPOT_ENDPOINT = '../api/predict_hotspot.php';

const months = <?= json_encode($chartData['labels']) ?>;
const trendData = <?= json_encode($trendData) ?>;
const topViolationLabels = <?= json_encode($topViolationLabels) ?>;
const topViolationData = <?= json_encode($topViolationData) ?>;
const chartPeriodLabel = <?= json_encode($chartData['period_label']) ?>;
const chartStatusLabel = <?= json_encode($chartData['status_label']) ?>;

const chartBlues = ['#0b3d78', '#1565c0', '#1976d2', '#2196f3', '#4fc3f7', '#7dd3fc'];
const blueGrid = 'rgba(25, 118, 210, .12)';
const blueTicks = '#49657f';

function showEmpty(id, message) {
  const target = document.getElementById(id);
  if (target) {
    target.innerHTML = '<div class="empty-state">' + message + '</div>';
  }
}

function setInterpretation(id, message) {
  const target = document.getElementById(id);
  if (!target) return;
  target.textContent = message;
  target.hidden = !message;
}

function total(values) {
  return values.reduce((sum, value) => sum + Number(value || 0), 0);
}

function maxIndex(values) {
  return values.reduce(
    (best, value, index, all) =>
      Number(value || 0) > Number(all[best] || 0) ? index : best,
    0
  );
}

function normalizeRisk(value) {
  return String(value || '').trim().toLowerCase().replace(' risk', '');
}

function getDecisionGuidance(riskLevel) {
  const risk = normalizeRisk(riskLevel);

  if (risk === 'high') {
    return {
      priority: 'High',
      personnel: '5–6 enforcers',
      monitoring: 'Intensive monitoring'
    };
  }

  if (risk === 'medium') {
    return {
      priority: 'Medium',
      personnel: '3–4 enforcers',
      monitoring: 'Enhanced monitoring'
    };
  }

  return {
    priority: 'Low',
    personnel: 'Regular staffing',
    monitoring: 'Routine monitoring'
  };
}

function applyRiskStyle(element, riskLevel) {
  const risk = normalizeRisk(riskLevel);

  element.classList.remove(
    'ai-risk-high',
    'ai-risk-medium',
    'ai-risk-low'
  );

  if (risk === 'high') {
    element.classList.add('ai-risk-high');
  } else if (risk === 'medium') {
    element.classList.add('ai-risk-medium');
  } else {
    element.classList.add('ai-risk-low');
  }
}


function riskEndpointValue(riskLevel) {
  const risk = normalizeRisk(riskLevel);
  if (risk === 'high') return 'high';
  if (risk === 'medium') return 'medium';
  return 'low';
}

function getHotspotLocations(payload) {
  if (Array.isArray(payload?.data?.locations)) return payload.data.locations;
  if (Array.isArray(payload?.locations)) return payload.locations;

  return [
    ...(payload?.high_risk || []),
    ...(payload?.medium_risk || []),
    ...(payload?.low_risk || [])
  ];
}

function classifyHotspots(records) {
  return records.reduce(
    (groups, record) => {
      const risk = normalizeRisk(record['Risk Level'] || record.risk_level);

      if (risk === 'high') groups.high.push(record);
      else if (risk === 'medium') groups.medium.push(record);
      else groups.low.push(record);

      return groups;
    },
    { high: [], medium: [], low: [] }
  );
}

function sortHotspots(records) {
  return [...records].sort(
    (a, b) =>
      Number(b['Total Violations'] ?? b.Total_Violations ?? b.total ?? 0) -
      Number(a['Total Violations'] ?? a.Total_Violations ?? a.total ?? 0)
  );
}

function renderHotspotGroup(risk, records) {
  const normalized = risk.charAt(0).toUpperCase() + risk.slice(1);
  const countElement = document.getElementById(`${risk}RiskCount`);
  const listElement = document.getElementById(`${risk}RiskLocations`);

  countElement.textContent = records.length;
  listElement.innerHTML = '';

  sortHotspots(records).forEach((record, index) => {
    const locationName =
      record.Location ||
      record.location ||
      record.violation_location ||
      'Unnamed location';

    const totalViolations =
      record['Total Violations'] ??
      record.Total_Violations ??
      record.total ??
      record.frequency_count ??
      null;

    const item = document.createElement('li');
    item.innerHTML = `
      <span class="hotspot-list-rank">${index + 1}</span>
      <span class="hotspot-list-copy">
        <strong>${locationName}</strong>
        ${totalViolations !== null
          ? `<small>${Number(totalViolations).toLocaleString()} historical records</small>`
          : ''}
      </span>
    `;
    listElement.appendChild(item);
  });

  if (!records.length) {
    const item = document.createElement('li');
    item.className = 'hotspot-list-empty';
    item.textContent = `No ${normalized.toLowerCase()}-risk locations found.`;
    listElement.appendChild(item);
  }
}

function highlightPredictedHotspotCard(riskLevel) {
  const risk = riskEndpointValue(riskLevel);

  ['High', 'Medium', 'Low'].forEach(level => {
    document
      .getElementById(`hotspotCard${level}`)
      ?.classList.remove('hotspot-card-highlighted');
  });

  const activeCard = document.getElementById(
    `hotspotCard${risk.charAt(0).toUpperCase()}${risk.slice(1)}`
  );

  activeCard?.classList.add('hotspot-card-highlighted');

  document.getElementById('hotspotMonthlyHighlight').textContent =
    `${risk.charAt(0).toUpperCase()}${risk.slice(1)} Risk`;
}

async function loadHotspots(monthlyRiskLevel = 'High') {
  const loading = document.getElementById('hotspotLoading');
  const errorBox = document.getElementById('hotspotError');
  const content = document.getElementById('hotspotContent');

  loading.classList.remove('d-none');
  errorBox.classList.add('d-none');
  content.classList.add('d-none');

  try {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => controller.abort(), 10000);

    const response = await fetch(HOTSPOT_ENDPOINT, {
      method: 'GET',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store',
      signal: controller.signal
    });

    window.clearTimeout(timeoutId);

    const payload = await response.json();

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'Unable to load hotspot results.');
    }

    const records = getHotspotLocations(payload);
    const groups = classifyHotspots(records);

    renderHotspotGroup('high', groups.high);
    renderHotspotGroup('medium', groups.medium);
    renderHotspotGroup('low', groups.low);
    highlightPredictedHotspotCard(monthlyRiskLevel);

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  } catch (error) {
    loading.classList.add('d-none');
    errorBox.textContent =
      error.name === 'AbortError'
        ? 'The hotspot request timed out. Make sure the Flask API is running on port 5001.'
        : `${error.message} Make sure predict_hotspot.php and the Flask API are available.`;
    errorBox.classList.remove('d-none');
  }
}

async function loadMonthlyPrediction() {
  const loading = document.getElementById('aiPredictionLoading');
  const errorBox = document.getElementById('aiPredictionError');
  const content = document.getElementById('aiPredictionContent');

  loading.classList.remove('d-none');
  errorBox.classList.add('d-none');
  content.classList.add('d-none');

  try {
    const month = Number(document.getElementById('predictionMonth')?.value);
    const year = Number(document.getElementById('predictionYear')?.value);
    const response = await fetch(MONTHLY_PREDICTION_ENDPOINT, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({ month, year }),
      cache: 'no-store'
    });

    const payload = await response.json();

    if (!response.ok || !payload.success) {
      throw new Error(payload.message || 'Unable to load the prediction.');
    }

    const prediction = payload.data || payload.prediction || {};
    const riskLevel = prediction.risk_level || 'Unknown';
    const confidence = Number(prediction.confidence || 0);
    const recommendations = prediction.recommendations || [];
    const guidance = getDecisionGuidance(riskLevel);

    const riskBadge = document.getElementById('aiRiskBadge');
    riskBadge.textContent = String(riskLevel).toUpperCase();
    applyRiskStyle(riskBadge, riskLevel);

    document.getElementById('aiPredictionPeriod').textContent =
      `${prediction.month_name || 'Current month'} ${prediction.year || ''}`.trim();

    document.getElementById('aiConfidenceText').textContent =
      `${confidence.toFixed(1)}%`;

    const confidenceBar = document.getElementById('aiConfidenceBar');
    confidenceBar.style.width = `${Math.max(0, Math.min(100, confidence))}%`;
    applyRiskStyle(confidenceBar, riskLevel);

    document.getElementById('aiDeploymentPriority').textContent = guidance.priority;
    document.getElementById('aiSuggestedPersonnel').textContent = guidance.personnel;
    document.getElementById('aiMonitoringIntensity').textContent = guidance.monitoring;

    const recommendationList = document.getElementById('aiRecommendations');
    recommendationList.innerHTML = '';

    const actions = recommendations.length
      ? recommendations
      : ['Review the prediction together with current monitoring data.'];

    actions.forEach(action => {
      const item = document.createElement('li');
      item.innerHTML = `<i class="bi bi-check-circle-fill"></i><span>${action}</span>`;
      recommendationList.appendChild(item);
    });

    loading.classList.add('d-none');
    content.classList.remove('d-none');
    await loadHotspots(riskLevel);
  } catch (error) {
    loading.classList.add('d-none');
    errorBox.textContent =
      `${error.message} Make sure the Flask API is running on port 5001.`;
    errorBox.classList.remove('d-none');
  }
}

function initializePredictionPeriodFilter() {
  const monthSelect = document.getElementById('predictionMonth');
  const yearSelect = document.getElementById('predictionYear');
  if (!monthSelect || !yearSelect) return;

  const monthNames = Array.from({ length: 12 }, (_, index) =>
    new Intl.DateTimeFormat('en', { month: 'long' }).format(new Date(2000, index, 1))
  );
  const nextMonth = new Date();
  nextMonth.setMonth(nextMonth.getMonth() + 1, 1);

  monthNames.forEach((name, index) => monthSelect.add(new Option(name, index + 1)));
  for (let year = nextMonth.getFullYear(); year <= Math.min(2100, nextMonth.getFullYear() + 10); year++) {
    yearSelect.add(new Option(String(year), year));
  }

  monthSelect.value = String(nextMonth.getMonth() + 1);
  yearSelect.value = String(nextMonth.getFullYear());
}

document.getElementById('refreshPredictionBtn')?.addEventListener('click', loadMonthlyPrediction);
document.getElementById('predictionMonth')?.addEventListener('change', loadMonthlyPrediction);
document.getElementById('predictionYear')?.addEventListener('change', loadMonthlyPrediction);

initializePredictionPeriodFilter();
loadMonthlyPrediction();
setInterval(loadMonthlyPrediction, 60000);

if (total(trendData) > 0) {
  new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
      labels: months,
      datasets: [{
        label: 'Violations',
        data: trendData,
        borderColor: '#1976d2',
        backgroundColor: 'rgba(79,195,247,.22)',
        pointBackgroundColor: '#0b3d78',
        pointBorderColor: '#fff',
        fill: true,
        tension: .4,
        borderWidth: 3,
        pointRadius: 3,
        pointHoverRadius: 5
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: {
        x: {
          grid: { display: false },
          ticks: { color: blueTicks }
        },
        y: {
          beginAtZero: true,
          grid: { color: blueGrid },
          ticks: { precision: 0, color: blueTicks }
        }
      }
    }
  });

  const peak = maxIndex(trendData);
  setInterpretation(
    'trendInterpretation',
    `Interpretation: For ${chartPeriodLabel.toLowerCase()} (${chartStatusLabel.toLowerCase()}), ${months[peak]} recorded the highest count at ${Number(trendData[peak]).toLocaleString()}.`
  );
} else {
  showEmpty('trendEmpty', 'No violation trend data yet.');
  setInterpretation(
    'trendInterpretation',
    `Interpretation: No ${chartStatusLabel.toLowerCase()} violation trend can be identified for ${chartPeriodLabel.toLowerCase()}.`
  );
}

if (total(topViolationData) > 0) {
  new Chart(document.getElementById('topViolationChart'), {
    type: 'bar',
    data: {
      labels: topViolationLabels,
      datasets: [{
        label: 'Recorded Violations',
        data: topViolationData,
        backgroundColor: topViolationLabels.map(
          (_, index) => chartBlues[(index + 1) % chartBlues.length]
        ),
        borderRadius: 7
      }]
    },
    options: {
      responsive: true,
      indexAxis: 'y',
      plugins: { legend: { display: false } },
      scales: {
        x: {
          beginAtZero: true,
          grid: { color: blueGrid },
          ticks: { precision: 0, color: blueTicks }
        },
        y: {
          grid: { display: false },
          ticks: { color: blueTicks }
        }
      }
    }
  });

  const leadingViolation = maxIndex(topViolationData);
  setInterpretation(
    'topViolationInterpretation',
    `Interpretation: For ${chartPeriodLabel.toLowerCase()} (${chartStatusLabel.toLowerCase()}), ${topViolationLabels[leadingViolation]} is the leading violation type with ${Number(topViolationData[leadingViolation]).toLocaleString()} records.`
  );
} else {
  showEmpty('topViolationEmpty', 'No violation type data available.');
  setInterpretation(
    'topViolationInterpretation',
    'Interpretation: No violation-type pattern can be identified yet.'
  );
}
</script>

<?php page_end(false); ?>
