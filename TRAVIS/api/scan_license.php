<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

const TRAVIS_LICENSE_OCR_GATEWAY_VERSION = '2026.11.07.1';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401); echo json_encode(['error' => 'Unauthorized']); exit;
}
if (!isset($_FILES['license']) || $_FILES['license']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400); echo json_encode(['error' => "A driver's license image is required"]); exit;
}
if ($_FILES['license']['size'] > 10 * 1024 * 1024) {
    http_response_code(413); echo json_encode(['error' => "Driver's license image must be 10 MB or smaller"]); exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['license']['tmp_name']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    http_response_code(415); echo json_encode(['error' => "Use a JPEG, PNG, or WebP driver's license image"]); exit;
}

$projectRoot = dirname(__DIR__);
$hostedMode = getenv('TRAVIS_HOSTED_MODE') === '1';
$mlApiUrl = rtrim((string)(getenv('TRAVIS_ML_API_URL') ?: ''), '/');
$cropFrame = trim((string)($_POST['crop_frame'] ?? ''));
if ($cropFrame !== '') {
    $decodedCropFrame = json_decode($cropFrame, true);
    if (!is_array($decodedCropFrame)) {
        http_response_code(400); echo json_encode(['error' => 'The license crop-frame metadata is invalid.']); exit;
    }
}

function sanitizeLicenseOcrResult(array $result, string $executionMode): array
{
    $allowedFields = array_fill_keys([
        'driver_name', 'driver_first_name', 'driver_middle_name', 'driver_last_name', 'driver_suffix',
        'driver_address', 'date_of_birth', 'license_number', 'license_expiry_date',
    ], true);
    $fields = isset($result['fields']) && is_array($result['fields']) ? $result['fields'] : [];
    $confidences = isset($result['field_confidences']) && is_array($result['field_confidences'])
        ? $result['field_confidences'] : [];
    $result['fields'] = array_intersect_key($fields, $allowedFields);
    $result['field_confidences'] = array_intersect_key($confidences, $allowedFields);
    $result['recognized_fields'] = count(array_filter(
        $result['fields'], static fn($value): bool => trim((string)$value) !== ''
    ));
    $result['ocr_gateway_version'] = TRAVIS_LICENSE_OCR_GATEWAY_VERSION;
    $result['ocr_execution_mode'] = $executionMode;
    unset($result['raw_text']);
    return $result;
}

if ($hostedMode) {
    if ($mlApiUrl === '' || !function_exists('curl_init')) {
        http_response_code(503); echo json_encode(['error' => 'The hosted OCR service is not configured.']); exit;
    }
    $upload = new CURLFile(
        $_FILES['license']['tmp_name'], $mime,
        basename((string)($_FILES['license']['name'] ?? 'license.jpg'))
    );
    $curl = curl_init($mlApiUrl . '/ocr/license');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => array_filter([
            'license' => $upload,
            'crop_frame' => $cropFrame !== '' ? $cropFrame : null,
        ], static fn($value): bool => $value !== null),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);
    $statusCode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($responseBody === false) {
        error_log('TRAVIS hosted license OCR connection failed: ' . $curlError);
        http_response_code(502); echo json_encode(['error' => 'The OCR service is temporarily unavailable.']); exit;
    }
    $result = json_decode((string)$responseBody, true);
    if (!is_array($result)) {
        error_log('TRAVIS hosted license OCR returned non-JSON output (HTTP ' . $statusCode . ').');
        http_response_code(502); echo json_encode(['error' => 'The OCR service returned an unreadable response.']); exit;
    }
    if ($statusCode >= 400 || empty($result['success'])) {
        http_response_code($statusCode >= 400 && $statusCode < 500 ? $statusCode : 502);
        echo json_encode(['error' => $result['error'] ?? $result['message'] ?? "Driver's license could not be read."]); exit;
    }
    echo json_encode(sanitizeLicenseOcrResult($result, 'hosted-ml-service'));
    exit;
}

$pythonCandidates = [
    $projectRoot . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe',
    $projectRoot . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'python3',
];
$python = '';
foreach ($pythonCandidates as $candidate) {
    if (is_file($candidate)) { $python = $candidate; break; }
}
$script = $projectRoot . DIRECTORY_SEPARATOR . 'computer_vision' . DIRECTORY_SEPARATOR . 'license_ocr.py';
if ($python === '' || !is_file($script)) {
    http_response_code(503); echo json_encode(['error' => 'The local OCR runtime is not installed.']); exit;
}
$command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($_FILES['license']['tmp_name']);
if ($cropFrame !== '') { $command .= ' ' . escapeshellarg($cropFrame); }
$command .= ' 2>&1';
$output = []; $exitCode = 0;
exec($command, $output, $exitCode);
$result = null;
for ($index = count($output) - 1; $index >= 0; $index--) {
    $decoded = json_decode((string)$output[$index], true);
    if (is_array($decoded)) { $result = $decoded; break; }
}
if (!is_array($result)) {
    error_log('TRAVIS local license OCR returned no JSON. Exit code: ' . $exitCode);
    http_response_code(500); echo json_encode(['error' => 'The local OCR process failed before returning a result.']); exit;
}
if ($exitCode !== 0 || empty($result['success'])) {
    http_response_code(422); echo json_encode(['error' => $result['error'] ?? "Driver's license could not be read."]); exit;
}
echo json_encode(sanitizeLicenseOcrResult($result, 'local-python'));
