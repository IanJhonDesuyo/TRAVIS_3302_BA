<?php
header("Content-Type: application/json");

require_once __DIR__ . "/../Admin/db_connect.php";

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
$current_frame = intval($data["current_frame"] ?? 0);
$total_frames = intval($data["total_frames"] ?? 0);
$progress_percent = floatval($data["progress_percent"] ?? 0);
$running_time_seconds = intval($data["running_time_seconds"] ?? 0);

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
    "current_frame" => $current_frame,
    "total_frames" => $total_frames,
    "progress_percent" => $progress_percent,
    "running_time_seconds" => $running_time_seconds,
    "recorded_at" => date("Y-m-d H:i:s"),
    "updated_at_epoch" => time()
];

$status_file = __DIR__ . "/latest_status.json";
file_put_contents($status_file, json_encode($latest_status));

$sql = "
INSERT INTO camera_monitoring_logs
(
camera_id,
vehicle_count,
inbound_count,
outbound_count,
congestion_level,
officer_presence,
potential_collision
)

VALUES
(
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

$stmt->bind_param(
"iiiisss",
$camera_id,
$vehicle_count,
$inbound_count,
$outbound_count,
$congestion_level,
$officer_presence,
$potential_collision
);

if($stmt->execute()){

    echo json_encode([
        "success"=>true
    ]);

}else{

    echo json_encode([
        "success"=>false,
        "error"=>$stmt->error
    ]);

}

$stmt->close();
$conn->close();
