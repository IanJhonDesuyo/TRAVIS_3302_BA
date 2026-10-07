<?php

header("Content-Type: application/json");

require_once __DIR__ . '/../Admin/db_connect.php';
require_once __DIR__ . '/hybrid_bridge.php';

$projectRoot = realpath(__DIR__ . "/../..");
$pythonExe = $projectRoot . "\\.venv\\Scripts\\python.exe";
$cvDir = $projectRoot . "\\computer_vision";
$detectScript = $cvDir . "\\detect_video.py";
$detectorLauncher = $cvDir . "\\start_detector.ps1";
$videoPath = $cvDir . "\\uploads\\videos\\test.mp4";
$cameraConfigPath = $cvDir . "\\camera_config.json";
$phoneCameraConfigPath = $cvDir . "\\phone_camera_config.json";
$calibrationDir = $cvDir . "\\calibration_profiles";

$statusFile = __DIR__ . "\\analysis_status.json";
$logDir = $projectRoot . "\\Web_app\\uploads\\logs";
$logFile = $logDir . "\\analysis_latest.log";

function is_private_camera_ipv4(string $host): bool
{
    if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }

    $parts = array_map('intval', explode('.', $host));
    return $parts[0] === 10
        || ($parts[0] === 172 && $parts[1] >= 16 && $parts[1] <= 31)
        || ($parts[0] === 192 && $parts[1] === 168)
        || ($parts[0] === 169 && $parts[1] === 254);
}

function is_valid_calibration_profile(array $profile): bool
{
    $validPoints = static function ($points, int $expected): bool {
        if (!is_array($points) || count($points) !== $expected) return false;
        foreach ($points as $point) {
            if (!is_array($point) || count($point) !== 2
                || !is_numeric($point[0]) || !is_numeric($point[1])) return false;
            $x = (float)$point[0];
            $y = (float)$point[1];
            if ($x < 0 || $x > 1 || $y < 0 || $y > 1) return false;
        }
        return true;
    };

    if (!isset($profile['profile_name']) || !is_string($profile['profile_name'])
        || trim($profile['profile_name']) === '' || strlen($profile['profile_name']) > 200) return false;
    if (!$validPoints($profile['inbound_line'] ?? null, 2)
        || !$validPoints($profile['outbound_line'] ?? null, 2)) return false;

    $officerZone = $profile['officer_zone'] ?? [];
    return $officerZone === [] || $officerZone === null || $validPoints($officerZone, 4);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "POST method required."
    ]);
    exit;
}

$payload = json_decode(file_get_contents("php://input"), true);
$payload = is_array($payload) ? $payload : [];

if (!travis_is_edge_host()) {
    $hostedSourceType = (string)($payload['source_type'] ?? 'uploaded_video');
    if (!in_array($hostedSourceType, ['uploaded_video', 'tapo_camera', 'phone_camera'], true)) {
        $hostedSourceType = 'uploaded_video';
    }
    $hostedCalibrationFile = basename((string)($payload['calibration_profile'] ?? ''));
    if ($hostedCalibrationFile !== '') {
        $hostedCalibrationDirectory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR
            . 'computer_vision' . DIRECTORY_SEPARATOR . 'calibration_profiles';
        $hostedCalibrationPath = $hostedCalibrationDirectory . DIRECTORY_SEPARATOR . $hostedCalibrationFile;
        if (!preg_match('/^[a-zA-Z0-9_-]+\.json$/', $hostedCalibrationFile)
            || !is_file($hostedCalibrationPath)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'The selected intersection configuration no longer exists on the web server.']);
            exit;
        }
        $hostedCalibrationProfile = json_decode((string)file_get_contents($hostedCalibrationPath), true);
        if (!is_array($hostedCalibrationProfile) || !is_valid_calibration_profile($hostedCalibrationProfile)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'The selected intersection configuration contains invalid line coordinates.']);
            exit;
        }
        // Send the selected profile with the start job so newly created or
        // edited hosted configurations are synchronized to the edge laptop.
        $payload['calibration_profile'] = $hostedCalibrationFile;
        $payload['calibration_profile_data'] = $hostedCalibrationProfile;
    }

    $hostedStartingStatus = [
        'analysis_status' => 'Starting',
        'ai_status' => 'Starting',
        'message' => $hostedSourceType === 'tapo_camera'
            ? 'Connecting to the Tapo camera through the monitoring laptop...'
            : 'Starting Computer Vision on the monitoring laptop...',
        'source_type' => $hostedSourceType,
        'stream_owner' => 'shared',
        'started_by' => strtolower((string)($payload['client'] ?? 'web')),
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_at_epoch' => time(),
    ];
    file_put_contents($statusFile, json_encode($hostedStartingStatus, JSON_PRETTY_PRINT), LOCK_EX);

    try {
        $result = travis_dispatch_edge_job($conn, 'cv_start', $payload, 45);
        if (!empty($result['pending'])) {
            // The worker may still be downloading a large uploaded video.
            // Keep the UI in Starting state instead of reporting a false
            // failure that encourages duplicate start requests.
            http_response_code(202);
            $result['success'] = true;
            $result['analysis_status'] = 'Starting';
            $result['message'] = 'The laptop worker is preparing the footage. Analysis will appear automatically when ready.';
            $hostedStartingStatus['analysis_status'] = 'Starting';
            $hostedStartingStatus['ai_status'] = 'Starting';
            $hostedStartingStatus['message'] = $result['message'];
        } else {
            $hostedStartingStatus['analysis_status'] = (string)($result['analysis_status'] ?? 'Starting');
            $hostedStartingStatus['ai_status'] = $hostedStartingStatus['analysis_status'];
            $hostedStartingStatus['message'] = (string)($result['message'] ?? $hostedStartingStatus['message']);
        }
        $hostedStartingStatus['updated_at'] = date('Y-m-d H:i:s');
        $hostedStartingStatus['updated_at_epoch'] = time();
        file_put_contents($statusFile, json_encode($hostedStartingStatus, JSON_PRETTY_PRINT), LOCK_EX);
        echo json_encode($result, JSON_UNESCAPED_SLASHES);
    } catch (Throwable $error) {
        error_log('TRAVIS CV start bridge: ' . $error->getMessage());
        $hostedStartingStatus['analysis_status'] = 'Error';
        $hostedStartingStatus['ai_status'] = 'Offline';
        $hostedStartingStatus['message'] = $error->getMessage();
        $hostedStartingStatus['updated_at'] = date('Y-m-d H:i:s');
        $hostedStartingStatus['updated_at_epoch'] = time();
        file_put_contents($statusFile, json_encode($hostedStartingStatus, JSON_PRETTY_PRINT), LOCK_EX);
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => $error->getMessage()]);
    }
    exit;
}

