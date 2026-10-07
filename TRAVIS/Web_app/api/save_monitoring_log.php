<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../Admin/db_connect.php";
require_once __DIR__ . "/enforcer_schedule.php";

$notificationSettings = [
    "notify_congestion" => 1,
    "notify_collision" => 1,
    "alert_cooldown_seconds" => 300,
    "congestion_light_max" => 5,
    "congestion_heavy_min" => 13,
    "enable_officer_detection" => 1,
    "enable_collision_detection" => 0,
    "enforcer_schedule_enabled" => 0,
    "enforcer_duty_start" => '06:00',
    "enforcer_duty_end" => '18:00',
    "enforcer_break_start" => '12:00',
    "enforcer_break_end" => '13:00',
    "notify_officer_absence" => 1,
    "officer_absence_seconds" => 180,
];
$settingsTable = $conn->query("SHOW TABLES LIKE 'system_settings'");
if ($settingsTable && $settingsTable->num_rows > 0) {
    $settingsResult = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    while ($settingsResult && ($settingRow = $settingsResult->fetch_assoc())) {
        $settingKey = (string)$settingRow['setting_key'];
        if (array_key_exists($settingKey, $notificationSettings)) $notificationSettings[$settingKey] = is_numeric($settingRow['setting_value']) ? (float)$settingRow['setting_value'] : (string)$settingRow['setting_value'];
    }
}
$alertCooldownSeconds = max(0, min(86400, $notificationSettings['alert_cooldown_seconds']));

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid JSON received."
    ]);
    exit;
}

$required_fields = [
    "camera_id",
    "vehicle_count",
    "inbound_count",
    "outbound_count",
    "congestion_level",
    "officer_presence",
    "potential_collision",
    "alert_generated"
];

foreach ($required_fields as $field) {
    if (!array_key_exists($field, $data)) {
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Missing required field: " . $field
        ]);
        exit;
    }
}

$camera_id = intval($data["camera_id"]);
$vehicle_count = intval($data["vehicle_count"]);
$inbound_count = intval($data["inbound_count"]);
$outbound_count = intval($data["outbound_count"]);
$alert_generated = intval($data["alert_generated"]) === 1 ? 1 : 0;

$congestion_map = [
    "none" => "none",
    "low" => "low",
    "light" => "low",
    "moderate" => "moderate",
    "heavy" => "heavy",
    "severe" => "severe"
];

$officer_map = [
    "none" => "none",
    "detected" => "detected",
    "multiple" => "multiple",
    "unknown" => "unknown"
];

$collision_map = [
    "none" => "none",
    "possible" => "possible",
    "confirmed" => "confirmed"
];

$congestion_key = strtolower(trim(strval($data["congestion_level"])));
$officer_key = strtolower(trim(strval($data["officer_presence"])));
$collision_key = strtolower(trim(strval($data["potential_collision"])));

if (!isset($congestion_map[$congestion_key])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid congestion_level."
    ]);
    exit;
}

if (!isset($officer_map[$officer_key])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid officer_presence."
    ]);
    exit;
}

if (!isset($collision_map[$collision_key])) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid potential_collision."
    ]);
    exit;
}

if ($camera_id <= 0 || $vehicle_count < 0 || $inbound_count < 0 || $outbound_count < 0) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid numeric monitoring values."
    ]);
    exit;
}

$congestion_level = $congestion_map[$congestion_key];
$officer_presence = $officer_map[$officer_key];
$potential_collision = $collision_map[$collision_key];
$incident_notes = $data["incident_notes"] ?? null;

// The database settings are authoritative. Recalculate congestion from the
// observed count and suppress disabled detectors even if a stale worker sends
// older values after an administrator changes the configuration.
$lightMax = max(0, min(100, (int)$notificationSettings['congestion_light_max']));
$heavyMin = max($lightMax + 1, min(200, (int)$notificationSettings['congestion_heavy_min']));
$congestion_level = $vehicle_count <= $lightMax ? 'low' : ($vehicle_count < $heavyMin ? 'moderate' : 'heavy');
if ((int)$notificationSettings['enable_officer_detection'] !== 1) $officer_presence = 'unknown';
if (!enforcer_detection_is_active($notificationSettings)) $officer_presence = 'unknown';
if ((int)$notificationSettings['enable_collision_detection'] !== 1) {
    $potential_collision = 'none';
    $incident_notes = null;
}

