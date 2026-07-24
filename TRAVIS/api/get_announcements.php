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

$status = $_GET['status'] ?? '';

$sql = "SELECT a.*, u.full_name as created_by_name 
        FROM public_announcements a 
        LEFT JOIN users u ON a.created_by = u.user_id 
        WHERE 1=1";
$params = [];

if (!empty($status)) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$announcements = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'data' => $announcements
]);
?>