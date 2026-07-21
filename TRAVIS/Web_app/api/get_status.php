<?php

header("Content-Type: application/json");

require_once __DIR__ . "/../Admin/db_connect.php";

$latest_status = [];
$status_file = __DIR__ . "/latest_status.json";
$analysis_status = [];
$analysis_status_file = __DIR__ . "/analysis_status.json";

if (file_exists($status_file)) {
    $stored_status = json_decode(file_get_contents($status_file), true);
    if (is_array($stored_status)) {
        $latest_status = $stored_status;
    }
}

if (file_exists($analysis_status_file)) {
    $stored_analysis_status = json_decode(file_get_contents($analysis_status_file), true);
    if (is_array($stored_analysis_status)) {
        $analysis_status = $stored_analysis_status;
    }
}

// A failed background launch can leave "Starting" saved indefinitely. Treat it
// as an error when the AI has not published a live update within 30 seconds.
$analysis_updated_at = intval($analysis_status["updated_at_epoch"] ?? 0);
if (
    strtolower((string) ($analysis_status["analysis_status"] ?? "")) === "starting"
    && $analysis_updated_at > 0
    && time() - $analysis_updated_at > 30
) {
    $analysis_status["analysis_status"] = "Error";
    $analysis_status["ai_status"] = "Offline";
    $analysis_status["message"] = "The previous start attempt did not connect. Check the camera details and try again.";
}

$sql = "
SELECT *
FROM camera_monitoring_logs
ORDER BY recorded_at DESC
LIMIT 1
";

$result = $conn->query($sql);

if(!$result || $result->num_rows==0){

    $fallback = [
        "vehicle_count"=>0,
        "inbound_count"=>0,
        "outbound_count"=>0,
        "congestion_level"=>"Unknown",
        "officer_presence"=>"Unknown",
        "potential_collision"=>"None",
        "alert_status"=>"NORMAL",
        "ai_status"=>"Offline",
        "recorded_at"=>"No Data"
    ];

    $response = array_merge($fallback, $latest_status, $analysis_status);

    $has_live_status = !empty($latest_status["updated_at_epoch"]) && time() - intval($latest_status["updated_at_epoch"]) <= 6;

    if ($has_live_status && strtolower((string)($analysis_status["analysis_status"] ?? "")) !== "stopped") {
        $response["analysis_status"] = "Running";
        $response["ai_status"] = "Running";
        $response["message"] = "Live AI analysis is running.";
    }

    if (empty($analysis_status["analysis_status"]) && !$has_live_status) {
        $response["ai_status"] = "Offline";
    }

    if (empty($response["analysis_status"])) {
        $response["analysis_status"] = $has_live_status ? ($response["ai_status"] ?? "Running") : "Idle";
    }

    echo json_encode($response);

    exit;

}

$row = $result->fetch_assoc();
$response = array_merge($row, $latest_status, $analysis_status);
$has_live_status = !empty($latest_status["updated_at_epoch"]) && time() - intval($latest_status["updated_at_epoch"]) <= 6;

if ($has_live_status && strtolower((string)($analysis_status["analysis_status"] ?? "")) !== "stopped") {
    $response["analysis_status"] = "Running";
    $response["ai_status"] = "Running";
    $response["message"] = "Live AI analysis is running.";
}

if (empty($analysis_status["analysis_status"]) && !empty($latest_status["updated_at_epoch"]) && !$has_live_status) {
    $response["ai_status"] = "Offline";
}

if (empty($response["analysis_status"])) {
    $response["analysis_status"] = $has_live_status ? ($response["ai_status"] ?? "Running") : "Idle";
}

if (empty($response["alert_status"])) {
    $response["alert_status"] = !empty($response["alert_generated"]) ? "ALERT" : "NORMAL";
}

echo json_encode($response);

$conn->close();
