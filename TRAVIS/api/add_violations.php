<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../Web_app/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required = ['driver_name', 'license_number', 'plate_number', 'vehicle_type', 'violation_type', 'location', 'penalty_amount'];
foreach ($required as $field) {
    if (empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Field '$field' is required"]);
        exit;
    }
}

// Generate ticket number
$ticketNumber = 'TRV-' . date('Ymd') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);

// Insert violation
$sql = "INSERT INTO violations (
    ticket_number, driver_name, license_number, plate_number, 
    vehicle_type, violation_type, violation_location, 
    violation_date, violation_time, penalty_amount, 
    encoded_by, status
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";

$stmt = $pdo->prepare($sql);
$result = $stmt->execute([
    $ticketNumber,
    $input['driver_name'],
    $input['license_number'],
    $input['plate_number'],
    $input['vehicle_type'],
    $input['violation_type'],
    $input['location'],
    date('Y-m-d'),
    date('H:i:s'),
    $input['penalty_amount'],
    $_SESSION['user_id']
]);

if ($result) {
    $violationId = $pdo->lastInsertId();
    echo json_encode([
        'success' => true,
        'message' => 'Violation added successfully',
        'violation_id' => $violationId,
        'ticket_number' => $ticketNumber
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to add violation']);
}
?>