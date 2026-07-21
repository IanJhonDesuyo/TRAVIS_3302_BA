<?php

header("Content-Type: application/json");

$projectRoot = realpath(__DIR__ . "/../..");
$pythonExe = $projectRoot . "\\.venv\\Scripts\\python.exe";
$cvDir = $projectRoot . "\\computer_vision";
$detectScript = $cvDir . "\\detect_video.py";
$videoPath = $cvDir . "\\uploads\\videos\\test.mp4";
$cameraConfigPath = $cvDir . "\\camera_config.json";
$calibrationDir = $cvDir . "\\calibration_profiles";

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

$payload = json_decode(file_get_contents("php://input"), true);
$sourceType = is_array($payload) ? ($payload["source_type"] ?? "uploaded_video") : "uploaded_video";
$calibrationFile = basename((string)($payload["calibration_profile"] ?? ""));
$calibrationArg = "";

if (!in_array($sourceType, ["uploaded_video", "tapo_camera"], true)) {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Invalid video source."]);
    exit;
}

if ($calibrationFile !== "") {
    if (!preg_match('/^[a-zA-Z0-9_-]+\.json$/', $calibrationFile) || !is_file($calibrationDir . "\\" . $calibrationFile)) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "The selected intersection configuration is invalid."]);
        exit;
    }
    $calibrationArg = ' --calibration-profile "calibration_profiles\\' . $calibrationFile . '"';
}

if ($sourceType === "uploaded_video" && !file_exists($videoPath)) {
    echo json_encode([
        "success" => false,
        "analysis_status" => "Idle",
        "message" => "Upload a video first."
    ]);
    exit;
}

if ($sourceType === "tapo_camera") {
    $savedCameraConfig = [];
    if (is_file($cameraConfigPath)) {
        $decodedCameraConfig = json_decode((string)file_get_contents($cameraConfigPath), true);
        if (is_array($decodedCameraConfig)) $savedCameraConfig = $decodedCameraConfig;
    }

    $submittedHost = trim((string)($payload["tapo_host"] ?? ""));
    $submittedUsername = trim((string)($payload["tapo_username"] ?? ""));
    $host = $submittedHost !== "" ? $submittedHost : trim((string)($savedCameraConfig["host"] ?? ""));
    $username = $submittedUsername !== "" ? $submittedUsername : trim((string)($savedCameraConfig["username"] ?? ""));
    $password = (string) ($payload["tapo_password"] ?? "");
    if ($password === "" && $host === ($savedCameraConfig["host"] ?? null) && $username === ($savedCameraConfig["username"] ?? null)) {
        $password = (string)($savedCameraConfig["password"] ?? "");
    }
    $streamValue = $payload["tapo_stream"] ?? $savedCameraConfig["stream"] ?? "stream2";
    $stream = $streamValue === "stream1" ? "stream1" : "stream2";

    if (!filter_var($host, FILTER_VALIDATE_IP) || $username === "" || $password === "") {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "Enter a valid camera IP, camera username, and password."]);
        exit;
    }

    $cameraConfig = json_encode([
        "host" => $host,
        "username" => $username,
        "password" => $password,
        "stream" => $stream
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (file_put_contents($cameraConfigPath, $cameraConfig, LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Could not save the local camera configuration."]);
        exit;
    }
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
    '"' . $pythonExe . '" "' . $detectScript . '" --source-type ' . $sourceType . $calibrationArg . ' ' .
    '> "' . $logFile . '" 2>&1';

$psexec = 'start "" /B cmd.exe /C "' . $command . '"';

pclose(popen($psexec, "r"));

echo json_encode([
    "success" => true,
    "analysis_status" => "Starting",
    "message" => $sourceType === "tapo_camera" ? "Tapo camera analysis is starting." : "Video analysis is starting."
]);
