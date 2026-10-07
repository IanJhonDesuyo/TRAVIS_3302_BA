<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../auth/session.php';
travis_session_start();

if (!travis_is_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST or DELETE method required.']);
    exit;
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$jsonPayload = str_contains($contentType, 'application/json')
    ? json_decode((string)file_get_contents('php://input'), true)
    : [];
$jsonPayload = is_array($jsonPayload) ? $jsonPayload : [];
$deletePayload = $_SERVER['REQUEST_METHOD'] === 'DELETE' ? $jsonPayload : [];
$requestCsrf = $_SERVER['REQUEST_METHOD'] === 'DELETE'
    ? (string)($deletePayload['csrf_token'] ?? '')
    : (string)($jsonPayload['csrf_token'] ?? $_POST['csrf_token'] ?? '');
if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), $requestCsrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid session token. Refresh and try again.']);
    exit;
}

$analysisStatusFile = __DIR__ . DIRECTORY_SEPARATOR . 'analysis_status.json';
$latestStatusFile = __DIR__ . DIRECTORY_SEPARATOR . 'latest_status.json';
$analysisStatus = is_file($analysisStatusFile)
    ? json_decode((string)file_get_contents($analysisStatusFile), true)
    : [];
$latestStatus = is_file($latestStatusFile)
    ? json_decode((string)file_get_contents($latestStatusFile), true)
    : [];
$analysisState = strtolower((string)($analysisStatus['analysis_status'] ?? ''));
$activeSourceType = strtolower((string)($analysisStatus['source_type'] ?? ''));
$analysisUpdated = (int)($analysisStatus['updated_at_epoch'] ?? 0);
$latestUpdated = (int)($latestStatus['updated_at_epoch'] ?? 0);
$isClearRequest = $_SERVER['REQUEST_METHOD'] === 'DELETE' || ($jsonPayload['action'] ?? '') === 'clear';
$analysisIsActive = ($analysisState !== 'stopped' && $latestUpdated > 0 && time() - $latestUpdated <= 6)
    || ($analysisState === 'starting' && $analysisUpdated > 0 && time() - $analysisUpdated <= 30);
// A Tapo worker does not use the uploaded-video file, so footage may safely be
// staged for later without stopping the shared live-camera analysis.
$canStageBesideLiveCamera = $analysisIsActive && in_array($activeSourceType, ['tapo_camera', 'tapo'], true);
if ($analysisIsActive && !$isClearRequest && !$canStageBesideLiveCamera) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Stop the active analysis before replacing its video.']);
    exit;
}

$projectRoot = dirname(__DIR__, 2);
$uploadDirectory = $projectRoot . DIRECTORY_SEPARATOR . 'computer_vision' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'videos';
$target = $uploadDirectory . DIRECTORY_SEPARATOR . 'test.mp4';

if ($isClearRequest) {
    if (is_file($target) && !@unlink($target)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'The uploaded footage could not be cleared.']);
        exit;
    }

    file_put_contents($analysisStatusFile, json_encode([
        'analysis_status' => 'Idle',
        'ai_status' => 'Idle',
        'message' => 'Upload CCTV footage before starting analysis.',
        'source_type' => 'uploaded_video',
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_at_epoch' => time(),
    ], JSON_PRETTY_PRINT), LOCK_EX);

    echo json_encode([
        'success' => true,
        'message' => 'Viewing stopped and the uploaded footage was cleared.',
    ]);
    exit;
}

