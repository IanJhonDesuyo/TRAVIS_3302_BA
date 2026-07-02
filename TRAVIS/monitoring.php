<?php
require_once __DIR__ . '/layout.php';

$uploadMessage = '';
$uploadedVideo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['cctv_video'])) {
    $allowed = ['mp4','avi','mov','mkv'];
    $ext = strtolower(pathinfo($_FILES['cctv_video']['name'] ?? '', PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed, true)) {
        $uploadMessage = 'Invalid file type. Please upload MP4, AVI, MOV, or MKV.';
    } elseif (($_FILES['cctv_video']['size'] ?? 0) > 300 * 1024 * 1024) {
        $uploadMessage = 'File too large. Maximum allowed size is 300MB.';
    } else {
        $dir = __DIR__ . '/uploads/videos';
        if (!is_dir($dir)) mkdir($dir, 0775, true);

        $safeName = 'cctv_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = $dir . '/' . $safeName;

        if (move_uploaded_file($_FILES['cctv_video']['tmp_name'], $target)) {
            $uploadedVideo = 'uploads/videos/' . $safeName;
            $uploadMessage = 'CCTV video uploaded successfully.';
        } else {
            $uploadMessage = 'Upload failed. Please check folder permissions.';
        }
    }
}

$camera = fetch_one("SELECT * FROM cameras ORDER BY camera_id ASC LIMIT 1");
$latest = fetch_one("SELECT * FROM camera_monitoring_logs ORDER BY recorded_at DESC LIMIT 1");
$logs = fetch_all("SELECT l.*, c.camera_name, c.location FROM camera_monitoring_logs l JOIN cameras c ON c.camera_id=l.camera_id ORDER BY l.recorded_at DESC LIMIT 10");
$activeAlerts = fetch_all("SELECT alert_type, severity, message, generated_at, status FROM monitoring_alerts ORDER BY generated_at DESC LIMIT 5");

