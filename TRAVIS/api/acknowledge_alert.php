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
$alertId = $input['alert_id'] ?? null;

if (!$alertId) {
    http_response_code(400);
    echo json_encode(['error' => 'Alert ID is required']);
    exit;
}

$sql = "UPDATE monitoring_alerts SET 
        status = 'acknowledged', 
        acknowledged_by = ?, 
        acknowledged_at = NOW() 
        WHERE alert_id = ?";

$stmt = $pdo->prepare($sql);
$result = $stmt->execute([$_SESSION['user_id'], $alertId]);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'Alert acknowledged'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to acknowledge alert']);
}
?>