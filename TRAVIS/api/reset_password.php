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

if (empty($input['user_id']) || empty($input['new_password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID and new password are required']);
    exit;
}

$hashedPassword = password_hash($input['new_password'], PASSWORD_DEFAULT);

$stmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
$result = $stmt->execute([$hashedPassword, $input['user_id']]);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'Password reset successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to reset password']);
}
?>