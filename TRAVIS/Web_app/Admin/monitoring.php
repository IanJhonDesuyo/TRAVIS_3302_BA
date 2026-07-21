<?php
ini_set('upload_max_filesize', '500M');
ini_set('post_max_size', '520M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');

require_once __DIR__ . '/layout.php';

$uploadMessage = '';
$uploadedVideo = null;
$maxUploadBytes = 500 * 1024 * 1024;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cctv_video'])) {
    $allowed = ['mp4','avi','mov','mkv'];
    $ext = strtolower(pathinfo($_FILES['cctv_video']['name'] ?? '', PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed, true)) {
        $uploadMessage = 'Invalid file type. Please upload MP4, AVI, MOV, or MKV.';
    } elseif (($_FILES['cctv_video']['size'] ?? 0) > $maxUploadBytes) {
        $uploadMessage = 'File too large. Maximum allowed size is 500MB.';
    } else {
        $projectRoot = dirname(__DIR__, 2);
        $dir = $projectRoot . '/computer_vision/uploads/videos';
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $target = $dir . '/test.mp4';

        if (move_uploaded_file($_FILES['cctv_video']['tmp_name'], $target)) {
            $uploadedVideo = 'computer_vision/uploads/videos/test.mp4';
            $statusFile = dirname(__DIR__) . '/api/analysis_status.json';
            file_put_contents($statusFile, json_encode([
                'analysis_status' => 'Idle',
                'ai_status' => 'Idle',
                'message' => '',
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_at_epoch' => time()
            ], JSON_PRETTY_PRINT));

            $uploadMessage = 'CCTV video uploaded successfully.';
        } else {
            $uploadMessage = 'Upload failed. Please check folder permissions.';
        }
    }
}

$camera = fetch_one("SELECT * FROM cameras ORDER BY camera_id ASC LIMIT 1");
$latest = fetch_one("SELECT * FROM camera_monitoring_logs ORDER BY recorded_at DESC LIMIT 1");

$calibrationProfiles = [];
$calibrationDirectory = dirname(__DIR__, 2) . '/computer_vision/calibration_profiles';
foreach (glob($calibrationDirectory . '/*.json') ?: [] as $profilePath) {
    $profileData = json_decode((string)file_get_contents($profilePath), true);
    if (!is_array($profileData)) continue;
    $calibrationProfiles[] = [
        'file' => basename($profilePath),
        'name' => (string)($profileData['profile_name'] ?? pathinfo($profilePath, PATHINFO_FILENAME)),
    ];
}
usort($calibrationProfiles, fn($a, $b) => strcasecmp($a['name'], $b['name']));

