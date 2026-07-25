<?php

header("Content-Type: application/json");

$statusFile = __DIR__ . "/analysis_status.json";
$payload = json_decode((string)file_get_contents("php://input"), true);
$requestOwner = strtolower((string)($payload["client"] ?? "web"));
$storedStatus = is_file($statusFile) ? json_decode((string)file_get_contents($statusFile), true) : [];
$streamOwner = strtolower((string)($storedStatus["stream_owner"] ?? ""));

if ($streamOwner !== "" && $requestOwner !== $streamOwner) {
    http_response_code(409);
    echo json_encode(["success" => false, "message" => "This analysis belongs to the " . $streamOwner . " client."]);
    exit;
}

/*
    Kill ONLY detect_video.py
*/

$command = 'powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { ($_.Name -eq \'python.exe\' -or $_.Name -eq \'pythonw.exe\') -and $_.CommandLine -like \'*detect_video.py*\' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }"';

exec($command, $output, $result);

file_put_contents($statusFile, json_encode([
    "success" => true,
    "analysis_status" => "Stopped",
    "ai_status" => "Stopped",
    "message" => "Analysis stopped.",
    "stream_owner" => $streamOwner,
    "updated_at" => date("Y-m-d H:i:s"),
    "updated_at_epoch" => time()
], JSON_PRETTY_PRINT));

echo json_encode([
    "success" => true,
    "analysis_status" => "Stopped",
    "message" => "Analysis stopped successfully."
]);
