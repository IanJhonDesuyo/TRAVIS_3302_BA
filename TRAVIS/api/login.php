<?php
// ============================================================
// ENABLE ERROR REPORTING (for debugging)
// ============================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

// ============================================================
// DATABASE CONNECTION - DIRECT CONNECTION
// ============================================================
$host = 'localhost';
$dbname = 'travis';        // Palitan kung iba ang pangalan ng database
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database connection failed',
        'debug' => $e->getMessage()
    ]);
    exit;
}

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
    http_response_code(500);
    echo json_encode([
        'error' => 'Database query failed',
        'debug' => $e->getMessage()
    ]);
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
// CHECK PASSWORD (supports both hashed and plain text)
// ============================================================
$passwordMatch = false;

// Check if password is hashed (starts with $2y$)
if (strpos($user['password'], '$2y$') === 0) {
    $passwordMatch = password_verify($password, $user['password']);
} else {
    // Plain text password (for testing only!)
    $passwordMatch = ($user['password'] === $password);
}

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
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['email'] = $user['email'];
$_SESSION['role'] = $user['role'];
$_SESSION['logged_in'] = true;

unset($user['password']);

echo json_encode([
    'success' => true,
    'user' => $user,
    'message' => 'Login successful'
]);
?>