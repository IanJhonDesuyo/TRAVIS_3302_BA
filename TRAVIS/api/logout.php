<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../Web_app/auth/session.php';
travis_logout_user();

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);
?>
