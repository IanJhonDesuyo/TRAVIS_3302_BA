<?php
require_once __DIR__ . '/layout.php';

$defaults = [
    'congestion_light_max' => '5',
    'congestion_heavy_min' => '13',
    'alert_cooldown_seconds' => '300',
    'confidence_threshold' => '0.50',
    'enable_officer_detection' => '1',
    'enable_collision_detection' => '0',
    'notify_congestion' => '1',
    'notify_collision' => '1',
];
$settingsMessage = '';
$settingsError = '';

$conn->query("CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''));
    if (!$csrfOk) {
        $settingsError = 'Your session token expired. Refresh the page and try again.';
    } else {
        $values = [
            'congestion_light_max' => max(0, min(100, (int)($_POST['congestion_light_max'] ?? 5))),
            'congestion_heavy_min' => max(1, min(200, (int)($_POST['congestion_heavy_min'] ?? 13))),
            'alert_cooldown_seconds' => max(0, min(86400, (int)($_POST['alert_cooldown_seconds'] ?? 300))),
            'confidence_threshold' => max(0.10, min(1.00, (float)($_POST['confidence_threshold'] ?? .5))),
            'enable_officer_detection' => isset($_POST['enable_officer_detection']) ? 1 : 0,
            'enable_collision_detection' => isset($_POST['enable_collision_detection']) ? 1 : 0,
            'notify_congestion' => isset($_POST['notify_congestion']) ? 1 : 0,
            'notify_collision' => isset($_POST['notify_collision']) ? 1 : 0,
        ];

        if ($values['congestion_heavy_min'] <= $values['congestion_light_max']) {
            $settingsError = 'Heavy congestion must start above the light congestion maximum.';
        } else {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            foreach ($values as $key => $value) {
                $stringValue = (string)$value;
                $stmt->bind_param('ss', $key, $stringValue);
                $stmt->execute();
            }
            $stmt->close();
            $settingsMessage = 'Settings saved. Computer-vision changes apply the next time analysis starts.';
        }
    }
}

$settings = $defaults;
$result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
while ($result && ($row = $result->fetch_assoc())) {
    if (array_key_exists($row['setting_key'], $settings)) {
        $settings[$row['setting_key']] = (string)$row['setting_value'];
    }
}

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
.alert-success{background:rgba(52,211,153,.12) !important;border:1px solid rgba(52,211,153,.3) !important;color:#34d399 !important}
.alert-danger{background:rgba(248,113,113,.12) !important;border:1px solid rgba(248,113,113,.3) !important;color:#f87171 !important}

.mini-metric{
    background:rgba(255,255,255,.03) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
}
.mini-metric small{color:var(--text-soft) !important;}

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
    margin:0 !important;
    background:rgba(255,255,255,.06);
    border:1px solid var(--border-glass);
    border-radius:999px;
    cursor:pointer;
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
    cursor:pointer;
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

<form method="post" id="settingsForm">
<input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <span class="dashboard-eyebrow">TRAVIS SETTINGS</span>
    <h3 class="page-title">System Settings</h3>
    <p class="page-sub">Configure live detection thresholds and notification preferences</p>
  </div>
  <button class="btn btn-primary" type="submit">
    <i class="bi bi-check2 me-1"></i>Save Changes
  </button>
</div>

<?php if ($settingsMessage): ?><div class="alert alert-success"><?= esc($settingsMessage) ?></div><?php endif; ?>
<?php if ($settingsError): ?><div class="alert alert-danger"><?= esc($settingsError) ?></div><?php endif; ?>

<div class="row g-3">
  <!-- Traffic Thresholds -->
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Traffic Thresholds</h6>
        <span class="tag tag-success">Active</span>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Light Congestion Maximum (visible vehicles)</label>
        <input type="number" min="0" max="100" name="congestion_light_max" class="form-control" value="<?= esc($settings['congestion_light_max']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Heavy Congestion Starts At (visible vehicles)</label>
        <input type="number" min="1" max="200" name="congestion_heavy_min" class="form-control" value="<?= esc($settings['congestion_heavy_min']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Alert Cooldown (seconds)</label>
        <input type="number" min="0" max="86400" name="alert_cooldown_seconds" class="form-control" value="<?= esc($settings['alert_cooldown_seconds']) ?>" required>
      </div>
    </div>
  </div>

  <!-- Computer Vision Integration -->
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Computer Vision Integration</h6>
        <span class="tag tag-success">Applied on next start</span>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold">Confidence Threshold</label>
        <input type="number" min="0.10" max="1" step="0.05" name="confidence_threshold" class="form-control" value="<?= esc($settings['confidence_threshold']) ?>" required>
      </div>

      <div class="form-check form-switch mb-2">
        <input class="form-check-input" name="enable_officer_detection" type="checkbox" <?= $settings['enable_officer_detection'] === '1' ? 'checked' : '' ?>>
        <label class="form-check-label">Officer presence detection</label>
      </div>

      <div class="form-check form-switch mb-2">
        <input class="form-check-input" name="enable_collision_detection" type="checkbox" <?= $settings['enable_collision_detection'] === '1' ? 'checked' : '' ?>>
        <label class="form-check-label">Potential collision detection</label>
      </div>

      <small class="text-muted">Camera address and stream quality remain configurable from Live Monitoring.</small>
    </div>
  </div>

  <!-- Notifications -->
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Notifications</h6>
        <span class="tag tag-success">Saved</span>
      </div>

      <div class="form-check form-switch mb-2">
        <input class="form-check-input" name="notify_congestion" type="checkbox" <?= $settings['notify_congestion'] === '1' ? 'checked' : '' ?>>
        <label class="form-check-label">Critical congestion alerts</label>
      </div>

      <div class="form-check form-switch mb-2">
        <input class="form-check-input" name="notify_collision" type="checkbox" <?= $settings['notify_collision'] === '1' ? 'checked' : '' ?>>
        <label class="form-check-label">Potential collision alerts</label>
      </div>

    </div>
  </div>

  <!-- Runtime information -->
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head">
        <h6>Runtime Information</h6>
        <span class="tag tag-info">Local services</span>
      </div>

      <div class="mini-metric mb-2">
        <small>Live Stream</small>
        <strong>Port 5000</strong>
      </div>
      <div class="mini-metric mb-2">
        <small>Detection Model</small>
        <strong>YOLOv8n</strong>
      </div>
      <div class="mini-metric">
        <small>Settings Storage</small>
        <strong>Database</strong>
      </div>
    </div>
  </div>
</div>
</form>

<?php page_end(); ?>