$sourceType = is_array($payload) ? ($payload["source_type"] ?? "uploaded_video") : "uploaded_video";
$streamOwner = is_array($payload) ? strtolower((string)($payload["client"] ?? "web")) : "web";
$calibrationFile = basename((string)($payload["calibration_profile"] ?? ""));
$calibrationArg = "";

if (!in_array($streamOwner, ["web", "mobile"], true)) {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Invalid monitoring client."]);
    exit;
}

if (is_file($statusFile)) {
    $existingStatus = json_decode((string)file_get_contents($statusFile), true);
    $existingState = strtolower((string)($existingStatus["analysis_status"] ?? ""));
    $existingUpdated = (int)($existingStatus["updated_at_epoch"] ?? 0);
    $latestStatusFile = __DIR__ . "/latest_status.json";
    $latestStatus = is_file($latestStatusFile) ? json_decode((string)file_get_contents($latestStatusFile), true) : [];
    $hasLiveFrames = !empty($latestStatus["updated_at_epoch"]) && time() - (int)$latestStatus["updated_at_epoch"] <= 6;
    // CPU model warm-up can take well over 30 seconds. Keep joining the same
    // launch during that period so retries cannot create competing RTSP and
    // Flask processes.
    $isStarting = $existingState === "starting" && $existingUpdated > 0 && time() - $existingUpdated <= 180;
    if ($hasLiveFrames || $isStarting) {
        echo json_encode([
            "success" => true,
            "analysis_status" => $hasLiveFrames ? "Running" : "Starting",
            "stream_owner" => "shared",
            "joined_existing" => true,
            "message" => $hasLiveFrames
                ? "Joined the active shared monitoring session."
                : "Joined the monitoring session while it is starting."
        ]);
        exit;
    }
}

if (!in_array($sourceType, ["uploaded_video", "tapo_camera", "phone_camera"], true)) {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Invalid video source."]);
    exit;
}

if ($calibrationFile !== "") {
    $calibrationPath = $calibrationDir . "\\" . $calibrationFile;
    $syncedProfile = $payload['calibration_profile_data'] ?? null;
    if (preg_match('/^[a-zA-Z0-9_-]+\.json$/', $calibrationFile)
        && is_array($syncedProfile) && is_valid_calibration_profile($syncedProfile)) {
        if (!is_dir($calibrationDir) && !mkdir($calibrationDir, 0775, true)) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Unable to prepare the local configuration directory."]);
            exit;
        }
        $profileJson = json_encode($syncedProfile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($profileJson === false || file_put_contents($calibrationPath, $profileJson . PHP_EOL, LOCK_EX) === false) {
            http_response_code(500);
            echo json_encode(["success" => false, "message" => "Unable to synchronize the selected configuration to the monitoring laptop."]);
            exit;
        }
    }
    if (!preg_match('/^[a-zA-Z0-9_-]+\.json$/', $calibrationFile) || !is_file($calibrationPath)) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "The selected intersection configuration is unavailable on the monitoring laptop."]);
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
    // A camera commonly receives a new DHCP address while retaining the same
    // local camera account. Reuse its saved password when the username matches.
    if ($password === "" && $username === ($savedCameraConfig["username"] ?? null)) {
        $password = (string)($savedCameraConfig["password"] ?? "");
    }
    $streamValue = $payload["tapo_stream"] ?? $savedCameraConfig["stream"] ?? "stream2";
    $stream = $streamValue === "stream1" ? "stream1" : "stream2";

    if (!filter_var($host, FILTER_VALIDATE_IP) || $username === "" || $password === "") {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "Enter a valid camera IP, camera username, and password."]);
        exit;
    }

    $rtspSocket = @fsockopen($host, 554, $socketError, $socketMessage, 2.0);
    if ($rtspSocket === false) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "The camera at " . $host . " is not reachable on RTSP port 554. Check its Wi-Fi connection and Camera Account settings."]);
        exit;
    }
    fclose($rtspSocket);

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

