<?php
declare(strict_types=1);

// Shared PDO connection used by the JSON API consumed by the mobile app.
require_once dirname(__DIR__) . '/config/bootstrap.php';
$database = travis_db_config();

try {
    $pdo = new PDO(
        "mysql:host={$database['host']};port={$database['port']};dbname={$database['name']};charset=utf8mb4",
        $database['user'],
        $database['pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}
