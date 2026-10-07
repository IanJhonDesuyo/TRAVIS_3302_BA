<?php

header("Content-Type: application/json");

require_once __DIR__ . '/../Admin/db_connect.php';
require_once __DIR__ . '/hybrid_bridge.php';

$statusFile = __DIR__ . "/analysis_status.json";
$payload = json_decode((string)file_get_contents("php://input"), true);
$payload = is_array($payload) ? $payload : [];

// TEMPORARY DIAGNOSTIC SWITCH. This prevents every hosted/local stop request
// from terminating detect_video.py during the continuous Tapo stream test.
// Set to true after the test to restore the normal Stop/Disconnect behavior.
$stopAnalysisEnabled = false;
if (!$stopAnalysisEnabled) {
    http_response_code(423);
    echo json_encode([
        "success" => false,
        "analysis_status" => "Running",
        "message" => "Stopping is temporarily disabled for the continuous Tapo stream test."
    ]);
    exit;
}

if (!travis_is_edge_host()) {
    try {
        $result = travis_dispatch_edge_job($conn, 'cv_stop', $payload, 15);
        if (!empty($result['pending'])) http_response_code(503);
        if (empty($result['pending']) && ($result['success'] ?? false)) {
            $hostedStoppedStatus = [
                'analysis_status' => 'Stopped',
                'ai_status' => 'Stopped',
                'message' => (string)($result['message'] ?? 'Analysis stopped.'),
                'stream_owner' => 'shared',
                'stopped_by' => strtolower((string)($payload['client'] ?? 'web')),
                'updated_at' => date('Y-m-d H:i:s'),
                'updated_at_epoch' => time(),
            ];
            file_put_contents($statusFile, json_encode($hostedStoppedStatus, JSON_PRETTY_PRINT), LOCK_EX);
        }
        echo json_encode($result, JSON_UNESCAPED_SLASHES);
    } catch (Throwable $error) {
        error_log('TRAVIS CV stop bridge: ' . $error->getMessage());
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => $error->getMessage()]);
    }
    exit;
}

$requestOwner = strtolower((string)($payload["client"] ?? "web"));
$storedStatus = is_file($statusFile) ? json_decode((string)file_get_contents($statusFile), true) : [];
$streamOwner = strtolower((string)($storedStatus["stream_owner"] ?? "shared"));

if (!in_array($requestOwner, ["web", "mobile"], true)) {
    http_response_code(422);
    echo json_encode(["success" => false, "message" => "Invalid monitoring client."]);
    exit;
}

/*
    Kill ONLY detect_video.py
*/

$command = 'powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { ($_.Name -eq \'python.exe\' -or $_.Name -eq \'pythonw.exe\') -and $_.CommandLine -like \'*detect_video.py*\' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }"';

exec($command, $output, $result);

$stoppedStatus = [
    "success" => true,
    "analysis_status" => "Stopped",
    "ai_status" => "Stopped",
    "message" => "Analysis stopped.",
    "stream_owner" => "shared",
    "stopped_by" => $requestOwner,
    "updated_at" => date("Y-m-d H:i:s"),
    "updated_at_epoch" => time()
];

// Keep the last selected feed details so the monitoring form can restore the
// most recently used source after stopping or reloading the page.
foreach (["source_type", "calibration_profile"] as $preferenceKey) {
    if (!empty($storedStatus[$preferenceKey])) {
        $stoppedStatus[$preferenceKey] = $storedStatus[$preferenceKey];
    }
}

file_put_contents($statusFile, json_encode($stoppedStatus, JSON_PRETTY_PRINT));

echo json_encode([
    "success" => true,
    "analysis_status" => "Stopped",
    "message" => "Analysis stopped successfully."
]);
