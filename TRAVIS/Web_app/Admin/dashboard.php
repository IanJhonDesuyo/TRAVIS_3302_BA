<?php
require_once __DIR__ . '/layout.php';
?>
<style id="travis-ai-v6-overrides">
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

:root{
    --navy-950:#060f1e;
    --navy-900:#0a1a30;
    --navy-800:#0f2544;
    --navy-700:#1a2a5a;
    --border-glass:rgba(255,255,255,.10);
    --blue-accent:#38bdf8;
    --blue-accent-2:#2563eb;
    --cyan-glow:#4fc3f7;
    --text-soft:#c9d8ea;
}

* {
    font-family: 'Poppins', sans-serif;
}

/* ==== Page Background with Image ==== */
body {
    background: url('../../assets/images/nasugbu-municipal-hall.jpg') center 30% / cover fixed no-repeat !important;
    min-height: 100vh;
    position: relative;
}

body::before {
    content: '';
    position: fixed;
    inset: 0;
    background: rgba(255, 255, 255, 0.85);
    z-index: 0;
}

/* ==== Transparent Header with Background Image ==== */
.topbar,
.app-topbar,
.top-header,
.dashboard-topbar,
header.topbar,
.navbar-top {
    background: rgba(10, 26, 48, 0.85) !important;
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.10) !important;
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.08) !important;
    position: relative;
    z-index: 10;
}

.topbar input,
.app-topbar input,
.top-header input,
.dashboard-topbar input,
.navbar-top input {
    background: rgba(255, 255, 255, 0.12) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: #fff !important;
    box-shadow: none !important;
}

.topbar input::placeholder,
.app-topbar input::placeholder,
.top-header input::placeholder,
.dashboard-topbar input::placeholder,
.navbar-top input::placeholder {
    color: rgba(255, 255, 255, 0.6) !important;
}

.topbar .bi-search,
.app-topbar .bi-search,
.top-header .bi-search,
.dashboard-topbar .bi-search,
.navbar-top .bi-search {
    color: rgba(255, 255, 255, 0.7) !important;
}

.topbar .bi-bell,
.app-topbar .bi-bell,
.top-header .bi-bell,
.dashboard-topbar .bi-bell,
.navbar-top .bi-bell,
.topbar .notif-icon,
.app-topbar .notif-icon {
    color: rgba(255, 255, 255, 0.7) !important;
}

.topbar .btn-icon,
.app-topbar .btn-icon,
.top-header .btn-icon,
.dashboard-topbar .btn-icon {
    background: rgba(255, 255, 255, 0.08) !important;
    border: 1px solid rgba(255, 255, 255, 0.10) !important;
}

.topbar .datetime,
.app-topbar .datetime,
.top-header .datetime,
.dashboard-topbar .datetime {
    color: rgba(255, 255, 255, 0.7) !important;
}

.topbar .user-avatar,
.app-topbar .user-avatar,
.top-header .user-avatar,
.dashboard-topbar .user-avatar {
    background: var(--blue-accent-2) !important;
    color: #fff !important;
}

.topbar .user-name,
.app-topbar .user-name,
.top-header .user-name,
.dashboard-topbar .user-name {
    color: #fff !important;
}

/* ==== Content Wrapper - White Background ==== */
#page-wrapper,
.main-content,
.container-fluid,
.dashboard-content {
    position: relative;
    z-index: 1;
    background: transparent !important;
}

/* ==== Dashboard Title Row ==== */
.dashboard-title-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 4px;
}

.dashboard-eyebrow {
    display: inline-block;
    color: #1a2350 !important;
    font-weight: 700;
    letter-spacing: 0.06em;
    font-size: 0.72rem;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.page-title {
    color: #0a1a30 !important;
    font-weight: 800 !important;
    margin-bottom: 6px;
}

.page-sub {
    color: #5a6a8a !important;
    margin-bottom: 0;
}

.system-online-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(52, 211, 153, 0.10) !important;
    border: 1px solid rgba(52, 211, 153, 0.25);
    color: #0a7a6a !important;
    padding: 6px 14px;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 600;
    white-space: nowrap;
}

