<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

require_once __DIR__ . '/../Admin/db_connect.php';
require_once __DIR__ . '/camera_credentials.php';
camera_ensure_password_column($conn);

function camera_rows(mysqli $conn): array
{
    $result = $conn->query(<<<'SQL'
        SELECT c.camera_id, c.camera_name, c.location, c.ip_address,
               c.camera_username,
               CASE WHEN c.camera_password_encrypted IS NOT NULL AND c.camera_password_encrypted <> '' THEN 1 ELSE 0 END AS has_saved_password,
               c.stream_quality, c.calibration_profile,
               c.edge_worker_id, c.is_enabled, c.status, c.installed_at,
               c.last_maintenance_at, c.created_at, c.updated_at,
               l.last_seen_at
        FROM cameras c
        LEFT JOIN (
            SELECT camera_id, MAX(recorded_at) AS last_seen_at
            FROM camera_monitoring_logs
            GROUP BY camera_id
        ) l ON l.camera_id = c.camera_id
        ORDER BY c.camera_id
    SQL);
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'data' => camera_rows($conn)]);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET, POST, or PUT required.']);
    exit;
}

if (strcasecmp((string)($_SESSION['user']['role'] ?? ''), 'Administrator') !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Administrator access is required.']);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request.']);
    exit;
}

if (!hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($payload['csrf_token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid session token. Refresh and try again.']);
    exit;
}

if (($payload['action'] ?? '') === 'assign_calibration') {
    $cameraId = (int)($payload['camera_id'] ?? 0);
    $profile = basename(trim((string)($payload['calibration_profile'] ?? '')));
    $profilePath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR
        . 'computer_vision' . DIRECTORY_SEPARATOR . 'calibration_profiles'
        . DIRECTORY_SEPARATOR . $profile;

    if ($cameraId <= 0
        || preg_match('/\A[a-zA-Z0-9_-]+\.json\z/D', $profile) !== 1
        || !is_file($profilePath)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Select a valid camera and line configuration.']);
        exit;
    }

    $stmt = $conn->prepare('UPDATE cameras SET calibration_profile=? WHERE camera_id=?');
    $stmt->bind_param('si', $profile, $cameraId);
    $stmt->execute();
    if ($stmt->affected_rows === 0) {
        $check = $conn->prepare('SELECT camera_id FROM cameras WHERE camera_id=?');
        $check->bind_param('i', $cameraId);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Camera record not found.']);
            exit;
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'The line configuration is now assigned to this camera.',
        'camera_id' => $cameraId,
        'calibration_profile' => $profile,
    ]);
    exit;
}

$cameraId = (int)($payload['camera_id'] ?? 0);
$name = trim((string)($payload['camera_name'] ?? ''));
$location = trim((string)($payload['location'] ?? ''));
$ip = trim((string)($payload['ip_address'] ?? ''));
$username = trim((string)($payload['camera_username'] ?? ''));
$password = (string)($payload['camera_password'] ?? '');
$stream = (string)($payload['stream_quality'] ?? 'stream2') === 'stream1' ? 'stream1' : 'stream2';
$profile = basename(trim((string)($payload['calibration_profile'] ?? '')));
$workerId = trim((string)($payload['edge_worker_id'] ?? ''));
$enabled = !empty($payload['is_enabled']) ? 1 : 0;

if ($name === '' || mb_strlen($name) > 120) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid camera name.']);
    exit;
}
if ($location === '' || mb_strlen($location) > 255) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid camera location.']);
    exit;
}
if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid camera IPv4 address.']);
    exit;
}
if ($username === '' || mb_strlen($username) > 120 || mb_strlen($workerId) > 100) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid camera account and worker assignment.']);
    exit;
}
if (strlen($password) > 255) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid camera password.']);
    exit;
}
if ($profile !== '' && preg_match('/\A[a-zA-Z0-9_-]+\.json\z/D', $profile) !== 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Select a valid calibration profile.']);
    exit;
}

try {
    if ($cameraId > 0) {
        $encryptedPassword = $password !== '' ? camera_encrypt_password($password) : null;
        $stmt = $conn->prepare(<<<'SQL'
            UPDATE cameras
            SET camera_name=?, location=?, ip_address=?, camera_username=?,
                camera_password_encrypted=COALESCE(?, camera_password_encrypted),
                stream_quality=?, calibration_profile=?, edge_worker_id=NULLIF(?, ''),
                is_enabled=?
            WHERE camera_id=?
        SQL);
        $stmt->bind_param('ssssssssii', $name, $location, $ip, $username, $encryptedPassword, $stream, $profile, $workerId, $enabled, $cameraId);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            $check = $conn->prepare('SELECT camera_id FROM cameras WHERE camera_id=?');
            $check->bind_param('i', $cameraId);
            $check->execute();
            if (!$check->get_result()->fetch_assoc()) throw new RuntimeException('Camera record not found.');
        }
    } else {
        if ($password === '') throw new InvalidArgumentException('Enter the camera password during first registration.');
        $encryptedPassword = camera_encrypt_password($password);
        $stmt = $conn->prepare(<<<'SQL'
            INSERT INTO cameras
                (camera_name, location, ip_address, camera_username, camera_password_encrypted, stream_quality,
                 calibration_profile, edge_worker_id, is_enabled, status, installed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, 'offline', CURDATE())
        SQL);
        $stmt->bind_param('ssssssssi', $name, $location, $ip, $username, $encryptedPassword, $stream, $profile, $workerId, $enabled);
        $stmt->execute();
        $cameraId = (int)$stmt->insert_id;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Camera settings and encrypted password saved securely.',
        'camera_id' => $cameraId,
        'data' => camera_rows($conn),
    ]);
} catch (mysqli_sql_exception $error) {
    http_response_code($error->getCode() === 1062 ? 409 : 500);
    echo json_encode([
        'success' => false,
        'message' => $error->getCode() === 1062
            ? 'That camera IP address is already registered.'
            : 'Unable to save the camera settings.',
    ]);
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
}

