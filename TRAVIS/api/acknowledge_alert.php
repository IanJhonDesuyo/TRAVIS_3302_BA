<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../Web_app/auth/session.php';
travis_session_start();

if (!travis_is_authenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../Web_app/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);
$alertId = $input['alert_id'] ?? null;

if (!$alertId) {
    http_response_code(400);
    echo json_encode(['error' => 'Alert ID is required']);
    exit;
}

$sessionUser = travis_current_user();
$sql = "UPDATE monitoring_alerts SET 
        status = CASE WHEN status = 'active' THEN 'acknowledged' ELSE status END,
        acknowledged_by = ?, 
        acknowledged_at = NOW() 
        WHERE alert_id = ?
          AND status IN ('active', 'resolved')
          AND acknowledged_by IS NULL";

$stmt = $pdo->prepare($sql);
$result = $stmt->execute([(int)$sessionUser['id'], $alertId]);

if ($result && $stmt->rowCount() === 1) {
    echo json_encode([
        'success' => true,
        'message' => 'Alert acknowledged'
    ]);
} else {
    $check = $pdo->prepare('SELECT status, acknowledged_by FROM monitoring_alerts WHERE alert_id = ? LIMIT 1');
    $check->execute([$alertId]);
    $existing = $check->fetch();
    if ($existing && !empty($existing['acknowledged_by'])) {
        echo json_encode(['success' => true, 'message' => 'Alert was already acknowledged']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Alert no longer exists']);
    }
}
?>
