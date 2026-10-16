<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once __DIR__ . '/service_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required.']);
    exit;
}
$rawBody = (string)file_get_contents('php://input');
travis_require_service_request($rawBody);
$input = json_decode($rawBody, true);
$encoded = is_array($input) ? (string)($input['image_base64'] ?? '') : '';
if ($encoded === '' || strlen($encoded) > 3_000_000) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Invalid or oversized snapshot.']);
    exit;
}
$jpeg = base64_decode($encoded, true);
if ($jpeg === false || strlen($jpeg) < 100 || substr($jpeg, 0, 2) !== "\xFF\xD8") {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Snapshot must be a valid JPEG image.']);
    exit;
}
$directory = dirname(__DIR__) . '/storage/cache';
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    throw new RuntimeException('Unable to create the snapshot cache directory.');
}
$target = $directory . '/latest_cv_snapshot.jpg';
$temporary = $target . '.tmp';
if (file_put_contents($temporary, $jpeg, LOCK_EX) === false || !rename($temporary, $target)) {
    @unlink($temporary);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to store the snapshot.']);
    exit;
}
$statusPath = dirname(__DIR__) . '/Web_app/api/analysis_status.json';
$status = is_file($statusPath) ? json_decode((string)file_get_contents($statusPath), true) : [];
$status = is_array($status) ? $status : [];
$status['analysis_status'] = 'Running';
$status['ai_status'] = 'Running';
$status['message'] = 'Live processed frames are being received from the edge laptop.';
$status['source_type'] = $status['source_type'] ?? 'tapo_camera';
$status['stream_owner'] = 'shared';
$status['updated_at'] = date('Y-m-d H:i:s');
$status['updated_at_epoch'] = time();
file_put_contents($statusPath, json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
echo json_encode(['success' => true, 'bytes' => strlen($jpeg)]);