$logs = fetch_all("
    SELECT l.*, c.camera_name, c.location 
    FROM camera_monitoring_logs l 
    JOIN cameras c ON c.camera_id = l.camera_id 
    ORDER BY l.recorded_at DESC 
    LIMIT 10
");

$activeAlerts = fetch_all("
    SELECT alert_type, severity, message, generated_at, status 
    FROM monitoring_alerts 
    ORDER BY generated_at DESC 
    LIMIT 5
");

page_start('Live Monitoring', 'monitoring', 'Search monitoring logs...');
?>

<style id="travis-ai-v6-overrides">
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

/* ==== Catch-all: any remaining white cards / oval-pill shapes ==== */
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

<style id="travis-ai-internal-css">
/* ==== TRAVIS AI V6 — Navy Glass Theme ==== */
body{
    font-family:'Poppins', sans-serif !important;
    background:
        radial-gradient(circle at 10% 10%, rgba(56,189,248,.08), transparent 30%),
        radial-gradient(circle at 90% 80%, rgba(37,99,235,.08), transparent 35%),
        linear-gradient(160deg, var(--navy-950) 0%, var(--navy-900) 45%, var(--navy-800) 100%) !important;
    color:#fff !important;
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
.btn-accent{
    background:linear-gradient(90deg,var(--blue-accent-2),var(--cyan-glow)) !important;
    border:none !important;color:#fff !important;
    box-shadow:0 12px 26px rgba(37,99,235,.32) !important;
    font-weight:600 !important;border-radius:12px !important;
}
.btn-accent:hover{filter:brightness(1.08);color:#fff !important}

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
.form-select{
    background-color:rgba(255,255,255,.06) !important;
    background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23c9d8ea' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e") !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
}
.form-select:focus{
    background-color:rgba(255,255,255,.09) !important;
    border-color:var(--blue-accent) !important;
    color:#fff !important;
    box-shadow:0 0 0 .2rem rgba(56,189,248,.18) !important;
}
.form-select option{
    background:var(--navy-800);
    color:#fff;
}
.form-control::file-selector-button{
    background:rgba(255,255,255,.08);
    color:#fff;
    border:none;
    border-right:1px solid var(--border-glass);
    padding:8px 14px;
    margin-right:10px;
}
.form-label{color:var(--text-soft) !important}

/* ==== Camera stage (video area) ==== */
.camera-stage{
    position:relative;
    width:100%;
    aspect-ratio:16/9;
    background:rgba(255,255,255,.03);
    border:1px solid var(--border-glass);
    border-radius:14px;
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
    color:var(--text-soft);
}
#calibrationCanvas{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    z-index:5;
    display:none;
    cursor:crosshair;
    touch-action:none;
}
#calibrationCanvas.active{display:block;}

/* Keep the monitoring summary balanced instead of leaving single cards behind. */
.monitoring-kpi-grid{
    display:grid;
    grid-template-columns:repeat(5,minmax(0,1fr));
    gap:14px;
    margin-bottom:1.5rem;
}
.monitoring-kpi-grid > div{min-width:0;}
.monitoring-kpi-grid .stat-card{height:100%;}
.monitoring-last-updated .stat-card{
    display:flex;
    min-height:auto;
    flex-direction:row;
    align-items:center;
    gap:14px;
}
.monitoring-last-updated .stat-icon{flex:0 0 auto;margin-bottom:0;}
.monitoring-last-updated .stat-label{margin:0;white-space:nowrap;}
.monitoring-last-updated .stat-value{font-size:1.1rem !important;}
@media (max-width:1199.98px){
    .monitoring-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr));}
}
@media (max-width:575.98px){
    .monitoring-kpi-grid{grid-template-columns:1fr;}
}
</style>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div class="dashboard-title-row">
    <div>
      <span class="dashboard-eyebrow">TRAVIS AI ENGINE</span>
      <h3 class="page-title">TRAVIS AI Monitoring</h3>
      <p class="page-sub">AI-powered traffic monitoring for uploaded footage or a live Tapo camera</p>
    </div>
  </div>

  <div class="d-flex gap-2">
    <span class="system-online-badge">
      <span class="system-online-dot <?= ($camera['status'] ?? 'offline') === 'online' ? 'online' : 'offline' ?>"></span>
      <?= esc($camera['status'] ?? 'offline') ?>
    </span>
  </div>
</div>

