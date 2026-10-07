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
require_once __DIR__ . '/violation_rules.php';
require_once __DIR__ . '/../Web_app/db_connect.php';
$driver = trim((string)($_GET['driver_name'] ?? ''));
$license = trim((string)($_GET['license_number'] ?? ''));
$dateOfBirth = trim((string)($_GET['date_of_birth'] ?? ''));
$type = trim((string)($_GET['violation_type'] ?? ''));
if (!in_array($type, travis_violation_types(), true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Select a valid violation type first']);
    exit;
}
echo json_encode(['success' => true, 'data' => travis_offense_analysis($pdo, $driver, $type, $license, $dateOfBirth)]);
