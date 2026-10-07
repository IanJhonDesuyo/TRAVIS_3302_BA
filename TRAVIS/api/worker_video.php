<?php
declare(strict_types=1);

require_once __DIR__ . '/service_auth.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$rawBody = (string)file_get_contents('php://input');
travis_require_service_request($rawBody);
$input = json_decode($rawBody, true);
if (!is_array($input) || ($input['action'] ?? '') !== 'download_latest') {
    http_response_code(422);
    exit;
}
try {
    $video = dirname(__DIR__) . '/computer_vision/uploads/videos/test.mp4';
    clearstatcache(true, $video);
    if (!is_file($video)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No hosted CCTV video is available. Upload the video again.']);
        exit;
    }
    if (!is_readable($video)) {
        throw new RuntimeException('The hosted CCTV video is not readable. Check its file permissions.');
    }
    $size = filesize($video);
    if ($size === false || $size < 1) {
        throw new RuntimeException('The hosted CCTV video is empty or its size cannot be determined.');
    }
    $handle = fopen($video, 'rb');
    if ($handle === false) {
        throw new RuntimeException('The hosted CCTV video could not be opened.');
    }
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: application/octet-stream');
    header('Content-Length: ' . $size);
    header('Content-Disposition: attachment; filename="test.mp4"');
    header('Cache-Control: no-store');
    while (!feof($handle)) {
        $chunk = fread($handle, 1024 * 1024);
        if ($chunk === false) throw new RuntimeException('The hosted CCTV video could not be read completely.');
        echo $chunk;
        flush();
    }
    fclose($handle);
} catch (Throwable $error) {
    error_log('TRAVIS hosted video download: ' . $error->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
}
