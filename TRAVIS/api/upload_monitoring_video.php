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
if (strtolower(trim((string)($_SESSION['role'] ?? ''))) !== 'administrator') {
    http_response_code(403); echo json_encode(['error' => 'Only administrators can upload monitoring footage']); exit;
}
if (!isset($_FILES['video']) || $_FILES['video']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['video']['error'] ?? UPLOAD_ERR_NO_FILE;
    http_response_code(400); echo json_encode(['error' => $code === UPLOAD_ERR_INI_SIZE ? 'The video exceeds the server upload limit' : 'Select a video to upload']); exit;
}
if ($_FILES['video']['size'] > 500 * 1024 * 1024) {
    http_response_code(413); echo json_encode(['error' => 'Video must be 500 MB or smaller']); exit;
}
$extension = strtolower(pathinfo((string)$_FILES['video']['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ['mp4', 'avi', 'mov', 'mkv'], true)) {
    http_response_code(415); echo json_encode(['error' => 'Supported formats: MP4, AVI, MOV, and MKV']); exit;
}
$directory = dirname(__DIR__) . '/computer_vision/uploads/videos';
if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
    http_response_code(500); echo json_encode(['error' => 'Unable to create the video directory']); exit;
}
$target = $directory . '/test.mp4';
if (!move_uploaded_file($_FILES['video']['tmp_name'], $target)) {
    http_response_code(500); echo json_encode(['error' => 'Unable to save the uploaded video']); exit;
}
echo json_encode(['success' => true, 'message' => 'CCTV video uploaded successfully', 'filename' => $_FILES['video']['name'], 'size' => (int)$_FILES['video']['size']]);