.system-online-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #34d399;
    box-shadow: 0 0 0 3px rgba(52, 211, 153, 0.20);
}

/* ==== Buttons ==== */
.btn-light {
    background: #f0f4f8 !important;
    border: 1px solid #d0d8e0 !important;
    color: #1a2a3a !important;
    font-weight: 600;
}

.btn-light:hover {
    background: #e4eaf0 !important;
    color: #0a1a30 !important;
}

.btn-primary {
    background: linear-gradient(135deg, #1a2350, #2a3a7a) !important;
    border: none !important;
    color: #fff !important;
    box-shadow: 0 4px 15px rgba(26, 35, 80, 0.25) !important;
    font-weight: 600;
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(26, 35, 80, 0.35) !important;
    filter: brightness(1.05);
}

.btn-light,
.btn-primary {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px;
    width: auto !important;
    height: 36px !important;
    min-width: 0 !important;
    padding: 0 16px !important;
    font-size: 0.78rem !important;
    font-weight: 600 !important;
    line-height: 1 !important;
    white-space: nowrap !important;
    border-radius: 8px !important;
}

.btn-light i,
.btn-primary i {
    font-size: 0.85rem;
    margin: 0 !important;
    line-height: 1;
    display: inline-flex;
    align-items: center;
}

/* ==== Cards - White with Navy Border ==== */
.stat-card,
.dashboard-stat-card,
.section-card,
.ai-decision-card,
.ai-kpi-card,
.hotspot-risk-card,
.ai-confidence-card,
.deployment-kpi,
.compact-metric,
#currentMonitoringCard .mini-metric,
.card,
[class*="card"] {
    background: #ffffff !important;
    border: 2px solid #1a2350 !important;
    border-radius: 16px !important;
    padding: 20px !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04) !important;
    color: #0a1a30 !important;
}

/* Card hover effect */
.stat-card:hover,
.dashboard-stat-card:hover,
.section-card:hover,
.ai-decision-card:hover {
    box-shadow: 0 4px 20px rgba(26, 35, 80, 0.08) !important;
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

/* Card text colors */
.stat-card .stat-label,
.dashboard-stat-card .stat-label,
.section-card small,
.section-card .text-muted,
.ai-decision-card small,
.ai-decision-card .text-muted,
.card small,
.card .text-muted,
[class*="card"] small,
[class*="card"] .text-muted {
    color: #5a6a8a !important;
}

.stat-card .stat-value,
.dashboard-stat-card .stat-value,
.section-card h6,
.ai-decision-card h5,
.ai-decision-card h6,
.card h6,
.compact-metric h4 {
    color: #0a1a30 !important;
}

/* Stat Icons */
.stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 14px;
    font-size: 18px;
}

.stat-icon.tone-primary {
    background: rgba(26, 35, 80, 0.08) !important;
    color: #1a2350 !important;
}

.stat-icon.tone-warning {
    background: rgba(251, 191, 36, 0.10) !important;
    color: #b8860b !important;
}

.stat-icon.tone-success {
    background: rgba(52, 211, 153, 0.10) !important;
    color: #0a7a6a !important;
}

.stat-icon.tone-danger {
    background: rgba(248, 113, 113, 0.10) !important;
    color: #b33c44 !important;
}

/* ==== AI Decision Card Specific ==== */
.ai-decision-card {
    background: #ffffff !important;
    border: 2px solid #1a2350 !important;
    border-radius: 16px !important;
    padding: 24px !important;
}

.ai-decision-card .ai-kicker {
    color: #1a2350 !important;
    font-weight: 700;
    letter-spacing: 0.04em;
    font-size: 0.75rem;
    text-transform: uppercase;
}

