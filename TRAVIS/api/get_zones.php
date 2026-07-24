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

// Get zones from cameras (or monitoring logs) – we'll use cameras as zones
$sql = "SELECT camera_name as name, status, 
        (SELECT vehicle_count FROM camera_monitoring_logs 
         WHERE camera_id = c.camera_id 
         ORDER BY recorded_at DESC LIMIT 1) as vehicles,
        (SELECT congestion_level FROM camera_monitoring_logs 
         WHERE camera_id = c.camera_id 
         ORDER BY recorded_at DESC LIMIT 1) as congestion
        FROM cameras c";
$stmt = $pdo->query($sql);
$zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert status and congestion to display values
foreach ($zones as &$zone) {
    $zone['status'] = $zone['status'] ?? 'offline';
    $zone['vehicles'] = (int)($zone['vehicles'] ?? 0);
    $zone['congestion'] = $zone['congestion'] ?? 'none';
    $zone['congestion'] = ucfirst($zone['congestion']);
}

echo json_encode([
    'success' => true,
    'data' => $zones
]);
?><?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../Web_app/db_connect.php';

// Get zones from cameras (or monitoring logs) – we'll use cameras as zones
$sql = "SELECT camera_name as name, status, 
        (SELECT vehicle_count FROM camera_monitoring_logs 
         WHERE camera_id = c.camera_id 
         ORDER BY recorded_at DESC LIMIT 1) as vehicles,
        (SELECT congestion_level FROM camera_monitoring_logs 
         WHERE camera_id = c.camera_id 
         ORDER BY recorded_at DESC LIMIT 1) as congestion
        FROM cameras c";
$stmt = $pdo->query($sql);
$zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Convert status and congestion to display values
foreach ($zones as &$zone) {
    $zone['status'] = $zone['status'] ?? 'offline';
    $zone['vehicles'] = (int)($zone['vehicles'] ?? 0);
    $zone['congestion'] = $zone['congestion'] ?? 'none';
    $zone['congestion'] = ucfirst($zone['congestion']);
}

echo json_encode([
    'success' => true,
    'data' => $zones
]);
?>