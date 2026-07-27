<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['success' => false, 'error' => 'Method not allowed']); exit; }

session_start();
if (($_SESSION['logged_in'] ?? false) !== true || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/violation_rules.php';
require_once __DIR__ . '/../Web_app/db_connect.php';

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid JSON request body is required']);
    exit;
}
if (!array_key_exists('has_no_license', $input) || !is_bool($input['has_no_license'])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'has_no_license must be true or false']);
    exit;
}

$driver = trim((string)($input['driver_name'] ?? ''));
$hasNoLicense = $input['has_no_license'];
$license = $hasNoLicense ? 'NO LICENSE' : strtoupper(trim((string)($input['license_number'] ?? '')));
$plate = strtoupper(trim((string)($input['plate_number'] ?? '')));
$vehicle = trim((string)($input['vehicle_type'] ?? ''));
$type = trim((string)($input['violation_type'] ?? ''));
$location = trim((string)($input['location'] ?? ''));
$penalty = filter_var($input['penalty_amount'] ?? null, FILTER_VALIDATE_FLOAT);
$inputMethod = ($input['input_method'] ?? 'manual') === 'ocr' ? 'ocr' : 'manual';

if ($driver === '' || mb_strlen($driver) > 150 || $plate === '' || mb_strlen($plate) > 50 || $location === '' || mb_strlen($location) > 255) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Driver, plate number, and location are required and must fit their allowed lengths']);
    exit;
}
if (!$hasNoLicense && ($license === '' || mb_strlen($license) > 80)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'License number is required unless Driver has no license is selected']);
    exit;
}
if (!in_array($vehicle, travis_vehicle_types(), true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Select a valid vehicle type']);
    exit;
}
if (!in_array($type, travis_violation_types(), true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Select a valid violation from the official list']);
    exit;
}
if ($penalty === false || !in_array((float)$penalty, array_map('floatval', travis_penalty_fees()), true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Select a valid penalty fee from the official list']);
    exit;
}

try {
    $offense = travis_offense_analysis($pdo, $license, $plate, $type);
    $pdo->beginTransaction();
    do {
        $ticket = 'TRV-' . date('Ymd') . '-' . str_pad((string)random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        $check = $pdo->prepare('SELECT 1 FROM violations WHERE ticket_number = ?');
        $check->execute([$ticket]);
    } while ($check->fetchColumn());

    $statement = $pdo->prepare("INSERT INTO violations (
        ticket_number, driver_name, license_number, has_no_license, plate_number, vehicle_type,
        violation_type, violation_location, violation_date, violation_time, penalty_amount,
        encoded_by, input_method, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, 'pending')");
    $statement->execute([$ticket, $driver, $license, $hasNoLicense ? 1 : 0, $plate, $vehicle, $type, $location, $penalty, (int)$_SESSION['user_id'], $inputMethod]);
    $id = (int)$pdo->lastInsertId();
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Violation added successfully', 'violation_id' => $id, 'ticket_number' => $ticket, 'offense_analysis' => $offense]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('add_violations.php: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to add violation']);
}
