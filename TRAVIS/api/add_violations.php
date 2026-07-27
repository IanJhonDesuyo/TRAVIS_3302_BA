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
$location = trim((string)($input['location'] ?? ''));
$inputMethod = ($input['input_method'] ?? 'manual') === 'ocr' ? 'ocr' : 'manual';

$submittedItems = isset($input['violations']) && is_array($input['violations'])
    ? $input['violations']
    : [[
        'violation_type' => $input['violation_type'] ?? '',
        'penalty_amount' => $input['penalty_amount'] ?? null,
    ]];
$items = [];
foreach ($submittedItems as $submittedItem) {
    if (!is_array($submittedItem)) continue;
    $itemType = trim((string)($submittedItem['violation_type'] ?? $submittedItem['type'] ?? ''));
    $itemPenalty = filter_var($submittedItem['penalty_amount'] ?? null, FILTER_VALIDATE_FLOAT);
    $confidence = isset($submittedItem['ocr_confidence']) && is_numeric($submittedItem['ocr_confidence'])
        ? max(0.0, min(1.0, (float)$submittedItem['ocr_confidence'])) : null;
    if (!in_array($itemType, travis_violation_types(), true)) {
        http_response_code(422); echo json_encode(['success' => false, 'error' => 'Every violation must come from the official list']); exit;
    }
    if ($itemPenalty === false || !in_array((float)$itemPenalty, array_map('floatval', travis_penalty_fees()), true)) {
        http_response_code(422); echo json_encode(['success' => false, 'error' => "Select a valid penalty for {$itemType}"]); exit;
    }
    $items[$itemType] = ['violation_type' => $itemType, 'penalty_amount' => (float)$itemPenalty, 'ocr_confidence' => $confidence];
}
if (!$items) {
    http_response_code(422); echo json_encode(['success' => false, 'error' => 'Select at least one violation']); exit;
}
$items = array_values($items);
$types = array_column($items, 'violation_type');
$typeSummary = implode(', ', $types);
$penalty = array_sum(array_column($items, 'penalty_amount'));

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
try {
    $offenses = array_map(static fn(array $item): array => [
        'violation_type' => $item['violation_type'],
        'analysis' => travis_offense_analysis($pdo, $license, $plate, $item['violation_type']),
    ], $items);
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
    $statement->execute([$ticket, $driver, $license, $hasNoLicense ? 1 : 0, $plate, $vehicle, $typeSummary, $location, $penalty, (int)$_SESSION['user_id'], $inputMethod]);
    $id = (int)$pdo->lastInsertId();
    $itemStatement = $pdo->prepare('INSERT INTO violation_items (violation_id, violation_type, penalty_amount, ocr_confidence) VALUES (?, ?, ?, ?)');
    foreach ($items as $item) {
        $itemStatement->execute([$id, $item['violation_type'], $item['penalty_amount'], $item['ocr_confidence']]);
    }
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => count($items) . ' violation(s) added successfully', 'violation_id' => $id, 'ticket_number' => $ticket, 'violation_items' => $items, 'offense_analyses' => $offenses, 'offense_analysis' => $offenses[0]['analysis']]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('add_violations.php: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to add violation']);
}
