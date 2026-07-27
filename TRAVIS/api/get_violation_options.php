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
echo json_encode(['success' => true, 'data' => [
    'violation_types' => travis_violation_types(),
    'penalty_fees' => travis_penalty_fees(),
    'vehicle_types' => travis_vehicle_types(),
]]);
