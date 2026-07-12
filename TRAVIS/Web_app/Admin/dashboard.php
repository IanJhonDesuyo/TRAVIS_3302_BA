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

$trendData = monthly_violation_counts();

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
    $topViolationLabels[] = (string) $row['violation_type'];
    $topViolationData[] = (int) $row['total'];
}

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

$vehiclesToday = (int) ($todaySummary['vehicle_observations'] ?? 0);
$inboundToday = (int) ($todaySummary['inbound_total'] ?? 0);
$outboundToday = (int) ($todaySummary['outbound_total'] ?? 0);
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
    <h3 class="page-title">Operations Dashboard</h3>
    <p class="page-sub">Live traffic operations and AI-powered decision support for the TMO.</p>
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
    <div class="stat-card h-100">
      <div class="stat-icon tone-primary"><i class="bi bi-car-front"></i></div>
      <div class="stat-label">Vehicle Observations Today</div>
      <div class="stat-value"><?= num($vehiclesToday) ?></div>
      <small class="text-muted">
        <?= num($inboundToday) ?> inbound • <?= num($outboundToday) ?> outbound
      </small>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-icon tone-warning"><i class="bi bi-cone-striped"></i></div>
      <div class="stat-label">Violations Today</div>
      <div class="stat-value"><?= num($violationsToday) ?></div>
      <small class="text-muted">
        <?= num($paidViolationsToday) ?> paid • <?= num($unpaidViolationsToday) ?> unpaid
      </small>
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

<!-- AI Decision Support -->
<div class="section-card ai-decision-card mb-4" id="aiDecisionCard">
  <div class="section-head ai-section-head">
    <div>
      <span class="ai-kicker"><i class="bi bi-cpu me-1"></i>TRAVIS AI</span>
      <h5 class="mb-1">Monthly Decision Support</h5>
      <small class="text-muted">Random Forest prediction with operational recommendations</small>
    </div>

    <button class="btn btn-sm btn-light" type="button" id="refreshPredictionBtn">
      <i class="bi bi-arrow-clockwise me-1"></i>Refresh
    </button>
  </div>

  <div id="aiPredictionLoading" class="ai-loading-state">
    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
    Loading monthly risk prediction...
  </div>

  <div id="aiPredictionError" class="alert alert-danger d-none mb-0" role="alert"></div>

  <div id="aiPredictionContent" class="d-none">
    <div class="row g-3">
      <div class="col-lg-4">
        <div class="ai-risk-panel">
          <div class="ai-label">Expected Risk</div>
          <div class="ai-risk-badge" id="aiRiskBadge">—</div>
          <div class="ai-period" id="aiPredictionPeriod">—</div>

          <div class="ai-confidence-wrap">
            <div class="d-flex justify-content-between small">
              <span>Model confidence</span>
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

          <small class="text-muted d-block mt-3">
            Prediction is based on historical TMO records and should be reviewed with current conditions.
          </small>
        </div>
      </div>

      <div class="col-md-6 col-lg-4">
        <div class="ai-support-panel h-100">
          <div class="ai-panel-title">
            <i class="bi bi-people me-2"></i>Deployment Guidance
          </div>

          <div class="ai-guidance-row">
            <span>Deployment priority</span>
            <strong id="aiDeploymentPriority">—</strong>
          </div>

          <div class="ai-guidance-row">
            <span>Suggested personnel</span>
            <strong id="aiSuggestedPersonnel">—</strong>
          </div>

          <div class="ai-guidance-row">
            <span>Monitoring intensity</span>
            <strong id="aiMonitoringIntensity">—</strong>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-lg-4">
        <div class="ai-support-panel h-100">
          <div class="ai-panel-title">
            <i class="bi bi-lightbulb me-2"></i>Recommended Actions
          </div>
          <ul class="ai-action-list" id="aiRecommendations"></ul>
        </div>
      </div>
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

<!-- Two essential charts only -->
<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="section-card h-100">
      <div class="section-head">
        <div>
          <h6>Monthly Violation Trends</h6>
          <small class="text-muted">Current year</small>
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
        <h6>Top Violation Types</h6>
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

const months = <?= json_encode(month_labels()) ?>;
const trendData = <?= json_encode($trendData) ?>;
const topViolationLabels = <?= json_encode($topViolationLabels) ?>;
const topViolationData = <?= json_encode($topViolationData) ?>;

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

async function loadMonthlyPrediction() {
  const loading = document.getElementById('aiPredictionLoading');
  const errorBox = document.getElementById('aiPredictionError');
  const content = document.getElementById('aiPredictionContent');

  loading.classList.remove('d-none');
  errorBox.classList.add('d-none');
  content.classList.add('d-none');

  try {
    const response = await fetch(MONTHLY_PREDICTION_ENDPOINT, {
      method: 'GET',
      headers: {
        'Accept': 'application/json'
      },
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
  } catch (error) {
    loading.classList.add('d-none');
    errorBox.textContent =
      `${error.message} Make sure the Flask API is running on port 5001.`;
    errorBox.classList.remove('d-none');
  }
}

document.getElementById('refreshPredictionBtn')?.addEventListener(
  'click',
  loadMonthlyPrediction
);

loadMonthlyPrediction();

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
    `Interpretation: ${months[peak]} recorded the highest monthly count at ${Number(trendData[peak]).toLocaleString()}.`
  );
} else {
  showEmpty('trendEmpty', 'No violation trend data yet.');
  setInterpretation(
    'trendInterpretation',
    'Interpretation: No current-year violation trend can be identified yet.'
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
    `Interpretation: ${topViolationLabels[leadingViolation]} is the leading violation type with ${Number(topViolationData[leadingViolation]).toLocaleString()} records.`
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
