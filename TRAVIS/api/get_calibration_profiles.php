<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

session_start();
if (($_SESSION['logged_in'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'computer_vision' . DIRECTORY_SEPARATOR . 'calibration_profiles';

function valid_mobile_calibration_filename(string $file): bool
{
    return preg_match('/\A[a-zA-Z0-9_-]+\.json\z/D', $file) === 1 && basename($file) === $file;
}

function mobile_calibration_analysis_is_active(): bool
{
    $statusDirectory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Web_app' . DIRECTORY_SEPARATOR . 'api';
    $analysisStatusPath = $statusDirectory . DIRECTORY_SEPARATOR . 'analysis_status.json';
    $latestStatusPath = $statusDirectory . DIRECTORY_SEPARATOR . 'latest_status.json';
    $analysisStatus = is_file($analysisStatusPath) ? json_decode((string)file_get_contents($analysisStatusPath), true) : [];
    $latestStatus = is_file($latestStatusPath) ? json_decode((string)file_get_contents($latestStatusPath), true) : [];
    $analysisState = strtolower((string)($analysisStatus['analysis_status'] ?? ''));
    $analysisUpdated = (int)($analysisStatus['updated_at_epoch'] ?? 0);
    $latestUpdated = (int)($latestStatus['updated_at_epoch'] ?? 0);

    return ($latestUpdated > 0 && time() - $latestUpdated <= 6)
        || ($analysisState === 'starting' && $analysisUpdated > 0 && time() - $analysisUpdated <= 30);
}

function mobile_calibration_archive_path(string $directory, string $file): ?string
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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && trim((string)($_GET['file'] ?? '')) !== '') {
    $file = trim((string)$_GET['file']);
    if (!valid_mobile_calibration_filename($file)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Select a valid intersection configuration.']);
        exit;
    }
    $path = $directory . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'The selected configuration no longer exists.']);
        exit;
    }
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'The selected configuration could not be read.']);
        exit;
    }
    echo json_encode([
        'success' => true,
        'data' => [
            'file' => $file,
            'name' => (string)($data['profile_name'] ?? pathinfo($path, PATHINFO_FILENAME)),
            'inbound_line' => $data['inbound_line'] ?? [],
            'outbound_line' => $data['outbound_line'] ?? [],
            'officer_zone' => $data['officer_zone'] ?? [],
        ],
    ]);
    exit;
}

if (in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'DELETE'], true)) {
    if (strcasecmp((string)($_SESSION['role'] ?? ''), 'Administrator') !== 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Administrator access is required.']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    $payload = json_decode((string)file_get_contents('php://input'), true);
    $file = is_array($payload) ? trim((string)($payload['file'] ?? '')) : '';
    if (!valid_mobile_calibration_filename($file)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Select a valid intersection configuration.']);
        exit;
    }
    if (strcasecmp($file, 'example.json') === 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'The default configuration cannot be deleted.']);
        exit;
    }

    if (mobile_calibration_analysis_is_active()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Stop the active analysis before deleting a configuration.']);
        exit;
    }

    $path = $directory . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'The selected configuration no longer exists.']);
        exit;
    }

    $archivePath = mobile_calibration_archive_path($directory, $file);
    if ($archivePath === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to prepare the configuration archive.']);
        exit;
    }

    if (!@rename($path, $archivePath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to remove the configuration.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => 'Intersection configuration deleted. A recovery copy was archived.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $payload = json_decode((string)file_get_contents('php://input'), true);
    $file = is_array($payload) ? trim((string)($payload['file'] ?? '')) : '';
    $name = is_array($payload) ? trim((string)($payload['profile_name'] ?? '')) : '';
    $inbound = is_array($payload) ? ($payload['inbound_line'] ?? null) : null;
    $outbound = is_array($payload) ? ($payload['outbound_line'] ?? null) : null;
    $officerZone = is_array($payload) ? ($payload['officer_zone'] ?? []) : [];

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

    if (!valid_mobile_calibration_filename($file) || $name === '' || mb_strlen($name) > 100 || !$validLine($inbound) || !$validLine($outbound) || !$validZone($officerZone)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => 'Enter a name and draw both counting lines.']);
        exit;
    }
    if (mobile_calibration_analysis_is_active()) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Stop the active analysis before editing a configuration.']);
        exit;
    }

    $path = $directory . DIRECTORY_SEPARATOR . $file;
    if (!is_file($path)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'The selected configuration no longer exists.']);
        exit;
    }
    $profile = json_decode((string)file_get_contents($path), true);
    if (!is_array($profile)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'The selected configuration could not be read.']);
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
    $archivePath = mobile_calibration_archive_path($directory, $file);
    if ($json === false || $archivePath === null || !@copy($path, $archivePath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to create a recovery copy before saving.']);
        exit;
    }
    if (file_put_contents($path, $json . PHP_EOL, LOCK_EX) === false) {
        @copy($archivePath, $path);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Unable to update the configuration.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Intersection configuration updated. The previous version was archived.',
        'data' => ['file' => $file, 'name' => $name],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'GET, PUT, or DELETE required.']);
    exit;
}

$profiles = [];
foreach (glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $path) {
    $data = json_decode((string)file_get_contents($path), true);
    if (!is_array($data)) continue;
    $profiles[] = ['file' => basename($path), 'name' => (string)($data['profile_name'] ?? pathinfo($path, PATHINFO_FILENAME))];
}
usort($profiles, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));
echo json_encode(['success' => true, 'data' => $profiles]);
