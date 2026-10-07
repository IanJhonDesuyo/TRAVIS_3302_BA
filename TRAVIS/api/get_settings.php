<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

session_start();

if (($_SESSION['logged_in'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if (strcasecmp((string)($_SESSION['role'] ?? ''), 'Administrator') !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Administrator access required']);
    exit;
}

require_once __DIR__ . '/../Web_app/db_connect.php';

$defaults = [
    'congestion_light_max' => '5',
    'congestion_heavy_min' => '13',
    'alert_cooldown_seconds' => '300',
    'confidence_threshold' => '0.50',
    'enable_officer_detection' => '1',
    'enable_collision_detection' => '0',
    'notify_congestion' => '1',
    'notify_collision' => '1',
    'notify_officer_absence' => '1',
    'officer_absence_seconds' => '180',
    'enforcer_schedule_enabled' => '0',
    'enforcer_duty_start' => '06:00',
    'enforcer_duty_end' => '18:00',
    'enforcer_break_start' => '12:00',
    'enforcer_break_end' => '13:00',
];

try {
    $settings = $defaults;
    $placeholders = implode(',', array_fill(0, count($defaults), '?'));
    $statement = $pdo->prepare(
        "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ($placeholders)"
    );
    $statement->execute(array_keys($defaults));

    foreach ($statement->fetchAll() as $row) {
        $settings[(string)$row['setting_key']] = (string)$row['setting_value'];
    }

    $lightMax = (int)$settings['congestion_light_max'];
    $heavyMin = (int)$settings['congestion_heavy_min'];
    if ($heavyMin <= $lightMax) {
        $lightMax = (int)$defaults['congestion_light_max'];
        $heavyMin = (int)$defaults['congestion_heavy_min'];
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'congestion_light_max' => $lightMax,
            'congestion_heavy_min' => $heavyMin,
            'alert_cooldown_seconds' => (int)$settings['alert_cooldown_seconds'],
            'confidence_threshold' => (float)$settings['confidence_threshold'],
            'enable_officer_detection' => $settings['enable_officer_detection'] === '1',
            'enable_collision_detection' => $settings['enable_collision_detection'] === '1',
            'notify_congestion' => $settings['notify_congestion'] === '1',
            'notify_collision' => $settings['notify_collision'] === '1',
            'notify_officer_absence' => $settings['notify_officer_absence'] === '1',
            'officer_absence_seconds' => (int)$settings['officer_absence_seconds'],
            'enforcer_schedule_enabled' => $settings['enforcer_schedule_enabled'] === '1',
            'enforcer_duty_start' => $settings['enforcer_duty_start'],
            'enforcer_duty_end' => $settings['enforcer_duty_end'],
            'enforcer_break_start' => $settings['enforcer_break_start'],
            'enforcer_break_end' => $settings['enforcer_break_end'],
        ],
    ]);
} catch (Throwable $exception) {
    error_log('get_settings.php: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load settings']);
}
