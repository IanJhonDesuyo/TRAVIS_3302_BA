<?php
require_once __DIR__ . '/layout.php';

/*
|--------------------------------------------------------------------------
| Database analytics for the Decision Support page
|--------------------------------------------------------------------------
*/

$todayAnalytics = fetch_one("
    SELECT
        COUNT(*) AS violations_today,
        COALESCE(SUM(penalty_amount), 0) AS penalties_today,
        COUNT(DISTINCT violation_location) AS affected_locations
    FROM violations
    WHERE violation_date = CURDATE()
") ?: [];

$topViolation = fetch_one("
    SELECT violation_type, COUNT(*) AS total
    FROM violations
    WHERE YEAR(violation_date) = YEAR(CURDATE())
    GROUP BY violation_type
    ORDER BY total DESC
    LIMIT 1
") ?: [];

$topLocation = fetch_one("
    SELECT violation_location, COUNT(*) AS total
    FROM violations
    WHERE YEAR(violation_date) = YEAR(CURDATE())
    GROUP BY violation_location
    ORDER BY total DESC
    LIMIT 1
") ?: [];

$peakHour = fetch_one("
    SELECT
        HOUR(created_at) AS peak_hour,
        COUNT(*) AS total
    FROM violations
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY HOUR(created_at)
    ORDER BY total DESC
    LIMIT 1
") ?: [];

$monthlyTrend = monthly_violation_counts();

$violationTypeRows = fetch_all("
    SELECT violation_type, COUNT(*) AS total
    FROM violations
    WHERE YEAR(violation_date) = YEAR(CURDATE())
    GROUP BY violation_type
    ORDER BY total DESC
    LIMIT 8
");

$locationRows = fetch_all("
    SELECT violation_location, COUNT(*) AS total
    FROM violations
    WHERE YEAR(violation_date) = YEAR(CURDATE())
    GROUP BY violation_location
    ORDER BY total DESC
    LIMIT 8
");

$violationTypeLabels = [];
$violationTypeData = [];

foreach ($violationTypeRows as $row) {
    $violationTypeLabels[] = (string) $row['violation_type'];
    $violationTypeData[] = (int) $row['total'];
}

$locationLabels = [];
$locationData = [];

foreach ($locationRows as $row) {
    $locationLabels[] = (string) $row['violation_location'];
    $locationData[] = (int) $row['total'];
}

$violationsToday = (int) ($todayAnalytics['violations_today'] ?? 0);
$penaltiesToday = (float) ($todayAnalytics['penalties_today'] ?? 0);
$affectedLocations = (int) ($todayAnalytics['affected_locations'] ?? 0);
$topViolationName = (string) ($topViolation['violation_type'] ?? 'No data');
$topViolationCount = (int) ($topViolation['total'] ?? 0);
$topLocationName = (string) ($topLocation['violation_location'] ?? 'No data');
$topLocationCount = (int) ($topLocation['total'] ?? 0);

$peakHourValue = isset($peakHour['peak_hour'])
    ? date('g A', mktime((int) $peakHour['peak_hour'], 0))
    : 'No data';

page_start(
    'Decision Support',
    'decision-support',
    'Search decision-support information...'
);
?>

<style>
/* ============================================================
   TRAVIS DECISION SUPPORT — INTERNAL PAGE STYLES
   ============================================================ */

.ds-page {
  --ds-blue: #1d4ed8;
  --ds-navy: #173b63;
  --ds-teal: #0f766e;
  --ds-green: #15803d;
  --ds-amber: #d97706;
  --ds-red: #c62828;
  --ds-purple: #6d28d9;
  --ds-border: #dfe7ef;
  --ds-muted: #6b7f91;
}

.ds-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.4rem;
  flex-wrap: wrap;
}

.ds-eyebrow {
  display: block;
  margin-bottom: .35rem;
  color: var(--ds-blue);
  font-size: .7rem;
  font-weight: 800;
  letter-spacing: .13em;
}

.ds-status {
  display: inline-flex;
  align-items: center;
  gap: .5rem;
  padding: .48rem .72rem;
  border: 1px solid rgba(21,128,61,.18);
  border-radius: 999px;
  background: rgba(21,128,61,.08);
  color: #126c35;
  font-size: .78rem;
  font-weight: 700;
}

.ds-status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 0 5px rgba(34,197,94,.12);
}

.ds-card {
  height: 100%;
  padding: 1.25rem;
  border: 1px solid var(--ds-border);
  border-radius: 18px;
  background: rgba(255,255,255,.80);
  box-shadow: 0 12px 30px rgba(15,45,75,.065);
  backdrop-filter: blur(14px) saturate(115%);
  transition: transform .34s cubic-bezier(.22,1,.36,1), box-shadow .34s ease, border-color .34s ease;
  animation: ds-card-enter .55s cubic-bezier(.22,1,.36,1) both;
}

.ds-card:hover {
  transform: translateY(-3px);
  border-color: rgba(29,78,216,.20);
  box-shadow: 0 18px 38px rgba(15,45,75,.10);
}

.ds-card-head {
  display: flex;
  align-items: center;
  gap: .8rem;
  margin-bottom: 1rem;
}

.ds-icon {
  display: grid;
  place-items: center;
  flex: 0 0 44px;
  width: 44px;
  height: 44px;
  border-radius: 13px;
  font-size: 1.1rem;
}

.ds-icon-blue { color: #1d4ed8; background: #eaf2ff; }
.ds-icon-teal { color: #0f766e; background: #e8f8f5; }
.ds-icon-purple { color: #6d28d9; background: #f1ebff; }
.ds-icon-amber { color: #a85d00; background: #fff3d6; }

.ds-card-head h6 {
  margin: 0;
  color: var(--ds-navy);
  font-weight: 800;
}

.ds-card-head small {
  color: var(--ds-muted);
}

.ds-hero {
  position: relative;
  overflow: hidden;
  background:
    radial-gradient(circle at top right, rgba(59,130,246,.16), transparent 35%),
    linear-gradient(145deg,rgba(255,255,255,.90),rgba(232,240,250,.82));
}

.ds-risk-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 165px;
  padding: .75rem 1.15rem;
  border-radius: 999px;
  font-size: 1.3rem;
  font-weight: 800;
  letter-spacing: .04em;
}

.ds-risk-low { background: rgba(22,163,74,.12); color: #137a38; }
.ds-risk-medium { background: rgba(245,158,11,.15); color: #a75b00; }
.ds-risk-high { background: rgba(220,38,38,.12); color: #b42336; }

.ds-confidence {
  margin-top: 1rem;
  padding: .9rem;
  border: 1px solid #e3eaf2;
  border-radius: 13px;
  background: #f8fafc;
}

.ds-progress {
  height: 8px;
  margin-top: .45rem;
  border-radius: 999px;
  background: #e8eef5;
}

.ds-progress .progress-bar {
  border-radius: 999px;
}

.ds-kpi-grid {
  display: grid;
  grid-template-columns: repeat(2,minmax(0,1fr));
  gap: .8rem;
}

.ds-kpi {
  padding: 1rem;
  border: 1px solid #e7edf3;
  border-radius: 14px;
  background: #f9fbfd;
}

.ds-kpi small {
  color: var(--ds-muted);
}

.ds-kpi strong {
  display: block;
  margin-top: .25rem;
  color: var(--ds-navy);
  font-size: 1.05rem;
}

.ds-section-title {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  gap: 1rem;
  margin: 1.6rem 0 .9rem;
  flex-wrap: wrap;
}

.ds-section-title h5 {
  margin: 0;
  color: var(--ds-navy);
  font-weight: 800;
}

.ds-section-title small {
  color: var(--ds-muted);
}

.ds-hotspot-card {
  height: 310px;
  display: flex;
  flex-direction: column;
  padding: 1.1rem;
  border: 1px solid var(--ds-border);
  border-radius: 18px;
  background: rgba(255,255,255,.80);
  box-shadow: 0 10px 24px rgba(15,45,75,.05);
  backdrop-filter: blur(14px) saturate(115%);
  transition: transform .34s cubic-bezier(.22,1,.36,1), box-shadow .34s ease, border-color .34s ease;
  animation: ds-card-enter .6s cubic-bezier(.22,1,.36,1) both;
}

.ds-hotspot-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 18px 36px rgba(15,45,75,.10);
}

@keyframes ds-card-enter {
  from { opacity: 0; transform: translateY(12px); }
  to { opacity: 1; transform: translateY(0); }
}

.ds-hotspot-card.high { border-top: 6px solid var(--ds-red); }
.ds-hotspot-card.medium { border-top: 6px solid var(--ds-amber); }
.ds-hotspot-card.low { border-top: 6px solid var(--ds-green); }

.ds-hotspot-card.active {
  border-color: rgba(29,78,216,.45);
  box-shadow: 0 0 0 3px rgba(29,78,216,.10), 0 16px 34px rgba(15,45,75,.11);
}

.ds-hotspot-head {
  display: flex;
  align-items: center;
  gap: .75rem;
  padding-bottom: .85rem;
  border-bottom: 1px solid #edf2f7;
}

.ds-hotspot-head strong {
  color: var(--ds-navy);
  font-size: 1.55rem;
}

.ds-hotspot-list {
  flex: 1;
  margin: .85rem 0 0;
  padding: 0 .25rem 0 0;
  overflow-y: auto;
  list-style: none;
}

.ds-hotspot-list li {
  display: flex;
  gap: .65rem;
  margin-bottom: .6rem;
  padding: .65rem;
  border: 1px solid #edf1f5;
  border-radius: 11px;
  background: #fafcff;
}

.ds-rank {
  display: grid;
  place-items: center;
  flex: 0 0 25px;
  width: 25px;
  height: 25px;
  border-radius: 8px;
  background: #eef4fb;
  color: #275a8d;
  font-size: .72rem;
  font-weight: 800;
}

.ds-location-copy {
  display: grid;
}

.ds-location-copy strong {
  color: #29445d;
  font-size: .8rem;
  line-height: 1.3;
}

.ds-location-copy small {
  color: #788da0;
  font-size: .69rem;
}

.ds-hotspot-list::-webkit-scrollbar {
  width: 7px;
}

.ds-hotspot-list::-webkit-scrollbar-thumb {
  border-radius: 999px;
  background: #a4b8cb;
}

.ds-analysis-grid {
  display: grid;
  grid-template-columns: repeat(4,minmax(0,1fr));
  gap: .85rem;
}

.ds-analysis-kpi {
  padding: 1rem;
  border: 1px solid #e6edf3;
  border-radius: 14px;
  background: #fff;
}

.ds-analysis-kpi small {
  color: var(--ds-muted);
}

.ds-analysis-kpi strong {
  display: block;
  margin-top: .3rem;
  color: var(--ds-navy);
  font-size: 1.12rem;
}

.ds-interpretation {
  min-height: 190px;
  padding: 1.1rem;
  border-left: 5px solid var(--ds-blue);
  border-radius: 14px;
  background: #f5f8ff;
  color: #38556d;
  line-height: 1.65;
}

.ds-recommendation-list {
  display: grid;
  gap: .7rem;
  margin: 0;
  padding: 0;
  list-style: none;
}

.ds-recommendation-list li {
  display: flex;
  gap: .65rem;
  padding: .8rem;
  border: 1px solid #e7edf3;
  border-radius: 12px;
  background: #f9fbfd;
  color: #435e73;
}

.ds-recommendation-list i {
  color: var(--ds-teal);
}

.ds-deployment-table {
  display: grid;
  gap: .7rem;
}

.ds-deployment-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  padding: .8rem;
  border-bottom: 1px solid #edf2f7;
}

.ds-deployment-row:last-child {
  border-bottom: 0;
}

.ds-deployment-row span {
  color: var(--ds-muted);
}

.ds-deployment-row strong {
  color: var(--ds-navy);
  text-align: right;
}

.ds-chart-card canvas {
  max-height: 300px;
}

@media (max-width: 991.98px) {
  .ds-analysis-grid {
    grid-template-columns: repeat(2,minmax(0,1fr));
  }
}

@media (max-width: 767.98px) {
  .ds-kpi-grid,
  .ds-analysis-grid {
    grid-template-columns: 1fr;
  }

  .ds-hotspot-card {
    height: 275px;
  }
}
</style>

<div class="ds-page">
  <div class="ds-heading">
    <div>
      <span class="ds-eyebrow">TRAVIS INTELLIGENCE MODULE</span>
      <h3 class="page-title">Decision Support</h3>
      <p class="page-sub">
        Machine-learning predictions, historical patterns, database analytics, and operational recommendations.
      </p>
    </div>

    <span class="ds-status">
      <span class="ds-status-dot"></span>
      AI services connected
    </span>
  </div>

  <div id="dsLoading" class="alert alert-light border">
    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
    Loading decision-support information...
  </div>

  <div id="dsError" class="alert alert-danger d-none"></div>

  <div id="dsContent" class="d-none">
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="ds-card ds-hero">
          <div class="ds-card-head">
            <span class="ds-icon ds-icon-blue">
              <i class="bi bi-graph-up-arrow"></i>
            </span>
            <div>
              <h6>Monthly Risk Prediction</h6>
              <small>Random Forest classifier</small>
            </div>
          </div>

          <div class="ds-risk-badge" id="dsRiskBadge">—</div>
          <h5 class="mt-3 mb-1" id="dsPredictionPeriod">—</h5>
          <small class="text-muted">Predicted traffic-violation risk level</small>

          <div class="ds-confidence">
            <div class="d-flex justify-content-between">
              <span>Prediction confidence</span>
              <strong id="dsConfidenceText">—</strong>
            </div>
            <div class="progress ds-progress">
              <div class="progress-bar" id="dsConfidenceBar" style="width:0%"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="ds-card">
          <div class="ds-card-head">
            <span class="ds-icon ds-icon-teal">
              <i class="bi bi-people"></i>
            </span>
            <div>
              <h6>Deployment Guidance</h6>
              <small>Business-rule recommendation</small>
            </div>
          </div>

          <div class="ds-kpi-grid">
            <div class="ds-kpi">
              <small>Priority</small>
              <strong id="dsDeploymentPriority">—</strong>
            </div>

            <div class="ds-kpi">
              <small>Personnel</small>
              <strong id="dsPersonnel">—</strong>
            </div>

            <div class="ds-kpi">
              <small>Monitoring</small>
              <strong id="dsMonitoring">—</strong>
            </div>

            <div class="ds-kpi">
              <small>Focus Area</small>
              <strong id="dsFocusArea">—</strong>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="ds-section-title">
      <div>
        <h5>Historical Hotspot Classification</h5>
        <small>K-Means clustering of historical TMO records</small>
      </div>

      <span class="badge text-bg-light border">
        Forecast emphasis: <strong id="dsForecastEmphasis">—</strong>
      </span>
    </div>

    <div class="row g-3">
      <div class="col-lg-4">
        <div class="ds-hotspot-card high" id="dsHighCard">
          <div class="ds-hotspot-head">
            <span class="ds-icon" style="color:#b91c1c;background:#feecef">
              <i class="bi bi-exclamation-triangle"></i>
            </span>
            <div>
              <div class="small text-uppercase fw-bold text-muted">High Risk</div>
              <strong id="dsHighCount">0</strong>
              <small class="text-muted"> locations</small>
            </div>
          </div>

          <ul class="ds-hotspot-list" id="dsHighList"></ul>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="ds-hotspot-card medium" id="dsMediumCard">
          <div class="ds-hotspot-head">
            <span class="ds-icon" style="color:#a75b00;background:#fff4d8">
              <i class="bi bi-exclamation-circle"></i>
            </span>
            <div>
              <div class="small text-uppercase fw-bold text-muted">Medium Risk</div>
              <strong id="dsMediumCount">0</strong>
              <small class="text-muted"> locations</small>
            </div>
          </div>

          <ul class="ds-hotspot-list" id="dsMediumList"></ul>
        </div>
      </div>

      <div class="col-lg-4">
        <div class="ds-hotspot-card low" id="dsLowCard">
          <div class="ds-hotspot-head">
            <span class="ds-icon" style="color:#137a38;background:#e9f8ee">
              <i class="bi bi-check-circle"></i>
            </span>
            <div>
              <div class="small text-uppercase fw-bold text-muted">Low Risk</div>
              <strong id="dsLowCount">0</strong>
              <small class="text-muted"> locations</small>
            </div>
          </div>

          <ul class="ds-hotspot-list" id="dsLowList"></ul>
        </div>
      </div>
    </div>

    <div class="ds-section-title">
      <div>
        <h5>Database Analytics</h5>
        <small>Operational indicators generated directly from MySQL records</small>
      </div>
    </div>

    <div class="ds-analysis-grid mb-3">
      <div class="ds-analysis-kpi">
        <small>Violations Today</small>
        <strong><?= num($violationsToday) ?></strong>
      </div>

      <div class="ds-analysis-kpi">
        <small>Estimated Penalties Today</small>
        <strong><?= peso($penaltiesToday) ?></strong>
      </div>

      <div class="ds-analysis-kpi">
        <small>Affected Locations Today</small>
        <strong><?= num($affectedLocations) ?></strong>
      </div>

      <div class="ds-analysis-kpi">
        <small>Peak Recording Hour</small>
        <strong><?= esc($peakHourValue) ?></strong>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-lg-6">
        <div class="ds-card">
          <div class="ds-card-head">
            <span class="ds-icon ds-icon-purple">
              <i class="bi bi-bar-chart"></i>
            </span>
            <div>
              <h6>Leading Violation</h6>
              <small>Current-year database result</small>
            </div>
          </div>

          <h5><?= esc($topViolationName) ?></h5>
          <p class="text-muted mb-0"><?= num($topViolationCount) ?> recorded cases</p>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="ds-card">
          <div class="ds-card-head">
            <span class="ds-icon ds-icon-amber">
              <i class="bi bi-geo-alt"></i>
            </span>
            <div>
              <h6>Leading Database Location</h6>
              <small>Current-year violation concentration</small>
            </div>
          </div>

          <h5><?= esc($topLocationName) ?></h5>
          <p class="text-muted mb-0"><?= num($topLocationCount) ?> recorded cases</p>
        </div>
      </div>
    </div>

    <div class="row g-3 mb-3">
      <div class="col-lg-7">
        <div class="ds-card ds-chart-card">
          <div class="ds-card-head">
            <span class="ds-icon ds-icon-blue">
              <i class="bi bi-activity"></i>
            </span>
            <div>
              <h6>Monthly Violation Trend</h6>
              <small>Database records for the current year</small>
            </div>
          </div>
          <canvas id="dsTrendChart"></canvas>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="ds-card ds-chart-card">
          <div class="ds-card-head">
            <span class="ds-icon ds-icon-purple">
              <i class="bi bi-diagram-3"></i>
            </span>
            <div>
              <h6>Top Violation Types</h6>
              <small>Most frequently recorded categories</small>
            </div>
          </div>
          <canvas id="dsViolationChart"></canvas>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-7">
        <div class="ds-card">
          <div class="ds-card-head">
            <span class="ds-icon ds-icon-blue">
              <i class="bi bi-chat-square-text"></i>
            </span>
            <div>
              <h6>Interpretation</h6>
              <small>Plain-language explanation of the combined results</small>
            </div>
          </div>

          <div class="ds-interpretation" id="dsInterpretation">
            Waiting for prediction and hotspot results...
          </div>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="ds-card">
          <div class="ds-card-head">
            <span class="ds-icon ds-icon-amber">
              <i class="bi bi-lightbulb"></i>
            </span>
            <div>
              <h6>Recommendations</h6>
              <small>Suggested operational actions</small>
            </div>
          </div>

          <ul class="ds-recommendation-list" id="dsRecommendations"></ul>
        </div>
      </div>
    </div>

    <div class="ds-card mt-3">
      <div class="ds-card-head">
        <span class="ds-icon ds-icon-teal">
          <i class="bi bi-sign-turn-right"></i>
        </span>
        <div>
          <h6>Deployment Plan</h6>
          <small>Summary for TMO operational planning</small>
        </div>
      </div>

      <div class="ds-deployment-table">
        <div class="ds-deployment-row">
          <span>Monthly risk</span>
          <strong id="dsPlanRisk">—</strong>
        </div>

        <div class="ds-deployment-row">
          <span>Suggested personnel</span>
          <strong id="dsPlanPersonnel">—</strong>
        </div>

        <div class="ds-deployment-row">
          <span>Primary focus</span>
          <strong id="dsPlanFocus">—</strong>
        </div>

        <div class="ds-deployment-row">
          <span>Monitoring approach</span>
          <strong id="dsPlanMonitoring">—</strong>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const DS_MONTHLY_ENDPOINT = '../api/predict_monthly.php';
const DS_HOTSPOT_ENDPOINT = '../api/predict_hotspot.php';

const dsMonths = <?= json_encode(month_labels()) ?>;
const dsMonthlyTrend = <?= json_encode($monthlyTrend) ?>;
const dsViolationLabels = <?= json_encode($violationTypeLabels) ?>;
const dsViolationData = <?= json_encode($violationTypeData) ?>;

const databaseTopViolation = <?= json_encode($topViolationName) ?>;
const databaseTopLocation = <?= json_encode($topLocationName) ?>;
const databasePeakHour = <?= json_encode($peakHourValue) ?>;

function dsNormalizeRisk(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(' risk', '');
}

function dsGuidance(riskLevel) {
  const risk = dsNormalizeRisk(riskLevel);

  if (risk === 'high') {
    return {
      priority: 'High',
      personnel: '5–6 enforcers',
      monitoring: 'Intensive monitoring',
      actions: [
        'Prioritize high-risk intersections for field deployment.',
        'Increase patrol visibility during peak traffic periods.',
        'Review live monitoring feeds more frequently.',
        'Prepare public advisories when traffic conditions worsen.'
      ]
    };
  }

  if (risk === 'medium') {
    return {
      priority: 'Medium',
      personnel: '3–4 enforcers',
      monitoring: 'Enhanced monitoring',
      actions: [
        'Maintain regular patrol visibility.',
        'Schedule additional monitoring during busy periods.',
        'Review recurring violation types and locations.',
        'Keep high-risk historical hotspots under observation.'
      ]
    };
  }

  return {
    priority: 'Low',
    personnel: 'Regular staffing',
    monitoring: 'Routine monitoring',
    actions: [
      'Continue routine traffic monitoring.',
      'Maintain the standard enforcer schedule.',
      'Periodically inspect historical high-risk intersections.',
      'Reassess conditions when new records become available.'
    ]
  };
}

function dsRiskClass(risk) {
  const normalized = dsNormalizeRisk(risk);
  if (normalized === 'high') return 'ds-risk-high';
  if (normalized === 'medium') return 'ds-risk-medium';
  return 'ds-risk-low';
}

function dsExtractLocations(payload) {
  if (Array.isArray(payload?.data?.locations)) {
    return payload.data.locations;
  }

  if (Array.isArray(payload?.locations)) {
    return payload.locations;
  }

  return [
    ...(payload?.high_risk || []),
    ...(payload?.medium_risk || []),
    ...(payload?.low_risk || [])
  ];
}

function dsGroupLocations(records) {
  return records.reduce(
    (groups, record) => {
      const risk = dsNormalizeRisk(record['Risk Level'] || record.risk_level);

      if (risk === 'high') groups.high.push(record);
      else if (risk === 'medium') groups.medium.push(record);
      else groups.low.push(record);

      return groups;
    },
    { high: [], medium: [], low: [] }
  );
}

function dsSortLocations(records) {
  return [...records].sort(
    (a, b) =>
      Number(b['Total Violations'] ?? b.Total_Violations ?? b.total ?? 0) -
      Number(a['Total Violations'] ?? a.Total_Violations ?? a.total ?? 0)
  );
}

function dsRenderHotspots(risk, records) {
  const count = document.getElementById(
    `ds${risk.charAt(0).toUpperCase()}${risk.slice(1)}Count`
  );

  const list = document.getElementById(
    `ds${risk.charAt(0).toUpperCase()}${risk.slice(1)}List`
  );

  count.textContent = records.length;
  list.innerHTML = '';

  dsSortLocations(records).forEach((record, index) => {
    const name =
      record.Location ||
      record.location ||
      record.violation_location ||
      'Unnamed location';

    const total =
      record['Total Violations'] ??
      record.Total_Violations ??
      record.total ??
      0;

    const item = document.createElement('li');

    item.innerHTML = `
      <span class="ds-rank">${index + 1}</span>
      <span class="ds-location-copy">
        <strong>${name}</strong>
        <small>${Number(total).toLocaleString()} historical records</small>
      </span>
    `;

    list.appendChild(item);
  });
}

function dsHighlightCard(riskLevel) {
  const risk = dsNormalizeRisk(riskLevel);

  document.querySelectorAll('.ds-hotspot-card').forEach(card => {
    card.classList.remove('active');
  });

  const target = document.getElementById(
    `ds${risk.charAt(0).toUpperCase()}${risk.slice(1)}Card`
  );

  target?.classList.add('active');

  document.getElementById('dsForecastEmphasis').textContent =
    `${risk.charAt(0).toUpperCase()}${risk.slice(1)} Risk`;
}

function dsBuildInterpretation(prediction, groups) {
  const risk = dsNormalizeRisk(prediction.risk_level);
  const highCount = groups.high.length;
  const highestLocation =
    dsSortLocations(groups.high)[0]?.Location ||
    dsSortLocations(groups.medium)[0]?.Location ||
    databaseTopLocation;

  const month = prediction.month_name || 'the selected month';

  let opening = '';

  if (risk === 'high') {
    opening =
      `${month} is predicted to have a high traffic-violation risk. ` +
      `This means stronger operational preparation is advisable.`;
  } else if (risk === 'medium') {
    opening =
      `${month} is predicted to have a medium traffic-violation risk. ` +
      `Regular deployment should be supported by additional monitoring during busy periods.`;
  } else {
    opening =
      `${month} is predicted to have a low traffic-violation risk. ` +
      `Routine operations are appropriate, but historically high-risk locations should not be ignored.`;
  }

  return `
    ${opening}
    K-Means clustering identified ${highCount} historically high-risk location${highCount === 1 ? '' : 's'}.
    The highest-priority location is ${highestLocation}.
    Database records also show that ${databaseTopViolation} is the leading violation type,
    while ${databasePeakHour} is the peak recording hour.
    These results should be reviewed together with live computer-vision monitoring before final deployment decisions are made.
  `;
}

function dsRenderRecommendations(actions, groups) {
  const list = document.getElementById('dsRecommendations');
  list.innerHTML = '';

  const highestLocation =
    dsSortLocations(groups.high)[0]?.Location ||
    databaseTopLocation;

  const combinedActions = [
    ...actions,
    `Keep regular visibility near ${highestLocation}.`,
    `Review ${databaseTopViolation} records for targeted enforcement planning.`
  ];

  [...new Set(combinedActions)].forEach(action => {
    const item = document.createElement('li');
    item.innerHTML = `
      <i class="bi bi-check-circle-fill"></i>
      <span>${action}</span>
    `;
    list.appendChild(item);
  });
}

async function loadDecisionSupport() {
  const loading = document.getElementById('dsLoading');
  const errorBox = document.getElementById('dsError');
  const content = document.getElementById('dsContent');

  loading.classList.remove('d-none');
  errorBox.classList.add('d-none');
  content.classList.add('d-none');

  try {
    const [monthlyResponse, hotspotResponse] = await Promise.all([
      fetch(DS_MONTHLY_ENDPOINT, {
        headers: { Accept: 'application/json' },
        cache: 'no-store'
      }),
      fetch(DS_HOTSPOT_ENDPOINT, {
        headers: { Accept: 'application/json' },
        cache: 'no-store'
      })
    ]);

    const monthlyPayload = await monthlyResponse.json();
    const hotspotPayload = await hotspotResponse.json();

    if (!monthlyResponse.ok || !monthlyPayload.success) {
      throw new Error(
        monthlyPayload.message || 'Unable to load monthly prediction.'
      );
    }

    if (!hotspotResponse.ok || !hotspotPayload.success) {
      throw new Error(
        hotspotPayload.message || 'Unable to load hotspot results.'
      );
    }

    const prediction =
      monthlyPayload.data ||
      monthlyPayload.prediction ||
      {};

    const riskLevel = prediction.risk_level || 'Unknown';
    const confidence = Number(prediction.confidence || 0);
    const guidance = dsGuidance(riskLevel);

    const records = dsExtractLocations(hotspotPayload);
    const groups = dsGroupLocations(records);

    const riskBadge = document.getElementById('dsRiskBadge');
    riskBadge.textContent = String(riskLevel).toUpperCase();
    riskBadge.className = `ds-risk-badge ${dsRiskClass(riskLevel)}`;

    document.getElementById('dsPredictionPeriod').textContent =
      `${prediction.month_name || 'Current month'} ${prediction.year || ''}`.trim();

    document.getElementById('dsConfidenceText').textContent =
      `${confidence.toFixed(1)}%`;

    document.getElementById('dsConfidenceBar').style.width =
      `${Math.max(0, Math.min(100, confidence))}%`;

    document.getElementById('dsDeploymentPriority').textContent =
      guidance.priority;

    document.getElementById('dsPersonnel').textContent =
      guidance.personnel;

    document.getElementById('dsMonitoring').textContent =
      guidance.monitoring;

    const focusArea =
      dsSortLocations(groups.high)[0]?.Location ||
      dsSortLocations(groups.medium)[0]?.Location ||
      databaseTopLocation;

    document.getElementById('dsFocusArea').textContent = focusArea;

    dsRenderHotspots('high', groups.high);
    dsRenderHotspots('medium', groups.medium);
    dsRenderHotspots('low', groups.low);
    dsHighlightCard(riskLevel);

    document.getElementById('dsInterpretation').textContent =
      dsBuildInterpretation(prediction, groups);

    dsRenderRecommendations(guidance.actions, groups);

    document.getElementById('dsPlanRisk').textContent = riskLevel;
    document.getElementById('dsPlanPersonnel').textContent =
      guidance.personnel;
    document.getElementById('dsPlanFocus').textContent = focusArea;
    document.getElementById('dsPlanMonitoring').textContent =
      guidance.monitoring;

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  } catch (error) {
    loading.classList.add('d-none');
    errorBox.textContent =
      `${error.message} Make sure Apache and the Flask API on port 5001 are running.`;
    errorBox.classList.remove('d-none');
  }
}

new Chart(document.getElementById('dsTrendChart'), {
  type: 'line',
  data: {
    labels: dsMonths,
    datasets: [{
      label: 'Violations',
      data: dsMonthlyTrend,
      borderColor: '#1d4ed8',
      backgroundColor: 'rgba(59,130,246,.15)',
      fill: true,
      tension: .4,
      borderWidth: 3,
      pointRadius: 3
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: { precision: 0 }
      }
    }
  }
});

new Chart(document.getElementById('dsViolationChart'), {
  type: 'bar',
  data: {
    labels: dsViolationLabels,
    datasets: [{
      label: 'Recorded violations',
      data: dsViolationData,
      backgroundColor: '#6d28d9',
      borderRadius: 7
    }]
  },
  options: {
    responsive: true,
    indexAxis: 'y',
    plugins: {
      legend: { display: false }
    },
    scales: {
      x: {
        beginAtZero: true,
        ticks: { precision: 0 }
      }
    }
  }
});

loadDecisionSupport();
setInterval(loadDecisionSupport, 60000);
</script>

<?php page_end(false); ?>
