<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

echo json_encode([
    'success' => true,
    'user' => [
        'user_id' => (int)$_SESSION['user_id'],
        'full_name' => (string)$_SESSION['full_name'],
        'email' => (string)$_SESSION['email'],
        'role' => (string)$_SESSION['role'],
    ],
]);
