<?php
declare(strict_types=1);

$client = strtolower((string)($_GET['client'] ?? ''));
if (!in_array($client, ['web', 'mobile'], true)) {
    http_response_code(422);
    exit;
}

if (PHP_OS_FAMILY !== 'Windows') {
    $snapshot = dirname(__DIR__, 2) . '/storage/cache/latest_cv_snapshot.jpg';
    if (!is_file($snapshot) || filemtime($snapshot) < time() - 15) {
        http_response_code(503);
        exit;
    }
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($snapshot));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    readfile($snapshot);
    exit;
}

$curl = curl_init('http://127.0.0.1:5000/snapshot');
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 2,
    CURLOPT_TIMEOUT => 3,
]);
$jpeg = curl_exec($curl);
$code = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$type = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
curl_close($curl);

if ($jpeg === false || $code !== 200 || stripos($type, 'image/jpeg') === false) {
    http_response_code(503);
    exit;
}

header('Content-Type: image/jpeg');
header('Content-Length: ' . strlen($jpeg));
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo $jpeg;
