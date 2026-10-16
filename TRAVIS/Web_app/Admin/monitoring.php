<?php
ini_set('upload_max_filesize', '500M');
ini_set('post_max_size', '520M');
ini_set('max_execution_time', '300');
ini_set('max_input_time', '300');

require_once __DIR__ . '/layout.php';

$uploadMessage = '';
$uploadedVideo = null;
$maxUploadBytes = 500 * 1024 * 1024;
$webrtcStreamUrl = trim(travis_config_value('TRAVIS_WEBRTC_URL', 'webrtc_url', ''));

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

$selectedCameraId = max(0, (int)($_GET['camera_id'] ?? 0));
$cameras = fetch_all(<<<'SQL'
    SELECT c.*,
           l.last_seen_at
    FROM cameras c
    LEFT JOIN (
        SELECT camera_id, MAX(recorded_at) AS last_seen_at
        FROM camera_monitoring_logs
        GROUP BY camera_id
    ) l ON l.camera_id = c.camera_id
    ORDER BY c.camera_id
SQL);
$camera = null;
foreach ($cameras as $candidate) {
    if ($selectedCameraId > 0 && (int)$candidate['camera_id'] === $selectedCameraId) {
        $camera = $candidate;
        break;
    }
}
if (!$camera) $camera = $cameras[0] ?? [];
$selectedCameraId = (int)($camera['camera_id'] ?? 0);
$selectedLastSeenAt = strtotime((string)($camera['last_seen_at'] ?? '')) ?: 0;
$cameraDisplayStatus = !empty($camera['is_enabled']) && $selectedLastSeenAt >= time() - 90
    ? 'online'
    : 'offline';
$latest = null;
if ($selectedCameraId > 0) {
    $latestStatement = $conn->prepare('SELECT * FROM camera_monitoring_logs WHERE camera_id=? ORDER BY recorded_at DESC LIMIT 1');
    $latestStatement->bind_param('i', $selectedCameraId);
    $latestStatement->execute();
    $latest = $latestStatement->get_result()->fetch_assoc() ?: null;
}
$cameraConfigPath = dirname(__DIR__, 2) . '/computer_vision/camera_config.json';
$cameraConfig = is_file($cameraConfigPath)
    ? json_decode((string)file_get_contents($cameraConfigPath), true)
    : [];
$cameraConfig = is_array($cameraConfig) ? $cameraConfig : [];
$cameraProfile = (string)($camera['calibration_profile'] ?? '');
$analysisStatusPath = dirname(__DIR__) . '/api/analysis_status.json';
$savedAnalysisStatus = is_file($analysisStatusPath)
    ? json_decode((string)file_get_contents($analysisStatusPath), true)
    : [];
$savedAnalysisStatus = is_array($savedAnalysisStatus) ? $savedAnalysisStatus : [];
$latestFeedStatusPath = dirname(__DIR__) . '/api/latest_status.json';
$latestFeedStatus = is_file($latestFeedStatusPath)
    ? json_decode((string)file_get_contents($latestFeedStatusPath), true)
    : [];
