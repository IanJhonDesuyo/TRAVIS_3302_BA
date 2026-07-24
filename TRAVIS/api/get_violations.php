<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../Web_app/db_connect.php';

// Get query parameters
$status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// Build query
$sql = "SELECT * FROM violations WHERE 1=1";
$params = [];

if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

if (!empty($search)) {
    $sql .= " AND (ticket_number LIKE ? OR driver_name LIKE ? OR plate_number LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$violations = $stmt->fetchAll();

// Get total count for pagination
$countSql = "SELECT COUNT(*) as total FROM violations WHERE 1=1";
$countParams = [];
// ... (add same filters)
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($countParams);
$total = $countStmt->fetchColumn();

echo json_encode([
    'success' => true,
    'data' => $violations,
    'pagination' => [
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ]
]);
?>