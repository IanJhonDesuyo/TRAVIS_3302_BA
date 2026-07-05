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
    <p class="page-sub">AI-powered traffic monitoring for uploaded CCTV footage, laptop camera, and Tapo camera integration</p>
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
          <small class="text-muted">Laptop camera preview or uploaded CCTV test video</small>
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
            Run <code>python detect_video.py</code> and start the Flask stream server.
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

      <form method="post" enctype="multipart/form-data">
        <label class="form-label small fw-semibold">LGU CCTV Video Copy</label>
        <input class="form-control mb-2" type="file" name="cctv_video" accept="video/mp4,video/avi,video/quicktime,video/x-matroska" required>
        <button class="btn btn-primary w-100">
          <i class="bi bi-upload me-1"></i>Upload Video
        </button>
      </form>

      <small class="text-muted d-block mt-2">
        Supported: MP4, AVI, MOV, MKV. Saved in <code>uploads/videos</code>.
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
      <div class="stat-label">Visible Vehicles</div>
      <div class="stat-value" id="visibleVehicles"><?= num($latest['vehicle_count'] ?? 0) ?></div>
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

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-danger"><i class="bi bi-clock-history"></i></div>
      <div class="stat-label">Last Updated</div>
      <div class="stat-value" style="font-size:1.1rem;" id="lastUpdated">
        <?= esc($latest['recorded_at'] ?? 'No data') ?>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-speedometer2"></i></div>
      <div class="stat-label">Traffic Status</div>
      <div class="stat-value" id="trafficStatus"><?= esc($latest['congestion_level'] ?? 'none') ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-person-badge"></i></div>
      <div class="stat-label">Officer Status</div>
      <div class="stat-value" id="officerStatus"><?= esc($latest['officer_presence'] ?? 'unknown') ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-danger"><i class="bi bi-exclamation-triangle"></i></div>
      <div class="stat-label">Collision Alert</div>
      <div class="stat-value" id="collisionStatus"><?= esc($latest['potential_collision'] ?? 'none') ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-cpu"></i></div>
      <div class="stat-label">AI Status</div>
      <div class="stat-value" id="aiStatus">Ready</div>
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
                <th>Inbound</th>
                <th>Outbound</th>
                <th>Traffic</th>
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
                  <td><?= num($l['inbound_count']) ?></td>
                  <td><?= num($l['outbound_count']) ?></td>
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
const startCameraBtn = document.getElementById('startCameraBtn');
const stopCameraBtn = document.getElementById('stopCameraBtn');
const captureSnapshotBtn = document.getElementById('captureSnapshotBtn');
const sourceStatus = document.getElementById('sourceStatus');
const aiLiveStream = document.getElementById('aiLiveStream');
const streamFallback = document.getElementById('streamFallback');

function updateText(id, value) {
  const el = document.getElementById(id);
  if (el) {
    el.textContent = value;
  }
}

function updateStatusColor(id, value) {
  const el = document.getElementById(id);
  if (!el) return;

  const displayValue = value ?? 'unknown';
  el.textContent = displayValue;

  const normalized = String(displayValue).toLowerCase();

  if (
    normalized.includes('running') ||
    normalized.includes('online') ||
    normalized.includes('detected') ||
    normalized.includes('present') ||
    normalized.includes('low') ||
    normalized.includes('none')
  ) {
    el.style.color = '#16a34a';
  } else if (
    normalized.includes('moderate') ||
    normalized.includes('monitoring') ||
    normalized.includes('warning') ||
    normalized.includes('unknown')
  ) {
    el.style.color = '#ca8a04';
  } else if (
    normalized.includes('heavy') ||
    normalized.includes('severe') ||
    normalized.includes('high') ||
    normalized.includes('absent') ||
    normalized.includes('collision') ||
    normalized.includes('disconnected')
  ) {
    el.style.color = '#dc2626';
  } else {
    el.style.color = '';
  }
}

async function refreshMonitoringStatus() {
  try {
    const response = await fetch('api/get_status.php', {
      cache: 'no-store'
    });

    if (!response.ok) {
      throw new Error('Could not fetch monitoring status.');
    }

    const data = await response.json();

    updateText('visibleVehicles', data.vehicle_count ?? 0);
    updateText('inboundCount', data.inbound_count ?? 0);
    updateText('outboundCount', data.outbound_count ?? 0);
    updateText('lastUpdated', data.recorded_at ?? 'No data');

    updateStatusColor('trafficStatus', data.congestion_level ?? 'none');
    updateStatusColor('officerStatus', data.officer_presence ?? 'unknown');
    updateStatusColor('collisionStatus', data.potential_collision ?? 'none');
    updateStatusColor('aiStatus', data.ai_status ?? 'Running');

    sourceStatus.textContent = 'Live Data Active';
    sourceStatus.className = 'tag tag-success';

  } catch (error) {
    updateStatusColor('aiStatus', 'Disconnected');
    sourceStatus.textContent = 'Waiting for AI Data';
    sourceStatus.className = 'tag tag-warning';
  }
}

function reconnectStream() {
  if (!aiLiveStream) return;

  aiLiveStream.style.display = 'block';
  if (streamFallback) {
    streamFallback.style.display = 'none';
  }

  aiLiveStream.src = 'http://localhost:5000/video_feed?t=' + new Date().getTime();

  sourceStatus.textContent = 'Connecting AI Stream';
  sourceStatus.className = 'tag tag-info';
}

function hideStream() {
  if (aiLiveStream) {
    aiLiveStream.style.display = 'none';
  }

  if (streamFallback) {
    streamFallback.style.display = 'flex';
  }

  sourceStatus.textContent = 'Stream Hidden';
  sourceStatus.className = 'tag tag-warning';
}

startCameraBtn.addEventListener('click', reconnectStream);
stopCameraBtn.addEventListener('click', hideStream);

captureSnapshotBtn.addEventListener('click', () => {
  window.open('http://localhost:5000/video_feed', '_blank');
});

refreshMonitoringStatus();
setInterval(refreshMonitoringStatus, 2000);
</script>

<?php page_end(); ?>