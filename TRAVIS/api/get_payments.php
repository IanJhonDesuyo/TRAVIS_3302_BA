<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../Web_app/db_connect.php';

// Get payments with violation details
$sql = "SELECT p.*, v.ticket_number, v.driver_name, v.plate_number, v.violation_type 
        FROM payments p 
        JOIN violations v ON p.violation_id = v.violation_id 
        ORDER BY p.payment_date DESC 
        LIMIT 50";

$stmt = $pdo->query($sql);
$payments = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'data' => $payments
]);
?>