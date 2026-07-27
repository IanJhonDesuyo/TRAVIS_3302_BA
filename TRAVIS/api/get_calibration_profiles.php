<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
session_start();
if (($_SESSION['logged_in'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'computer_vision' . DIRECTORY_SEPARATOR . 'calibration_profiles';
$profiles = [];
foreach (glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) continue;
    $profiles[] = ['file' => basename($path), 'name' => (string)($data['profile_name'] ?? pathinfo($path, PATHINFO_FILENAME))];
}
usort($profiles, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
echo json_encode(['success' => true, 'data' => $profiles]);
