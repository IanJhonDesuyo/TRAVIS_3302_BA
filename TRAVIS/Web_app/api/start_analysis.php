<?php

header("Content-Type: application/json");

$projectRoot = realpath(__DIR__ . "/../..");
$pythonExe = $projectRoot . "\\.venv\\Scripts\\python.exe";
$cvDir = $projectRoot . "\\computer_vision";
$detectScript = $cvDir . "\\detect_video.py";
$videoPath = $cvDir . "\\uploads\\videos\\test.mp4";

$statusFile = __DIR__ . "\\analysis_status.json";
$logDir = $projectRoot . "\\Web_app\\uploads\\logs";
$logFile = $logDir . "\\analysis_latest.log";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);
    exit;
}

if (!file_exists($videoPath)) {
    echo json_encode([
        "success" => false,
        "analysis_status" => "Idle",
        "message" => "Upload a video first."
    ]);
    exit;
}

if (!file_exists($pythonExe)) {
    echo json_encode([
        "success" => false,
        "analysis_status" => "Error",
        "message" => "Python executable not found: " . $pythonExe
    ]);
    exit;
}

if (!file_exists($detectScript)) {
    echo json_encode([
        "success" => false,
        "analysis_status" => "Error",
        "message" => "detect_video.py not found."
    ]);
    exit;
}

if (!is_dir($logDir)) {
    mkdir($logDir, 0777, true);
}

file_put_contents($statusFile, json_encode([
    "analysis_status" => "Starting",
    "ai_status" => "Starting",
    "message" => "Starting AI analysis...",
    "updated_at" => date("Y-m-d H:i:s"),
    "updated_at_epoch" => time()
], JSON_PRETTY_PRINT));

$command =
    'cd /d "' . $cvDir . '" && ' .
    '"' . $pythonExe . '" "' . $detectScript . '" ' .
    '> "' . $logFile . '" 2>&1';

$psexec = 'start "" /B cmd.exe /C "' . $command . '"';

pclose(popen($psexec, "r"));

echo json_encode([
    "success" => true,
    "analysis_status" => "Starting",
    "message" => "AI analysis is starting."
]);