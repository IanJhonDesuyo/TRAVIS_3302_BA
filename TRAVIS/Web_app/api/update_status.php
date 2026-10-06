<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../Admin/db_connect.php";
require_once __DIR__ . "/enforcer_schedule.php";

$runtimeSettings = [
    'congestion_light_max' => 5,
    'congestion_heavy_min' => 13,
    'confidence_threshold' => 0.50,
    'enable_officer_detection' => 1,
    'enable_collision_detection' => 0,
    'notify_congestion' => 1,
    'notify_collision' => 1,
    'notify_officer_absence' => 1,
    'officer_absence_seconds' => 180,
    'alert_cooldown_seconds' => 300,
    'enforcer_schedule_enabled' => 0,
    'enforcer_duty_start' => '06:00',
    'enforcer_duty_end' => '18:00',
    'enforcer_break_start' => '12:00',
    'enforcer_break_end' => '13:00',
];
$settingsResult = $conn->query("SELECT setting_key, setting_value FROM system_settings");
while ($settingsResult && ($setting = $settingsResult->fetch_assoc())) {
    $key = (string)$setting['setting_key'];
    if (array_key_exists($key, $runtimeSettings)) {
        $runtimeSettings[$key] = is_numeric($setting['setting_value']) ? (float)$setting['setting_value'] : (string)$setting['setting_value'];
    }
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data) {
    echo json_encode([
        "success" => false,
        "message" => "No JSON received."
    ]);
    exit;
}

$camera_id = 1;

$vehicle_count = intval($data["vehicle_count"] ?? 0);
$inbound_count = intval($data["inbound_count"] ?? 0);
$outbound_count = intval($data["outbound_count"] ?? 0);

$congestion_level = $data["congestion_level"] ?? "Low";
$officer_presence = $data["officer_presence"] ?? "Unknown";
$potential_collision = $data["potential_collision"] ?? "None";
$alert_status = $data["alert_status"] ?? "NORMAL";
$ai_status = $data["ai_status"] ?? "Running";
$analysis_status = $data["analysis_status"] ?? null;
$status_message = trim((string)($data["message"] ?? ""));
$source_type = $data["source_type"] ?? null;
$calibration_profile = $data["calibration_profile"] ?? null;
$current_frame = intval($data["current_frame"] ?? 0);
$total_frames = intval($data["total_frames"] ?? 0);
$progress_percent = floatval($data["progress_percent"] ?? 0);
$running_time_seconds = intval($data["running_time_seconds"] ?? 0);
$capture_fps = max(0, floatval($data["capture_fps"] ?? 0));
$processing_fps = max(0, floatval($data["processing_fps"] ?? 0));
$source_fps = max(0, floatval($data["source_fps"] ?? 0));
$frame_age_ms = isset($data["frame_age_ms"]) ? max(0, floatval($data["frame_age_ms"])) : null;
$dropped_frames = max(0, intval($data["dropped_frames"] ?? 0));
$reconnect_count = max(0, intval($data["reconnect_count"] ?? 0));
$reader_failed = !empty($data["reader_failed"]);

$lightMax = max(0, min(100, (int)$runtimeSettings['congestion_light_max']));
$heavyMin = max($lightMax + 1, min(200, (int)$runtimeSettings['congestion_heavy_min']));
$congestion_level = $vehicle_count <= $lightMax ? 'Light' : ($vehicle_count < $heavyMin ? 'Moderate' : 'Heavy');
if ((int)$runtimeSettings['enable_officer_detection'] !== 1) $officer_presence = 'Unknown';
if (!enforcer_detection_is_active($runtimeSettings)) $officer_presence = 'Unknown';
if ((int)$runtimeSettings['enable_collision_detection'] !== 1) $potential_collision = 'None';

$latest_status = [
    "vehicle_count" => $vehicle_count,
    "inbound_count" => $inbound_count,
    "outbound_count" => $outbound_count,
    "congestion_level" => $congestion_level,
    "alert_status" => $alert_status,
    "officer_presence" => $officer_presence,
    "potential_collision" => $potential_collision,
    "ai_status" => $ai_status,
    "analysis_status" => $analysis_status,
    "message" => $status_message,
    "source_type" => $source_type,
    "calibration_profile" => $calibration_profile,
    "current_frame" => $current_frame,
    "total_frames" => $total_frames,
    "progress_percent" => $progress_percent,
    "running_time_seconds" => $running_time_seconds,
    "capture_fps" => $capture_fps,
    "processing_fps" => $processing_fps,
    "source_fps" => $source_fps,
    "frame_age_ms" => $frame_age_ms,
    "dropped_frames" => $dropped_frames,
    "reconnect_count" => $reconnect_count,
    "reader_failed" => $reader_failed,
    "runtime_settings" => [
        "congestion_light_max" => $lightMax,
        "congestion_heavy_min" => $heavyMin,
        "confidence_threshold" => (float)$runtimeSettings['confidence_threshold'],
        "enable_officer_detection" => (int)$runtimeSettings['enable_officer_detection'] === 1,
        "enable_collision_detection" => (int)$runtimeSettings['enable_collision_detection'] === 1,
        "notify_congestion" => (int)$runtimeSettings['notify_congestion'] === 1,
        "notify_collision" => (int)$runtimeSettings['notify_collision'] === 1,
        "notify_officer_absence" => (int)$runtimeSettings['notify_officer_absence'] === 1,
        "officer_absence_seconds" => (int)$runtimeSettings['officer_absence_seconds'],
        "alert_cooldown_seconds" => (int)$runtimeSettings['alert_cooldown_seconds'],
        "enforcer_schedule_enabled" => (int)$runtimeSettings['enforcer_schedule_enabled'] === 1,
        "enforcer_duty_start" => (string)$runtimeSettings['enforcer_duty_start'],
        "enforcer_duty_end" => (string)$runtimeSettings['enforcer_duty_end'],
        "enforcer_break_start" => (string)$runtimeSettings['enforcer_break_start'],
        "enforcer_break_end" => (string)$runtimeSettings['enforcer_break_end'],
        "enforcer_duty_active" => enforcer_detection_is_active($runtimeSettings),
    ],
    "recorded_at" => date("Y-m-d H:i:s"),
    "updated_at_epoch" => time()
];

$status_file = __DIR__ . "/latest_status.json";
file_put_contents($status_file, json_encode($latest_status));

echo json_encode(["success" => true]);
$conn->close();