$latestFeedStatus = is_array($latestFeedStatus) ? $latestFeedStatus : [];
$liveStatusEpoch = max(
    (int)($latestFeedStatus['updated_at_epoch'] ?? 0),
    (int)($savedAnalysisStatus['updated_at_epoch'] ?? 0)
);
$liveStatusCameraId = max(0, (int)($latestFeedStatus['camera_id'] ?? $savedAnalysisStatus['camera_id'] ?? 0));
$hasRecentLiveStatus = $liveStatusEpoch > 0 && time() - $liveStatusEpoch <= 10;
if ($hasRecentLiveStatus && ($liveStatusCameraId === 0 || $liveStatusCameraId === $selectedCameraId)) {
    $cameraDisplayStatus = 'online';
}
$savedSourceType = (string)($savedAnalysisStatus['source_type'] ?? $latestFeedStatus['source_type'] ?? 'uploaded_video');
if (!in_array($savedSourceType, ['uploaded_video', 'tapo_camera'], true)) {
    $savedSourceType = 'uploaded_video';
}

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
$canManageCameras = strcasecmp((string)($_SESSION['user']['role'] ?? ''), 'Administrator') === 0;

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
/* Keep the monitor and its source controls aligned as one desktop row. */
.monitoring-main-row{align-items:stretch}
.monitoring-camera-card{height:100%;display:flex;flex-direction:column}
.monitoring-camera-card .camera-stage{flex:1;min-height:360px}
.monitoring-controls-column{gap:16px}
.monitoring-controls-column > .section-card{margin-bottom:0 !important}
#uploadSourceCard{flex:none;display:flex;flex-direction:column}
#uploadSourceCard.camera-source-hidden{display:none!important}
#uploadVideoForm{display:flex;flex-direction:column}
#uploadVideoBtn{width:100% !important;min-height:40px}
.monitoring-control-card,.camera-session-card{padding:18px!important;border-radius:16px!important;box-shadow:0 10px 24px rgba(16,47,73,.07)!important}.control-card-title{display:flex;align-items:center;gap:11px;margin-bottom:16px}.control-card-icon{display:grid;flex:0 0 38px;width:38px;height:38px;place-items:center;border-radius:11px;background:rgba(8,125,120,.1);color:#087d78;font-size:1rem}.control-card-title h6{margin:0!important;color:#10202c!important;font-size:.9rem!important}.control-card-title small{display:block;margin-top:2px;color:#73857f!important;font-size:.64rem}.monitoring-control-card .form-label{margin-bottom:6px!important;color:#334c45!important;font-size:.68rem!important;text-transform:uppercase;letter-spacing:.04em}.monitoring-control-card .form-select{min-height:43px;border-radius:11px!important}.calibration-action-row{display:grid!important;grid-template-columns:minmax(0,1fr) 44px 44px;gap:8px!important;margin-bottom:0!important}.calibration-action-row .btn{height:38px!important;min-height:38px!important;border-radius:9px!important}.camera-session-selected{display:flex;align-items:center;gap:10px;margin-bottom:13px;padding:10px 12px;border:1px solid rgba(8,125,120,.14);border-radius:11px;background:rgba(8,125,120,.055)}.camera-session-selected i{color:#087d78}.camera-session-selected span{display:block;color:#667a74;font-size:.62rem;text-transform:uppercase;letter-spacing:.05em}.camera-session-selected strong{display:block;color:#10202c;font-size:.76rem}.camera-session-actions{display:grid!important;grid-template-columns:1fr auto;gap:8px!important;margin-top:0!important}.camera-session-actions .btn{min-height:40px!important;height:40px!important;border-radius:10px!important}.camera-session-card #stopAnalysisBtn:disabled{display:none!important}.camera-session-card #analysisMessage{min-height:1.2rem;margin-top:10px!important;color:#60736d!important;font-size:.69rem!important;line-height:1.45}
.cv-process-modal .modal-dialog{max-width:460px}
.cv-process-modal .modal-content{overflow:hidden;border-radius:18px!important}
.cv-process-modal .cv-process-icon{display:grid;width:54px;height:54px;place-items:center;flex:0 0 54px;border-radius:15px;background:rgba(8,125,120,.11);color:#087d78;font-size:1.35rem}
.cv-process-modal .cv-process-progress{height:9px;border-radius:999px;overflow:hidden;background:rgba(16,47,73,.09)!important}
.cv-process-modal .cv-process-progress .progress-bar{background:linear-gradient(90deg,#087d78,#22b8a7)!important;transition:width .22s ease}
.cv-process-modal .cv-process-detail{min-height:1.25rem;color:#60716b;font-size:.76rem;line-height:1.55}
body.municipal-portal #cctvVideoInput{
    min-height:48px;
    padding:4px !important;
    color:var(--municipal-ink) !important;
    background:rgba(255,255,255,.88) !important;
    border:1px solid rgba(16,47,73,.22) !important;
    overflow:hidden;
}
body.municipal-portal #cctvVideoInput::file-selector-button{
    display:inline-block;
    min-height:38px;
    margin:-1px 12px -1px -1px !important;
    padding:0 16px !important;
    color:#fff !important;
    font-weight:700;
    background:#087d78 !important;
    border:0 !important;
    border-radius:8px !important;
    cursor:pointer;
}
body.municipal-portal #cctvVideoInput::-webkit-file-upload-button{
    min-height:38px;
    margin:-1px 12px -1px -1px !important;
    padding:0 16px !important;
    color:#fff !important;
    font-weight:700;
    background:#087d78 !important;
    border:0 !important;
    border-radius:8px !important;
}
@media(max-width:991.98px){
    .monitoring-camera-card{height:auto}
    .monitoring-camera-card .camera-stage{min-height:0}
    #uploadSourceCard{flex:none}
}
.form-label{color:var(--text-soft) !important}

/* ==== Camera stage (video area) ==== */
.camera-stage{
    position:relative;
    width:100%;
    aspect-ratio:16/9;
    background:#020617;
    border:1px solid var(--border-glass);
    border-radius:14px;
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
    color:var(--text-soft);
}
#aiLiveStream{
    width:100%;
    height:100%;
    object-fit:contain;
    object-position:center;
    border-radius:12px;
}
#webrtcLiveStream{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    border:0;
    z-index:2;
    background:#020617;
}
#snapshotCanvas{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    z-index:1;
    border-radius:12px;
}
#calibrationCanvas{
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    z-index:5;
    display:none;
    pointer-events:none;
    cursor:crosshair;
    touch-action:none;
}
#calibrationCanvas.active{display:block;pointer-events:auto;}

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
      <span class="system-online-dot <?= $cameraDisplayStatus === 'online' ? 'online' : 'offline' ?>"></span>
      <?= esc($cameraDisplayStatus) ?>
    </span>
  </div>
</div>

<?php if ($uploadMessage): ?>
<?php $uploadSucceeded = str_contains(strtolower($uploadMessage), 'success'); ?>
<div
  class="alert fade show <?= $uploadSucceeded ? 'alert-success' : 'alert-warning' ?>"
  id="uploadResultAlert"
  data-upload-success="<?= $uploadSucceeded ? 'true' : 'false' ?>"
>
  <?= esc($uploadMessage) ?>
</div>
<?php endif; ?>

