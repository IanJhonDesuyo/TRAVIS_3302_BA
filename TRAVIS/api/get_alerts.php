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
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

$sql = "SELECT * FROM monitoring_alerts WHERE 1=1";
$params = [];

if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY generated_at DESC LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$alerts = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'data' => $alerts
]);
?>