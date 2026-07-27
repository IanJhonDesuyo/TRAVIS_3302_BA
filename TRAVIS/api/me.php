<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../Web_app/auth/session.php';
travis_session_start();

if (!travis_is_authenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$sessionUser = travis_current_user();
echo json_encode([
    'success' => true,
    'user' => [
        'user_id' => (int)$sessionUser['id'],
        'full_name' => (string)$sessionUser['name'],
        'email' => (string)$sessionUser['email'],
        'role' => (string)$sessionUser['role'],
    ],
]);
