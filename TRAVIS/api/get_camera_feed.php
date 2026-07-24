<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); // <-- TAMA: http_response_code() hindi http_code()
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../Web_app/db_connect.php';

// Kunin ang pinaka-aktibong camera (may latest monitoring log)
$sql = "SELECT c.camera_id, c.camera_name, c.location, m.vehicle_count, m.inbound_count, m.outbound_count, 
               m.congestion_level, m.officer_presence, m.potential_collision, m.recorded_at, c.status
        FROM cameras c
        LEFT JOIN camera_monitoring_logs m ON c.camera_id = m.camera_id
        WHERE m.recorded_at = (
            SELECT MAX(recorded_at) FROM camera_monitoring_logs WHERE camera_id = c.camera_id
        )
        ORDER BY m.recorded_at DESC
        LIMIT 1";

$stmt = $pdo->query($sql);
$camera = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$camera) {
    // Kung walang logs, gumamit ng default camera data
    $sql = "SELECT camera_id, camera_name, location, status FROM cameras LIMIT 1";
    $stmt = $pdo->query($sql);
    $camera = $stmt->fetch(PDO::FETCH_ASSOC);
    $camera['vehicle_count'] = 0;
    $camera['inbound_count'] = 0;
    $camera['outbound_count'] = 0;
    $camera['congestion_level'] = 'none';
    $camera['officer_presence'] = 'unknown';
    $camera['potential_collision'] = 'none';
    $camera['recorded_at'] = date('Y-m-d H:i:s');
}

// I-convert ang congestion level para sa display
$congestionMap = [
    'none' => 'None',
    'low' => 'Low',
    'moderate' => 'Moderate',
    'heavy' => 'Heavy',
    'severe' => 'Severe'
];
$camera['congestion_level_display'] = $congestionMap[$camera['congestion_level']] ?? $camera['congestion_level'];

// Kung may snapshot URL na nakaimbak sa settings, gamitin ito
// Para sa demo, gumamit ng placeholder
$camera['snapshot_url'] = null;

echo json_encode([
    'success' => true,
    'data' => $camera
]);
?>