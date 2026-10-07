<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../Web_app/auth/session.php';
travis_session_start();
if (!travis_is_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required.']);
    exit;
}

require_once __DIR__ . '/../Web_app/db_connect.php';
$input = json_decode((string)file_get_contents('php://input'), true);
$alertId = max(0, (int)($input['alert_id'] ?? 0));
if ($alertId === 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid alert is required.']);
    exit;
}

$user = travis_current_user();
$statement = $pdo->prepare("UPDATE monitoring_alerts SET status='resolved', acknowledged_by=COALESCE(acknowledged_by, ?), acknowledged_at=COALESCE(acknowledged_at, NOW()) WHERE alert_id=? AND status IN ('active','acknowledged')");
$statement->execute([(int)$user['id'], $alertId]);

if ($statement->rowCount() !== 1) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'This alert is already resolved or unavailable.']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Alert resolved successfully.']);
