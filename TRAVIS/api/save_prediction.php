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

$type = $data['prediction_type'] ?? 'high-violation-period';
$result = $data['predicted_result'] ?? 'Prediction';
$confidence = (float)($data['confidence_score'] ?? 0);
$location = $data['location'] ?? null;
$violationType = $data['violation_type'] ?? null;
$frequency = (int)($data['frequency_count'] ?? 0);
$risk = $data['risk_level'] ?? 'medium';
$notes = $data['notes'] ?? null;

$stmt = $conn->prepare("INSERT INTO ml_predictions (prediction_type, predicted_result, confidence_score, location, violation_type, frequency_count, risk_level, notes) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param('ssdssiss', $type, $result, $confidence, $location, $violationType, $frequency, $risk, $notes);
$ok = $stmt->execute();
$id = $conn->insert_id;
$stmt->close();

echo json_encode(['success' => $ok, 'prediction_id' => $id]);
