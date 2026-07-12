<?php
require_once __DIR__ . '/../db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) $data = $_POST;

$cameraId = (int)($data['camera_id'] ?? 1);
$vehicleCount = (int)($data['vehicle_count'] ?? 0);
$inboundCount = (int)($data['inbound_count'] ?? 0);
$outboundCount = (int)($data['outbound_count'] ?? 0);
$congestion = $data['congestion_level'] ?? 'none';
$officer = $data['officer_presence'] ?? 'unknown';
$collision = $data['potential_collision'] ?? 'none';
$notes = $data['incident_notes'] ?? null;
$alertGenerated = (int)($data['alert_generated'] ?? 0);

$stmt = $conn->prepare("INSERT INTO camera_monitoring_logs (camera_id, vehicle_count, inbound_count, outbound_count, congestion_level, officer_presence, potential_collision, incident_notes, alert_generated) VALUES (?,?,?,?,?,?,?,?,?)");
$stmt->bind_param('iiiissssi', $cameraId, $vehicleCount, $inboundCount, $outboundCount, $congestion, $officer, $collision, $notes, $alertGenerated);
$ok = $stmt->execute();
$logId = $conn->insert_id;
$stmt->close();

if ($ok && ($congestion === 'heavy' || $congestion === 'severe' || $collision === 'possible' || $collision === 'confirmed')) {
    $type = $collision !== 'none' ? 'collision' : 'congestion';
    $severity = ($congestion === 'severe' || $collision === 'confirmed') ? 'critical' : 'warning';
    $message = $type === 'collision' ? 'Potential collision detected by computer vision.' : 'Traffic congestion detected by computer vision.';
    $stmt = $conn->prepare("INSERT INTO monitoring_alerts (camera_log_id, alert_type, severity, message) VALUES (?,?,?,?)");
    $stmt->bind_param('isss', $logId, $type, $severity, $message);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(['success' => $ok, 'log_id' => $logId]);
