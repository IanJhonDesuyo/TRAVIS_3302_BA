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

// Get monthly violation counts for the current year
$year = date('Y');
$sql = "SELECT MONTH(violation_date) as month, COUNT(*) as count 
        FROM violations 
        WHERE YEAR(violation_date) = ? 
        GROUP BY MONTH(violation_date) 
        ORDER BY month";
$stmt = $pdo->prepare($sql);
$stmt->execute([$year]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare 12 months with zeros
$monthlyData = array_fill(0, 12, 0);
foreach ($results as $row) {
    $monthlyData[(int)$row['month'] - 1] = (int)$row['count'];
}

$labels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

echo json_encode([
    'success' => true,
    'labels' => $labels,
    'data' => $monthlyData
]);
?>