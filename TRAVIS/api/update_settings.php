<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

session_start();
if (($_SESSION['logged_in'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
if (strcasecmp((string)($_SESSION['role'] ?? ''), 'Administrator') !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Administrator access required']);
    exit;
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input) || json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A valid JSON request body is required']);
    exit;
}

$integerRules = [
    'congestion_light_max' => [0, 100],
    'congestion_heavy_min' => [1, 200],
    'alert_cooldown_seconds' => [0, 86400],
];
$values = [];
foreach ($integerRules as $key => [$minimum, $maximum]) {
    $validated = filter_var($input[$key] ?? null, FILTER_VALIDATE_INT);
    if ($validated === false || $validated < $minimum || $validated > $maximum) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => "$key must be between $minimum and $maximum"]);
        exit;
    }
    $values[$key] = $validated;
}

if ($values['congestion_heavy_min'] <= $values['congestion_light_max']) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Heavy congestion must start above the light congestion maximum']);
    exit;
}

$confidence = filter_var($input['confidence_threshold'] ?? null, FILTER_VALIDATE_FLOAT);
if ($confidence === false || $confidence < 0.10 || $confidence > 1.00) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => 'Confidence threshold must be between 0.10 and 1.00']);
    exit;
}
$values['confidence_threshold'] = number_format((float)$confidence, 2, '.', '');

foreach (['enable_officer_detection', 'enable_collision_detection', 'notify_congestion', 'notify_collision'] as $key) {
    if (!array_key_exists($key, $input) || !is_bool($input[$key])) {
        http_response_code(422);
        echo json_encode(['success' => false, 'error' => "$key must be true or false"]);
        exit;
    }
    $values[$key] = $input[$key] ? 1 : 0;
}

require_once __DIR__ . '/../Web_app/db_connect.php';
try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare(
        'INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) '
        . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($values as $key => $value) {
        $statement->execute([$key, (string)$value]);
    }
    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Settings saved. Detection changes apply the next time analysis starts.',
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('update_settings.php: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to save settings']);
}
