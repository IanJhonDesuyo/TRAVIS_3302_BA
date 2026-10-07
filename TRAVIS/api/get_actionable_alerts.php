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
    $latestStatusPath = __DIR__ . '/../Web_app/api/latest_status.json';
    $analysisStatusPath = __DIR__ . '/../Web_app/api/analysis_status.json';
    $latestStatus = is_file($latestStatusPath)
        ? json_decode((string)file_get_contents($latestStatusPath), true)
        : [];
    $analysisStatus = is_file($analysisStatusPath)
        ? json_decode((string)file_get_contents($analysisStatusPath), true)
        : [];

    $lastHeartbeat = (int)($latestStatus['updated_at_epoch'] ?? 0);
    $analysisState = strtolower(trim((string)($analysisStatus['analysis_status'] ?? 'stopped')));
    $monitoringLive = $lastHeartbeat > 0
        && (time() - $lastHeartbeat) <= 6
        && $analysisState !== 'stopped';

    $settings = ['alert_cooldown_seconds' => 300, 'notify_congestion' => 1, 'notify_collision' => 1, 'notify_officer_absence' => 1];
    $settingsStatement = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('alert_cooldown_seconds','notify_congestion','notify_collision','notify_officer_absence')");
    foreach ($settingsStatement->fetchAll() as $row) $settings[(string)$row['setting_key']] = (int)$row['setting_value'];
    $cooldown = max(60, min(86400, (int)$settings['alert_cooldown_seconds']));

    // Never surface queued or historical alerts after monitoring has stopped.
    if (!$monitoringLive) {
        echo json_encode([
            'success' => true,
            'monitoring_live' => false,
            'cooldown_seconds' => $cooldown,
            'data' => [],
        ]);
        exit;
    }

    $enabledTypes = [];
    if ((int)$settings['notify_congestion'] === 1) $enabledTypes[] = 'congestion';
    if ((int)$settings['notify_collision'] === 1) $enabledTypes[] = 'collision';
    if ((int)$settings['notify_officer_absence'] === 1) $enabledTypes[] = 'officer_absence';
    if (!$enabledTypes) {
        echo json_encode(['success' => true, 'monitoring_live' => true, 'cooldown_seconds' => $cooldown, 'data' => []]);
        exit;
    }
    $typePlaceholders = implode(',', array_fill(0, count($enabledTypes), '?'));

    $statement = $pdo->prepare("
        SELECT alert_id, alert_type, severity, message, status, generated_at
        FROM monitoring_alerts
        WHERE status = 'active'
          AND alert_type IN ($typePlaceholders)
          AND (
              alert_type = 'officer_absence'
              OR generated_at >= DATE_SUB(NOW(), INTERVAL 20 SECOND)
          )
        ORDER BY FIELD(severity, 'critical', 'warning', 'info'), generated_at DESC
        LIMIT 20
    ");
    $statement->execute($enabledTypes);

    echo json_encode([
        'success' => true,
        'monitoring_live' => true,
        'cooldown_seconds' => $cooldown,
        'data' => $statement->fetchAll(),
    ]);
} catch (Throwable $exception) {
    error_log('get_actionable_alerts.php: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load actionable alerts']);
}
