<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=45');

require_once dirname(__DIR__) . '/Admin/db_connect.php';

$apiKey = '';
$result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'tomtom_api_key' LIMIT 1");
if ($result && ($row = $result->fetch_assoc())) {
    $apiKey = trim((string)$row['setting_value']);
}

if (strlen($apiKey) < 20) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Traffic service is not configured.']);
    exit;
}

$cacheDirectory = dirname(__DIR__, 2) . '/storage/cache';
$cacheFile = $cacheDirectory . '/public_traffic_status.json';
if (is_file($cacheFile) && filemtime($cacheFile) >= time() - 45) {
    $cachedResponse = file_get_contents($cacheFile);
    if ($cachedResponse !== false) {
        echo $cachedResponse;
        exit;
    }
}

$latitude = 14.07104;
$longitude = 120.632455;
$url = sprintf(
    'https://api.tomtom.com/traffic/services/4/flowSegmentData/absolute/10/json?point=%s,%s&unit=KMPH&key=%s',
    $latitude,
    $longitude,
    rawurlencode($apiKey)
);

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 4,
    CURLOPT_TIMEOUT => 8,
    CURLOPT_FOLLOWLOCATION => false,
]);
$body = curl_exec($curl);
$statusCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

if ($body === false || $statusCode !== 200) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => $curlError ?: 'Live traffic data is temporarily unavailable.']);
    exit;
}

$payload = json_decode($body, true);
$flow = $payload['flowSegmentData'] ?? null;
if (!is_array($flow)) {
    http_response_code(502);
    echo json_encode(['success' => false, 'message' => 'TomTom returned incomplete traffic data.']);
    exit;
}

$currentSpeed = max(0, (int)round((float)($flow['currentSpeed'] ?? 0)));
$freeFlowSpeed = max(1, (int)round((float)($flow['freeFlowSpeed'] ?? 1)));
$speedRatio = $currentSpeed / $freeFlowSpeed;
$roadClosed = (bool)($flow['roadClosure'] ?? false);

if ($roadClosed || $speedRatio < 0.5) {
    $level = 'heavy';
    $label = $roadClosed ? 'Road Closed' : 'Heavy';
} elseif ($speedRatio < 0.8) {
    $level = 'moderate';
    $label = 'Moderate';
} else {
    $level = 'light';
    $label = 'Light';
}

$response = json_encode([
    'success' => true,
    'data' => [
        'location' => 'J.P. Laurel Street, Nasugbu',
        'level' => $level,
        'label' => $label,
        'current_speed' => $currentSpeed,
        'normal_speed' => $freeFlowSpeed,
        'confidence' => round((float)($flow['confidence'] ?? 0) * 100),
        'road_closed' => $roadClosed,
        'updated_at' => date(DATE_ATOM),
    ],
], JSON_UNESCAPED_SLASHES);

if (!is_dir($cacheDirectory)) {
    @mkdir($cacheDirectory, 0775, true);
}
if ($response !== false && is_dir($cacheDirectory)) {
    @file_put_contents($cacheFile, $response, LOCK_EX);
}

echo $response;
