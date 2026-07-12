<?php

header("Content-Type: application/json");

// Flask Hotspot API
$risk = trim((string) ($_GET['risk'] ?? ''));
$flask_api = "http://127.0.0.1:5001/hotspots";

if ($risk !== '') {
    $flask_api .= '/' . rawurlencode($risk);
}

// Initialize cURL
$ch = curl_init($flask_api);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Accept: application/json"
]);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);

$response = curl_exec($ch);

// cURL error
if (curl_errno($ch)) {
    http_response_code(502);
    echo json_encode([
        "success" => false,
        "message" => curl_error($ch)
    ]);

    curl_close($ch);
    exit;
}

// HTTP Status
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// Return Flask response
http_response_code($httpCode);
echo $response;
