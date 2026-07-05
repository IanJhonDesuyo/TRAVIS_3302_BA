<?php
header("Content-Type: application/json");

require_once "../db_connect.php";

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