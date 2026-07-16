<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';

$defaults = [
    'congestion_trigger' => '1500',
    'alert_cooldown' => '15',
    'officer_absence_threshold' => '30',
    'collision_stationary_threshold' => '10',
    'flask_api_url' => 'http://127.0.0.1:5001',
    'rtsp_camera_source' => '',
    'tomtom_api_key' => '',
    'tomtom_center_latitude' => '14.07395',
    'tomtom_center_longitude' => '120.63267',
    'tomtom_map_zoom' => '13',
    'notify_congestion' => '1',
    'notify_officer_absence' => '1',
    'notify_collision' => '1',
    'session_timeout' => '30',
    'password_policy' => 'strong',
];

$tableReady = $conn->query("
    CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(100) NOT NULL,
        setting_value TEXT NOT NULL,
        updated_by BIGINT UNSIGNED NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (setting_key),
        KEY idx_system_settings_updated_by (updated_by),
        CONSTRAINT fk_system_settings_updated_by
            FOREIGN KEY (updated_by) REFERENCES users(user_id)
            ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$settings = $defaults;
if ($tableReady) {
    foreach (fetch_all('SELECT setting_key, setting_value FROM system_settings') as $row) {
        $key = (string)($row['setting_key'] ?? '');
        if (array_key_exists($key, $settings)) {
            $settings[$key] = (string)$row['setting_value'];
        }
    }
} else {
    $message = 'Settings storage could not be initialized. Check the database permissions.';
    $messageType = 'danger';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableReady) {
    $submittedToken = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals(csrf_token(), $submittedToken)) {
        $message = 'Your session token expired. Refresh the page and try again.';
        $messageType = 'danger';
    } else {
        $candidate = [
            'congestion_trigger' => trim((string)($_POST['congestion_trigger'] ?? '')),
            'alert_cooldown' => trim((string)($_POST['alert_cooldown'] ?? '')),
            'officer_absence_threshold' => trim((string)($_POST['officer_absence_threshold'] ?? '')),
            'collision_stationary_threshold' => trim((string)($_POST['collision_stationary_threshold'] ?? '')),
            'flask_api_url' => rtrim(trim((string)($_POST['flask_api_url'] ?? '')), '/'),
            'rtsp_camera_source' => trim((string)($_POST['rtsp_camera_source'] ?? '')),
            'tomtom_api_key' => trim((string)($_POST['tomtom_api_key'] ?? '')),
            'tomtom_center_latitude' => trim((string)($_POST['tomtom_center_latitude'] ?? '')),
            'tomtom_center_longitude' => trim((string)($_POST['tomtom_center_longitude'] ?? '')),
            'tomtom_map_zoom' => trim((string)($_POST['tomtom_map_zoom'] ?? '')),
            'notify_congestion' => isset($_POST['notify_congestion']) ? '1' : '0',
            'notify_officer_absence' => isset($_POST['notify_officer_absence']) ? '1' : '0',
            'notify_collision' => isset($_POST['notify_collision']) ? '1' : '0',
            'session_timeout' => trim((string)($_POST['session_timeout'] ?? '')),
            'password_policy' => trim((string)($_POST['password_policy'] ?? '')),
        ];

        $numberRules = [
            'congestion_trigger' => [1, 100000, 'Congestion trigger'],
            'alert_cooldown' => [1, 1440, 'Alert cooldown'],
            'officer_absence_threshold' => [1, 1440, 'Officer absence threshold'],
            'collision_stationary_threshold' => [1, 3600, 'Collision stationary threshold'],
            'session_timeout' => [5, 1440, 'Session timeout'],
        ];
        $errors = [];

        foreach ($numberRules as $key => [$minimum, $maximum, $label]) {
            $value = filter_var($candidate[$key], FILTER_VALIDATE_INT);
            if ($value === false || $value < $minimum || $value > $maximum) {
                $errors[] = "$label must be between $minimum and $maximum.";
            } else {
                $candidate[$key] = (string)$value;
            }
        }

        if (!filter_var($candidate['flask_api_url'], FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $candidate['flask_api_url'])) {
            $errors[] = 'Flask API URL must be a valid HTTP or HTTPS address.';
        }
        if ($candidate['rtsp_camera_source'] !== '' && !preg_match('/^rtsps?:\/\//i', $candidate['rtsp_camera_source'])) {
            $errors[] = 'Camera source must begin with rtsp:// or rtsps://.';
        }
        if ($candidate['tomtom_api_key'] !== '' && strlen($candidate['tomtom_api_key']) < 20) {
            $errors[] = 'TomTom API key appears invalid. Copy the complete API key from the TomTom Developer Portal.';
        }
        if (!in_array($candidate['password_policy'], ['strong', 'standard'], true)) {
            $errors[] = 'Select a valid password policy.';
        }
        $latitude = filter_var($candidate['tomtom_center_latitude'], FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($candidate['tomtom_center_longitude'], FILTER_VALIDATE_FLOAT);
        $mapZoom = filter_var($candidate['tomtom_map_zoom'], FILTER_VALIDATE_INT);
        if ($latitude === false || $latitude < -90 || $latitude > 90) $errors[] = 'Map latitude must be between -90 and 90.';
        if ($longitude === false || $longitude < -180 || $longitude > 180) $errors[] = 'Map longitude must be between -180 and 180.';
        if ($mapZoom === false || $mapZoom < 5 || $mapZoom > 20) $errors[] = 'Map zoom must be between 5 and 20.';
        if ($latitude !== false) $candidate['tomtom_center_latitude'] = (string)$latitude;
        if ($longitude !== false) $candidate['tomtom_center_longitude'] = (string)$longitude;
        if ($mapZoom !== false) $candidate['tomtom_map_zoom'] = (string)$mapZoom;

        $settings = $candidate;
        if ($errors) {
            $message = implode(' ', $errors);
            $messageType = 'danger';
        } else {
            $userId = (int)($_SESSION['user']['id'] ?? 0);
            $stmt = $conn->prepare("
                INSERT INTO system_settings (setting_key, setting_value, updated_by)
                VALUES (?, ?, NULLIF(?, 0))
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_by = VALUES(updated_by)
            ");
            $saved = $stmt !== false;

            if ($stmt) {
                $conn->begin_transaction();
                foreach ($settings as $key => $value) {
                    $stmt->bind_param('ssi', $key, $value, $userId);
                    if (!$stmt->execute()) {
                        $saved = false;
                        break;
                    }
                }
                $saved ? $conn->commit() : $conn->rollback();
                $stmt->close();
            }

            $message = $saved ? 'System settings saved successfully.' : 'The settings could not be saved.';
            $messageType = $saved ? 'success' : 'danger';
        }
    }
}

page_start('Settings', 'settings', 'Search settings...');
?>

<form method="post" id="settingsForm">
  <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">

  <div class="d-flex justify-content-between align-items-end flex-wrap mb-4 gap-3">
    <div>
      <h3 class="page-title">System Settings</h3>
      <p class="page-sub mb-0">Configure traffic thresholds, integrations, notifications, and security preferences.</p>
    </div>
    <button class="btn btn-primary" type="submit" <?= !$tableReady ? 'disabled' : '' ?>>
      <i class="bi bi-check2 me-1"></i>Save Changes
    </button>
  </div>

  <?php feedback_notice($message, $messageType); ?>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="section-card h-100">
        <div class="section-head"><h6>Traffic Thresholds</h6></div>
        <div class="mb-3"><label class="form-label small fw-semibold" for="congestionTrigger">Congestion Trigger (vehicles/hr)</label><input id="congestionTrigger" name="congestion_trigger" type="number" min="1" max="100000" required class="form-control" value="<?= esc($settings['congestion_trigger']) ?>"></div>
        <div class="mb-3"><label class="form-label small fw-semibold" for="alertCooldown">Alert Cooldown (minutes)</label><input id="alertCooldown" name="alert_cooldown" type="number" min="1" max="1440" required class="form-control" value="<?= esc($settings['alert_cooldown']) ?>"></div>
        <div class="mb-3"><label class="form-label small fw-semibold" for="absenceThreshold">Officer Absence Threshold (minutes)</label><input id="absenceThreshold" name="officer_absence_threshold" type="number" min="1" max="1440" required class="form-control" value="<?= esc($settings['officer_absence_threshold']) ?>"></div>
        <div><label class="form-label small fw-semibold" for="collisionThreshold">Potential Collision Stationary Threshold (seconds)</label><input id="collisionThreshold" name="collision_stationary_threshold" type="number" min="1" max="3600" required class="form-control" value="<?= esc($settings['collision_stationary_threshold']) ?>"></div>
      </div>
    </div>

    <div class="col-12">
      <div class="section-card">
        <div class="section-head"><div><h6>TomTom Public Traffic Map</h6><small class="text-muted">Controls the live map and route outlook shown on the public website.</small></div></div>
        <div class="row g-3">
          <div class="col-lg-5"><label class="form-label small fw-semibold" for="tomtomApiKey">TomTom API Key</label><input id="tomtomApiKey" name="tomtom_api_key" type="password" autocomplete="off" minlength="20" class="form-control" value="<?= esc($settings['tomtom_api_key']) ?>" placeholder="Paste the complete TomTom API key"><small class="text-muted">Copy the key value—not its application name—from the TomTom Developer Portal. Use a browser key restricted to your website domain.</small></div>
          <div class="col-sm-6 col-lg-2"><label class="form-label small fw-semibold" for="tomtomLatitude">Center Latitude</label><input id="tomtomLatitude" name="tomtom_center_latitude" type="number" step="any" min="-90" max="90" required class="form-control" value="<?= esc($settings['tomtom_center_latitude']) ?>"></div>
          <div class="col-sm-6 col-lg-2"><label class="form-label small fw-semibold" for="tomtomLongitude">Center Longitude</label><input id="tomtomLongitude" name="tomtom_center_longitude" type="number" step="any" min="-180" max="180" required class="form-control" value="<?= esc($settings['tomtom_center_longitude']) ?>"></div>
          <div class="col-sm-6 col-lg-2"><label class="form-label small fw-semibold" for="tomtomZoom">Default Zoom</label><input id="tomtomZoom" name="tomtom_map_zoom" type="number" min="5" max="20" required class="form-control" value="<?= esc($settings['tomtom_map_zoom']) ?>"></div>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="section-card h-100">
        <div class="section-head"><h6>Computer Vision Integration</h6></div>
        <div class="mb-3"><label class="form-label small fw-semibold" for="flaskApiUrl">Flask API URL</label><input id="flaskApiUrl" name="flask_api_url" type="url" required class="form-control" placeholder="http://127.0.0.1:5001" value="<?= esc($settings['flask_api_url']) ?>"></div>
        <div class="mb-2"><label class="form-label small fw-semibold" for="rtspSource">RTSP Camera Source</label><input id="rtspSource" name="rtsp_camera_source" class="form-control" placeholder="rtsp://username:password@camera-ip:554/stream1" value="<?= esc($settings['rtsp_camera_source']) ?>"></div>
        <small class="text-muted">Leave the camera source blank if no RTSP stream is configured.</small>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="section-card h-100">
        <div class="section-head"><h6>Notifications</h6></div>
        <?php foreach ([
            'notify_congestion' => 'Critical congestion alerts',
            'notify_officer_absence' => 'Officer absence alerts',
            'notify_collision' => 'Potential collision alerts',
        ] as $key => $label): ?>
          <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" role="switch" id="<?= esc($key) ?>" name="<?= esc($key) ?>" value="1" <?= $settings[$key] === '1' ? 'checked' : '' ?>>
            <label class="form-check-label" for="<?= esc($key) ?>"><?= esc($label) ?></label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="section-card h-100">
        <div class="section-head"><h6>Security</h6></div>
        <div class="mb-3"><label class="form-label small fw-semibold" for="sessionTimeout">Session Timeout (minutes)</label><input id="sessionTimeout" name="session_timeout" type="number" min="5" max="1440" required class="form-control" value="<?= esc($settings['session_timeout']) ?>"></div>
        <div><label class="form-label small fw-semibold" for="passwordPolicy">Password Policy</label><select id="passwordPolicy" name="password_policy" class="form-select" required><option value="strong" <?= $settings['password_policy'] === 'strong' ? 'selected' : '' ?>>Strong (12+ characters)</option><option value="standard" <?= $settings['password_policy'] === 'standard' ? 'selected' : '' ?>>Standard (8+ characters)</option></select></div>
      </div>
    </div>
  </div>
</form>

<?php page_end(); ?>
