<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, POST, OPTIONS');
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

if (empty($input['user_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'User ID is required']);
    exit;
}

// Build update query
$sql = "UPDATE users SET ";
$params = [];
$updates = [];

if (isset($input['full_name'])) {
    $updates[] = "full_name = ?";
    $params[] = $input['full_name'];
}
if (isset($input['email'])) {
    $updates[] = "email = ?";
    $params[] = $input['email'];
}
if (isset($input['role'])) {
    $updates[] = "role = ?";
    $params[] = $input['role'];
}
if (isset($input['status'])) {
    $updates[] = "status = ?";
    $params[] = $input['status'];
}

if (empty($updates)) {
    http_response_code(400);
    echo json_encode(['error' => 'No fields to update']);
    exit;
}

$sql .= implode(", ", $updates) . " WHERE user_id = ?";
$params[] = $input['user_id'];

$stmt = $pdo->prepare($sql);
$result = $stmt->execute($params);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update user']);
}
?>