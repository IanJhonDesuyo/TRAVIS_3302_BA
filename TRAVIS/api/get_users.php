<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only Admin can view users
if ($_SESSION['role'] !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

require_once '../Web_app/db_connect.php';

$sql = "SELECT user_id, full_name, email, role, status, created_at, updated_at 
        FROM users ORDER BY created_at DESC";

$stmt = $pdo->query($sql);
$users = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'data' => $users
]);
?>