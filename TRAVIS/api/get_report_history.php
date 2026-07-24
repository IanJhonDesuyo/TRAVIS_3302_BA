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

$sql = "SELECT r.*, u.full_name as generated_by_name 
        FROM reports r 
        LEFT JOIN users u ON r.generated_by = u.user_id 
        ORDER BY r.generated_at DESC 
        LIMIT 20";

$stmt = $pdo->query($sql);
$history = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'data' => $history
]);
?>