.ai-decision-card .ai-card-icon {
    background: rgba(26, 35, 80, 0.06) !important;
    border: 1px solid rgba(26, 35, 80, 0.10) !important;
    color: #1a2350 !important;
}

.ai-decision-card .ai-risk-badge {
    background: rgba(26, 35, 80, 0.04) !important;
    border: 1px solid rgba(26, 35, 80, 0.10) !important;
    color: #1a2350 !important;
}

.ai-decision-card .ai-risk-high {
    color: #b33c44 !important;
    border-color: rgba(220, 53, 69, 0.3) !important;
    background: rgba(220, 53, 69, 0.06) !important;
}

.ai-decision-card .ai-risk-medium {
    color: #b8860b !important;
    border-color: rgba(251, 191, 36, 0.3) !important;
    background: rgba(251, 191, 36, 0.06) !important;
}

.ai-decision-card .ai-risk-low {
    color: #0a7a6a !important;
    border-color: rgba(52, 211, 153, 0.3) !important;
    background: rgba(52, 211, 153, 0.06) !important;
}

.ai-decision-card .ai-confidence-card {
    background: rgba(26, 35, 80, 0.03) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    border-radius: 12px;
    padding: 14px;
    color: #0a1a30 !important;
}

.ai-decision-card .ai-confidence-progress {
    background: rgba(26, 35, 80, 0.06) !important;
}

.ai-decision-card .ai-confidence-progress .progress-bar.ai-risk-high {
    background: #b33c44 !important;
}

.ai-decision-card .ai-confidence-progress .progress-bar.ai-risk-medium {
    background: #b8860b !important;
}

.ai-decision-card .ai-confidence-progress .progress-bar.ai-risk-low {
    background: #0a7a6a !important;
}

.ai-decision-card .deployment-kpi {
    background: rgba(26, 35, 80, 0.03) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    border-radius: 12px;
    padding: 12px 15px;
    margin-bottom: 10px;
    color: #0a1a30 !important;
}

.ai-decision-card .deployment-kpi small {
    color: #5a6a8a !important;
}

.ai-decision-card .deployment-kpi strong {
    color: #0a1a30 !important;
}

.ai-decision-card .ai-subsection-heading {
    border-top: 1px solid rgba(26, 35, 80, 0.08) !important;
}

.ai-decision-card .ai-subsection-heading h6 {
    color: #0a1a30 !important;
}

.ai-decision-card .ai-subsection-heading small {
    color: #5a6a8a !important;
}

.ai-decision-card .ai-highlight-note {
    background: rgba(26, 35, 80, 0.04) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    color: #5a6a8a !important;
}

.ai-decision-card .ai-highlight-note strong {
    color: #0a1a30 !important;
}

.ai-decision-card .ai-action-grid li {
    background: rgba(26, 35, 80, 0.02) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    color: #0a1a30 !important;
}

.ai-decision-card .ai-action-grid li i {
    color: #1a2350 !important;
}

.ai-decision-card .hotspot-risk-card {
    background: #ffffff !important;
    border: 2px solid #1a2350 !important;
    border-radius: 16px !important;
    padding: 16px !important;
}

.ai-decision-card .hotspot-card-head {
    border-bottom: 1px solid rgba(26, 35, 80, 0.08) !important;
}

.ai-decision-card .hotspot-card-head strong {
    color: #0a1a30 !important;
}

.ai-decision-card .hotspot-card-head .hotspot-card-label {
    color: #5a6a8a !important;
}

.ai-decision-card .hotspot-card-list li {
    background: rgba(26, 35, 80, 0.02) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
}

.ai-decision-card .hotspot-list-copy strong {
    color: #0a1a30 !important;
}

.ai-decision-card .hotspot-list-copy small {
    color: #5a6a8a !important;
}

