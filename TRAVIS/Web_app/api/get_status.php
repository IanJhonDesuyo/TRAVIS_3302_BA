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

// A failed background launch can leave "Starting" saved indefinitely. Show a
// recent failed attempt as an error, but reset an old session to Idle so a
// historical failure is not presented as a new error on every page visit.
$analysis_updated_at = intval($analysis_status["updated_at_epoch"] ?? 0);
$analysis_age_seconds = $analysis_updated_at > 0 ? time() - $analysis_updated_at : 0;
$stored_analysis_state = strtolower((string) ($analysis_status["analysis_status"] ?? ""));
if ($analysis_updated_at > 0 && $analysis_age_seconds > 300 && in_array($stored_analysis_state, ["starting", "running", "error"], true)) {
    $analysis_status["analysis_status"] = "Idle";
    $analysis_status["ai_status"] = "Offline";
    $analysis_status["message"] = "";
} elseif ($stored_analysis_state === "starting" && $analysis_updated_at > 0) {
    // A verified live-camera frame can arrive before the first CPU YOLO
    // inference finishes. Keep this aligned with monitoring.js and the edge
    // launch guard so a normal model warm-up is not reported as a failure and
    // the browser does not tear down an already-connected stream.
    if ($analysis_age_seconds > 180) {
        $analysis_status["analysis_status"] = "Error";
        $analysis_status["ai_status"] = "Offline";
        $analysisLog = dirname(__DIR__) . "/uploads/logs/analysis_latest.log";
        $logTail = is_file($analysisLog) ? (string)file_get_contents($analysisLog, false, null, max(0, filesize($analysisLog) - 8192)) : "";
        if (($analysis_status["source_type"] ?? "") === "tapo_camera" && (str_contains($logTail, "fctx->async_lock") || str_contains($logTail, "Cannot open video source"))) {
            $analysis_status["message"] = "The Tapo RTSP stream did not connect. Check the camera IP and Camera Account credentials, confirm RTSP is enabled, and use Standard quality.";
        } else {
            $analysis_status["message"] = "The latest start attempt did not connect. Check the selected source and try again.";
        }
    }
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

    $latestAiState = strtolower((string)($latest_status["ai_status"] ?? ""));
    if ($has_live_status && $latestAiState === "error") {
        $response["analysis_status"] = "Error";
        $response["ai_status"] = "Error";
        $response["message"] = (string)($latest_status["message"] ?? "Computer Vision stopped because the camera stream failed.");
    } elseif ($has_live_status && !in_array($latestAiState, ["completed", "stopped"], true) && strtolower((string)($analysis_status["analysis_status"] ?? "")) !== "stopped") {
        $response["analysis_status"] = "Running";
        $response["ai_status"] = "Running";
        $response["message"] = "Live AI analysis is running.";
    }

    if ($latestAiState === 'completed') {
        $response['analysis_status'] = 'Completed';
        $response['ai_status'] = 'Completed';
        $response['message'] = 'Uploaded video analysis completed.';
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

$latestAiState = strtolower((string)($latest_status["ai_status"] ?? ""));
if ($has_live_status && $latestAiState === "error") {
    $response["analysis_status"] = "Error";
    $response["ai_status"] = "Error";
    $response["message"] = (string)($latest_status["message"] ?? "Computer Vision stopped because the camera stream failed.");
} elseif ($has_live_status && !in_array($latestAiState, ["completed", "stopped"], true) && strtolower((string)($analysis_status["analysis_status"] ?? "")) !== "stopped") {
    $response["analysis_status"] = "Running";
    $response["ai_status"] = "Running";
    $response["message"] = "Live AI analysis is running.";
}

if ($latestAiState === 'completed') {
    $response['analysis_status'] = 'Completed';
    $response['ai_status'] = 'Completed';
    $response['message'] = 'Uploaded video analysis completed.';
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
