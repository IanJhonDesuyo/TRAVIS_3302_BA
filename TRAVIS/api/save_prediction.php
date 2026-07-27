<?php
require_once __DIR__ . '/../Web_app/Admin/db_connect.php';
require_once __DIR__ . '/service_auth.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required']);
    exit;
}

$rawBody = (string)file_get_contents('php://input');
travis_require_service_request($rawBody);
$data = json_decode($rawBody, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A valid JSON request body is required']);
    exit;
}

$type = $data['prediction_type'] ?? 'high-violation-period';
$result = $data['predicted_result'] ?? 'Prediction';
$confidence = (float)($data['confidence_score'] ?? 0);
$location = $data['location'] ?? null;
$violationType = $data['violation_type'] ?? null;
$frequency = (int)($data['frequency_count'] ?? 0);
$risk = $data['risk_level'] ?? 'medium';
$notes = $data['notes'] ?? null;

if (!in_array($type, ['season-based', 'time-based', 'high-violation-period', 'other'], true)
    || $result === '' || mb_strlen((string)$result) > 255
    || !is_finite($confidence) || $confidence < 0 || $confidence > 1
    || $frequency < 0
    || !in_array($risk, ['low', 'medium', 'high', 'critical'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid prediction values']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO ml_predictions (prediction_type, predicted_result, confidence_score, location, violation_type, frequency_count, risk_level, notes) VALUES (?,?,?,?,?,?,?,?)");
$stmt->bind_param('ssdssiss', $type, $result, $confidence, $location, $violationType, $frequency, $risk, $notes);
$ok = $stmt->execute();
$id = $conn->insert_id;
$stmt->close();

echo json_encode(['success' => $ok, 'prediction_id' => $id]);
