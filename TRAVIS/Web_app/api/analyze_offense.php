<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Admin/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$driverName = trim((string)($_GET['driver_name'] ?? ''));
$licenseNumber = trim((string)($_GET['license_number'] ?? ''));
$dateOfBirth = trim((string)($_GET['date_of_birth'] ?? ''));
$violationType = trim((string)($_GET['violation_type'] ?? ''));

if ($violationType === '' || !in_array($violationType, traffic_violation_types(), true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Select a valid violation type first.']);
    exit;
}

echo json_encode([
    'success' => true,
    'data' => traffic_offense_analysis($conn, $driverName, $violationType, $licenseNumber, $dateOfBirth),
]);