$sql = "
INSERT INTO camera_monitoring_logs
(
camera_id,
vehicle_count,
inbound_count,
outbound_count,
congestion_level,
officer_presence,
potential_collision,
alert_generated,
incident_notes
)

VALUES
(
?,
?,
?,
?,
?,
?,
?,
?,
?
)
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $conn->error
    ]);
    exit;
}

$stmt->bind_param(
"iiiisssis",
$camera_id,
$vehicle_count,
$inbound_count,
$outbound_count,
$congestion_level,
$officer_presence,
$potential_collision,
$alert_generated,
$incident_notes
);

if ($stmt->execute()) {

    $log_id = (int) $stmt->insert_id;
    $alert_created = false;
    $alert_id = null;

    // Create one congestion notification when the detector confirms a
    // sustained Heavy/Severe state. The cooldown prevents the 30-second
    // monitoring snapshots from generating duplicate alerts.
    if ((int)$notificationSettings['notify_congestion'] === 1 && $alert_generated === 1 && in_array($congestion_level, ["heavy", "severe"], true)) {
        $duplicate_sql = "
            SELECT a.alert_id
            FROM monitoring_alerts a
            INNER JOIN camera_monitoring_logs l ON l.log_id = a.camera_log_id
            WHERE l.camera_id = ?
              AND a.alert_type = 'congestion'
              AND a.status IN ('active', 'acknowledged', 'resolved')
              AND a.generated_at >= DATE_SUB(NOW(), INTERVAL {$alertCooldownSeconds} SECOND)
            ORDER BY a.generated_at DESC
            LIMIT 1
        ";
        $duplicate_stmt = $conn->prepare($duplicate_sql);
        $duplicate_alert_id = null;

        if ($duplicate_stmt) {
            $duplicate_stmt->bind_param("i", $camera_id);
            $duplicate_stmt->execute();
            $duplicate_result = $duplicate_stmt->get_result();
            $duplicate_row = $duplicate_result ? $duplicate_result->fetch_assoc() : null;
            $duplicate_alert_id = $duplicate_row ? (int) $duplicate_row["alert_id"] : null;
            $duplicate_stmt->close();
        }

        if ($duplicate_alert_id === null) {
            $camera_name = "Camera #" . $camera_id;
            $camera_location = "the monitored area";
            $camera_stmt = $conn->prepare("SELECT camera_name, location FROM cameras WHERE camera_id = ? LIMIT 1");

            if ($camera_stmt) {
                $camera_stmt->bind_param("i", $camera_id);
                $camera_stmt->execute();
                $camera_result = $camera_stmt->get_result();
                $camera_row = $camera_result ? $camera_result->fetch_assoc() : null;
                if ($camera_row) {
                    $camera_name = (string) $camera_row["camera_name"];
                    $camera_location = (string) $camera_row["location"];
                }
                $camera_stmt->close();
            }

            $severity = $congestion_level === "severe" ? "critical" : "warning";
            $message = sprintf(
                "%s traffic congestion detected by %s at %s (%d vehicles visible).",
                ucfirst($congestion_level),
                $camera_name,
                $camera_location,
                $vehicle_count
            );

            $alert_stmt = $conn->prepare("
                INSERT INTO monitoring_alerts
                    (camera_log_id, alert_type, severity, message, status)
                VALUES (?, 'congestion', ?, ?, 'active')
            ");

            if ($alert_stmt) {
                $alert_stmt->bind_param("iss", $log_id, $severity, $message);
                $alert_created = $alert_stmt->execute();
                if ($alert_created) $alert_id = (int) $alert_stmt->insert_id;
                $alert_stmt->close();
            }
        }
    }

    // Possible collision risk remains monitoring data only. Notify operators
    // only after computer vision classifies the collision as confirmed.
    if ((int)$notificationSettings['notify_collision'] === 1 && $alert_generated === 1 && $potential_collision === "confirmed") {
        $collision_alert_id = null;
        $collision_alert_severity = null;
        $existing_collision_stmt = $conn->prepare("
            SELECT a.alert_id, a.severity
            FROM monitoring_alerts a
            INNER JOIN camera_monitoring_logs l ON l.log_id = a.camera_log_id
            WHERE l.camera_id = ?
              AND a.alert_type = 'collision'
              AND a.status IN ('active', 'acknowledged', 'resolved')
              AND a.generated_at >= DATE_SUB(NOW(), INTERVAL {$alertCooldownSeconds} SECOND)
            ORDER BY a.generated_at DESC
            LIMIT 1
        ");

        if ($existing_collision_stmt) {
            $existing_collision_stmt->bind_param("i", $camera_id);
            $existing_collision_stmt->execute();
            $existing_collision_result = $existing_collision_stmt->get_result();
            $existing_collision_row = $existing_collision_result ? $existing_collision_result->fetch_assoc() : null;
            if ($existing_collision_row) {
                $collision_alert_id = (int) $existing_collision_row["alert_id"];
                $collision_alert_severity = (string) $existing_collision_row["severity"];
            }
            $existing_collision_stmt->close();
        }

        $collision_severity = "critical";
        $collision_message = trim((string)$incident_notes);
        if ($collision_message !== '') {
            // Older CV workers described even confirmed events as a
            // "Potential collision". Normalize that wording at the API
            // boundary so operator alerts always state the confirmed status.
            $collision_message = (string)preg_replace(
                '/^Potential collision(?: confirmed)?/i',
                'Confirmed collision detected',
                $collision_message
            );
        } else {
            $collision_message = sprintf(
                "Confirmed collision detected by Camera #%d (%d vehicles visible).",
                $camera_id,
                $vehicle_count
            );
        }

        if ($collision_alert_id === null) {
            $collision_stmt = $conn->prepare("
                INSERT INTO monitoring_alerts
                    (camera_log_id, alert_type, severity, message, status)
                VALUES (?, 'collision', ?, ?, 'active')
            ");
            if ($collision_stmt) {
                $collision_stmt->bind_param("iss", $log_id, $collision_severity, $collision_message);
                $collision_alert_created = $collision_stmt->execute();
                if ($collision_alert_created) {
                    $alert_created = true;
                    $alert_id = (int) $collision_stmt->insert_id;
                }
                $collision_stmt->close();
            }
        } elseif ($potential_collision === "confirmed") {
            $upgrade_stmt = $conn->prepare("
                UPDATE monitoring_alerts
                SET camera_log_id = ?, severity = 'critical', message = ?, status = 'active'
                WHERE alert_id = ?
            ");
            if ($upgrade_stmt) {
                $upgrade_stmt->bind_param("isi", $log_id, $collision_message, $collision_alert_id);
                $upgrade_stmt->execute();
                $upgrade_stmt->close();
                $alert_id = $collision_alert_id;
            }
        }
    }

    // Officer-absence alerts only run during scheduled duty time. Unknown
    // means off-duty/break/disabled and must never count as an absence.
    if ((int)$notificationSettings['notify_officer_absence'] === 1
        && enforcer_detection_is_active($notificationSettings)
        && $officer_presence === 'none') {
        $absenceDelay = max(60, min(3600, (int)$notificationSettings['officer_absence_seconds']));
        $absence_stmt = $conn->prepare("
            SELECT MIN(recorded_at) AS absent_since,
                   TIMESTAMPDIFF(SECOND, MIN(recorded_at), NOW()) AS absent_seconds
            FROM camera_monitoring_logs
            WHERE camera_id = ? AND officer_presence = 'none'
              AND log_id > COALESCE((
                SELECT MAX(previous.log_id) FROM camera_monitoring_logs previous
                WHERE previous.camera_id = ? AND previous.officer_presence <> 'none'
              ), 0)
        ");
        $absentSince = null;
        $absentSeconds = 0;
        if ($absence_stmt) {
            $absence_stmt->bind_param('ii', $camera_id, $camera_id);
            $absence_stmt->execute();
            $absenceRow = $absence_stmt->get_result()?->fetch_assoc();
            $absentSince = $absenceRow['absent_since'] ?? null;
            $absentSeconds = (int)($absenceRow['absent_seconds'] ?? 0);
            $absence_stmt->close();
        }
        if ($absentSince && $absentSeconds >= $absenceDelay) {
            // Upgrade installations created with the earlier enum before the
            // first officer alert is inserted.
            $typeColumn = $conn->query("SHOW COLUMNS FROM monitoring_alerts LIKE 'alert_type'")?->fetch_assoc();
            if ($typeColumn && stripos((string)$typeColumn['Type'], 'officer_absence') === false) {
                $conn->query("ALTER TABLE monitoring_alerts MODIFY alert_type ENUM('congestion','collision','officer_absence','incident','system') NOT NULL");
            }
            $duplicateOfficer = $conn->prepare("
                SELECT alert_id FROM monitoring_alerts
                WHERE alert_type = 'officer_absence' AND status IN ('active','acknowledged','resolved')
                  AND generated_at >= DATE_SUB(NOW(), INTERVAL {$alertCooldownSeconds} SECOND)
                ORDER BY generated_at DESC LIMIT 1
            ");
            $existingOfficerId = null;
            if ($duplicateOfficer) {
                $duplicateOfficer->execute();
                $existingOfficerId = $duplicateOfficer->get_result()?->fetch_assoc()['alert_id'] ?? null;
                $duplicateOfficer->close();
            }
            if ($existingOfficerId === null) {
                $officerMessage = sprintf('No traffic enforcer has been detected at Camera #%d for at least %d seconds during active duty.', $camera_id, $absenceDelay);
                $officerAlert = $conn->prepare("INSERT INTO monitoring_alerts (camera_log_id, alert_type, severity, message, status) VALUES (?, 'officer_absence', 'warning', ?, 'active')");
                if ($officerAlert) {
                    $officerAlert->bind_param('is', $log_id, $officerMessage);
                    if ($officerAlert->execute()) {
                        $alert_created = true;
                        $alert_id = (int)$officerAlert->insert_id;
                    }
                    $officerAlert->close();
                }
            }
        }
    }

    if ($officer_presence !== 'none' || !enforcer_detection_is_active($notificationSettings)) {
        $resolveOfficer = $conn->prepare("
            UPDATE monitoring_alerts a
            INNER JOIN camera_monitoring_logs l ON l.log_id = a.camera_log_id
            SET a.status = 'resolved'
            WHERE l.camera_id = ? AND a.alert_type = 'officer_absence' AND a.status = 'active'
        ");
        if ($resolveOfficer) {
            $resolveOfficer->bind_param('i', $camera_id);
            $resolveOfficer->execute();
            $resolveOfficer->close();
        }
    }

    // Close active congestion notifications after traffic returns below the
    // heavy threshold, allowing a later congestion episode to alert again.
    if (!in_array($congestion_level, ["heavy", "severe"], true)) {
        $resolve_stmt = $conn->prepare("
            UPDATE monitoring_alerts a
            INNER JOIN camera_monitoring_logs l ON l.log_id = a.camera_log_id
            SET a.status = 'resolved'
            WHERE l.camera_id = ?
              AND a.alert_type = 'congestion'
              AND a.status = 'active'
              AND NOT EXISTS (
                SELECT 1 FROM camera_monitoring_logs recent
                WHERE recent.camera_id = ?
                  AND recent.congestion_level IN ('heavy','severe')
                  AND recent.recorded_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
              )
        ");
        if ($resolve_stmt) {
            $resolve_stmt->bind_param("ii", $camera_id, $camera_id);
            $resolve_stmt->execute();
            $resolve_stmt->close();
        }
    }

    if ($potential_collision !== "confirmed") {
        $resolve_collision_stmt = $conn->prepare("
            UPDATE monitoring_alerts a
            INNER JOIN camera_monitoring_logs l ON l.log_id = a.camera_log_id
            SET a.status = 'resolved'
            WHERE l.camera_id = ?
              AND a.alert_type = 'collision'
              AND a.status = 'active'
              AND NOT EXISTS (
                SELECT 1 FROM camera_monitoring_logs recent
                WHERE recent.camera_id = ?
                  AND recent.potential_collision = 'confirmed'
                  AND recent.recorded_at >= DATE_SUB(NOW(), INTERVAL 10 SECOND)
              )
        ");
        if ($resolve_collision_stmt) {
            $resolve_collision_stmt->bind_param("ii", $camera_id, $camera_id);
            $resolve_collision_stmt->execute();
            $resolve_collision_stmt->close();
        }
    }

    echo json_encode([
        "success" => true,
        "log_id" => $log_id,
        "alert_created" => $alert_created,
        "alert_id" => $alert_id
    ]);

} else {

    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $stmt->error
    ]);

}

$stmt->close();
$conn->close();
