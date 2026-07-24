<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SESSION['role'] !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

require_once '../Web_app/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

// Validate
if (empty($input['full_name']) || empty($input['email']) || empty($input['password']) || empty($input['role'])) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required']);
    exit;
}

// Check if email exists
$stmt = $pdo->prepare("SELECT email FROM users WHERE email = ?");
$stmt->execute([$input['email']]);
if ($stmt->fetch()) {
    http_response_code(400);
    echo json_encode(['error' => 'Email already exists']);
    exit;
}

// Hash password
$hashedPassword = password_hash($input['password'], PASSWORD_DEFAULT);

// Insert user
$sql = "INSERT INTO users (full_name, email, password, role, status) VALUES (?, ?, ?, ?, ?)";
$stmt = $pdo->prepare($sql);
$result = $stmt->execute([
    $input['full_name'],
    $input['email'],
    $hashedPassword,
    $input['role'],
    $input['status'] ?? 'active'
]);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'User created successfully',
        'user_id' => $pdo->lastInsertId()
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create user']);
}
?>