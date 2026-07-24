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

require_once '../Web_app/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

// Validate
if (empty($input['title']) || empty($input['content']) || empty($input['announcement_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Title, content, and type are required']);
    exit;
}

$sql = "INSERT INTO public_announcements (
    title, content, announcement_type, publish_date, status, created_by
) VALUES (?, ?, ?, ?, ?, ?)";

$stmt = $pdo->prepare($sql);
$result = $stmt->execute([
    $input['title'],
    $input['content'],
    $input['announcement_type'],
    $input['publish_date'] ?? date('Y-m-d H:i:s'),
    $input['status'] ?? 'draft',
    $_SESSION['user_id']
]);

if ($result) {
    echo json_encode([
        'success' => true,
        'message' => 'Announcement created successfully',
        'announcement_id' => $pdo->lastInsertId()
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create announcement']);
}
?>