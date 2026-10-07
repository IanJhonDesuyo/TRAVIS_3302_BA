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

function valid_calibration_filename(string $file): bool
{
    return preg_match('/\A[a-zA-Z0-9_-]+\.json\z/D', $file) === 1 && basename($file) === $file;
}

function calibration_analysis_is_active(): bool
{
    $analysisStatusPath = __DIR__ . DIRECTORY_SEPARATOR . 'analysis_status.json';
    $latestStatusPath = __DIR__ . DIRECTORY_SEPARATOR . 'latest_status.json';
    $analysisStatus = is_file($analysisStatusPath) ? json_decode((string)file_get_contents($analysisStatusPath), true) : [];
    $latestStatus = is_file($latestStatusPath) ? json_decode((string)file_get_contents($latestStatusPath), true) : [];
    $analysisState = strtolower((string)($analysisStatus['analysis_status'] ?? ''));
    $analysisUpdated = (int)($analysisStatus['updated_at_epoch'] ?? 0);
    $latestUpdated = (int)($latestStatus['updated_at_epoch'] ?? 0);

    return ($latestUpdated > 0 && time() - $latestUpdated <= 6)
        || ($analysisState === 'starting' && $analysisUpdated > 0 && time() - $analysisUpdated <= 30);
}

function calibration_archive_path(string $directory, string $file): ?string
{
    $archiveDirectory = $directory . DIRECTORY_SEPARATOR . '.archive';
    if (!is_dir($archiveDirectory) && !mkdir($archiveDirectory, 0775, true)) return null;

    $timestamp = date('Ymd_His');
    $archivePath = $archiveDirectory . DIRECTORY_SEPARATOR . $timestamp . '_' . $file;
    $counter = 1;
    while (file_exists($archivePath)) {
        $archivePath = $archiveDirectory . DIRECTORY_SEPARATOR . $timestamp . '_' . $counter . '_' . $file;
        $counter++;
    }
    return $archivePath;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $file = trim((string)($_GET['file'] ?? ''));
    if ($file !== '') {
        if (!valid_calibration_filename($file)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'Select a valid intersection configuration.']);
            exit;
        }
        $path = $directory . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'The selected configuration no longer exists.']);
            exit;
        }
        $data = json_decode((string)file_get_contents($path), true);
        if (!is_array($data)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'The selected configuration could not be read.']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'profile' => [
                'file' => $file,
                'name' => (string)($data['profile_name'] ?? pathinfo($path, PATHINFO_FILENAME)),
                'inbound_line' => $data['inbound_line'] ?? [],
                'outbound_line' => $data['outbound_line'] ?? [],
                'officer_zone' => $data['officer_zone'] ?? [],
            ],
        ]);
        exit;
    }
    echo json_encode(['success' => true, 'profiles' => calibration_profiles()]);
    exit;
}

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE'], true)) {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'GET, POST, PUT, or DELETE required.']);
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

if (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'DELETE'], true)) {
    if (strcasecmp((string)($_SESSION['user']['role'] ?? ''), 'Administrator') !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Administrator access is required.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $file = trim((string)($payload['file'] ?? ''));
    if (!valid_calibration_filename($file)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Select a valid intersection configuration.']);
        exit;
    }
    if (strcasecmp($file, 'example.json') === 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'The default configuration cannot be deleted.']);
        exit;
    }

    if (calibration_analysis_is_active()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Stop the active analysis before deleting a configuration.']);
        exit;
    }

    $path = $directory . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'The selected configuration no longer exists.']);
        exit;
    }

    $data = json_decode((string)file_get_contents($path), true);
    $name = is_array($data)
        ? (string)($data['profile_name'] ?? pathinfo($path, PATHINFO_FILENAME))
        : pathinfo($path, PATHINFO_FILENAME);
    $archivePath = calibration_archive_path($directory, $file);
    if ($archivePath === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to prepare the configuration archive.']);
        exit;
    }

    if (!@rename($path, $archivePath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to remove the configuration.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Intersection configuration deleted. A recovery copy was archived.',
        'profile' => ['file' => $file, 'name' => $name],
    ]);
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

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $file = trim((string)($payload['file'] ?? ''));
    if (!valid_calibration_filename($file)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Select a valid intersection configuration.']);
        exit;
    }
    if (calibration_analysis_is_active()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Stop the active analysis before editing a configuration.']);
        exit;
    }

    $path = $directory . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'The selected configuration no longer exists.']);
        exit;
    }
    $profile = json_decode((string)file_get_contents($path), true);
    if (!is_array($profile)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'The selected configuration could not be read.']);
        exit;
    }

    $profile['profile_name'] = $name;
    $profile['inbound_line'] = $inbound;
    $profile['outbound_line'] = $outbound;
    $profile['officer_zone'] = $officerZone ?: [];
    $officer = is_array($profile['officer'] ?? null) ? $profile['officer'] : [];
    $officer['enabled'] = !empty($officerZone);
    $officer['presence_frames'] = (int)($officer['presence_frames'] ?? 3);
    $officer['absence_frames'] = (int)($officer['absence_frames'] ?? 15);
    $profile['officer'] = $officer;

    $json = json_encode($profile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $archivePath = calibration_archive_path($directory, $file);
    if ($json === false || $archivePath === null || !@copy($path, $archivePath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to create a recovery copy before saving.']);
        exit;
    }
    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        @copy($archivePath, $path);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to update the configuration.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Intersection configuration updated. The previous version was archived.',
        'profile' => ['file' => $file, 'name' => $name],
    ]);
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
    'collision' => [
        'enabled' => true,
        'ttc_warning_seconds' => 2.5,
        'maximum_pair_distance' => 2.6,
        'contact_iou' => 0.08,
        'confirmation_iou' => 0.04,
        'possible_frames' => 3,
        'confirmed_frames' => 4,
        'risk_memory_seconds' => 4.0,
        'minimum_track_confidence' => 0.45,
        'minimum_motorcycle_confidence' => 0.25,
        'occlusion_confirmation_frames' => 3,
        'occlusion_window_seconds' => 2.0,
        'occlusion_max_distance' => 1.5,
        'cooldown_seconds' => 30,
    ],
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
