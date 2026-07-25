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
if (!isset($_FILES['ticket']) || $_FILES['ticket']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); echo json_encode(['error' => 'A ticket image is required']); exit;
}
if ($_FILES['ticket']['size'] > 10 * 1024 * 1024) {
    http_response_code(413); echo json_encode(['error' => 'Ticket image must be 10 MB or smaller']); exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['ticket']['tmp_name']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415); echo json_encode(['error' => 'Use a JPEG, PNG, or WebP ticket image']); exit;
}

$projectRoot = dirname(__DIR__);
$python = $projectRoot . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
$script = $projectRoot . DIRECTORY_SEPARATOR . 'computer_vision' . DIRECTORY_SEPARATOR . 'ticket_ocr.py';
$command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($_FILES['ticket']['tmp_name']) . ' 2>&1';
$output = []; $exitCode = 0;
exec($command, $output, $exitCode);
$lastLine = $output ? end($output) : '';
$result = json_decode((string)$lastLine, true);
if (!is_array($result)) {
    http_response_code(500); echo json_encode(['error' => 'OCR service returned an invalid response']); exit;
}
if ($exitCode !== 0 || empty($result['success'])) {
    http_response_code(422); echo json_encode(['error' => $result['error'] ?? 'Ticket could not be read']); exit;
}
echo json_encode($result);
