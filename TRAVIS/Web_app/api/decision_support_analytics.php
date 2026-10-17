<?php
declare(strict_types=1);

require_once __DIR__ . '/../Admin/helpers.php';
require_once __DIR__ . '/../Admin/decision_support_analytics_data.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

try {
    $scope = (string)($_GET['analytics_period'] ?? 'year');
    $value = (string)($_GET['analytics_period_value'] ?? '');
    echo json_encode(
        decision_support_analytics_data($scope, $value),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to load database analytics.',
    ]);
}
