<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Admin/db_connect.php';

$settings = [
    'congestion_light_max' => 5,
    'congestion_heavy_min' => 13,
    'alert_cooldown_seconds' => 300,
    'confidence_threshold' => 0.50,
    'enable_officer_detection' => 1,
    'enable_collision_detection' => 0,
];

$table = $conn->query("SHOW TABLES LIKE 'system_settings'");
if ($table && $table->num_rows > 0) {
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    while ($result && ($row = $result->fetch_assoc())) {
        $key = (string)$row['setting_key'];
        if (array_key_exists($key, $settings) && is_numeric($row['setting_value'])) {
            $settings[$key] = (float)$row['setting_value'];
        }
    }
}

if ($settings['congestion_heavy_min'] <= $settings['congestion_light_max']) {
    $settings['congestion_light_max'] = 5;
    $settings['congestion_heavy_min'] = 13;
}

echo json_encode(['success' => true, 'data' => $settings]);
