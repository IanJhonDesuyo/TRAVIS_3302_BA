<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../Admin/db_connect.php";

$runtimeSettings = [
    'congestion_light_max' => 5,
    'congestion_heavy_min' => 13,
    'confidence_threshold' => 0.50,
    'enable_officer_detection' => 1,
    'enable_collision_detection' => 0,
];
$settingsResult = $conn->query("SELECT setting_key, setting_value FROM system_settings");
while ($settingsResult && ($setting = $settingsResult->fetch_assoc())) {
    $key = (string)$setting['setting_key'];
    if (array_key_exists($key, $runtimeSettings) && is_numeric($setting['setting_value'])) {
        $runtimeSettings[$key] = (float)$setting['setting_value'];
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
$source_type = $data["source_type"] ?? null;
$calibration_profile = $data["calibration_profile"] ?? null;
$current_frame = intval($data["current_frame"] ?? 0);
$total_frames = intval($data["total_frames"] ?? 0);
$progress_percent = floatval($data["progress_percent"] ?? 0);
$running_time_seconds = intval($data["running_time_seconds"] ?? 0);

$lightMax = max(0, min(100, (int)$runtimeSettings['congestion_light_max']));
$heavyMin = max($lightMax + 1, min(200, (int)$runtimeSettings['congestion_heavy_min']));
$congestion_level = $vehicle_count <= $lightMax ? 'Light' : ($vehicle_count < $heavyMin ? 'Moderate' : 'Heavy');
if ((int)$runtimeSettings['enable_officer_detection'] !== 1) $officer_presence = 'Unknown';
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
    "source_type" => $source_type,
    "calibration_profile" => $calibration_profile,
    "current_frame" => $current_frame,
    "total_frames" => $total_frames,
    "progress_percent" => $progress_percent,
    "running_time_seconds" => $running_time_seconds,
    "runtime_settings" => [
        "congestion_light_max" => $lightMax,
        "congestion_heavy_min" => $heavyMin,
        "confidence_threshold" => (float)$runtimeSettings['confidence_threshold'],
        "enable_officer_detection" => (int)$runtimeSettings['enable_officer_detection'] === 1,
        "enable_collision_detection" => (int)$runtimeSettings['enable_collision_detection'] === 1,
    ],
    "recorded_at" => date("Y-m-d H:i:s"),
    "updated_at_epoch" => time()
];

$status_file = __DIR__ . "/latest_status.json";
file_put_contents($status_file, json_encode($latest_status));

echo json_encode(["success" => true]);
$conn->close();
