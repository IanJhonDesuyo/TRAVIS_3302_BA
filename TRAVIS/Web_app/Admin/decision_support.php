<?php
require_once __DIR__ . '/layout.php';

/*
|--------------------------------------------------------------------------
| Database analytics for the Decision Support page
|--------------------------------------------------------------------------
*/

$analyticsPeriod = strtolower(trim((string)($_GET['analytics_period'] ?? 'day')));
$analyticsDay = trim((string)($_GET['analytics_day'] ?? date('Y-m-d')));
$analyticsMonth = trim((string)($_GET['analytics_month'] ?? date('Y-m')));
$analyticsYear = trim((string)($_GET['analytics_year'] ?? date('Y')));
$parsedAnalyticsDay = DateTime::createFromFormat('!Y-m-d', $analyticsDay);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $analyticsDay) || !$parsedAnalyticsDay || $parsedAnalyticsDay->format('Y-m-d') !== $analyticsDay) $analyticsDay = date('Y-m-d');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $analyticsMonth)) $analyticsMonth = date('Y-m');
if (!preg_match('/^\d{4}$/', $analyticsYear) || (int)$analyticsYear < 2000 || (int)$analyticsYear > 2100) $analyticsYear = date('Y');
$analyticsPeriods = [
    'day' => ["v.violation_date = '{$analyticsDay}'", date('F j, Y', strtotime($analyticsDay))],
    'month' => ["DATE_FORMAT(v.violation_date, '%Y-%m') = '{$analyticsMonth}'", date('F Y', strtotime($analyticsMonth . '-01'))],
    'year' => ["YEAR(v.violation_date) = {$analyticsYear}", $analyticsYear],
    'all' => ['1=1', 'All Records'],
];
if (!isset($analyticsPeriods[$analyticsPeriod])) $analyticsPeriod = 'day';
[$analyticsDateCondition, $analyticsPeriodLabel] = $analyticsPeriods[$analyticsPeriod];
$analyticsWhere = "({$analyticsDateCondition}) AND v.status <> 'cancelled'";
$paymentPeriods = [
    'day' => "DATE(p.payment_date) = '{$analyticsDay}'",
    'month' => "DATE_FORMAT(p.payment_date, '%Y-%m') = '{$analyticsMonth}'",
    'year' => "YEAR(p.payment_date) = {$analyticsYear}",
    'all' => '1=1',
];
$paymentWhere = $paymentPeriods[$analyticsPeriod] . " AND p.payment_status = 'completed'";

