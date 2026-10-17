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
$officers = [];
try {
    require_once __DIR__ . '/../Web_app/db_connect.php';
    $statement = $pdo->query("SELECT o.officer_id, o.first_name, o.last_name, o.badge_number, o.rank, z.zone_name FROM officers o LEFT JOIN officer_zones z ON z.zone_id = o.zone_id WHERE o.status = 'active' ORDER BY o.last_name, o.first_name");
    $officers = $statement ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $exception) {
    error_log('Unable to load mobile TMO officer options: ' . $exception->getMessage());
}
echo json_encode(['success' => true, 'data' => [
    'violation_types' => travis_violation_types(),
    'penalty_fees' => travis_penalty_fees(),
    'vehicle_types' => travis_vehicle_types(),
    'tmo_officers' => $officers,
    'penalty_schedules' => [
        'minor' => [200, 500, 1000],
        'coding_colorum' => [500, 1000, 2500],
        'nuisance_muffler' => [500, 750, 2500],
    ],
]]);
