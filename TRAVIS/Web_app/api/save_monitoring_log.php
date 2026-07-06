<?php
header("Content-Type: application/json");

require_once "../db_connect.php";

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

    echo json_encode([
        "success" => true,
        "log_id" => $stmt->insert_id
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
