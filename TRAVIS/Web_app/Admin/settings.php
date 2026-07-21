<?php
require_once __DIR__ . '/layout.php';

page_start('Settings', 'settings', 'Search settings...');
?>

<style>
/* ============================================================
   TRAVIS SETTINGS — NAVY GLASS THEME
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

.btn-primary:disabled{
    opacity:.5;
    cursor:not-allowed;
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
.btn-primary:hover:not(:disabled){filter:brightness(1.08)}

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
.form-control::placeholder{color:var(--text-soft) !important;}
.form-control:disabled{
    opacity:.5;
    cursor:not-allowed;
}
.form-select{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
}
.form-select:focus{
    background:rgba(255,255,255,.09) !important;
    border-color:var(--blue-accent) !important;
    color:#fff !important;
    box-shadow:0 0 0 .2rem rgba(56,189,248,.18) !important;
}
.form-select option{background:var(--navy-800);color:#fff;}
.form-select:disabled{
    opacity:.5;
    cursor:not-allowed;
}
.form-label{color:var(--text-soft) !important;font-weight:600;font-size:.8rem;margin-bottom:4px;}

/* Form Check / Switch */
.form-check{
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 0;
}
.form-check-input{
    width:44px;
    height:24px;
    background:rgba(255,255,255,.06);
    border:1px solid var(--border-glass);
    border-radius:999px;
    cursor:not-allowed;
    flex-shrink:0;
}
.form-check-input:checked{
    background:var(--blue-accent-2) !important;
    border-color:var(--blue-accent-2) !important;
}
.form-check-input:disabled{
    opacity:.5;
    cursor:not-allowed;
}
.form-check-label{
    color:#fff;
    font-size:.85rem;
    cursor:not-allowed;
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

.card *:not(.tag),
[class*="card"] *:not(.tag){
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
    <span class="dashboard-eyebrow">TRAVIS SETTINGS</span>
    <h3 class="page-title">System Settings</h3>
    <p class="page-sub">Prepared configuration screen for computer vision thresholds, notifications, duty schedule, and security</p>
  </div>
  <button class="btn btn-primary" disabled>
    <i class="bi bi-check2 me-1"></i>Save Changes
  </button>
</div>

<div class="row g-3">
  <!-- Traffic Thresholds -->
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Traffic Thresholds</h6>
        <span class="tag tag-info">Placeholder</span>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Congestion Trigger (vehicles/hr)</label>
        <input type="number" class="form-control" value="1500" disabled>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Alert Cooldown (minutes)</label>
        <input type="number" class="form-control" value="15" disabled>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Officer Absence Threshold (minutes)</label>
        <input type="number" class="form-control" value="30" disabled>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Potential Collision Stationary Threshold (seconds)</label>
        <input type="number" class="form-control" value="10" disabled>
      </div>
    </div>
  </div>

  <!-- Computer Vision Integration -->
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Computer Vision Integration</h6>
        <span class="tag tag-info">Placeholder</span>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Flask API URL</label>
        <input class="form-control" value="http://localhost:5000" disabled>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">RTSP Camera Source</label>
        <input class="form-control" value="rtsp://username:password@camera-ip:554/stream1" disabled>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Confidence Threshold</label>
        <input class="form-control" value="0.65" disabled>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Model Path</label>
        <input class="form-control" value="computer_vision/models/yolov8n.pt" disabled>
      </div>

      <small class="text-muted">These fields are placeholders for later integration with Tapo C210, YOLOv8, and OpenCV.</small>
    </div>
  </div>

  <!-- Notifications -->
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Notifications</h6>
        <span class="tag tag-info">Placeholder</span>
      </div>

      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" checked disabled>
        <label class="form-check-label">Critical congestion alerts</label>
      </div>

      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" checked disabled>
        <label class="form-check-label">Officer absence alerts</label>
      </div>

      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" checked disabled>
        <label class="form-check-label">Potential collision alerts</label>
      </div>

      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" disabled>
        <label class="form-check-label">System maintenance notices</label>
      </div>

      <div class="form-check form-switch mb-2">
        <input class="form-check-input" type="checkbox" checked disabled>
        <label class="form-check-label">Daily summary reports</label>
      </div>
    </div>
  </div>

  <!-- Security -->
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Security</h6>
        <span class="tag tag-info">Placeholder</span>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Session Timeout (minutes)</label>
        <input type="number" class="form-control" value="30" disabled>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Password Policy</label>
        <select class="form-select" disabled>
          <option>Strong (12+ chars)</option>
          <option>Standard (8+ chars)</option>
        </select>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Max Login Attempts</label>
        <input type="number" class="form-control" value="5" disabled>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Two-Factor Authentication</label>
        <select class="form-select" disabled>
          <option>Disabled</option>
          <option>Enabled</option>
        </select>
      </div>
    </div>
  </div>
</div>

<?php page_end(); ?>