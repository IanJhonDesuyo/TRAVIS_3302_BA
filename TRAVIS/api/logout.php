<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-TRAVIS-Mobile-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../Web_app/auth/session.php';
require_once __DIR__ . '/../Web_app/db_connect.php';
require_once __DIR__ . '/mobile_session.php';
travis_mobile_revoke_session($pdo, travis_mobile_request_token());
travis_logout_user();

echo json_encode([
    'success' => true,
    'message' => 'Logged out successfully'
]);
?>
