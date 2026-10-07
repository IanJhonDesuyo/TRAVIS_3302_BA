<?php
declare(strict_types=1);

require_once __DIR__ . '/ml_service.php';
require_once __DIR__ . '/../Admin/db_connect.php';
require_once __DIR__ . '/hybrid_bridge.php';

header("Content-Type: application/json");

// Allow testing from browser
if ($_SERVER["REQUEST_METHOD"] === "GET") {
    $defaultPeriod = new DateTimeImmutable('first day of next month');
    $input = [
        "year" => isset($_GET['year']) ? (int)$_GET['year'] : (int)$defaultPeriod->format('Y'),
        "month" => isset($_GET['month']) ? (int)$_GET['month'] : (int)$defaultPeriod->format('n')
    ];

} else {

    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid JSON input."
        ]);
        exit;
    }

}

if ($input['year'] < 2000 || $input['year'] > 2100 || $input['month'] < 1 || $input['month'] > 12) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Select a valid month and year between 2000 and 2100.']);
    exit;
}

if (!travis_is_edge_host()) {
    try {
        $result = travis_dispatch_edge_job($conn, 'ml_monthly', $input, 35);
        if (!empty($result['pending'])) http_response_code(503);
        echo json_encode($result, JSON_UNESCAPED_SLASHES);
    } catch (Throwable $error) {
        error_log('TRAVIS monthly hybrid prediction: ' . $error->getMessage());
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => $error->getMessage()]);
    }
    exit;
}

ensure_ml_service_running();

$flask_api = "http://127.0.0.1:5001/predict/monthly";

$response = false;
$curlError = 'Machine-learning API is still starting.';
$ch = null;

// api.py loads its model bundles before Flask begins listening. Retry long
// enough for a fresh authenticated-session auto-start to finish.
for ($attempt = 1; $attempt <= 20; $attempt++) {
    $ch = curl_init($flask_api);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($input));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    if ($response !== false) break;

    $curlError = curl_error($ch);
    curl_close($ch);
    $ch = null;
    if ($attempt < 20) usleep(750000);
}

if ($response === false || !$ch) {
    http_response_code(503);
    echo json_encode([
        "success" => false,
        "message" => "Machine-learning service did not become ready in time.",
        "details" => $curlError
    ]);
    exit;
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

http_response_code($httpCode);

echo $response;
