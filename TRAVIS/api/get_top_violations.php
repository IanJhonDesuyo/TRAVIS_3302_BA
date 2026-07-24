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

$sql = "SELECT violation_type, COUNT(*) as count 
        FROM violations 
        GROUP BY violation_type 
        ORDER BY count DESC 
        LIMIT 6";
$stmt = $pdo->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$labels = array_column($results, 'violation_type');
$data = array_column($results, 'count');

echo json_encode([
    'success' => true,
    'labels' => $labels,
    'data' => $data
]);
?>