<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$risk = trim((string)($_GET['risk'] ?? ''));
$endpoint = 'http://127.0.0.1:5001/hotspots';
if ($risk !== '') $endpoint .= '/' . rawurlencode($risk);

$response = false;
$curlError = 'Machine-learning API is still starting.';
$status = 503;
for ($attempt = 1; $attempt <= 20; $attempt++) {
    $curl = curl_init($endpoint);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPGET => true,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 1,
        CURLOPT_TIMEOUT => 5,
    ]);
    $response = curl_exec($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if ($response !== false && $status > 0) break;
    if ($attempt < 20) usleep(500000);
}

if ($response === false) {
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Hotspot clustering service did not become ready in time.', 'details' => $curlError]);
    exit;
}

http_response_code($status);
echo $response;