if (($_POST['action'] ?? '') === 'upload_chunk') {
    if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'The video upload directory is unavailable.']);
        exit;
    }

    $uploadId = (string)($_POST['upload_id'] ?? '');
    $chunkIndex = (int)($_POST['chunk_index'] ?? -1);
    $totalChunks = (int)($_POST['total_chunks'] ?? 0);
    $totalSize = (int)($_POST['total_size'] ?? 0);
    $originalName = (string)($_POST['file_name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $chunk = $_FILES['video_chunk'] ?? null;
    if (!preg_match('/\A[a-f0-9]{32}\z/', $uploadId)
        || $chunkIndex < 0 || $totalChunks < 1 || $chunkIndex >= $totalChunks
        || $totalSize < 1 || $totalSize > 500 * 1024 * 1024
        || !in_array($extension, ['mp4', 'avi', 'mov', 'mkv'], true)
        || !is_array($chunk) || (int)($chunk['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'The video upload chunk is invalid.']);
        exit;
    }

    $chunkPath = $uploadDirectory . DIRECTORY_SEPARATOR . '.chunk-' . $uploadId . '.tmp';
    if ($chunkIndex === 0) @unlink($chunkPath);
    $input = fopen((string)$chunk['tmp_name'], 'rb');
    $output = fopen($chunkPath, $chunkIndex === 0 ? 'wb' : 'ab');
    if ($input === false || $output === false || !flock($output, LOCK_EX)) {
        if (is_resource($input)) fclose($input);
        if (is_resource($output)) fclose($output);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'The server could not assemble the uploaded video.']);
        exit;
    }
    stream_copy_to_stream($input, $output);
    fflush($output);
    flock($output, LOCK_UN);
    fclose($input);
    fclose($output);

    if ($chunkIndex + 1 < $totalChunks) {
        echo json_encode(['success' => true, 'complete' => false, 'received_chunk' => $chunkIndex]);
        exit;
    }

    clearstatcache(true, $chunkPath);
    if (!is_file($chunkPath) || filesize($chunkPath) !== $totalSize) {
        @unlink($chunkPath);
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'The assembled video size does not match the selected file.']);
        exit;
    }

    $previousTarget = $uploadDirectory . DIRECTORY_SEPARATOR . '.previous-' . bin2hex(random_bytes(8)) . '.tmp';
    $hadPreviousVideo = is_file($target);
    if ($hadPreviousVideo && !@rename($target, $previousTarget)) {
        @unlink($chunkPath);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'The existing footage is still in use. Stop analysis and try again.']);
        exit;
    }
    if (!@rename($chunkPath, $target)) {
        if ($hadPreviousVideo) @rename($previousTarget, $target);
        @unlink($chunkPath);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'The server could not activate the newly uploaded footage.']);
        exit;
    }
    if ($hadPreviousVideo) @unlink($previousTarget);

    if (!$canStageBesideLiveCamera) {
        file_put_contents($analysisStatusFile, json_encode([
            'analysis_status' => 'Idle', 'ai_status' => 'Idle',
            'message' => 'Video ready for analysis.', 'source_type' => 'uploaded_video',
            'updated_at' => date('Y-m-d H:i:s'), 'updated_at_epoch' => time(),
        ], JSON_PRETTY_PRINT), LOCK_EX);
    }
    echo json_encode([
        'success' => true, 'complete' => true,
        'message' => $canStageBesideLiveCamera
            ? 'CCTV footage uploaded and prepared. The shared Tapo analysis remains active.'
            : ($hadPreviousVideo
                ? 'Previous CCTV footage replaced successfully. The new video is ready for analysis.'
                : 'CCTV footage uploaded successfully and is ready for analysis.'),
        'file_name' => $originalName, 'file_size' => $totalSize,
    ]);
    exit;
}

$video = $_FILES['cctv_video'] ?? null;
if (!is_array($video) || (int)($video['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $error = (int)($video['error'] ?? UPLOAD_ERR_NO_FILE);
    $message = $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE
        ? 'The selected video exceeds the server upload limit.'
        : 'Select a video file and try again.';
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$maximumBytes = 500 * 1024 * 1024;
$extension = strtolower(pathinfo((string)($video['name'] ?? ''), PATHINFO_EXTENSION));
if (!in_array($extension, ['mp4', 'avi', 'mov', 'mkv'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Upload an MP4, AVI, MOV, or MKV video.']);
    exit;
}
if ((int)($video['size'] ?? 0) <= 0 || (int)$video['size'] > $maximumBytes) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The video must be between 1 byte and 500MB.']);
    exit;
}

if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The video upload directory is unavailable.']);
    exit;
}

$stagedTarget = $uploadDirectory . DIRECTORY_SEPARATOR . '.upload-' . bin2hex(random_bytes(8)) . '.tmp';
$previousTarget = $uploadDirectory . DIRECTORY_SEPARATOR . '.previous-' . bin2hex(random_bytes(8)) . '.tmp';

// Stage the complete upload first so an interrupted request never destroys
// the currently usable footage.
if (!move_uploaded_file((string)$video['tmp_name'], $stagedTarget)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The server could not save the uploaded video.']);
    exit;
}

$hadPreviousVideo = is_file($target);
if ($hadPreviousVideo && !@rename($target, $previousTarget)) {
    @unlink($stagedTarget);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'The existing footage is still in use. Stop the analysis and try replacing it again.',
    ]);
    exit;
}

if (!@rename($stagedTarget, $target)) {
    if ($hadPreviousVideo) @rename($previousTarget, $target);
    @unlink($stagedTarget);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The server could not replace the existing footage.']);
    exit;
}

if ($hadPreviousVideo) @unlink($previousTarget);

if (!$canStageBesideLiveCamera) {
    file_put_contents($analysisStatusFile, json_encode([
        'analysis_status' => 'Idle',
        'ai_status' => 'Idle',
        'message' => 'Video ready for analysis.',
        'source_type' => 'uploaded_video',
        'updated_at' => date('Y-m-d H:i:s'),
        'updated_at_epoch' => time(),
    ], JSON_PRETTY_PRINT), LOCK_EX);
}

echo json_encode([
    'success' => true,
    'message' => $canStageBesideLiveCamera
        ? 'CCTV footage uploaded and prepared. The shared Tapo analysis remains active.'
        : ($hadPreviousVideo
            ? 'Previous CCTV footage replaced successfully. The new video is ready for analysis.'
            : 'CCTV footage uploaded successfully and is ready for analysis.'),
    'file_name' => (string)$video['name'],
    'file_size' => (int)$video['size'],
]);
