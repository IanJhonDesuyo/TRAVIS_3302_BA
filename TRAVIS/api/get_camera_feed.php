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
    $camera['recorded_at'] = null;
}

$latestStatusPath = __DIR__ . '/../Web_app/api/latest_status.json';
$analysisStatusPath = __DIR__ . '/../Web_app/api/analysis_status.json';
$latestStatus = is_file($latestStatusPath) ? json_decode((string)file_get_contents($latestStatusPath), true) : [];
$analysisStatus = is_file($analysisStatusPath) ? json_decode((string)file_get_contents($analysisStatusPath), true) : [];
$latestStatus = is_array($latestStatus) ? $latestStatus : [];
$analysisStatus = is_array($analysisStatus) ? $analysisStatus : [];
$frameEpoch = (int)($latestStatus['updated_at_epoch'] ?? 0);
$frameAge = $frameEpoch > 0 ? time() - $frameEpoch : PHP_INT_MAX;
$analysisState = strtolower((string)($analysisStatus['analysis_status'] ?? 'idle'));
$latestAiState = strtolower((string)($latestStatus['ai_status'] ?? 'offline'));
$isLive = $frameAge >= 0 && $frameAge <= 6
    && !in_array($analysisState, ['idle', 'stopped', 'completed', 'error'], true)
    && !in_array($latestAiState, ['offline', 'stopped', 'completed', 'error'], true);

$sourceType = (string)($analysisStatus['source_type'] ?? $latestStatus['source_type'] ?? '');
$sourceNames = [
    'tapo_camera' => 'Tapo Camera',
    'phone_camera' => 'Cellphone Camera',
    'uploaded_video' => 'Uploaded Video',
];
$camera['is_live'] = $isLive;
$camera['status'] = $isLive ? 'online' : 'offline';
$camera['analysis_status'] = $isLive ? 'Running' : ucfirst($analysisState ?: 'idle');
$camera['source_type'] = $sourceType;
if ($isLive && isset($sourceNames[$sourceType])) {
    $camera['camera_name'] = $sourceNames[$sourceType];
}

// Use fresh edge-worker values only while the stream is actually alive.
if ($isLive) {
    foreach (['vehicle_count', 'inbound_count', 'outbound_count', 'congestion_level', 'officer_presence', 'potential_collision', 'recorded_at'] as $field) {
        if (array_key_exists($field, $latestStatus)) $camera[$field] = $latestStatus[$field];
    }
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