.ai-decision-card .hotspot-list-rank {
    background: linear-gradient(135deg, #1a2350, #2a3a7a) !important;
    color: #fff !important;
}

.ai-decision-card .hotspot-card-high {
    border-top: 6px solid #b33c44 !important;
}

.ai-decision-card .hotspot-card-medium {
    border-top: 6px solid #b8860b !important;
}

.ai-decision-card .hotspot-card-low {
    border-top: 6px solid #0a7a6a !important;
}

.ai-decision-card .ai-label,
.ai-decision-card .ai-period,
.ai-decision-card .ai-model-note {
    color: #5a6a8a !important;
}

.ai-decision-card .ai-loading-state,
.ai-decision-card .hotspot-loading-card {
    color: #5a6a8a !important;
}

/* ==== Hotspot Cards ==== */
.hotspot-risk-card {
    height: 290px !important;
    display: flex !important;
    flex-direction: column !important;
}

.hotspot-card-head {
    flex: 0 0 auto;
    margin-bottom: 10px !important;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(26, 35, 80, 0.08);
}

.hotspot-card-list {
    flex: 1 1 auto !important;
    overflow-y: auto !important;
    overflow-x: hidden;
    padding-right: 6px;
    margin-top: 4px;
}

.hotspot-card-list li {
    padding: 8px 10px !important;
    margin-bottom: 0;
}

.hotspot-card-list::-webkit-scrollbar {
    width: 7px;
}

.hotspot-card-list::-webkit-scrollbar-track {
    background: rgba(26, 35, 80, 0.04);
    border-radius: 20px;
}

.hotspot-card-list::-webkit-scrollbar-thumb {
    background: rgba(26, 35, 80, 0.20);
    border-radius: 20px;
}

.hotspot-card-list::-webkit-scrollbar-thumb:hover {
    background: rgba(26, 35, 80, 0.35);
}

.hotspot-card-highlighted {
    border-color: #2563eb !important;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12), 0 8px 25px rgba(37, 99, 235, 0.08) !important;
}

/* ==== Current Monitoring Mini Metrics ==== */
#currentMonitoringCard .mini-metric {
    min-width: 0;
    min-height: 86px;
    background: rgba(26, 35, 80, 0.02) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    border-radius: 12px !important;
    padding: 13px 14px !important;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

#currentMonitoringCard .mini-metric small {
    color: #5a6a8a !important;
    display: block;
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 8px;
}

#currentMonitoringCard .mini-metric strong {
    color: #0a1a30 !important;
    font-size: 1rem;
    line-height: 1.25;
    overflow-wrap: anywhere;
}

/* ==== Tags ==== */
.tag {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: capitalize;
    background: rgba(26, 35, 80, 0.04);
    color: #5a6a8a;
    border: 1px solid rgba(26, 35, 80, 0.06);
}

.tag-success,
.tag-online,
.tag-paid,
.tag-completed,
.tag-active,
.tag-low {
    background: rgba(52, 211, 153, 0.08) !important;
    color: #0a7a6a !important;
    border-color: rgba(52, 211, 153, 0.15) !important;
}

.tag-danger,
.tag-offline,
.tag-overdue,
.tag-high,
.tag-critical {
    background: rgba(220, 53, 69, 0.08) !important;
    color: #b33c44 !important;
    border-color: rgba(220, 53, 69, 0.15) !important;
}

.tag-warning,
.tag-pending,
.tag-unpaid,
.tag-medium {
    background: rgba(251, 191, 36, 0.08) !important;
    color: #b8860b !important;
    border-color: rgba(251, 191, 36, 0.15) !important;
}

/* ==== Empty State ==== */
.empty-state {
    background: rgba(26, 35, 80, 0.02) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    border-radius: 14px;
    color: #5a6a8a !important;
    text-align: center;
    padding: 26px 10px;
    font-size: 0.9rem;
}

.empty-state i,
.empty-state svg {
    color: #5a6a8a !important;
    fill: #5a6a8a !important;
    opacity: 0.7;
}

