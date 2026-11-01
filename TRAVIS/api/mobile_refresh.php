<?php
declare(strict_types=1);

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

$token = travis_mobile_request_token();
$mobileSession = travis_mobile_find_session($pdo, $token);
if (!$mobileSession || strcasecmp((string)$mobileSession['status'], 'active') !== 0) {
    if ($mobileSession) travis_mobile_revoke_session($pdo, $token);
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Mobile session expired or revoked']);
    exit;
}

// Rotate the renewable token whenever the app starts or recovers an expired
// PHP session. Create the replacement first so a transient insert failure does
// not destroy the user's last valid mobile session.
try {
    $renewed = travis_mobile_issue_session($pdo, (int)$mobileSession['user_id']);
    travis_mobile_revoke_session($pdo, $token);
    $touch = $pdo->prepare('UPDATE mobile_sessions SET last_used_at = NOW() WHERE token_hash = ?');
    $touch->execute([hash('sha256', $renewed['token'])]);
} catch (Throwable $exception) {
    error_log('TRAVIS mobile session refresh failed: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to renew mobile session']);
    exit;
}

travis_login_user([
    'user_id' => (int)$mobileSession['user_id'],
    'full_name' => (string)$mobileSession['full_name'],
    'email' => (string)$mobileSession['email'],
    'role' => (string)$mobileSession['role'],
]);
echo json_encode([
    'success' => true,
    'session_id' => session_id(),
    'refresh_token' => $renewed['token'],
    'refresh_expires_at' => $renewed['expires_at'],
    'user' => [
        'user_id' => (int)$mobileSession['user_id'],
        'full_name' => (string)$mobileSession['full_name'],
        'email' => (string)$mobileSession['email'],
        'role' => (string)$mobileSession['role'],
    ],
]);