if ($sourceType === "phone_camera") {
    $savedPhoneConfig = [];
    if (is_file($phoneCameraConfigPath)) {
        $decodedPhoneConfig = json_decode((string)file_get_contents($phoneCameraConfigPath), true);
        if (is_array($decodedPhoneConfig)) $savedPhoneConfig = $decodedPhoneConfig;
    }

    $submittedStreamUrl = trim((string)($payload["phone_stream_url"] ?? ""));
    $streamUrl = $submittedStreamUrl !== ""
        ? $submittedStreamUrl
        : trim((string)($savedPhoneConfig["stream_url"] ?? ""));

    if ($streamUrl === "" || strlen($streamUrl) > 2048 || preg_match('/[\x00-\x20\x7f]/', $streamUrl)) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "Enter the complete RTSP or HTTP video URL shown by the cellphone camera app."]);
        exit;
    }

    $urlParts = parse_url($streamUrl);
    $scheme = strtolower((string)($urlParts["scheme"] ?? ""));
    $host = (string)($urlParts["host"] ?? "");
    $allowedSchemes = ["http", "https", "rtsp", "rtsps"];

    if (!in_array($scheme, $allowedSchemes, true) || !is_private_camera_ipv4($host)) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "Use an RTSP or HTTP stream URL with the phone's private Wi-Fi IP address (for example, 192.168.x.x)."]);
        exit;
    }

    $defaultPorts = ["http" => 80, "https" => 443, "rtsp" => 554, "rtsps" => 322];
    $port = (int)($urlParts["port"] ?? $defaultPorts[$scheme]);
    if ($port < 1 || $port > 65535) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "The cellphone stream URL contains an invalid port."]);
        exit;
    }

    $phoneSocket = @fsockopen($host, $port, $socketError, $socketMessage, 2.0);
    if ($phoneSocket === false) {
        http_response_code(422);
        echo json_encode(["success" => false, "message" => "The cellphone camera is not reachable at " . $host . ":" . $port . ". Keep its camera server running and connect both devices to the same Wi-Fi."]);
        exit;
    }
    fclose($phoneSocket);

    $phoneCameraConfig = json_encode([
        "stream_url" => $streamUrl
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (file_put_contents($phoneCameraConfigPath, $phoneCameraConfig, LOCK_EX) === false) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Could not save the local cellphone camera configuration."]);
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

if (!file_exists($detectorLauncher)) {
    echo json_encode([
        "success" => false,
        "analysis_status" => "Error",
        "message" => "Computer Vision launcher not found."
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
    "source_type" => $sourceType,
    "stream_owner" => "shared",
    "started_by" => $streamOwner,
    "updated_at" => date("Y-m-d H:i:s"),
    "updated_at_epoch" => time()
], JSON_PRETTY_PRINT));

$launcherCommand = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File '
    . escapeshellarg($detectorLauncher)
    . ' -PythonExe ' . escapeshellarg($pythonExe)
    . ' -DetectScript ' . escapeshellarg($detectScript)
    . ' -WorkingDirectory ' . escapeshellarg($cvDir)
    . ' -LogFile ' . escapeshellarg($logFile)
    . ' -SourceType ' . escapeshellarg($sourceType);
if ($calibrationFile !== '') {
    $launcherCommand .= ' -CalibrationProfile ' . escapeshellarg('calibration_profiles\\' . $calibrationFile);
}

$launcherOutput = [];
$launcherExitCode = 0;
exec($launcherCommand, $launcherOutput, $launcherExitCode);
if ($launcherExitCode !== 0) {
    file_put_contents($statusFile, json_encode([
        "analysis_status" => "Error",
        "ai_status" => "Error",
        "message" => "Computer Vision could not be launched as a background process.",
        "source_type" => $sourceType,
        "updated_at" => date("Y-m-d H:i:s"),
        "updated_at_epoch" => time()
    ], JSON_PRETTY_PRINT), LOCK_EX);
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "analysis_status" => "Error",
        "message" => "Computer Vision could not be launched as a background process."
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "analysis_status" => "Starting",
    "stream_owner" => "shared",
    "message" => $sourceType === "tapo_camera"
        ? "Tapo camera analysis is starting."
        : ($sourceType === "phone_camera" ? "Cellphone camera analysis is starting." : "Video analysis is starting.")
]);
