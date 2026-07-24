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

require_once '../Web_app/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['announcement_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Announcement ID is required']);
    exit;
}

$updates = [];
$params = [];

if (isset($input['title'])) {
    $updates[] = "title = ?";
    $params[] = $input['title'];
}
if (isset($input['content'])) {
    $updates[] = "content = ?";
    $params[] = $input['content'];
}
if (isset($input['announcement_type'])) {
    $updates[] = "announcement_type = ?";
    $params[] = $input['announcement_type'];
}
if (isset($input['status'])) {
    $updates[] = "status = ?";
    $params[] = $input['status'];
}
if (isset($input['publish_date'])) {
    $updates[] = "publish_date = ?";
    $params[] = $input['publish_date'];
}

if (empty($updates)) {
    http_response_code(400);
    echo json_encode(['error' => 'No fields to update']);
    exit;
}

$sql = "UPDATE public_announcements SET " . implode(", ", $updates) . " WHERE announcement_id = ?";
$params[] = $input['announcement_id'];

$stmt = $pdo->prepare($sql);
$result = $stmt->execute($params);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'Announcement updated successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update announcement']);
}
?>