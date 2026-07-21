<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user']['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

$directory = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'computer_vision' . DIRECTORY_SEPARATOR . 'calibration_profiles';
if (!is_dir($directory) && !mkdir($directory, 0775, true)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Calibration directory is unavailable.']);
    exit;
}

function calibration_profiles(): array
{
    global $directory;
    $profiles = [];
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) continue;
        $profiles[] = [
            'file' => basename($path),
            'name' => (string)($data['profile_name'] ?? pathinfo($path, PATHINFO_FILENAME)),
        ];
    }
    usort($profiles, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $profiles;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(['success' => true, 'profiles' => calibration_profiles()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET or POST required.']);
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

$name = trim((string)($payload['profile_name'] ?? ''));
$inbound = $payload['inbound_line'] ?? null;
$outbound = $payload['outbound_line'] ?? null;
$officerZone = $payload['officer_zone'] ?? [];

$validLine = static function ($line): bool {
    if (!is_array($line) || count($line) !== 2) return false;
    foreach ($line as $point) {
        if (!is_array($point) || count($point) !== 2 || !is_numeric($point[0]) || !is_numeric($point[1])) return false;
        if ((float)$point[0] < 0 || (float)$point[0] > 1 || (float)$point[1] < 0 || (float)$point[1] > 1) return false;
    }
    return true;
};

$validZone = static function ($zone): bool {
    if ($zone === [] || $zone === null) return true;
    if (!is_array($zone) || count($zone) !== 4) return false;
    foreach ($zone as $point) {
        if (!is_array($point) || count($point) !== 2 || !is_numeric($point[0]) || !is_numeric($point[1])) return false;
        if ((float)$point[0] < 0 || (float)$point[0] > 1 || (float)$point[1] < 0 || (float)$point[1] > 1) return false;
    }
    return true;
};

if ($name === '' || mb_strlen($name) > 100 || !$validLine($inbound) || !$validLine($outbound) || !$validZone($officerZone)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a name and draw both counting lines.']);
    exit;
}

$slug = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
if ($slug === '') $slug = 'intersection-profile';
$file = $slug . '.json';
$path = $directory . DIRECTORY_SEPARATOR . $file;
if (file_exists($path)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'A configuration with this name already exists.']);
    exit;
}

$profile = [
    'profile_name' => $name,
    'road_roi' => [[0.0, 0.0], [1.0, 0.0], [1.0, 1.0], [0.0, 1.0]],
    'inbound_line' => $inbound,
    'outbound_line' => $outbound,
    'officer_zone' => $officerZone ?: [],
    'officer' => ['enabled' => !empty($officerZone), 'presence_frames' => 3, 'absence_frames' => 15],
    'collision' => ['enabled' => false, 'cooldown_seconds' => 30],
];

$json = json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save the configuration.']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => 'Intersection configuration saved.',
    'profile' => ['file' => $file, 'name' => $name],
]);
