<?php
declare(strict_types=1);

// Shared PDO connection used by the JSON API consumed by the mobile app.
$dbHost = getenv('TRAVIS_DB_HOST') ?: 'localhost';
$dbName = getenv('TRAVIS_DB_NAME') ?: 'travis';
$dbUser = getenv('TRAVIS_DB_USER') ?: 'root';
$dbPass = getenv('TRAVIS_DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
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
