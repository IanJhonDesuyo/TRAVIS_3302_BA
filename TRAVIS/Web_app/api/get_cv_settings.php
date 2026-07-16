<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../Admin/db_connect.php';

$settings = [
    'congestion_light_max' => 5,
    'congestion_heavy_min' => 13,
];

$table = $conn->query("SHOW TABLES LIKE 'system_settings'");
if ($table && $table->num_rows > 0) {
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('congestion_light_max','congestion_heavy_min')");
    while ($result && ($row = $result->fetch_assoc())) {
        $settings[(string)$row['setting_key']] = (int)$row['setting_value'];
    }
}

if ($settings['congestion_heavy_min'] <= $settings['congestion_light_max']) {
    $settings = ['congestion_light_max' => 5, 'congestion_heavy_min' => 13];
}

echo json_encode(['success' => true, 'data' => $settings]);