<?php if ($uploadMessage): ?>
<div class="alert <?= str_contains(strtolower($uploadMessage), 'success') ? 'alert-success' : 'alert-warning' ?>">
  <?= esc($uploadMessage) ?>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="section-card">
      <div class="section-head">
        <div>
          <h6>Main Camera Monitor</h6>
          <small class="text-muted">Live browser stream from the selected AI video source</small>
        </div>
        <span class="tag tag-info" id="sourceStatus">Ready</span>
      </div>

      <div class="camera-stage mb-3" id="cameraStage">
        <img
          id="aiLiveStream"
          src="http://<?= esc(!empty($_SERVER['HTTP_HOST']) ? explode(':', $_SERVER['HTTP_HOST'])[0] : 'localhost') ?>:5000/video_feed"
          alt="TRAVIS Live AI Detection Stream"
          style="width:100%; height:100%; object-fit:cover; border-radius:12px; display:block;"
          onerror="this.style.display='none'; document.getElementById('streamFallback').style.display='flex';"
          onload="this.style.display='block'; document.getElementById('streamFallback').style.display='none';"
        >

        <div
          class="text-center p-4"
          id="streamFallback"
          style="display:none; width:100%; height:100%; align-items:center; justify-content:center; flex-direction:column;"
        >
          <i class="bi bi-broadcast fs-1 d-block mb-3" style="color:var(--cyan-glow);"></i>
          <h5>Waiting for AI live stream</h5>
          <p class="mb-0 opacity-75">
            Start analysis from the dashboard to activate the AI stream.
          </p>
        </div>

        <canvas id="snapshotCanvas" style="display:none;"></canvas>
        <canvas id="calibrationCanvas" aria-label="Line configuration editor"></canvas>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-light" type="button" id="stopCameraBtn">
          <i class="bi bi-stop-fill me-1"></i>Hide Stream
        </button>

        <button class="btn btn-light" type="button" id="captureSnapshotBtn">
          <i class="bi bi-camera me-1"></i>Open Stream Snapshot
        </button>

        <button class="btn btn-light" onclick="location.reload()">
          <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
      </div>

      <small class="text-muted d-block mt-2">
        Live AI detection stream will appear here from <code>http://localhost:5000/video_feed</code>. Dashboard values update automatically from <code>api/get_status.php</code>.
      </small>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="section-card mb-3">
      <div class="section-head"><h6>Monitoring Source</h6></div>
      <label class="form-label small fw-semibold" for="monitoringSource">Source</label>
      <select class="form-select mb-3" id="monitoringSource">
        <option value="uploaded_video">Uploaded CCTV video</option>
        <option value="tapo_camera">Tapo camera (RTSP)</option>
      </select>

      <label class="form-label small fw-semibold" for="calibrationProfile">Intersection Configuration</label>
      <select class="form-select mb-2" id="calibrationProfile">
        <?php foreach ($calibrationProfiles as $profile): ?>
          <option value="<?= esc($profile['file']) ?>"><?= esc($profile['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-light w-100 mb-3" type="button" id="newCalibrationBtn">
        <i class="bi bi-bezier2 me-1"></i>New Line Configuration
      </button>

      <div id="calibrationEditor" class="d-none mb-3">
        <label class="form-label small fw-semibold" for="calibrationName">Configuration Name</label>
        <input class="form-control mb-2" id="calibrationName" maxlength="100" placeholder="Example: City Hall - North View">
        <div class="small text-info mb-2" id="calibrationInstruction">Click two points for the inbound line.</div>
        <button class="btn btn-light w-100 mb-2" type="button" id="addOfficerZoneBtn" disabled>
          <i class="bi bi-person-bounding-box me-1"></i>Add Enforcer Zone (Optional)
        </button>
        <div class="d-flex gap-2">
          <button class="btn btn-primary flex-fill" type="button" id="saveCalibrationBtn" disabled>Save Lines</button>
          <button class="btn btn-light flex-fill" type="button" id="cancelCalibrationBtn">Cancel</button>
        </div>
        <input type="hidden" id="calibrationCsrf" value="<?= esc(csrf_token()) ?>">
      </div>

      <div id="tapoCameraFields" class="d-none">
        <label class="form-label small fw-semibold" for="tapoHost">Camera IP address</label>
        <input class="form-control mb-2" id="tapoHost" inputmode="decimal" placeholder="192.168.1.100" value="<?= esc($camera['ip_address'] ?? '') ?>">
        <label class="form-label small fw-semibold" for="tapoUsername">Tapo camera account</label>
        <input class="form-control mb-2" id="tapoUsername" autocomplete="username" placeholder="Camera account username">
        <label class="form-label small fw-semibold" for="tapoPassword">Camera password</label>
        <input class="form-control mb-2" id="tapoPassword" type="password" autocomplete="current-password" placeholder="Camera account password">
        <label class="form-label small fw-semibold" for="tapoStream">Stream quality</label>
        <select class="form-select mb-2" id="tapoStream">
          <option value="stream2">Standard quality (recommended)</option>
          <option value="stream1">High quality</option>
        </select>
        <small class="text-muted d-block mb-3">Use the camera account created in the Tapo app, not your TP-Link cloud login.</small>
      </div>
    </div>

    <div class="section-card mb-3" id="uploadSourceCard">
      <div class="section-head"><h6 id="sourceActionTitle">Upload CCTV Video</h6></div>

      <form method="post" enctype="multipart/form-data" id="uploadVideoForm">
        <label class="form-label small fw-semibold">LGU CCTV Video Copy</label>
        <input type="hidden" name="MAX_FILE_SIZE" value="<?= $maxUploadBytes ?>">
        <input class="form-control mb-2" type="file" name="cctv_video" id="cctvVideoInput" accept="video/mp4,video/avi,video/quicktime,video/x-matroska" required>
        <button class="btn btn-primary w-100" type="submit" id="uploadVideoBtn">
          <i class="bi bi-upload me-1"></i>Upload Video
        </button>
        <small class="text-muted d-block mt-2">
          Supported: MP4, AVI, MOV, MKV up to 500MB. Saved as <code>computer_vision/uploads/videos/test.mp4</code>.
        </small>
      </form>

      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-accent flex-fill" type="button" id="startAnalysisBtn">
          <i class="bi bi-play-circle me-1"></i><span id="startAnalysisLabel">Start Analysis</span>
        </button>

        <button class="btn btn-light flex-fill" type="button" id="stopAnalysisBtn">
          <i class="bi bi-stop-circle me-1"></i>Stop Analysis
        </button>
      </div>

      <div class="small mt-2" id="analysisMessage"></div>
    </div>

  </div>
</div>

<div class="monitoring-kpi-grid">
  <div>
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-cpu"></i></div>
      <div class="stat-label">AI Status</div>
      <div class="stat-value"><span class="tag tag-muted" id="aiStatus">Idle</span></div>
    </div>
  </div>

  <div>
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-camera-video"></i></div>
      <div class="stat-label">Source</div>
      <div class="stat-value" style="font-size:1.05rem;" id="analysisSource">Not selected</div>
    </div>
  </div>

  <div>
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-car-front"></i></div>
      <div class="stat-label">Vehicle Count</div>
      <div class="stat-value" id="vehicleCount"><?= num($latest['vehicle_count'] ?? 0) ?></div>
    </div>
  </div>

  <div>
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-arrow-up-circle"></i></div>
      <div class="stat-label">Inbound</div>
      <div class="stat-value" id="inboundCount"><?= num($latest['inbound_count'] ?? 0) ?></div>
    </div>
  </div>

  <div>
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-arrow-down-circle"></i></div>
      <div class="stat-label">Outbound</div>
      <div class="stat-value" id="outboundCount"><?= num($latest['outbound_count'] ?? 0) ?></div>
    </div>
  </div>
  <div>
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-activity"></i></div>
      <div class="stat-label">Progress</div>
      <div class="stat-value" style="font-size:1rem;" id="analysisProgressText">Idle</div>
      <div class="progress mt-2" style="height:8px;">
        <div class="progress-bar" id="analysisProgressBar" style="width:0%"></div>
      </div>
    </div>
  </div>

  <div>
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-speedometer2"></i></div>
      <div class="stat-label">Congestion Level</div>
      <div class="stat-value"><span class="tag <?= tag_class($latest['congestion_level'] ?? 'none') ?>" id="congestionLevel"><?= esc($latest['congestion_level'] ?? 'none') ?></span></div>
    </div>
  </div>

  <div>
    <div class="stat-card">
      <div class="stat-icon tone-danger"><i class="bi bi-bell"></i></div>
      <div class="stat-label">Alert Status</div>
      <div class="stat-value"><span class="tag <?= !empty($latest['alert_generated']) ? 'tag-danger' : 'tag-success' ?>" id="alertStatus"><?= !empty($latest['alert_generated']) ? 'ALERT' : 'NORMAL' ?></span></div>
    </div>
  </div>

  <div>
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-person-badge"></i></div>
      <div class="stat-label">Officer Presence</div>
      <div class="stat-value"><span class="tag tag-muted" id="officerPresence"><?= esc($latest['officer_presence'] ?? 'unknown') ?></span></div>
    </div>
  </div>

  <div>
    <div class="stat-card">
      <div class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="stat-label">Potential Collision</div>
      <div class="stat-value"><span class="tag tag-muted" id="potentialCollision"><?= esc($latest['potential_collision'] ?? 'none') ?></span></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4 monitoring-last-updated">
  <div class="col-12">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-clock-history"></i></div>
      <div class="stat-label">Last Updated Time</div>
      <div class="stat-value" style="font-size:1.1rem;" id="lastUpdated">
        <?= esc($latest['recorded_at'] ?? 'No data') ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="section-card">
      <div class="section-head"><h6>Recent Monitoring Logs</h6></div>

      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Recorded At</th>
              <th>Vehicle Count</th>
              <th>Inbound</th>
              <th>Outbound</th>
              <th>Congestion</th>
              <th>Alert</th>
            </tr>
          </thead>
          <tbody id="monitoringLogsBody">
            <?php if (!$logs): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">No monitoring logs yet.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($logs as $l): ?>
                <tr>
                  <td><?= esc($l['recorded_at']) ?></td>
                  <td><?= num($l['vehicle_count']) ?></td>
                  <td><?= num($l['inbound_count']) ?></td>
                  <td><?= num($l['outbound_count']) ?></td>
                  <td><span class="tag <?= tag_class($l['congestion_level']) ?>"><?= esc($l['congestion_level']) ?></span></td>
                  <td><span class="tag <?= !empty($l['alert_generated']) ? 'tag-danger' : 'tag-success' ?>"><?= !empty($l['alert_generated']) ? 'ALERT' : 'NORMAL' ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="section-card">
      <div class="section-head"><h6>Recent CV Alerts</h6></div>

      <?php if (!$activeAlerts): ?>
        <?php empty_state('No computer vision alerts found.'); ?>
      <?php else: ?>
        <?php foreach ($activeAlerts as $a): ?>
          <div class="d-flex justify-content-between border-bottom py-2">
            <div>
              <strong><?= esc(ucfirst($a['alert_type'])) ?></strong><br>
              <small class="text-muted"><?= esc($a['message']) ?></small>
            </div>
            <span class="tag <?= tag_class($a['severity']) ?>"><?= esc($a['severity']) ?></span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="congestion-live-alert" id="congestionLiveAlert" role="alert" aria-live="assertive" aria-atomic="true">
  <div class="congestion-live-alert-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
  <div class="congestion-live-alert-copy">
    <strong id="liveSafetyAlertTitle">Heavy traffic congestion detected</strong>
    <span id="congestionLiveAlertMessage">The monitored area has exceeded the safe traffic threshold.</span>
    <a href="<?= esc(app_url('alerts.php')) ?>">View alert details <i class="bi bi-arrow-right"></i></a>
  </div>
  <button type="button" aria-label="Dismiss congestion alert" onclick="hideCongestionAlert()"><i class="bi bi-x-lg"></i></button>
</div>

<script src="<?= esc(asset_url('js/monitoring.js') . '?v=' . filemtime(dirname(__DIR__, 2) . '/js/monitoring.js')) ?>"></script>

<?php page_end(); ?>
