<?php
declare(strict_types=1);

$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'travis';

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}

$conn->set_charset('utf8mb4');
