<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// For now, return default settings
// Kung may settings table, i-query dito

echo json_encode([
    'success' => true,
    'data' => [
        'congestion_trigger' => '1500',
        'alert_cooldown' => '15',
        'officer_absence' => '30',
        'collision_stationary' => '10',
        'flask_api_url' => 'http://localhost:5000',
        'rtsp_source' => 'rtsp://username:password@camera-ip:554/stream1',
        'session_timeout' => '30',
        'password_policy' => 'Strong (12+ chars)'
    ]
]);
?>