<style>
.camera-registry-panel{position:relative;z-index:6;margin-bottom:1.5rem;padding:18px 20px;border:1px solid rgba(16,47,73,.13);border-radius:18px;background:rgba(255,255,255,.78);box-shadow:0 12px 28px rgba(16,47,73,.07)}
.camera-registry-heading{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:14px}.camera-registry-heading h5{margin:0 0 3px;color:#10202c;font-size:1rem;font-weight:800}.camera-registry-heading small{color:#667a74;font-size:.75rem}
.camera-registry-add{flex:0 0 auto;min-height:38px!important;height:38px!important;padding:0 15px!important;border-radius:10px!important;background:#087d78!important;box-shadow:none!important;color:#fff!important}
.camera-registry-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.camera-registry-card{position:relative;z-index:1;display:flex;min-width:0;flex-direction:column;overflow:hidden;border:1px solid rgba(16,47,73,.14)!important;border-radius:14px;background:#fff!important;color:#10202c!important;box-shadow:0 5px 16px rgba(16,47,73,.05)}
.camera-registry-card.is-selected{border-color:#0b8f89!important;box-shadow:0 0 0 2px rgba(8,125,120,.12),0 7px 18px rgba(16,47,73,.08)}
.camera-monitor-screen canvas{position:absolute;inset:0;z-index:1;width:100%;height:100%;object-fit:cover}.camera-monitor-placeholder{z-index:0}
.camera-monitor-screen{position:relative;aspect-ratio:16/9;overflow:hidden;background:linear-gradient(145deg,#07111f,#102f49);color:#d9e8e3}.camera-monitor-screen img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.camera-monitor-placeholder{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:7px;background:radial-gradient(circle at 50% 30%,rgba(56,189,248,.12),transparent 45%)}.camera-monitor-placeholder i{font-size:1.45rem;color:#65d5ce}.camera-monitor-placeholder small{color:#9db4ad!important;font-size:.65rem}.camera-monitor-open{position:absolute;inset:0;z-index:3;border:0;background:transparent;cursor:zoom-in}.camera-monitor-open:focus-visible{outline:3px solid #38bdf8;outline-offset:-4px}.camera-monitor-live{position:absolute;top:9px;left:9px;z-index:4;display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:999px;background:rgba(6,15,30,.78);color:#fff;font-size:.61rem;font-weight:800;text-transform:uppercase}.camera-monitor-live::before{content:"";width:6px;height:6px;border-radius:50%;background:#34d399;box-shadow:0 0 0 3px rgba(52,211,153,.2)}
.camera-registry-card-foot{display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:10px;padding:11px 12px}.camera-registry-card h6{margin:0;color:#10202c!important;font-size:.8rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.camera-registry-card p{margin:2px 0 0;color:#667a74!important;font-size:.64rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.camera-registry-actions{display:flex;align-items:center;gap:6px}.camera-registry-actions .btn{position:relative;z-index:5;min-height:32px!important;height:32px!important;border-radius:8px!important;box-shadow:none!important}.camera-registry-actions .camera-join-btn{width:auto!important;padding:0 10px!important;background:#087d78!important}.camera-registry-actions .camera-settings-btn{width:34px!important;padding:0!important;color:#405b54!important;background:#fff!important;border:1px solid rgba(16,47,73,.2)!important}
.camera-registry-card.is-empty{border-style:dashed!important;background:rgba(247,250,249,.8)!important;box-shadow:none}.camera-registry-card.is-empty .camera-monitor-screen{background:#eef4f2}.camera-registry-card.is-empty .camera-monitor-placeholder{color:#6b7f79;background:none}.camera-registry-card.is-empty .camera-monitor-placeholder i{color:#8da39d}.camera-registry-card.is-empty .camera-monitor-placeholder small{color:#758a84!important}.camera-slot-add{position:absolute;inset:0;z-index:3;border:0;background:transparent;cursor:pointer}
.camera-registry-empty{grid-column:1/-1;padding:18px;border:1px dashed rgba(16,47,73,.2);border-radius:12px;color:#667a74;text-align:center}
body.municipal-portal .camera-settings-modal .modal-content{border:1px solid rgba(16,47,73,.16)!important;border-radius:18px!important;background:#fff!important;color:#10202c!important;box-shadow:0 22px 60px rgba(16,47,73,.22)!important}body.municipal-portal .camera-settings-modal .modal-header,body.municipal-portal .camera-settings-modal .modal-footer{border-color:rgba(16,47,73,.1)!important}body.municipal-portal .camera-settings-modal .modal-title{color:#10202c!important}body.municipal-portal .camera-settings-modal .btn-close{filter:none!important}
#monitoringSetupCard{display:none!important}.camera-line-settings{padding:15px;border:1px solid rgba(8,125,120,.18);border-radius:13px;background:rgba(8,125,120,.045)}.camera-line-settings-head{display:flex;align-items:center;gap:10px;margin-bottom:13px}.camera-line-settings-head>i{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:rgba(8,125,120,.11);color:#087d78}.camera-line-settings-head strong{display:block;color:#10202c;font-size:.82rem}.camera-line-settings-head small{display:block;color:#71817c;font-size:.67rem}.camera-line-settings #calibrationProfile{margin-bottom:9px!important}.camera-line-settings .calibration-action-row{margin:0!important}.calibration-editor-card{width:100%}.monitoring-main-row:has(#uploadSourceCard.camera-source-hidden):not(:has(#calibrationEditor:not(.d-none))) .monitoring-view-column{width:100%!important}.monitoring-main-row:has(#uploadSourceCard.camera-source-hidden):not(:has(#calibrationEditor:not(.d-none))) .monitoring-controls-column{display:none!important}
@media(max-width:1199.98px){.camera-registry-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:575.98px){.camera-registry-panel{padding:15px}.camera-registry-heading{align-items:flex-start}.camera-registry-heading small{display:none}.camera-registry-grid{grid-template-columns:1fr}}
</style>

<section class="camera-registry-panel" aria-labelledby="cameraRegistryTitle">
<div class="camera-registry-heading">
  <div><h5 id="cameraRegistryTitle">Registered Cameras</h5><small>Choose a saved camera to join its monitoring session.</small></div>
  <?php if ($canManageCameras): ?><button class="btn camera-registry-add" id="addCameraBtn" type="button"><i class="bi bi-plus-lg me-1"></i>Add Camera</button><?php endif; ?>
</div>
<div class="camera-registry-grid" id="cameraRegistryGrid">
  <?php if (!$cameras): ?><div class="camera-registry-empty">No cameras registered yet. Use Add Camera to create the first one.</div><?php endif; ?>
  <?php foreach ($cameras as $registeredCamera): ?>
    <?php
      $registeredId = (int)$registeredCamera['camera_id'];
      $lastSeen = trim((string)($registeredCamera['last_seen_at'] ?? ''));
      $lastSeenEpoch = strtotime($lastSeen) ?: 0;
      $hasActiveFeed = $hasRecentLiveStatus
        && ($liveStatusCameraId === 0 || $liveStatusCameraId === $registeredId);
      $cardStatus = !empty($registeredCamera['is_enabled']) && ($hasActiveFeed || $lastSeenEpoch >= time() - 90)
        ? 'online'
        : 'offline';
    ?>
    <article class="camera-registry-card <?= $registeredId === $selectedCameraId ? 'is-selected' : '' ?>" data-camera='<?= esc(json_encode($registeredCamera, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)) ?>'>
      <div class="camera-monitor-screen">
        <canvas class="camera-wall-preview" aria-label="<?= esc($registeredCamera['camera_name']) ?> live preview" hidden></canvas>
        <div class="camera-monitor-placeholder"><i class="bi bi-camera-video"></i><small><?= $cardStatus === 'online' ? 'Opening live preview…' : 'Camera currently offline' ?></small></div>
        <?php if ($cardStatus === 'online'): ?><span class="camera-monitor-live">Live</span><?php endif; ?>
        <button class="camera-monitor-open" type="button" aria-label="Open large view for <?= esc($registeredCamera['camera_name']) ?>"></button>
      </div>
      <div class="camera-registry-card-foot">
        <div><h6><?= esc($registeredCamera['camera_name']) ?></h6><p><?= esc($registeredCamera['location']) ?> · <?= esc(ucfirst($cardStatus)) ?></p></div>
        <div class="camera-registry-actions">
          <button class="btn btn-primary camera-join-btn" type="button" data-camera-id="<?= $registeredId ?>" <?= empty($registeredCamera['is_enabled']) ? 'disabled title="Camera is disabled"' : '' ?>><i class="bi bi-broadcast"></i><span class="d-none d-xl-inline ms-1">Join</span></button>
          <?php if ($canManageCameras): ?><button class="btn btn-light camera-settings-btn" type="button" aria-label="Settings for <?= esc($registeredCamera['camera_name']) ?>"><i class="bi bi-gear"></i></button><?php endif; ?>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
  <?php for ($slot = count($cameras) + 1; $slot <= 6; $slot++): ?>
    <article class="camera-registry-card is-empty">
      <div class="camera-monitor-screen">
        <div class="camera-monitor-placeholder"><i class="bi bi-camera-video-off"></i><strong>Camera <?= $slot ?></strong><small>Not configured</small></div>
        <?php if ($canManageCameras): ?><button class="camera-slot-add" type="button" aria-label="Configure camera slot <?= $slot ?>"></button><?php endif; ?>
      </div>
      <div class="camera-registry-card-foot"><div><h6>Available Camera Slot</h6><p>Ready for future installation</p></div><span class="tag tag-muted">Inactive</span></div>
    </article>
  <?php endfor; ?>
</div>
</section>

<div class="row g-3 mb-4 monitoring-main-row" id="monitoringDetailPanel">
  <div class="col-lg-8 d-flex monitoring-view-column">
    <div class="section-card monitoring-camera-card w-100">
      <div class="section-head">
        <div>
          <h6 id="activeCameraName"><?= esc($camera['camera_name'] ?? 'Main Camera Monitor') ?></h6>
          <small class="text-muted" id="activeCameraLocation"><?= esc($camera['location'] ?? 'Select a registered camera') ?></small>
        </div>
        <span class="tag tag-info" id="sourceStatus">Ready</span>
      </div>

      <div
        class="camera-stage mb-3"
        id="cameraStage"
        data-webrtc-url="<?= esc($webrtcStreamUrl) ?>"
        data-camera-id="<?= $selectedCameraId ?>"
      >
        <iframe
          id="webrtcLiveStream"
          title="TRAVIS WebRTC live stream"
          allow="autoplay; fullscreen; picture-in-picture"
          referrerpolicy="strict-origin-when-cross-origin"
          style="display:none;"
        ></iframe>
        <img
          id="aiLiveStream"
          alt="TRAVIS Live AI Detection Stream"
          style="display:none;"
          onerror="this.style.display='none'; document.getElementById('streamFallback').style.display='flex';"
          onload="this.style.display='block'; document.getElementById('streamFallback').style.display='none';"
        >

        <div
          class="text-center p-4"
          id="streamFallback"
          style="display:flex; width:100%; height:100%; align-items:center; justify-content:center; flex-direction:column;"
        >
          <i class="bi bi-broadcast fs-1 d-block mb-3" style="color:var(--cyan-glow);"></i>
          <h5 id="streamFallbackTitle">Waiting for AI live stream</h5>
          <p class="mb-0 opacity-75" id="streamFallbackMessage">
            Select a source and start analysis to activate the AI stream.
          </p>
        </div>

        <canvas id="snapshotCanvas" style="display:none;"></canvas>
        <canvas id="calibrationCanvas" aria-label="Line configuration editor"></canvas>
      </div>

      <small class="text-muted d-block mt-2">
        <span id="streamTransportHelp">The best available live-stream transport is selected automatically.</span>
        Dashboard values update automatically from <code>api/get_status.php</code>.
      </small>
    </div>
  </div>

  <div class="col-lg-4 d-flex flex-column monitoring-controls-column">
    <div class="section-card monitoring-control-card mb-3" id="monitoringSetupCard">
      <div class="control-card-title"><span class="control-card-icon"><i class="bi bi-sliders2"></i></span><div><h6>Monitoring Setup</h6><small>Choose the source and line configuration</small></div></div>
      <label class="form-label small fw-semibold" for="monitoringSource">Source</label>
      <select class="form-select mb-3" id="monitoringSource">
        <option value="uploaded_video" <?= $savedSourceType === 'uploaded_video' ? 'selected' : '' ?>>Uploaded CCTV video</option>
        <option value="tapo_camera" <?= $savedSourceType === 'tapo_camera' ? 'selected' : '' ?>>Tapo camera (RTSP)</option>
      </select>

      <label class="form-label small fw-semibold" for="calibrationProfile">Intersection Configuration</label>
      <select class="form-select mb-2" id="calibrationProfile">
        <?php foreach ($calibrationProfiles as $profile): ?>
          <option value="<?= esc($profile['file']) ?>" <?= $cameraProfile === $profile['file'] ? 'selected' : '' ?>><?= esc($profile['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="calibration-action-row">
        <button class="btn btn-light flex-fill" type="button" id="newCalibrationBtn">
          <i class="bi bi-bezier2 me-1"></i>New Line Configuration
        </button>
        <button class="btn btn-outline-primary px-3" type="button" id="editCalibrationBtn" aria-label="Edit selected intersection configuration" title="Edit selected configuration">
          <i class="bi bi-pencil-square"></i>
        </button>
        <button class="btn btn-outline-danger px-3" type="button" id="deleteCalibrationBtn" aria-label="Delete selected intersection configuration" title="The default configuration cannot be deleted">
          <i class="bi bi-trash3"></i>
        </button>
      </div>

      <div id="calibrationEditor" class="d-none mb-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <span class="small fw-bold" id="calibrationEditorHeading">New Configuration</span>
          <span class="badge text-bg-info d-none" id="calibrationEditBadge">Editing</span>
        </div>
        <label class="form-label small fw-semibold" for="calibrationName">Configuration Name</label>
        <input class="form-control mb-2" id="calibrationName" maxlength="100" placeholder="Example: City Hall - North View">
        <div class="small text-info mb-2" id="calibrationInstruction">Choose which line you want to draw first.</div>
        <div class="d-flex gap-2 mb-2">
          <button class="btn btn-light flex-fill" type="button" id="drawInboundLineBtn">
            <i class="bi bi-arrow-down-left me-1"></i>Inbound Line
          </button>
          <button class="btn btn-light flex-fill" type="button" id="drawOutboundLineBtn">
            <i class="bi bi-arrow-up-right me-1"></i>Outbound Line
          </button>
        </div>
        <button class="btn btn-light w-100 mb-2" type="button" id="addOfficerZoneBtn">
          <i class="bi bi-person-bounding-box me-1"></i>Add Enforcer Zone (Optional)
        </button>
        <button class="btn btn-outline-secondary w-100 mb-2 d-none" type="button" id="clearOfficerZoneBtn">
          <i class="bi bi-eraser me-1"></i>Remove Enforcer Zone
        </button>
        <div class="d-flex gap-2">
          <button class="btn btn-primary flex-fill" type="button" id="saveCalibrationBtn" disabled>Save Configuration</button>
          <button class="btn btn-light flex-fill" type="button" id="cancelCalibrationBtn">Cancel</button>
        </div>
        <input type="hidden" id="calibrationCsrf" value="<?= esc(csrf_token()) ?>">
      </div>

      <div id="tapoCameraFields" class="d-none">
        <label class="form-label small fw-semibold" for="tapoHost">Camera IP address</label>
        <input class="form-control mb-2" id="tapoHost" inputmode="decimal" placeholder="192.168.1.100" value="<?= esc($cameraConfig['host'] ?? $camera['ip_address'] ?? '') ?>">
        <label class="form-label small fw-semibold" for="tapoUsername">Tapo camera account</label>
        <input class="form-control mb-2" id="tapoUsername" autocomplete="username" placeholder="Camera account username" value="<?= esc($cameraConfig['username'] ?? '') ?>">
        <label class="form-label small fw-semibold" for="tapoPassword">Camera password</label>
        <input class="form-control mb-2" id="tapoPassword" type="password" autocomplete="current-password" placeholder="<?= !empty($cameraConfig['password']) ? 'Saved password (leave blank to reuse)' : 'Camera account password' ?>">
        <label class="form-label small fw-semibold" for="tapoStream">Stream quality</label>
        <select class="form-select mb-2" id="tapoStream">
          <option value="stream2" <?= ($cameraConfig['stream'] ?? 'stream2') !== 'stream1' ? 'selected' : '' ?>>Standard quality (recommended)</option>
          <option value="stream1" <?= ($cameraConfig['stream'] ?? '') === 'stream1' ? 'selected' : '' ?>>High quality</option>
        </select>
        <small class="text-muted d-block mb-3"><strong>Important:</strong> use the separate Camera Account username created under Tapo App → Device Settings → Advanced Settings → Camera Account. The device name and TP-Link cloud login will not work for RTSP.</small>
      </div>

    </div>

    <div class="section-card camera-session-card" id="uploadSourceCard">
      <div class="control-card-title"><span class="control-card-icon"><i class="bi bi-broadcast-pin"></i></span><div><h6 id="sourceActionTitle">Upload CCTV Video</h6><small>Connection and analysis controls</small></div></div>

      <div class="camera-session-selected"><i class="bi bi-camera-video"></i><div><span>Selected monitor</span><strong id="sessionCameraName"><?= esc($camera['camera_name'] ?? 'No camera selected') ?></strong></div></div>

      <form method="post" enctype="multipart/form-data" id="uploadVideoForm" data-footage-ready="<?= is_file(dirname(__DIR__, 2) . '/computer_vision/uploads/videos/test.mp4') ? 'true' : 'false' ?>">
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

      <div class="camera-session-actions">
        <button class="btn btn-accent flex-fill" type="button" id="startAnalysisBtn">
          <i class="bi bi-play-circle me-1"></i><span id="startAnalysisLabel">Start Analysis</span>
        </button>

        <button
          class="btn btn-light flex-fill"
          type="button"
          id="stopAnalysisBtn"
        >
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
      <div class="stat-label">Collision</div>
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

<div class="modal fade camera-settings-modal" id="cameraSettingsModal" tabindex="-1" aria-labelledby="cameraSettingsTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <div><span class="dashboard-eyebrow">Camera registry</span><h5 class="modal-title" id="cameraSettingsTitle">Camera Settings</h5></div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-light small"><i class="bi bi-shield-lock me-1"></i>The password is sent only to the assigned gate laptop. Leave it blank when the saved edge credential has not changed.</div>
        <input type="hidden" id="registryCameraId" value="0">
        <input type="hidden" id="cameraRegistryCsrf" value="<?= esc(csrf_token()) ?>">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label" for="registryCameraName">Camera name</label><input class="form-control" id="registryCameraName" maxlength="120" placeholder="Gate Entrance Camera"></div>
          <div class="col-md-6"><label class="form-label" for="registryCameraLocation">Location</label><input class="form-control" id="registryCameraLocation" maxlength="255" placeholder="Main gate, inbound lane"></div>
          <div class="col-md-6"><label class="form-label" for="registryCameraIp">Camera IP address</label><input class="form-control" id="registryCameraIp" inputmode="decimal" placeholder="192.168.1.100"></div>
          <div class="col-md-6"><label class="form-label" for="registryCameraUsername">Tapo camera account</label><input class="form-control" id="registryCameraUsername" maxlength="120" autocomplete="username"></div>
          <div class="col-md-6"><label class="form-label" for="registryCameraPassword">Camera password</label><input class="form-control" id="registryCameraPassword" type="password" autocomplete="new-password" placeholder="Leave blank to reuse saved edge password"></div>
          <div class="col-md-6"><label class="form-label" for="registryCameraStream">Stream quality</label><select class="form-select" id="registryCameraStream"><option value="stream2">Standard quality (recommended)</option><option value="stream1">High quality</option></select></div>
          <div class="col-md-6"><label class="form-label" for="registryCameraCalibration">Assigned line configuration</label><select class="form-select" id="registryCameraCalibration"><option value="">No profile assigned</option><?php foreach ($calibrationProfiles as $profile): ?><option value="<?= esc($profile['file']) ?>"><?= esc($profile['name']) ?></option><?php endforeach; ?></select><small class="text-muted">Select the configuration used by this camera.</small></div>
          <div class="col-md-6"><label class="form-label" for="registryCameraWorker">Gate worker ID</label><input class="form-control" id="registryCameraWorker" maxlength="100" placeholder="GATE-LAPTOP-cv"><small class="text-muted">Optional until multiple gate workers are installed.</small></div>
          <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="registryCameraEnabled" checked><label class="form-check-label" for="registryCameraEnabled">Camera enabled for monitoring</label></div></div>
          <div class="col-12"><div class="camera-line-settings" id="cameraLineSettings"><div class="camera-line-settings-head"><i class="bi bi-bezier2"></i><div><strong>Line configurations</strong><small>Create, edit, or remove counting-line profiles.</small></div></div><div id="cameraLineConfigurationControls"></div></div></div>
        </div>
        <div class="small mt-3" id="cameraSettingsMessage" aria-live="polite"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="saveCameraSettingsBtn"><i class="bi bi-check2-circle me-1"></i>Save Camera</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade cv-process-modal" id="cvProcessModal" tabindex="-1" aria-labelledby="cvProcessTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-body p-4">
        <div class="d-flex align-items-start gap-3">
          <span class="cv-process-icon" id="cvProcessIcon" aria-hidden="true"><i class="bi bi-cloud-arrow-up-fill"></i></span>
          <div class="flex-grow-1 min-w-0">
            <h5 class="mb-1" id="cvProcessTitle">Preparing Computer Vision</h5>
            <p class="cv-process-detail mb-3" id="cvProcessDetail">Please keep this page open while the process completes.</p>
            <div class="progress cv-process-progress" role="progressbar" aria-label="Computer Vision process" aria-valuemin="0" aria-valuemax="100">
              <div class="progress-bar progress-bar-striped progress-bar-animated" id="cvProcessBar" style="width:12%"></div>
            </div>
            <div class="d-flex justify-content-between mt-2 small text-muted">
              <span id="cvProcessStage">Preparing…</span>
              <strong id="cvProcessPercent">—</strong>
            </div>
          </div>
        </div>
        <div class="d-none mt-3" id="cvProcessActions">
          <button class="btn btn-light w-100" type="button" id="cvProcessCloseBtn">Close</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script src="<?= esc(asset_url('js/monitoring.js') . '?v=' . filemtime(dirname(__DIR__, 2) . '/js/monitoring.js')) ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const modalElement = document.getElementById('cameraSettingsModal');
  const modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
  const lineControlsTarget = document.getElementById('cameraLineConfigurationControls');
  const calibrationProfile = document.getElementById('calibrationProfile');
  const calibrationLabel = document.querySelector('label[for="calibrationProfile"]');
  const calibrationActions = document.querySelector('.calibration-action-row');
  const calibrationEditor = document.getElementById('calibrationEditor');
  const uploadSourceCard = document.getElementById('uploadSourceCard');

  if (lineControlsTarget && calibrationProfile && calibrationActions) {
    calibrationLabel?.remove();
    const modalLabel = document.createElement('label');
    modalLabel.className = 'form-label small fw-semibold';
    modalLabel.htmlFor = 'calibrationProfile';
    modalLabel.textContent = 'Manage line configuration';
    lineControlsTarget.append(modalLabel, calibrationProfile, calibrationActions);
  }
  if (calibrationEditor && uploadSourceCard) {
    uploadSourceCard.parentElement?.insertBefore(calibrationEditor, uploadSourceCard);
  }

  ['newCalibrationBtn', 'editCalibrationBtn'].forEach(id => {
    document.getElementById(id)?.addEventListener('click', () => {
      modal?.hide();
      window.setTimeout(() => document.getElementById('monitoringDetailPanel')?.scrollIntoView({behavior: 'smooth', block: 'start'}), 180);
    });
  });

  const fields = {
    id: document.getElementById('registryCameraId'),
    name: document.getElementById('registryCameraName'),
    location: document.getElementById('registryCameraLocation'),
    ip: document.getElementById('registryCameraIp'),
    username: document.getElementById('registryCameraUsername'),
    password: document.getElementById('registryCameraPassword'),
    stream: document.getElementById('registryCameraStream'),
    calibration: document.getElementById('registryCameraCalibration'),
    worker: document.getElementById('registryCameraWorker'),
    enabled: document.getElementById('registryCameraEnabled')
  };
  const message = document.getElementById('cameraSettingsMessage');
  const saveButton = document.getElementById('saveCameraSettingsBtn');

  function readCamera(card) {
    try { return JSON.parse(card?.dataset.camera || '{}'); } catch (_) { return {}; }
  }

  function populate(camera = {}) {
    fields.id.value = camera.camera_id || 0;
    fields.name.value = camera.camera_name || '';
    fields.location.value = camera.location || '';
    fields.ip.value = camera.ip_address || '';
    fields.username.value = camera.camera_username || '';
    fields.password.value = '';
    fields.password.placeholder = camera.has_saved_password
      ? 'Saved securely — leave blank to keep it'
      : 'Enter camera password';
    fields.stream.value = camera.stream_quality === 'stream1' ? 'stream1' : 'stream2';
    fields.calibration.value = camera.calibration_profile || '';
    fields.worker.value = camera.edge_worker_id || '';
    fields.enabled.checked = String(camera.is_enabled ?? '1') !== '0';
    message.textContent = '';
    document.getElementById('cameraSettingsTitle').textContent = camera.camera_id ? 'Camera Settings' : 'Register Camera';
  }

  function selectCamera(camera, card) {
    document.querySelectorAll('.camera-registry-card.is-selected').forEach(item => item.classList.remove('is-selected'));
    card?.classList.add('is-selected');
    document.getElementById('activeCameraName').textContent = camera.camera_name || 'Camera Monitor';
    document.getElementById('activeCameraLocation').textContent = camera.location || '';
    document.getElementById('sessionCameraName').textContent = camera.camera_name || 'No camera selected';
    document.getElementById('cameraStage').dataset.cameraId = camera.camera_id || 0;
    document.getElementById('tapoHost').value = camera.ip_address || '';
    document.getElementById('tapoUsername').value = camera.camera_username || '';
    document.getElementById('tapoStream').value = camera.stream_quality === 'stream1' ? 'stream1' : 'stream2';
    const profile = document.getElementById('calibrationProfile');
    if (profile && camera.calibration_profile) profile.value = camera.calibration_profile;
  }

  document.querySelectorAll('.camera-monitor-open').forEach(button => button.addEventListener('click', () => {
    const card = button.closest('.camera-registry-card');
    selectCamera(readCamera(card), card);
    document.getElementById('monitoringDetailPanel')?.scrollIntoView({behavior: 'smooth', block: 'start'});
  }));

  document.querySelectorAll('.camera-settings-btn').forEach(button => button.addEventListener('click', () => {
    populate(readCamera(button.closest('.camera-registry-card')));
    modal?.show();
  }));

  document.getElementById('addCameraBtn')?.addEventListener('click', () => {
    populate();
    modal?.show();
  });

  document.querySelectorAll('.camera-slot-add').forEach(button => button.addEventListener('click', () => {
    populate();
    modal?.show();
  }));

  document.querySelectorAll('.camera-join-btn').forEach(button => button.addEventListener('click', () => {
    const card = button.closest('.camera-registry-card');
    const camera = readCamera(card);
    selectCamera(camera, card);
    const source = document.getElementById('monitoringSource');
    source.value = 'tapo_camera';
    source.dispatchEvent(new Event('change', { bubbles: true }));
    if (typeof window.travisJoinMonitoringFeed === 'function') {
      window.travisJoinMonitoringFeed();
    } else {
      document.getElementById('startAnalysisBtn')?.click();
    }
  }));

  saveButton?.addEventListener('click', async () => {
    if (Number(fields.id.value || 0) === 0 && !fields.password.value) {
      message.className = 'small mt-3 text-danger';
      message.textContent = 'Enter the camera password during first registration. It will be stored only on the gate laptop.';
      fields.password.focus();
      return;
    }
    saveButton.disabled = true;
    message.className = 'small mt-3 text-info';
    message.textContent = 'Saving camera settings...';
    const payload = {
      csrf_token: document.getElementById('cameraRegistryCsrf').value,
      camera_id: Number(fields.id.value || 0),
      camera_name: fields.name.value.trim(),
      location: fields.location.value.trim(),
      ip_address: fields.ip.value.trim(),
      camera_username: fields.username.value.trim(),
      camera_password: fields.password.value,
      stream_quality: fields.stream.value,
      calibration_profile: fields.calibration.value,
      edge_worker_id: fields.worker.value.trim(),
      is_enabled: fields.enabled.checked
    };
    try {
      const response = await fetch('../api/cameras.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
      });
      const result = await response.json();
      if (!response.ok || result.success !== true) throw new Error(result.message || 'Unable to save camera settings.');
      fields.id.value = result.camera_id;
      document.getElementById('cameraStage').dataset.cameraId = result.camera_id;
      document.getElementById('tapoHost').value = payload.ip_address;
      document.getElementById('tapoUsername').value = payload.camera_username;
      document.getElementById('tapoPassword').value = fields.password.value;
      document.getElementById('tapoStream').value = payload.stream_quality;
      document.getElementById('calibrationProfile').value = payload.calibration_profile;
      message.className = 'small mt-3 text-success';
      if (fields.password.value) {
        message.textContent = result.message + ' Connecting once now to securely store the credential on the gate laptop.';
        const source = document.getElementById('monitoringSource');
        source.value = 'tapo_camera';
        source.dispatchEvent(new Event('change', { bubbles: true }));
        window.setTimeout(() => {
          modal?.hide();
          if (typeof window.travisJoinMonitoringFeed === 'function') {
            window.travisJoinMonitoringFeed();
          } else {
            document.getElementById('startAnalysisBtn')?.click();
          }
        }, 700);
      } else {
        message.textContent = result.message;
        window.setTimeout(() => window.location.assign(`monitoring.php?camera_id=${result.camera_id}`), 900);
      }
    } catch (error) {
      message.className = 'small mt-3 text-danger';
      message.textContent = error.message;
    } finally {
      saveButton.disabled = false;
    }
  });
});
</script>

<?php page_end(); ?>
