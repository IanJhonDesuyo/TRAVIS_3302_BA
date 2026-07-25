<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
}
$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($role, ['administrator', 'treasurer', 'treasury personnel'], true)) {
    http_response_code(403); echo json_encode(['error' => 'Permission denied']); exit;
}
require_once __DIR__ . '/../Web_app/db_connect.php';
$input = json_decode(file_get_contents('php://input'), true);
$violationId = (int)($input['violation_id'] ?? 0);
$status = strtolower(trim((string)($input['status'] ?? '')));
if (!$violationId || $status !== 'cancelled') {
    http_response_code(400); echo json_encode(['error' => 'Invalid violation status request']); exit;
}
$stmt = $pdo->prepare("UPDATE violations SET status = 'cancelled', updated_at = NOW() WHERE violation_id = ? AND status IN ('pending', 'overdue')");
$stmt->execute([$violationId]);
if ($stmt->rowCount() !== 1) {
    http_response_code(409); echo json_encode(['error' => 'Only pending or overdue violations can be cancelled']); exit;
}
echo json_encode(['success' => true, 'message' => 'Violation cancelled']);
