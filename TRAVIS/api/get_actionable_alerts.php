<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../Web_app/auth/session.php';
travis_session_start();
if (!travis_is_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../Web_app/db_connect.php';

try {
    $cooldownStatement = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'alert_cooldown_seconds' LIMIT 1");
    $cooldownStatement->execute();
    $cooldown = max(60, min(86400, (int)($cooldownStatement->fetchColumn() ?: 300)));

    $statement = $pdo->query("
        SELECT alert_id, alert_type, severity, message, status, generated_at
        FROM monitoring_alerts
        WHERE status = 'active'
          AND (severity = 'critical' OR alert_type = 'officer_absence')
        ORDER BY FIELD(severity, 'critical', 'warning', 'info'), generated_at ASC
        LIMIT 20
    ");

    echo json_encode([
        'success' => true,
        'cooldown_seconds' => $cooldown,
        'data' => $statement->fetchAll(),
    ]);
} catch (Throwable $exception) {
    error_log('get_actionable_alerts.php: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load actionable alerts']);
}
