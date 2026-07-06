<?php

header("Content-Type: application/json");

$status_file = __DIR__ . "/analysis_status.json";
$project_root = realpath(__DIR__ . "/../..");
$video_path = $project_root . "/computer_vision/uploads/videos/test.mp4";
$runner = $project_root . "/Web_app/scripts/run_analysis.ps1";

function read_analysis_status(string $status_file): array {
    if (!file_exists($status_file)) {
        return [];
    }

    $data = json_decode(file_get_contents($status_file), true);
    return is_array($data) ? $data : [];
}

function write_analysis_status(string $status_file, string $status, string $message): void {
    file_put_contents($status_file, json_encode([
        "analysis_status" => $status,
        "ai_status" => $status,
        "message" => $message,
        "updated_at" => date("Y-m-d H:i:s"),
        "updated_at_epoch" => time()
    ]));
}

function is_recent_running_status(array $status): bool {
    $state = strtolower(strval($status["analysis_status"] ?? ""));
    $updated = intval($status["updated_at_epoch"] ?? 0);

    if ($state === "starting") {
        return $updated > 0 && time() - $updated < 30;
    }

    if ($state === "running") {
        return $updated > 0 && time() - $updated < 86400;
    }

    return false;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);
    exit;
}

if (!file_exists($video_path)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "analysis_status" => "Idle",
        "message" => "Upload a video before starting analysis."
    ]);
    exit;
}

if (!file_exists($runner)) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "analysis_status" => "Error",
        "message" => "Analysis runner was not found."
    ]);
    exit;
}

$current_status = read_analysis_status($status_file);

if (is_recent_running_status($current_status)) {
    echo json_encode([
        "success" => true,
        "analysis_status" => $current_status["analysis_status"],
        "message" => "Analysis already running"
    ]);
    exit;
}

write_analysis_status($status_file, "Starting", "Starting AI analysis.");

$runner = str_replace("/", "\\", $runner);
$command = 'start "" /B powershell.exe -NoProfile -ExecutionPolicy Bypass -File "' . $runner . '"';
$process = popen($command, "r");

if ($process === false) {
    write_analysis_status($status_file, "Error", "Unable to start AI analysis.");
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "analysis_status" => "Error",
        "message" => "Unable to start AI analysis."
    ]);
    exit;
}

pclose($process);

echo json_encode([
    "success" => true,
    "analysis_status" => "Starting",
    "message" => "AI analysis is starting."
]);
