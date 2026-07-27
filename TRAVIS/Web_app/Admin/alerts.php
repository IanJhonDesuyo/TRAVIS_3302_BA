<?php
require_once __DIR__ . '/layout.php';

$critical = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE severity='critical' AND status IN ('active','acknowledged')", 0);
$warning = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE severity='warning' AND status IN ('active','acknowledged')", 0);
$info = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE severity='info' AND status IN ('active','acknowledged')", 0);
$resolved = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE status='resolved'", 0);

$alerts = fetch_all("
    SELECT a.*, u.full_name AS ack_by 
    FROM monitoring_alerts a 
    LEFT JOIN users u ON u.user_id = a.acknowledged_by 
    ORDER BY FIELD(a.status, 'active', 'acknowledged', 'resolved'), a.generated_at DESC 
    LIMIT 50
");

page_start('Alerts', 'alerts', 'Search alerts...');
?>

<style>
/* ============================================================
   TRAVIS ALERTS — NAVY GLASS THEME
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

.tag{
    display:inline-block;padding:4px 12px;border-radius:999px;
    font-size:.72rem;font-weight:700;text-transform:capitalize;
    background:rgba(255,255,255,.08);color:var(--text-soft);
    border:1px solid var(--border-glass);
}
.tag-success,.tag-online,.tag-paid,.tag-completed,.tag-active,.tag-low,.tag-resolved{
    background:rgba(52,211,153,.14) !important;color:#34d399 !important;border-color:rgba(52,211,153,.3) !important;
}
.tag-danger,.tag-offline,.tag-overdue,.tag-high,.tag-critical{
    background:rgba(248,113,113,.14) !important;color:#f87171 !important;border-color:rgba(248,113,113,.3) !important;
}
.tag-warning,.tag-pending,.tag-unpaid,.tag-medium,.tag-warning{
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

.table{color:#fff !important}
.table thead th{color:var(--text-soft) !important;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;border-color:var(--border-glass) !important;font-weight:600}
.table td,.table th{border-color:var(--border-glass) !important;vertical-align:middle}
.table-responsive{border-radius:12px}

/* Dot indicator */
.dot{
    display:inline-block;
    width:8px;
    height:8px;
    border-radius:50%;
    margin-right:6px;
    animation:pulse-dot 1.8s ease-in-out infinite;
}

@keyframes pulse-dot{
    0%,100%{opacity:1;transform:scale(1);}
    50%{opacity:.5;transform:scale(.85);}
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

.card *:not(.tag):not(.dot),
[class*="card"] *:not(.tag):not(.dot){
    color:inherit;
}

.card small,
[class*="card"] small,
.card .text-muted,
[class*="card"] .text-muted{
    color:var(--text-soft) !important;
}

.rounded-pill:not(.tag),
span[style*="border-radius:999px"]:not(.tag),
span[style*="border-radius: 999px"]:not(.tag),
div[style*="border-radius:999px"]:not(.tag),
div[style*="border-radius: 999px"]:not(.tag){
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
</style>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <span class="dashboard-eyebrow">TRAVIS ALERTS MODULE</span>
    <h3 class="page-title">Alerts & Notifications</h3>
    <p class="page-sub">Real-time computer vision and system event stream</p>
  </div>
  <button class="btn btn-light" disabled><i class="bi bi-check2-all me-1"></i>Mark all read</button>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-icon tone-danger"><i class="bi bi-exclamation-octagon"></i></div>
      <div class="stat-label">Critical</div>
      <div class="stat-value"><?= num($critical) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-exclamation"></i></div>
      <div class="stat-label">Warning</div>
      <div class="stat-value"><?= num($warning) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-info-circle"></i></div>
      <div class="stat-label">Info</div>
      <div class="stat-value"><?= num($info) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-check2-circle"></i></div>
      <div class="stat-label">Resolved</div>
      <div class="stat-value"><?= num($resolved) ?></div>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-head">
    <div>
      <h6 class="mb-0">Live Event Stream</h6>
      <small class="text-muted">Real-time alerts from computer vision and system monitoring</small>
    </div>
    <span class="tag tag-success">
      <span class="dot" style="background:#34d399;"></span>
      Database Connected
    </span>
  </div>

  <?php if (!$alerts): ?>
    <?php empty_state('No alerts found. Computer vision alerts will appear here after records are inserted into monitoring_alerts.'); ?>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Type</th>
            <th>Message</th>
            <th>Severity</th>
            <th>Status</th>
            <th>Generated</th>
            <th class="text-end">Acknowledged By</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($alerts as $a): ?>
            <tr>
              <td>
                <span class="badge" style="background:rgba(56,189,248,.14);color:var(--cyan-glow);padding:4px 10px;border-radius:6px;font-weight:600;font-size:.72rem;">
                  <?= esc(ucfirst($a['alert_type'])) ?>
                </span>
              </td>
              <td><?= esc($a['message']) ?></td>
              <td><span class="tag <?= tag_class($a['severity']) ?>"><?= esc($a['severity']) ?></span></td>
              <td><span class="tag <?= tag_class($a['status']) ?>"><?= esc($a['status']) ?></span></td>
              <td class="text-muted"><?= esc($a['generated_at']) ?></td>
              <td class="text-end text-muted"><?= esc($a['ack_by'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php page_end(); ?>
