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

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <h3 class="page-title">TRAVIS AI Monitoring</h3>
    <p class="page-sub">AI-powered traffic monitoring for uploaded CCTV footage</p>
  </div>

  <div class="d-flex gap-2">
    <span class="tag <?= tag_class($camera['status'] ?? 'offline') ?>">
      <span class="dot" style="background:#16a34a"></span>
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
          <small class="text-muted">Uploaded CCTV test video analysis stream</small>
        </div>
        <span class="tag tag-info" id="sourceStatus">Ready</span>
      </div>

      <div class="camera-stage mb-3" id="cameraStage">
        <img
          id="aiLiveStream"
          src="http://localhost:5000/video_feed"
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
          <i class="bi bi-broadcast fs-1 d-block mb-3"></i>
          <h5>Waiting for AI live stream</h5>
          <p class="mb-0 opacity-75">
            Start analysis from the dashboard to activate the AI stream.
          </p>
        </div>

        <canvas id="snapshotCanvas" style="display:none;"></canvas>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary" type="button" id="startCameraBtn">
          <i class="bi bi-play-fill me-1"></i>Reconnect AI Stream
        </button>

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
      <div class="section-head"><h6>Upload CCTV Video</h6></div>

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
          <i class="bi bi-play-circle me-1"></i>Start Analysis
        </button>

        <button class="btn btn-light flex-fill" type="button" id="stopAnalysisBtn">
          <i class="bi bi-stop-circle me-1"></i>Stop Analysis
        </button>
      </div>

      <div class="small mt-2" id="analysisMessage"></div>
    </div>

    <div class="section-card">
      <div class="section-head"><h6>Camera Information</h6></div>
      <div class="mini-metric mb-2"><small>Camera Name</small><strong><?= esc($camera['camera_name'] ?? 'No camera registered') ?></strong></div>
      <div class="mini-metric mb-2"><small>Location</small><strong><?= esc($camera['location'] ?? 'Not set') ?></strong></div>
      <div class="mini-metric mb-2"><small>IP Address</small><strong><?= esc($camera['ip_address'] ?? 'Not set') ?></strong></div>
      <div class="mini-metric"><small>Status</small><strong><?= esc($camera['status'] ?? 'offline') ?></strong></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-cpu"></i></div>
      <div class="stat-label">AI Status</div>
      <div class="stat-value"><span class="tag tag-muted" id="aiStatus">Idle</span></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-camera-video"></i></div>
      <div class="stat-label">Source</div>
      <div class="stat-value" style="font-size:1.05rem;" id="analysisSource">Not selected</div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-car-front"></i></div>
      <div class="stat-label">Vehicle Count</div>
      <div class="stat-value" id="vehicleCount"><?= num($latest['vehicle_count'] ?? 0) ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-arrow-up-circle"></i></div>
      <div class="stat-label">Inbound</div>
      <div class="stat-value" id="inboundCount"><?= num($latest['inbound_count'] ?? 0) ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-arrow-down-circle"></i></div>
      <div class="stat-label">Outbound</div>
      <div class="stat-value" id="outboundCount"><?= num($latest['outbound_count'] ?? 0) ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-activity"></i></div>
      <div class="stat-label">Progress</div>
      <div class="stat-value" style="font-size:1rem;" id="analysisProgressText">Idle</div>
      <div class="progress mt-2" style="height:8px;">
        <div class="progress-bar" id="analysisProgressBar" style="width:0%"></div>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-speedometer2"></i></div>
      <div class="stat-label">Congestion Level</div>
      <div class="stat-value"><span class="tag <?= tag_class($latest['congestion_level'] ?? 'none') ?>" id="congestionLevel"><?= esc($latest['congestion_level'] ?? 'none') ?></span></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-danger"><i class="bi bi-bell"></i></div>
      <div class="stat-label">Alert Status</div>
      <div class="stat-value"><span class="tag <?= !empty($latest['alert_generated']) ? 'tag-danger' : 'tag-success' ?>" id="alertStatus"><?= !empty($latest['alert_generated']) ? 'ALERT' : 'NORMAL' ?></span></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-person-badge"></i></div>
      <div class="stat-label">Officer Presence</div>
      <div class="stat-value"><span class="tag tag-muted" id="officerPresence"><?= esc($latest['officer_presence'] ?? 'unknown') ?></span></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="stat-label">Potential Collision</div>
      <div class="stat-value"><span class="tag tag-muted" id="potentialCollision"><?= esc($latest['potential_collision'] ?? 'none') ?></span></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
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

<script src="<?= esc(asset_url('js/monitoring.js') . '?v=' . filemtime(dirname(__DIR__, 2) . '/js/monitoring.js')) ?>"></script>

<?php page_end(); ?>