$todayAnalytics = fetch_one("
    SELECT
        COUNT(*) AS violations_today,
        COALESCE(SUM(v.penalty_amount), 0) AS penalties_today,
        COUNT(DISTINCT v.violation_location) AS affected_locations
    FROM violations v
    WHERE {$analyticsWhere}
") ?: [];

$collectionAnalytics = fetch_one("
    SELECT COUNT(*) AS payment_count, COALESCE(SUM(p.amount_paid), 0) AS collected_amount
    FROM payments p
    WHERE {$paymentWhere}
") ?: [];

$topViolation = fetch_one("
    SELECT item.violation_type, COUNT(*) AS total
    FROM violation_items item
    JOIN violations v ON v.violation_id = item.violation_id
    WHERE {$analyticsWhere}
    GROUP BY item.violation_type
    ORDER BY total DESC
    LIMIT 1
") ?: [];

$topLocation = fetch_one("
    SELECT v.violation_location, COUNT(*) AS total
    FROM violations v
    WHERE {$analyticsWhere}
    GROUP BY v.violation_location
    ORDER BY total DESC
    LIMIT 1
") ?: [];

$peakHour = fetch_one("
    SELECT
        HOUR(v.created_at) AS peak_hour,
        COUNT(*) AS total
    FROM violations v
    WHERE {$analyticsWhere}
    GROUP BY HOUR(v.created_at)
    ORDER BY total DESC
    LIMIT 1
") ?: [];

$analyticsChartYear = match ($analyticsPeriod) {
    'day' => (int) substr($analyticsDay, 0, 4),
    'month' => (int) substr($analyticsMonth, 0, 4),
    'year' => (int) $analyticsYear,
    default => (int) date('Y'),
};

$monthlyTrend = monthly_violation_counts($analyticsChartYear);
$monthlyCollectionTrend = monthly_collection_totals($analyticsChartYear);

$locationRows = fetch_all("
    SELECT v.violation_location, COUNT(*) AS total
    FROM violations v
    WHERE {$analyticsWhere}
    GROUP BY v.violation_location
    ORDER BY total DESC
    LIMIT 8
");

$locationLabels = [];
$locationData = [];

foreach ($locationRows as $row) {
    $locationLabels[] = (string) $row['violation_location'];
    $locationData[] = (int) $row['total'];
}

$violationsToday = (int) ($todayAnalytics['violations_today'] ?? 0);
$penaltiesToday = (float) ($todayAnalytics['penalties_today'] ?? 0);
$affectedLocations = (int) ($todayAnalytics['affected_locations'] ?? 0);
$paymentCount = (int) ($collectionAnalytics['payment_count'] ?? 0);
$collectedAmount = (float) ($collectionAnalytics['collected_amount'] ?? 0);
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
   TRAVIS DECISION SUPPORT — NAVY GLASS THEME
   ============================================================ */

@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

:root{
    --navy-950:#060f1e;
    --navy-900:#0a1a30;
    --navy-800:#0f2544;
    --border-glass:rgba(255,255,255,.10);
    --blue-accent:#38bdf8;
    --blue-accent-2:#2563eb;
    --cyan-glow:#4fc3f7;
    --text-soft:#c9d8ea;
}

body{
    font-family:'Poppins', sans-serif !important;
    background:
        radial-gradient(circle at 10% 10%, rgba(56,189,248,.08), transparent 30%),
        radial-gradient(circle at 90% 80%, rgba(37,99,235,.08), transparent 35%),
        linear-gradient(160deg, var(--navy-950) 0%, var(--navy-900) 45%, var(--navy-800) 100%) !important;
    color:#fff !important;
}

/* ==== Topbar alignment to navy theme ==== */
.topbar,
.app-topbar,
.top-header,
.dashboard-topbar,
header.topbar,
.navbar-top{
    background:var(--navy-900) !important;
    border-bottom:1px solid var(--border-glass) !important;
    box-shadow:none !important;
}

.topbar input,
.app-topbar input,
.top-header input,
.dashboard-topbar input,
.navbar-top input{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
    box-shadow:none !important;
}

.topbar input::placeholder,
.app-topbar input::placeholder,
.top-header input::placeholder,
.dashboard-topbar input::placeholder,
.navbar-top input::placeholder{
    color:var(--text-soft) !important;
}

.topbar .bi-search,
.app-topbar .bi-search,
.top-header .bi-search,
.dashboard-topbar .bi-search,
.navbar-top .bi-search{
    color:var(--text-soft) !important;
}

.topbar .bi-bell,
.app-topbar .bi-bell,
.top-header .bi-bell,
.dashboard-topbar .bi-bell,
.navbar-top .bi-bell,
.topbar .notif-icon,
.app-topbar .notif-icon{
    color:var(--text-soft) !important;
}

.topbar .btn-icon,
.app-topbar .btn-icon,
.top-header .btn-icon,
.dashboard-topbar .btn-icon{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
}

.topbar .datetime,
.app-topbar .datetime,
.top-header .datetime,
.dashboard-topbar .datetime{
    color:var(--text-soft) !important;
}

.topbar .user-avatar,
.app-topbar .user-avatar,
.top-header .user-avatar,
.dashboard-topbar .user-avatar{
    background:var(--blue-accent-2) !important;
    color:#fff !important;
}

.topbar .user-name,
.app-topbar .user-name,
.top-header .user-name,
.dashboard-topbar .user-name{
    color:#fff !important;
}

/* ==== Reports / Open Monitoring buttons: exact size fit ==== */
.btn-light,
.btn-primary{
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:4px;
    width:auto !important;
    height:32px !important;
    min-width:0 !important;
    padding:0 12px !important;
    font-size:.75rem !important;
    font-weight:600 !important;
    line-height:1 !important;
    white-space:nowrap !important;
    border-radius:6px !important;
}

.btn-light i,
.btn-primary i{
    font-size:.80rem;
    margin:0 !important;
    line-height:1;
    display:inline-flex;
    align-items:center;
}

/* ==== System Online Badge - exact size for dot ==== */
.system-online-badge{
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:8px !important;
    background:rgba(52,211,153,.12) !important;
    border:1px solid rgba(52,211,153,.3) !important;
    color:#34d399 !important;
    padding:6px 14px !important;
    border-radius:999px !important;
    font-size:.75rem !important;
    font-weight:600 !important;
    white-space:nowrap !important;
    height:32px !important;
}

.system-online-dot{
    width:8px !important;
    height:8px !important;
    min-width:8px !important;
    min-height:8px !important;
    border-radius:50% !important;
    display:inline-block !important;
    flex-shrink:0 !important;
}

.system-online-dot.online{
    background:#34d399 !important;
    box-shadow:0 0 0 3px rgba(52,211,153,.25) !important;
}

.system-online-dot.offline{
    background:#f87171 !important;
    box-shadow:0 0 0 3px rgba(248,113,113,.25) !important;
}

.dashboard-title-row{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:16px;margin-bottom:4px}
.dashboard-eyebrow{
    display:inline-block;color:var(--cyan-glow) !important;font-weight:700;
    letter-spacing:.06em;font-size:.72rem;text-transform:uppercase;margin-bottom:8px;
}
.page-title{color:#fff !important;font-weight:800 !important;margin-bottom:6px}
.page-sub{color:var(--text-soft) !important;margin-bottom:0}

.btn-light{background:rgba(255,255,255,.06) !important;border:1px solid var(--border-glass) !important;color:#fff !important;}
.btn-light:hover{background:rgba(255,255,255,.14) !important;color:#fff !important}
.btn-primary{
    background:linear-gradient(90deg,var(--blue-accent-2),var(--cyan-glow)) !important;
    border:none !important;color:#fff !important;
    box-shadow:0 12px 26px rgba(37,99,235,.32) !important;
}
.btn-primary:hover{filter:brightness(1.08)}

.stat-card,.dashboard-stat-card{
    background:rgba(255,255,255,.03) !important;
    border:1px solid var(--border-glass) !important;
    border-radius:18px !important;
    padding:20px !important;
    box-shadow:0 14px 30px rgba(0,0,0,.28) !important;
    color:#fff !important;
}
.stat-icon{
    width:44px;height:44px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    margin-bottom:14px;font-size:18px;
}
.stat-icon.tone-primary{background:rgba(56,189,248,.14) !important;color:var(--cyan-glow) !important}
.stat-icon.tone-warning{background:rgba(251,191,36,.14) !important;color:#fbbf24 !important}
.stat-icon.tone-success{background:rgba(52,211,153,.14) !important;color:#34d399 !important}
.stat-icon.tone-danger{background:rgba(248,113,113,.14) !important;color:#f87171 !important}
.stat-label{color:var(--text-soft) !important;font-size:.8rem;margin-bottom:4px}
.stat-value{color:#fff !important;font-size:1.7rem;font-weight:800;line-height:1.2}

.section-card{
    background:rgba(255,255,255,.03) !important;
    border:1px solid var(--border-glass) !important;
    border-radius:18px !important;
    padding:20px !important;
    box-shadow:0 14px 30px rgba(0,0,0,.28) !important;
    color:#fff !important;
}
.section-head{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:16px}
.section-head h6{color:#fff !important;font-weight:700;margin:0}
.section-card small,.section-card .text-muted{color:var(--text-soft) !important}
.section-head a{color:var(--cyan-glow) !important}

.mini-metric{
    background:rgba(255,255,255,.03);
    border:1px solid var(--border-glass);
    border-radius:12px;padding:12px 14px;
}
.mini-metric small{color:var(--text-soft);display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;margin-bottom:4px}
.mini-metric strong{color:#fff}

.tag{
    display:inline-block;padding:4px 12px;border-radius:999px;
    font-size:.72rem;font-weight:700;text-transform:capitalize;
    background:rgba(255,255,255,.08);color:var(--text-soft);
    border:1px solid var(--border-glass);
}
.tag-success,.tag-online,.tag-paid,.tag-completed,.tag-active,.tag-low{
    background:rgba(52,211,153,.14) !important;color:#34d399 !important;border-color:rgba(52,211,153,.3) !important;
}
.tag-danger,.tag-offline,.tag-overdue,.tag-high,.tag-critical{
    background:rgba(248,113,113,.14) !important;color:#f87171 !important;border-color:rgba(248,113,113,.3) !important;
}
.tag-warning,.tag-pending,.tag-unpaid,.tag-medium{
    background:rgba(251,191,36,.14) !important;color:#fbbf24 !important;border-color:rgba(251,191,36,.3) !important;
}
.tag-info{
    background:rgba(56,189,248,.14) !important;color:var(--cyan-glow) !important;border-color:rgba(56,189,248,.3) !important;
}
.tag-muted{
    background:rgba(255,255,255,.06) !important;color:var(--text-soft) !important;
}

.empty-state{
    background:rgba(255,255,255,.03) !important;
    border:1px solid var(--border-glass) !important;
    border-radius:14px;
    color:var(--text-soft) !important;
    text-align:center;
    padding:26px 10px;
    font-size:.9rem;
}
.empty-state i,.empty-state svg{color:var(--text-soft) !important;fill:var(--text-soft) !important;opacity:.7}

.border-bottom{border-color:var(--border-glass) !important}
.alert-light{background:rgba(255,255,255,.03) !important;border:1px solid var(--border-glass) !important;color:var(--text-soft) !important}
.alert-success{background:rgba(52,211,153,.12) !important;border:1px solid rgba(52,211,153,.3) !important;color:#34d399 !important}
.alert-warning{background:rgba(251,191,36,.12) !important;border:1px solid rgba(251,191,36,.3) !important;color:#fbbf24 !important}

a{color:var(--cyan-glow)}
a:hover{color:#fff}

.table{color:#fff !important}
.table thead th{color:var(--text-soft) !important;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;border-color:var(--border-glass) !important;font-weight:600}
.table td,.table th{border-color:var(--border-glass) !important;vertical-align:middle}
.table-responsive{border-radius:12px}

.form-control{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
}
.form-control:focus{
    background:rgba(255,255,255,.09) !important;
    border-color:var(--blue-accent) !important;
    color:#fff !important;
    box-shadow:0 0 0 .2rem rgba(56,189,248,.18) !important;
}

/* ==== Custom DS Components ==== */
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

.ds-risk-low { background: rgba(52,211,153,.15); color: #34d399; border: 1px solid rgba(52,211,153,.3); }
.ds-risk-medium { background: rgba(251,191,36,.15); color: #fbbf24; border: 1px solid rgba(251,191,36,.3); }
.ds-risk-high { background: rgba(248,113,113,.15); color: #f87171; border: 1px solid rgba(248,113,113,.3); }

.ds-confidence {
    margin-top: 1rem;
    padding: .9rem;
    border: 1px solid var(--border-glass);
    border-radius: 13px;
    background: rgba(255,255,255,.04);
}

.ds-progress {
    height: 8px;
    margin-top: .45rem;
    border-radius: 999px;
    background: rgba(255,255,255,.08);
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
    border: 1px solid var(--border-glass);
    border-radius: 14px;
    background: rgba(255,255,255,.03);
}

.ds-kpi small {
    color: var(--text-soft);
}

.ds-kpi strong {
    display: block;
    margin-top: .25rem;
    color: #fff;
    font-size: 1.05rem;
}

.ds-guidance-card {
    position: relative;
    overflow: hidden;
    height: 100%;
    padding: 0 !important;
}

.ds-guidance-card::before {
    content: '';
    position: absolute;
    inset: 0 0 auto;
    height: 5px;
    background: linear-gradient(90deg, #2563eb, #20a4c7, #18a878);
}

.ds-guidance-head {
    display: flex;
    align-items: center;
    gap: .8rem;
    padding: 1.2rem 1.25rem 1rem;
    border-bottom: 1px solid var(--border-glass);
}

.ds-guidance-mark,
.ds-kpi-icon {
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    border-radius: 12px;
}

.ds-guidance-mark {
    width: 43px;
    height: 43px;
    background: rgba(56,189,248,.14);
    color: var(--cyan-glow);
    font-size: 1.05rem;
}

.ds-guidance-card .ds-kpi-grid {
    padding: 1rem 1.25rem 1.25rem;
}

.ds-guidance-card .ds-kpi {
    display: flex;
    align-items: flex-start;
    gap: .7rem;
    min-height: 104px;
    transition: transform .2s ease, border-color .2s ease;
}

.ds-guidance-card .ds-kpi:hover {
    transform: translateY(-2px);
    border-color: rgba(56,189,248,.3);
}

.ds-kpi-icon {
    width: 35px;
    height: 35px;
    background: rgba(56,189,248,.12);
    color: var(--cyan-glow);
}

.ds-kpi-copy { min-width: 0; }
.ds-kpi-copy small { display: block; }
.ds-kpi-copy strong { line-height: 1.35; }
.ds-kpi-help {
    display: block;
    margin-top: .35rem;
    color: var(--text-soft);
    font-size: .66rem;
    line-height: 1.4;
}

body.municipal-portal .content .ds-guidance-card {
    border: 1px solid rgba(16,47,73,.16) !important;
    background: linear-gradient(145deg, #fff, #f5fbfa) !important;
    box-shadow: 0 14px 32px rgba(16,47,73,.09) !important;
}

body.municipal-portal .content .ds-guidance-head {
    border-bottom-color: rgba(16,47,73,.09);
    background: linear-gradient(110deg, #f7fbff, #fff 55%, #f1faf7);
}

body.municipal-portal .content .ds-guidance-mark {
    background: #e5f6f4 !important;
    color: #087d78 !important;
}

body.municipal-portal .content .ds-guidance-card .ds-kpi {
    border: 1px solid #d7e4e8 !important;
    background: rgba(255,255,255,.9) !important;
    box-shadow: 0 7px 16px rgba(16,47,73,.05);
}

body.municipal-portal .content .ds-kpi-icon {
    background: #e7f5f7 !important;
    color: #168eb2 !important;
}

body.municipal-portal .content .ds-kpi-copy small { color: #657a75 !important; }
body.municipal-portal .content .ds-kpi-copy strong { color: #102f49 !important; }
body.municipal-portal .content .ds-kpi-help { color: #71849a !important; }
body.municipal-portal .content .ds-guidance-card.is-high::before { background: linear-gradient(90deg, #dc3545, #ef7a68); }
body.municipal-portal .content .ds-guidance-card.is-medium::before { background: linear-gradient(90deg, #e5a100, #f2c052); }
body.municipal-portal .content .ds-guidance-card.is-low::before { background: linear-gradient(90deg, #18a878, #55c89f); }

/* Equal-height layouts with contained scrolling for long dynamic content. */
.ds-equal-card { height: 100%; }

.ds-guidance-card .ds-kpi {
    height: 118px;
    min-height: 118px;
}

.ds-guidance-card .ds-kpi-copy {
    max-height: 86px;
    padding-right: .3rem;
    overflow-y: auto;
    scrollbar-gutter: stable;
}

.ds-analysis-kpi {
    min-height: 98px;
}

.ds-insight-card {
    display: flex;
    flex-direction: column;
    height: 330px;
}

.ds-insight-card .section-head {
    flex: 0 0 auto;
}

.ds-insight-card .ds-interpretation,
.ds-insight-card .ds-recommendation-list {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
    padding-right: .55rem;
    scrollbar-gutter: stable;
}

.ds-deployment-row {
    height: 132px;
    min-height: 132px;
}

.ds-deployment-row .ds-plan-copy {
    max-height: 96px;
    padding-right: .3rem;
    overflow-y: auto;
    scrollbar-gutter: stable;
}

.ds-guidance-card .ds-kpi-copy,
.ds-insight-card .ds-interpretation,
.ds-insight-card .ds-recommendation-list,
.ds-deployment-row .ds-plan-copy,
.ds-hotspot-list {
    scrollbar-width: thin;
    scrollbar-color: rgba(8,125,120,.42) rgba(16,47,73,.06);
}

.ds-guidance-card .ds-kpi-copy::-webkit-scrollbar,
.ds-insight-card .ds-interpretation::-webkit-scrollbar,
.ds-insight-card .ds-recommendation-list::-webkit-scrollbar,
.ds-deployment-row .ds-plan-copy::-webkit-scrollbar {
    width: 6px;
}

.ds-guidance-card .ds-kpi-copy::-webkit-scrollbar-thumb,
.ds-insight-card .ds-interpretation::-webkit-scrollbar-thumb,
.ds-insight-card .ds-recommendation-list::-webkit-scrollbar-thumb,
.ds-deployment-row .ds-plan-copy::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgba(8,125,120,.38);
}

.ds-guidance-card .ds-kpi-copy::-webkit-scrollbar-track,
.ds-insight-card .ds-interpretation::-webkit-scrollbar-track,
.ds-insight-card .ds-recommendation-list::-webkit-scrollbar-track,
.ds-deployment-row .ds-plan-copy::-webkit-scrollbar-track {
    background: rgba(16,47,73,.05);
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
    color: #fff;
    font-weight: 800;
}

.ds-section-title small {
    color: var(--text-soft);
}

.ds-hotspot-card {
    height: 310px;
    display: flex;
    flex-direction: column;
    padding: 1.1rem;
    border: 1px solid var(--border-glass);
    border-radius: 18px;
    background: rgba(255,255,255,.03);
    box-shadow: 0 10px 24px rgba(0,0,0,.15);
    backdrop-filter: blur(14px) saturate(115%);
    transition: transform .34s cubic-bezier(.22,1,.36,1), box-shadow .34s ease, border-color .34s ease;
    animation: ds-card-enter .6s cubic-bezier(.22,1,.36,1) both;
}

.ds-hotspot-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 18px 36px rgba(0,0,0,.25);
}

@keyframes ds-card-enter {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}

.ds-hotspot-card.high { border-top: 6px solid #f87171; }
.ds-hotspot-card.medium { border-top: 6px solid #fbbf24; }
.ds-hotspot-card.low { border-top: 6px solid #34d399; }

.ds-hotspot-card.active {
    border-color: var(--blue-accent);
    box-shadow: 0 0 0 3px rgba(56,189,248,.10), 0 16px 34px rgba(0,0,0,.20);
}

.ds-hotspot-head {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding-bottom: .85rem;
    border-bottom: 1px solid var(--border-glass);
}

.ds-hotspot-head strong {
    color: #fff;
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
    border: 1px solid var(--border-glass);
    border-radius: 11px;
    background: rgba(255,255,255,.04);
}

.ds-rank {
    display: grid;
    place-items: center;
    flex: 0 0 25px;
    width: 25px;
    height: 25px;
    border-radius: 8px;
    background: rgba(56,189,248,.15);
    color: var(--cyan-glow);
    font-size: .72rem;
    font-weight: 800;
}

.ds-location-copy {
    display: grid;
}

.ds-location-copy strong {
    color: #fff;
    font-size: .8rem;
    line-height: 1.3;
}

.ds-location-copy small {
    color: var(--text-soft);
    font-size: .69rem;
}

.ds-hotspot-list::-webkit-scrollbar {
    width: 7px;
}

.ds-hotspot-list::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: rgba(56,189,248,.35);
}

.ds-analysis-grid {
    display: grid;
    grid-template-columns: repeat(4,minmax(0,1fr));
    gap: .85rem;
}

.ds-analysis-kpi {
    padding: 1rem;
    border: 1px solid var(--border-glass);
    border-radius: 14px;
    background: rgba(255,255,255,.03);
}

.ds-analysis-kpi small {
    color: var(--text-soft);
}

.ds-analysis-kpi strong {
    display: block;
    margin-top: .3rem;
    color: #fff;
    font-size: 1.12rem;
}

.ds-interpretation {
    min-height: 190px;
    padding: 1.1rem;
    border-left: 5px solid var(--cyan-glow);
    border-radius: 14px;
    background: rgba(255,255,255,.03);
    color: var(--text-soft);
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
    border: 1px solid var(--border-glass);
    border-radius: 12px;
    background: rgba(255,255,255,.03);
    color: var(--text-soft);
}

.ds-recommendation-list i {
    color: var(--cyan-glow);
}

.ds-deployment-card {
    position: relative;
    overflow: hidden;
    padding: 0 !important;
    background:
        radial-gradient(circle at 92% 0%, rgba(56,189,248,.16), transparent 32%),
        linear-gradient(145deg, rgba(15,37,68,.98), rgba(6,15,30,.96)) !important;
}

.ds-deployment-card::before {
    content: '';
    position: absolute;
    inset: 0 0 auto;
    height: 4px;
    background: linear-gradient(90deg, var(--blue-accent-2), var(--cyan-glow), #34d399);
}

.ds-deployment-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 1.25rem 1.35rem 1rem;
    border-bottom: 1px solid var(--border-glass);
}

.ds-deployment-heading {
    display: flex;
    align-items: center;
    gap: .85rem;
}

.ds-deployment-mark {
    display: grid;
    place-items: center;
    flex: 0 0 46px;
    width: 46px;
    height: 46px;
    border: 1px solid rgba(56,189,248,.3);
    border-radius: 14px;
    background: linear-gradient(145deg, rgba(56,189,248,.2), rgba(37,99,235,.14));
    color: var(--cyan-glow) !important;
    font-size: 1.2rem;
    box-shadow: 0 10px 24px rgba(0,0,0,.18);
}

.ds-deployment-heading h6 {
    margin: 0 0 .2rem;
    color: #fff;
    font-size: 1rem;
    font-weight: 800;
}

.ds-plan-status {
    display: inline-flex;
    align-items: center;
    gap: .45rem;
    padding: .5rem .8rem;
    border: 1px solid rgba(56,189,248,.25);
    border-radius: 999px;
    background: rgba(56,189,248,.1);
    color: #bae6fd !important;
    font-size: .72rem;
    font-weight: 700;
    white-space: nowrap;
}

.ds-plan-status i {
    color: #34d399 !important;
    font-size: .55rem;
}

.ds-plan-status.is-high {
    border-color: rgba(248,113,113,.34);
    background: rgba(248,113,113,.12);
    color: #fecaca !important;
}

.ds-plan-status.is-high i { color: #f87171 !important; }
.ds-plan-status.is-medium {
    border-color: rgba(251,191,36,.32);
    background: rgba(251,191,36,.11);
    color: #fde68a !important;
}
.ds-plan-status.is-medium i { color: #fbbf24 !important; }
.ds-plan-status.is-low {
    border-color: rgba(52,211,153,.3);
    background: rgba(52,211,153,.1);
    color: #a7f3d0 !important;
}
.ds-plan-status.is-low i { color: #34d399 !important; }

.ds-deployment-table {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: .85rem;
    padding: 1.15rem 1.35rem;
}

.ds-deployment-row {
    position: relative;
    display: flex;
    align-items: flex-start;
    gap: .8rem;
    min-height: 116px;
    padding: 1rem;
    overflow: hidden;
    border: 1px solid var(--border-glass);
    border-radius: 15px;
    background: rgba(255,255,255,.045);
    transition: transform .22s ease, border-color .22s ease, background .22s ease;
}

.ds-deployment-row:hover {
    transform: translateY(-2px);
    border-color: rgba(56,189,248,.32);
    background: rgba(255,255,255,.07);
}

.ds-deployment-row::after {
    content: '';
    position: absolute;
    right: -28px;
    bottom: -38px;
    width: 88px;
    height: 88px;
    border-radius: 50%;
    background: rgba(56,189,248,.07);
}

.ds-plan-icon {
    display: grid;
    place-items: center;
    flex: 0 0 36px;
    width: 36px;
    height: 36px;
    border-radius: 11px;
    background: rgba(56,189,248,.13);
    color: var(--cyan-glow) !important;
    font-size: 1rem;
}

.ds-plan-copy {
    position: relative;
    z-index: 1;
    min-width: 0;
}

.ds-deployment-row span:not(.ds-plan-icon) {
    display: block;
    margin-bottom: .4rem;
    color: var(--text-soft) !important;
    font-size: .7rem;
    font-weight: 700;
    letter-spacing: .045em;
    text-transform: uppercase;
}

.ds-deployment-row strong {
    display: block;
    color: #fff !important;
    font-size: .9rem;
    line-height: 1.45;
    overflow-wrap: anywhere;
}

.ds-deployment-row.is-high {
    border-color: rgba(248,113,113,.36);
    background: linear-gradient(145deg, rgba(248,113,113,.13), rgba(255,255,255,.035));
}

.ds-deployment-row.is-high .ds-plan-icon { background: rgba(248,113,113,.16); color: #fca5a5 !important; }
.ds-deployment-row.is-medium { border-color: rgba(251,191,36,.34); }
.ds-deployment-row.is-medium .ds-plan-icon { background: rgba(251,191,36,.15); color: #fbbf24 !important; }
.ds-deployment-row.is-low { border-color: rgba(52,211,153,.32); }
.ds-deployment-row.is-low .ds-plan-icon { background: rgba(52,211,153,.14); color: #34d399 !important; }

.ds-deployment-note {
    display: flex;
    align-items: center;
    gap: .6rem;
    margin: 0 1.35rem 1.2rem;
    padding: .75rem .9rem;
    border: 1px solid rgba(56,189,248,.16);
    border-radius: 12px;
    background: rgba(56,189,248,.065);
    color: var(--text-soft) !important;
    font-size: .76rem;
    line-height: 1.5;
}

.ds-deployment-note i {
    color: var(--cyan-glow) !important;
    font-size: 1rem;
}

.ds-chart-card canvas {
    max-height: 300px;
}

/* ==== Catch-all: any remaining white cards ==== */
.card,
.badge,
.rounded-pill,
.bg-white,
.bg-light,
[class*="card"]{
    background-color:rgba(255,255,255,.03) !important;
    color:#fff !important;
    border-color:var(--border-glass) !important;
}

.card *:not(.tag):not(.system-online-dot),
[class*="card"] *:not(.tag):not(.system-online-dot){
    color:inherit;
}

.card small,
[class*="card"] small,
.card .text-muted,
[class*="card"] .text-muted{
    color:var(--text-soft) !important;
}

.rounded-pill:not(.tag):not(.system-online-badge),
span[style*="border-radius:999px"]:not(.tag):not(.system-online-badge),
span[style*="border-radius: 999px"]:not(.tag):not(.system-online-badge),
div[style*="border-radius:999px"]:not(.tag):not(.system-online-badge),
div[style*="border-radius: 999px"]:not(.tag):not(.system-online-badge){
    background:rgba(255,255,255,.05) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
}

.progress{
    background:rgba(255,255,255,.08) !important;
}

.dropdown-menu,
.popover,
.tooltip-inner{
    background:var(--navy-800) !important;
    color:#fff !important;
    border:1px solid var(--border-glass) !important;
}

.dropdown-item{
    color:var(--text-soft) !important;
}

.dropdown-item:hover,
.dropdown-item:focus{
    background:rgba(255,255,255,.06) !important;
    color:#fff !important;
}

.modal-content{
    background:var(--navy-900) !important;
    color:#fff !important;
    border:1px solid var(--border-glass) !important;
}

/* Deployment plan: scoped after the page-wide card reset so its hierarchy,
   contrast, and four equal metric panels remain intact. */
body.municipal-portal .content .ds-deployment-card {
    overflow: hidden;
    padding: 0 !important;
    border: 1px solid rgba(16,47,73,.16) !important;
    border-radius: 20px !important;
    background: #fff !important;
    color: #10202c !important;
    box-shadow: 0 18px 42px rgba(16,47,73,.11) !important;
}

body.municipal-portal .content .ds-deployment-card::before {
    height: 5px;
    background: linear-gradient(90deg, #2563eb 0%, #20a4c7 48%, #18a878 100%);
}

body.municipal-portal .content .ds-deployment-header {
    padding: 1.2rem 1.4rem 1.05rem;
    border-bottom: 1px solid rgba(16,47,73,.09);
    background: linear-gradient(110deg, #f7fbff 0%, #fff 56%, #f3fbf8 100%);
}

body.municipal-portal .content .ds-deployment-mark {
    border-color: rgba(8,125,120,.2);
    background: #e7f7f5 !important;
    color: #087d78 !important;
    box-shadow: none;
}

body.municipal-portal .content .ds-deployment-heading h6 {
    color: #102f49 !important;
    font-size: 1.05rem;
}

body.municipal-portal .content .ds-deployment-heading small {
    color: #60716b !important;
}

body.municipal-portal .content .ds-plan-status {
    border-color: rgba(8,125,120,.2) !important;
    background: #eaf7f5 !important;
    color: #087d78 !important;
}

body.municipal-portal .content .ds-plan-status.is-high {
    border-color: #fecaca !important;
    background: #fff1f1 !important;
    color: #b42336 !important;
}
body.municipal-portal .content .ds-plan-status.is-medium {
    border-color: #fde0a4 !important;
    background: #fff8e9 !important;
    color: #99610b !important;
}
body.municipal-portal .content .ds-plan-status.is-low {
    border-color: #bce8d4 !important;
    background: #edf9f3 !important;
    color: #147153 !important;
}

body.municipal-portal .content .ds-deployment-table {
    gap: 1rem;
    padding: 1.25rem 1.4rem 1.1rem;
    background: #fff;
}

body.municipal-portal .content .ds-deployment-row {
    min-height: 122px;
    padding: 1.05rem;
    border: 1px solid #d9e5eb !important;
    border-top: 3px solid #4db7d7 !important;
    border-radius: 14px;
    background: #f9fcfd !important;
    color: #10202c !important;
    box-shadow: 0 8px 20px rgba(16,47,73,.055);
}

body.municipal-portal .content .ds-deployment-row::after {
    right: -35px;
    bottom: -46px;
    width: 105px;
    height: 105px;
    background: rgba(49,174,204,.07);
}

body.municipal-portal .content .ds-deployment-row:hover {
    border-color: #9ed8e8 !important;
    border-top-color: #087d78 !important;
    background: #f4fbfb !important;
    box-shadow: 0 12px 26px rgba(16,47,73,.09);
}

body.municipal-portal .content .ds-plan-icon {
    background: #e3f5f8 !important;
    color: #168eb2 !important;
}

body.municipal-portal .content .ds-deployment-row span:not(.ds-plan-icon) {
    color: #71849a !important;
    font-size: .68rem;
}

body.municipal-portal .content .ds-deployment-row strong {
    color: #10202c !important;
    font-size: .9rem;
    line-height: 1.45;
}

body.municipal-portal .content .ds-deployment-row.is-high {
    border-color: #fecaca !important;
    border-top-color: #ef6b6b !important;
    background: linear-gradient(145deg, #fff5f5, #fff) !important;
}
body.municipal-portal .content .ds-deployment-row.is-high .ds-plan-icon {
    background: #ffe4e4 !important;
    color: #d94f55 !important;
}
body.municipal-portal .content .ds-deployment-row.is-medium {
    border-top-color: #e9a72e !important;
    background: #fffbf2 !important;
}
body.municipal-portal .content .ds-deployment-row.is-low {
    border-top-color: #23a477 !important;
    background: #f4fbf7 !important;
}

body.municipal-portal .content .ds-deployment-note {
    margin: 0 1.4rem 1.3rem;
    padding: .8rem 1rem;
    border-color: #cbe4ed;
    background: #f0f8fb !important;
    color: #526b64 !important;
}

body.municipal-portal .content .ds-deployment-note i {
    color: #168eb2 !important;
}

@media (max-width: 991.98px) {
    .ds-analysis-grid {
        grid-template-columns: repeat(2,minmax(0,1fr));
    }
    .ds-deployment-table {
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
    .ds-deployment-header {
        align-items: flex-start;
        flex-direction: column;
    }
    .ds-deployment-table {
        grid-template-columns: 1fr;
        padding: 1rem;
    }
    .ds-deployment-row {
        min-height: 96px;
    }
    .ds-deployment-note {
        margin: 0 1rem 1rem;
    }
}
</style>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div class="dashboard-title-row">
    <div>
      <span class="dashboard-eyebrow">TRAVIS INTELLIGENCE MODULE</span>
      <h3 class="page-title">Decision Support</h3>
      <p class="page-sub">Machine-learning predictions, historical patterns, database analytics, and operational recommendations.</p>
    </div>
  </div>

  <div class="d-flex gap-2">
    <span class="system-online-badge">
      <span class="system-online-dot online"></span>
      AI services connected
    </span>
  </div>
</div>

<div id="dsLoading" class="alert alert-light border">
  <div class="spinner-border spinner-border-sm me-2" role="status"></div>
  Loading decision-support information...
</div>

<div id="dsError" class="alert alert-danger d-none"></div>

<div id="dsContent" class="d-none">
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="section-card ds-equal-card">
        <div class="section-head">
          <div>
            <h6>Monthly Violation-Risk Prediction</h6>
            <small class="text-muted">Based on historical monthly violation records</small>
          </div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <label class="small text-muted mb-0" for="dsPredictionMonth">Forecast period</label>
            <input class="form-control form-control-sm" type="month" id="dsPredictionMonth" value="<?= esc(date('Y-m', strtotime('first day of next month'))) ?>" min="2000-01" max="2100-12" style="width:155px">
            <button class="btn btn-sm btn-light" type="button" id="dsPredictBtn"><i class="bi bi-stars me-1"></i>Predict</button>
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
        <p class="small text-muted mt-3 mb-0">
          This forecast does not use traffic-flow, congestion, or live camera data.
        </p>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="section-card ds-guidance-card ds-equal-card" id="dsGuidanceCard">
        <div class="ds-guidance-head">
          <span class="ds-guidance-mark" aria-hidden="true"><i class="bi bi-compass-fill"></i></span>
          <div>
            <h6 class="mb-1">Recommended Response</h6>
            <small class="text-muted">A simple guide for planning the next action</small>
          </div>
        </div>

        <div class="ds-kpi-grid">
          <div class="ds-kpi">
            <span class="ds-kpi-icon" aria-hidden="true"><i class="bi bi-flag-fill"></i></span>
            <div class="ds-kpi-copy">
              <small>Level of attention</small>
              <strong id="dsDeploymentPriority">—</strong>
              <span class="ds-kpi-help">How soon the area should be reviewed</span>
            </div>
          </div>

          <div class="ds-kpi">
            <span class="ds-kpi-icon" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
            <div class="ds-kpi-copy">
              <small>Recommended response</small>
              <strong id="dsPersonnel">—</strong>
              <span class="ds-kpi-help">Suggested short-term field activity</span>
            </div>
          </div>

          <div class="ds-kpi">
            <span class="ds-kpi-icon" aria-hidden="true"><i class="bi bi-clipboard-data-fill"></i></span>
            <div class="ds-kpi-copy">
              <small>Check results</small>
              <strong id="dsMonitoring">—</strong>
              <span class="ds-kpi-help">How often progress should be checked</span>
            </div>
          </div>

          <div class="ds-kpi">
            <span class="ds-kpi-icon" aria-hidden="true"><i class="bi bi-geo-alt-fill"></i></span>
            <div class="ds-kpi-copy">
              <small>Start at this location</small>
              <strong id="dsFocusArea">—</strong>
              <span class="ds-kpi-help">Confirm current conditions before deployment</span>
            </div>
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
          <span class="stat-icon tone-danger" style="width:36px;height:36px;border-radius:10px;font-size:.9rem;margin:0;">
            <i class="bi bi-exclamation-triangle"></i>
          </span>
          <div>
            <div class="small text-uppercase fw-bold text-muted" style="color:var(--text-soft)!important;">High Risk</div>
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
          <span class="stat-icon tone-warning" style="width:36px;height:36px;border-radius:10px;font-size:.9rem;margin:0;">
            <i class="bi bi-exclamation-circle"></i>
          </span>
          <div>
            <div class="small text-uppercase fw-bold text-muted" style="color:var(--text-soft)!important;">Medium Risk</div>
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
          <span class="stat-icon tone-success" style="width:36px;height:36px;border-radius:10px;font-size:.9rem;margin:0;">
            <i class="bi bi-check-circle"></i>
          </span>
          <div>
            <div class="small text-uppercase fw-bold text-muted" style="color:var(--text-soft)!important;">Low Risk</div>
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
      <small>Operational indicators generated directly from MySQL records · Showing <?= esc($analyticsPeriodLabel) ?></small>
    </div>
    <form method="get" class="d-flex align-items-center gap-2 flex-wrap" id="analyticsFilterForm">
      <label class="small text-muted mb-0" for="analyticsPeriodSelect">Collection period</label>
      <select class="form-select form-select-sm" name="analytics_period" id="analyticsPeriodSelect" style="width:150px">
        <option value="day" <?= $analyticsPeriod === 'day' ? 'selected' : '' ?>>Specific day</option>
        <option value="month" <?= $analyticsPeriod === 'month' ? 'selected' : '' ?>>Specific month</option>
        <option value="year" <?= $analyticsPeriod === 'year' ? 'selected' : '' ?>>Specific year</option>
        <option value="all" <?= $analyticsPeriod === 'all' ? 'selected' : '' ?>>All records</option>
      </select>
      <input class="form-control form-control-sm analytics-filter-value" type="date" name="analytics_day" id="analyticsDayInput" value="<?= esc($analyticsDay) ?>" style="width:150px">
      <input class="form-control form-control-sm analytics-filter-value" type="month" name="analytics_month" id="analyticsMonthInput" value="<?= esc($analyticsMonth) ?>" style="width:150px">
      <input class="form-control form-control-sm analytics-filter-value" type="number" name="analytics_year" id="analyticsYearInput" value="<?= esc($analyticsYear) ?>" min="2000" max="2100" style="width:110px">
      <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
    </form>
  </div>

  <div class="ds-analysis-grid mb-3">
    <div class="ds-analysis-kpi">
      <small>Actual Collection</small>
      <strong><?= peso($collectedAmount) ?></strong>
    </div>

    <div class="ds-analysis-kpi">
      <small>Completed Payments</small>
      <strong><?= num($paymentCount) ?></strong>
    </div>

    <div class="ds-analysis-kpi">
      <small>Violations</small>
      <strong><?= num($violationsToday) ?></strong>
    </div>

    <div class="ds-analysis-kpi">
      <small>Estimated Penalties</small>
      <strong><?= peso($penaltiesToday) ?></strong>
    </div>

    <div class="ds-analysis-kpi">
      <small>Affected Locations</small>
      <strong><?= num($affectedLocations) ?></strong>
    </div>

    <div class="ds-analysis-kpi">
      <small>Peak Recording Hour</small>
      <strong><?= esc($peakHourValue) ?></strong>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-lg-6">
      <div class="section-card ds-equal-card">
        <div class="section-head">
          <div>
            <h6>Leading Violation</h6>
            <small class="text-muted"><?= esc($analyticsPeriodLabel) ?> database result</small>
          </div>
        </div>

        <h5><?= esc($topViolationName) ?></h5>
        <p class="text-muted mb-0"><?= num($topViolationCount) ?> recorded cases</p>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="section-card ds-equal-card">
        <div class="section-head">
          <div>
            <h6>Leading Database Location</h6>
            <small class="text-muted"><?= esc($analyticsPeriodLabel) ?> violation concentration</small>
          </div>
        </div>

        <h5><?= esc($topLocationName) ?></h5>
        <p class="text-muted mb-0"><?= num($topLocationCount) ?> recorded cases</p>
      </div>
    </div>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-lg-6">
      <div class="section-card ds-chart-card ds-equal-card">
        <div class="section-head">
          <div>
            <h6>Violation Records</h6>
            <small class="text-muted">Monthly records · <?= esc((string) $analyticsChartYear) ?></small>
          </div>
        </div>
        <div style="height:240px"><canvas id="dsTrendChart"></canvas></div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="section-card ds-chart-card ds-equal-card">
        <div class="section-head">
          <div>
            <h6>Monthly Collection</h6>
            <small class="text-muted">Completed payments · <?= esc((string) $analyticsChartYear) ?></small>
          </div>
        </div>
        <div style="height:240px"><canvas id="dsCollectionChart"></canvas></div>
      </div>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="section-card ds-insight-card">
        <div class="section-head">
          <div>
            <h6>Interpretation</h6>
            <small class="text-muted">Plain-language explanation of the combined results</small>
          </div>
        </div>

        <div class="ds-interpretation" id="dsInterpretation">
          Waiting for prediction and hotspot results...
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="section-card ds-insight-card">
        <div class="section-head">
          <div>
            <h6>Recommendations</h6>
            <small class="text-muted">Suggested operational actions</small>
          </div>
        </div>

        <ul class="ds-recommendation-list" id="dsRecommendations"></ul>
      </div>
    </div>
  </div>

  <div class="section-card ds-deployment-card mt-3">
    <div class="ds-deployment-header">
      <div class="ds-deployment-heading">
        <span class="ds-deployment-mark" aria-hidden="true"><i class="bi bi-signpost-split-fill"></i></span>
        <div>
          <h6>Action Plan</h6>
          <small class="text-muted">Summary for TMO operational planning</small>
        </div>
      </div>
      <span class="ds-plan-status" id="dsPlanStatus">
        <i class="bi bi-circle-fill" aria-hidden="true"></i>
        <span id="dsPlanStatusText">Awaiting prediction</span>
      </span>
    </div>

    <div class="ds-deployment-table">
      <div class="ds-deployment-row" id="dsPlanRiskCard">
        <span class="ds-plan-icon" aria-hidden="true"><i class="bi bi-shield-exclamation"></i></span>
        <div class="ds-plan-copy">
          <span>Monthly risk</span>
          <strong id="dsPlanRisk">—</strong>
        </div>
      </div>

      <div class="ds-deployment-row">
        <span class="ds-plan-icon" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
        <div class="ds-plan-copy">
          <span>Recommended intervention</span>
          <strong id="dsPlanPersonnel">—</strong>
        </div>
      </div>

      <div class="ds-deployment-row">
        <span class="ds-plan-icon" aria-hidden="true"><i class="bi bi-geo-alt-fill"></i></span>
        <div class="ds-plan-copy">
          <span>Primary focus</span>
          <strong id="dsPlanFocus">—</strong>
        </div>
      </div>

      <div class="ds-deployment-row">
        <span class="ds-plan-icon" aria-hidden="true"><i class="bi bi-camera-video-fill"></i></span>
        <div class="ds-plan-copy">
          <span>Monitoring approach</span>
          <strong id="dsPlanMonitoring">—</strong>
        </div>
      </div>
    </div>

    <div class="ds-deployment-note">
      <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
      <span>Confirm current road conditions, apply a time-bound intervention, and compare results before continuing it.</span>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const DS_MONTHLY_ENDPOINT = '../api/predict_monthly.php';
const DS_HOTSPOT_ENDPOINT = '../api/predict_hotspot.php';

const dsMonths = <?= json_encode(month_labels()) ?>;
const dsMonthlyTrend = <?= json_encode($monthlyTrend) ?>;
const dsMonthlyCollections = <?= json_encode($monthlyCollectionTrend) ?>;

const databaseTopViolation = <?= json_encode($topViolationName) ?>;
const databaseTopLocation = <?= json_encode($topLocationName) ?>;
const databasePeakHour = <?= json_encode($peakHourValue) ?>;

function updateAnalyticsFilterInput() {
  const selected = document.getElementById('analyticsPeriodSelect')?.value || 'day';
  const inputs = {
    day: document.getElementById('analyticsDayInput'),
    month: document.getElementById('analyticsMonthInput'),
    year: document.getElementById('analyticsYearInput')
  };
  Object.entries(inputs).forEach(([period, input]) => {
    if (!input) return;
    const visible = selected === period;
    input.classList.toggle('d-none', !visible);
    input.disabled = !visible;
  });
}
document.getElementById('analyticsPeriodSelect')?.addEventListener('change', updateAnalyticsFilterInput);
updateAnalyticsFilterInput();

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
      priority: 'Urgent attention',
      personnel: 'Run a focused 7-day operation',
      monitoring: 'Check the results every day',
      actions: [
        'Confirm the current road condition at the priority location before sending personnel.',
        'Run a focused 7-day operation during the historically busiest period.',
        'Use the response that matches the common problem, such as loading control, clearing, signage, reminders, or random checks.',
        'Compare the number of violations before and after the 7-day operation.'
      ]
    };
  }

  if (risk === 'medium') {
    return {
      priority: 'Needs attention',
      personnel: 'Run a focused 14-day trial',
      monitoring: 'Check results twice a week',
      actions: [
        'Observe the priority location during its historically busiest periods.',
        'Use reminders, signs, or random checks for the most common violation.',
        'Review the results after 14 days before continuing or expanding the operation.'
      ]
    };
  }

  return {
    priority: 'Routine priority',
    personnel: 'Continue regular observation',
    monitoring: 'Review results every month',
    actions: [
      'Continue normal monitoring without adding a permanent checkpoint.',
      'Visit the location periodically to confirm that conditions remain stable.',
      'Review the area again when new violation records become available.'
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

    const item = document.createElement('li');

    item.innerHTML = `
      <span class="ds-rank">${index + 1}</span>
      <span class="ds-location-copy">
        <strong>${name}</strong>
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
      `Regular deployment should be supported by additional enforcement during historically high-violation periods.`;
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
    The monthly forecast uses violation records only; live computer-vision monitoring is a separate operational input.
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
    const selectedPeriod = document.getElementById('dsPredictionMonth')?.value || '';
    const [selectedYear, selectedMonth] = selectedPeriod.split('-').map(Number);
    const monthlyUrl = selectedYear && selectedMonth
      ? `${DS_MONTHLY_ENDPOINT}?year=${selectedYear}&month=${selectedMonth}`
      : DS_MONTHLY_ENDPOINT;

    const [monthlyResponse, hotspotResponse] = await Promise.all([
      fetch(monthlyUrl, {
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

    const planRisk = dsNormalizeRisk(riskLevel);
    const planRiskCard = document.getElementById('dsPlanRiskCard');
    const planStatus = document.getElementById('dsPlanStatus');
    const planStatusText = document.getElementById('dsPlanStatusText');
    const guidanceCard = document.getElementById('dsGuidanceCard');

    [planRiskCard, planStatus, guidanceCard].forEach((element) => {
      element?.classList.remove('is-high', 'is-medium', 'is-low');
      if (['high', 'medium', 'low'].includes(planRisk)) {
        element?.classList.add(`is-${planRisk}`);
      }
    });

    if (planStatusText) {
      planStatusText.textContent = planRisk === 'high'
        ? 'High-priority plan'
        : planRisk === 'medium'
          ? 'Focused monitoring plan'
          : 'Routine monitoring plan';
    }

    loading.classList.add('d-none');
    content.classList.remove('d-none');
  } catch (error) {
    loading.classList.add('d-none');
    errorBox.textContent =
      `${error.message} Make sure Apache and the Flask API on port 5001 are running.`;
    errorBox.classList.remove('d-none');
  }
}

Chart.defaults.font.family = "'Poppins', sans-serif";

function dsViolationChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#102f49',
        titleColor: '#ffffff',
        bodyColor: '#e8f0ed',
        padding: 12,
        cornerRadius: 8,
        callbacks: {
          label: context => {
            const count = Number(context.parsed.y || 0);
            return `${count.toLocaleString()} violation${count === 1 ? '' : 's'}`;
          }
        }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: '#526b64' } },
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(16, 47, 73, .12)' },
        ticks: { precision: 0, color: '#526b64' }
      }
    }
  };
}

function dsCollectionChartOptions() {
  return {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: '#102f49',
        titleColor: '#ffffff',
        bodyColor: '#e8f0ed',
        padding: 12,
        cornerRadius: 8,
        callbacks: {
          label: context => `₱${Number(context.parsed.y || 0).toLocaleString()}`
        }
      }
    },
    scales: {
      x: { grid: { display: false }, ticks: { color: '#526b64' } },
      y: {
        beginAtZero: true,
        grid: { color: 'rgba(16, 47, 73, .12)' },
        ticks: {
          color: '#526b64',
          callback: value => `₱${Number(value).toLocaleString()}`
        }
      }
    }
  };
}

const dsViolationContext = document.getElementById('dsTrendChart').getContext('2d');
const dsViolationGradient = dsViolationContext.createLinearGradient(0, 0, 0, 240);
dsViolationGradient.addColorStop(0, 'rgba(8, 125, 120, .34)');
dsViolationGradient.addColorStop(1, 'rgba(8, 125, 120, 0)');

new Chart(dsViolationContext, {
  type: 'line',
  data: {
    labels: dsMonths,
    datasets: [{
      label: 'Violation Records',
      data: dsMonthlyTrend,
      borderColor: '#087d78',
      backgroundColor: dsViolationGradient,
      fill: true,
      tension: .4,
      borderWidth: 3,
      pointBackgroundColor: '#eb941f',
      pointBorderColor: '#fffdf7',
      pointBorderWidth: 2,
      pointRadius: 4,
      pointHoverRadius: 6
    }]
  },
  options: dsViolationChartOptions()
});

const dsCollectionContext = document.getElementById('dsCollectionChart').getContext('2d');
const dsCollectionGradient = dsCollectionContext.createLinearGradient(0, 0, 0, 240);
dsCollectionGradient.addColorStop(0, '#087d78');
dsCollectionGradient.addColorStop(1, '#eb941f');

new Chart(dsCollectionContext, {
  type: 'bar',
  data: {
    labels: dsMonths,
    datasets: [{
      label: 'Monthly Collection',
      data: dsMonthlyCollections,
      backgroundColor: dsCollectionGradient,
      borderRadius: 6,
      borderSkipped: false
    }]
  },
  options: dsCollectionChartOptions()
});

loadDecisionSupport();
document.getElementById('dsPredictBtn')?.addEventListener('click', loadDecisionSupport);
document.getElementById('dsPredictionMonth')?.addEventListener('change', loadDecisionSupport);
setInterval(loadDecisionSupport, 60000);
</script>

<?php page_end(false); ?>
