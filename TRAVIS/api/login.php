<?php
// ============================================================
// ENABLE ERROR REPORTING (for debugging)
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../Web_app/auth/session.php';
require_once __DIR__ . '/../Web_app/db_connect.php';
travis_session_start();

// ============================================================
// GET INPUT
// ============================================================
$input = json_decode(file_get_contents('php://input'), true);

$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required']);
    exit;
}

// ============================================================
// QUERY USER
// ============================================================
try {
    $stmt = $pdo->prepare("SELECT user_id, full_name, email, password, role, status FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log('TRAVIS login query failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed']);
    exit;
}

// ============================================================
// CHECK USER EXISTS
// ============================================================
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

// ============================================================
// CHECK PASSWORD (hashed credentials only)
// ============================================================
$passwordMatch = password_verify($password, (string)$user['password']);

if (!$passwordMatch) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit;
}

// ============================================================
// CHECK USER STATUS
// ============================================================
if ($user['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['error' => 'Account is ' . $user['status']]);
    exit;
}

// ============================================================
// LOGIN SUCCESS
// ============================================================
travis_login_user($user);

unset($user['password']);

echo json_encode([
    'success' => true,
    'user' => $user,
    'message' => 'Login successful'
]);
?>
