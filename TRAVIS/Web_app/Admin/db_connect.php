<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
$database = travis_db_config();

$conn = new mysqli(
    $database['host'],
    $database['user'],
    $database['pass'],
    $database['name'],
    $database['port']
);

if ($conn->connect_error) {
    error_log('TRAVIS database connection failed: ' . $conn->connect_error);
    http_response_code(500);
    die('Database connection failed.');
}

$conn->set_charset('utf8mb4');