/* ==== Dividers ==== */
.border-bottom {
    border-color: rgba(26, 35, 80, 0.06) !important;
}

/* ==== Links ==== */
a {
    color: #1a2350 !important;
}

a:hover {
    color: #2a3a7a !important;
}

.section-head a {
    color: #1a2350 !important;
}

/* ==== Alerts ==== */
.alert-light {
    background: rgba(26, 35, 80, 0.02) !important;
    border: 1px solid rgba(26, 35, 80, 0.06) !important;
    color: #5a6a8a !important;
}

/* ==== Modal ==== */
.modal-content {
    background: #ffffff !important;
    color: #0a1a30 !important;
    border: 2px solid #1a2350 !important;
}

/* ==== Dropdown ==== */
.dropdown-menu {
    background: #ffffff !important;
    color: #0a1a30 !important;
    border: 1px solid rgba(26, 35, 80, 0.10) !important;
}

.dropdown-item {
    color: #0a1a30 !important;
}

.dropdown-item:hover,
.dropdown-item:focus {
    background: rgba(26, 35, 80, 0.04) !important;
    color: #0a1a30 !important;
}

/* ==== Progress ==== */
.progress {
    background: rgba(26, 35, 80, 0.06) !important;
}

/* ==== Responsive ==== */
@media (max-width: 991.98px) {
    .metric-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .hotspot-risk-card {
        height: 260px !important;
    }
}

@media (max-width: 575.98px) {
    .metric-grid {
        grid-template-columns: 1fr;
    }
}

/* ==== Grid Layout ==== */
.metric-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
}

/* ==== Compact Metrics ==== */
.compact-metric h4 {
    color: #0a1a30 !important;
    font-weight: 800;
}

.compact-metric small {
    color: #5a6a8a !important;
}
</style>

<?php
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

    <div class="d-flex align-items-center gap-2 flex-wrap">
      <label class="small text-muted mb-0" for="aiPredictionMonth">Forecast period</label>
      <input class="form-control form-control-sm" type="month" id="aiPredictionMonth" value="<?= esc(date('Y-m', strtotime('first day of next month'))) ?>" min="2000-01" max="2100-12" style="width:155px">
      <button class="btn btn-sm btn-light" type="button" id="refreshPredictionBtn">
        <i class="bi bi-arrow-clockwise me-1"></i>Predict
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
<div class="section-card mb-4" id="currentMonitoringCard">
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
const HOTSPOT_ENDPOINT = '../api/predict_hotspot.php';

const months = <?= json_encode(month_labels()) ?>;
const trendData = <?= json_encode($trendData) ?>;
const topViolationLabels = <?= json_encode($topViolationLabels) ?>;
const topViolationData = <?= json_encode($topViolationData) ?>;

const chartBlues = ['#1a2350', '#2a3a7a', '#3a4a9a', '#4a5aba', '#5a6aca', '#6a7ada'];
const blueGrid = 'rgba(26, 35, 80, .08)';
const blueTicks = '#5a6a8a';

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
    const selectedPeriod = document.getElementById('aiPredictionMonth')?.value || '';
    const [selectedYear, selectedMonth] = selectedPeriod.split('-').map(Number);
    const predictionUrl = selectedYear && selectedMonth
      ? `${MONTHLY_PREDICTION_ENDPOINT}?year=${selectedYear}&month=${selectedMonth}`
      : MONTHLY_PREDICTION_ENDPOINT;

    const response = await fetch(predictionUrl, {
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
    await loadHotspots(riskLevel);
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
document.getElementById('aiPredictionMonth')?.addEventListener('change', loadMonthlyPrediction);

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
        borderColor: '#1a2350',
        backgroundColor: 'rgba(26,35,80,.08)',
        pointBackgroundColor: '#2a3a7a',
        pointBorderColor: '#ffffff',
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