page_start('Live Monitoring', 'monitoring', 'Search monitoring logs...');
?>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <h3 class="page-title">Live Traffic Monitoring</h3>
    <p class="page-sub">Single-camera computer vision workspace for laptop camera, Tapo camera, or uploaded LGU CCTV footage</p>
  </div>
  <div class="d-flex gap-2">
    <span class="tag <?= tag_class($camera['status'] ?? 'offline') ?>">
      <span class="dot" style="background:#16a34a"></span><?= esc($camera['status'] ?? 'offline') ?>
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
          <small class="text-muted">Laptop camera preview or uploaded CCTV test video</small>
        </div>
        <span class="tag tag-info" id="sourceStatus">Ready</span>
      </div>

      <div class="camera-stage mb-3" id="cameraStage">
        <video id="webcamPreview" autoplay playsinline muted style="display:none; width:100%; height:100%; object-fit:cover; border-radius:12px;"></video>

        <?php if ($uploadedVideo): ?>
          <video id="uploadedVideoPreview" controls src="<?= esc($uploadedVideo) ?>" style="width:100%; height:100%; object-fit:cover; border-radius:12px;"></video>
        <?php else: ?>
          <div class="text-center p-4" id="emptyPreview">
            <i class="bi bi-camera-video fs-1 d-block mb-3"></i>
            <h5>No active video source displayed</h5>
            <p class="mb-0 opacity-75">Click Start Monitoring to open your laptop camera or upload LGU CCTV footage below.</p>
          </div>
        <?php endif; ?>

        <canvas id="snapshotCanvas" style="display:none;"></canvas>
      </div>

      <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-primary" type="button" id="startCameraBtn">
          <i class="bi bi-play-fill me-1"></i>Start Monitoring
        </button>

        <button class="btn btn-light" type="button" id="stopCameraBtn">
          <i class="bi bi-stop-fill me-1"></i>Stop Monitoring
        </button>

        <button class="btn btn-light" type="button" id="captureSnapshotBtn">
          <i class="bi bi-camera me-1"></i>Capture Snapshot
        </button>

        <button class="btn btn-light" onclick="location.reload()">
          <i class="bi bi-arrow-clockwise me-1"></i>Refresh
        </button>
      </div>

      <small class="text-muted d-block mt-2">
        This opens your laptop camera for browser preview only. YOLO/OpenCV processing will be connected later through Python/Flask.
      </small>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="section-card mb-3">
      <div class="section-head"><h6>Upload CCTV Video</h6></div>

      <form method="post" enctype="multipart/form-data">
        <label class="form-label small fw-semibold">LGU CCTV Video Copy</label>
        <input class="form-control mb-2" type="file" name="cctv_video" accept="video/mp4,video/avi,video/quicktime,video/x-matroska" required>
        <button class="btn btn-primary w-100">
          <i class="bi bi-upload me-1"></i>Upload Video
        </button>
      </form>

      <small class="text-muted d-block mt-2">
        Supported: MP4, AVI, MOV, MKV. The uploaded file is saved in <code>uploads/videos</code>.
      </small>
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
      <div class="stat-icon tone-primary"><i class="bi bi-car-front"></i></div>
      <div class="stat-label">Vehicle Count</div>
      <div class="stat-value"><?= num($latest['vehicle_count'] ?? 0) ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-arrow-down-up"></i></div>
      <div class="stat-label">Inbound / Outbound</div>
      <div class="stat-value"><?= num($latest['inbound_count'] ?? 0) ?> / <?= num($latest['outbound_count'] ?? 0) ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-speedometer"></i></div>
      <div class="stat-label">Congestion</div>
      <div class="stat-value"><?= esc($latest['congestion_level'] ?? 'none') ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="stat-label">Potential Collision</div>
      <div class="stat-value"><?= esc($latest['potential_collision'] ?? 'none') ?></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="section-card">
      <div class="section-head"><h6>Detection Logs</h6></div>

      <?php if (!$logs): ?>
        <?php empty_state('No monitoring logs yet. Logs will appear after Python/OpenCV saves records to camera_monitoring_logs.'); ?>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Time</th>
                <th>Camera</th>
                <th>Vehicles</th>
                <th>Congestion</th>
                <th>Officer</th>
                <th>Collision</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($logs as $l): ?>
                <tr>
                  <td><?= esc($l['recorded_at']) ?></td>
                  <td><?= esc($l['camera_name']) ?><br><small class="text-muted"><?= esc($l['location']) ?></small></td>
                  <td><?= num($l['vehicle_count']) ?></td>
                  <td><span class="tag <?= tag_class($l['congestion_level']) ?>"><?= esc($l['congestion_level']) ?></span></td>
                  <td><?= esc($l['officer_presence']) ?></td>
                  <td><?= esc($l['potential_collision']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
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

<script>
let cameraStream = null;

const startCameraBtn = document.getElementById('startCameraBtn');
const stopCameraBtn = document.getElementById('stopCameraBtn');
const captureSnapshotBtn = document.getElementById('captureSnapshotBtn');
const webcamPreview = document.getElementById('webcamPreview');
const uploadedVideoPreview = document.getElementById('uploadedVideoPreview');
const emptyPreview = document.getElementById('emptyPreview');
const sourceStatus = document.getElementById('sourceStatus');
const snapshotCanvas = document.getElementById('snapshotCanvas');

startCameraBtn.addEventListener('click', async () => {
  try {
    if (uploadedVideoPreview) {
      uploadedVideoPreview.style.display = 'none';
      uploadedVideoPreview.pause();
    }

    if (emptyPreview) {
      emptyPreview.style.display = 'none';
    }

    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: {
        width: { ideal: 1280 },
        height: { ideal: 720 }
      },
      audio: false
    });

    webcamPreview.srcObject = cameraStream;
    webcamPreview.style.display = 'block';
    sourceStatus.textContent = 'Laptop Camera Active';
    sourceStatus.className = 'tag tag-success';

  } catch (error) {
    alert('Camera access failed. Please allow camera permission in your browser.');
    sourceStatus.textContent = 'Camera Permission Denied';
    sourceStatus.className = 'tag tag-danger';
  }
});

stopCameraBtn.addEventListener('click', () => {
  if (cameraStream) {
    cameraStream.getTracks().forEach(track => track.stop());
    cameraStream = null;
  }

  webcamPreview.srcObject = null;
  webcamPreview.style.display = 'none';

  if (emptyPreview) {
    emptyPreview.style.display = 'block';
  }

  sourceStatus.textContent = 'Stopped';
  sourceStatus.className = 'tag tag-warning';
});

captureSnapshotBtn.addEventListener('click', () => {
  if (!cameraStream) {
    alert('Start the camera first before capturing a snapshot.');
    return;
  }

  const video = webcamPreview;
  snapshotCanvas.width = video.videoWidth;
  snapshotCanvas.height = video.videoHeight;

  const ctx = snapshotCanvas.getContext('2d');
  ctx.drawImage(video, 0, 0, snapshotCanvas.width, snapshotCanvas.height);

  const imageData = snapshotCanvas.toDataURL('image/png');
  const link = document.createElement('a');
  link.href = imageData;
  link.download = 'traffiq_snapshot_' + new Date().toISOString().replace(/[:.]/g, '-') + '.png';
  link.click();
});
</script>

<?php page_end(); ?>