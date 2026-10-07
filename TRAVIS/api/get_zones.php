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

$latestStatusPath = __DIR__ . '/../Web_app/api/latest_status.json';
$analysisStatusPath = __DIR__ . '/../Web_app/api/analysis_status.json';
$latestStatus = is_file($latestStatusPath) ? json_decode((string)file_get_contents($latestStatusPath), true) : [];
$analysisStatus = is_file($analysisStatusPath) ? json_decode((string)file_get_contents($analysisStatusPath), true) : [];
$latestStatus = is_array($latestStatus) ? $latestStatus : [];
$analysisStatus = is_array($analysisStatus) ? $analysisStatus : [];
$frameEpoch = (int)($latestStatus['updated_at_epoch'] ?? 0);
$frameAge = $frameEpoch > 0 ? time() - $frameEpoch : PHP_INT_MAX;
$analysisState = strtolower((string)($analysisStatus['analysis_status'] ?? 'idle'));
$cameraLive = $frameAge >= 0 && $frameAge <= 6
    && !in_array($analysisState, ['idle', 'stopped', 'completed', 'error'], true);

// Convert status and congestion to display values
foreach ($zones as &$zone) {
    $zone['status'] = $cameraLive ? ($zone['status'] ?? 'online') : 'offline';
    $zone['vehicles'] = (int)($zone['vehicles'] ?? 0);
    $zone['congestion'] = $zone['congestion'] ?? 'none';
    $zone['congestion'] = ucfirst($zone['congestion']);
}

echo json_encode([
    'success' => true,
    'data' => $zones
]);
?>
