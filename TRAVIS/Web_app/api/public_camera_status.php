<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../Admin/db_connect.php';

try {
    $result = $conn->query("SELECT COUNT(*) AS active_count FROM cameras WHERE status = 'online'");
    $row = $result ? $result->fetch_assoc() : null;
    echo json_encode([
        'success' => true,
        'active_camera_count' => max(0, (int)($row['active_count'] ?? 0)),
    ]);
} catch (Throwable $exception) {
    error_log('public_camera_status.php: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'active_camera_count' => 0]);
}
