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

// Get hotspots from violation_hotspots table (if exists) or compute from violations
// For demo, compute from violations grouping by location
$sql = "SELECT violation_location as location, COUNT(*) as total 
        FROM violations 
        GROUP BY violation_location 
        ORDER BY total DESC 
        LIMIT 10";
$stmt = $pdo->query($sql);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate into high, medium, low based on counts
$high = [];
$medium = [];
$low = [];
if (count($results) > 0) {
    $max = $results[0]['total'];
    $third = $max / 3;
    foreach ($results as $row) {
        if ($row['total'] > $third * 2) {
            $high[] = $row;
        } elseif ($row['total'] > $third) {
            $medium[] = $row;
        } else {
            $low[] = $row;
        }
    }
}

echo json_encode([
    'success' => true,
    'high' => $high,
    'medium' => $medium,
    'low' => $low
]);
?>