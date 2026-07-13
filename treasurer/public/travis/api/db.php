<?php
// TRAVIS Treasurer - shared DB connection (PHP + MySQL)
// Update credentials to match your XAMPP/MySQL setup.
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

$DB_HOST = 'localhost';
$DB_NAME = 'travis_system';
$DB_USER = 'root';
$DB_PASS = '';

function db() {
  global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
  static $pdo = null;
  if ($pdo === null) {
    try {
      $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER, $DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    } catch (PDOException $e) {
      http_response_code(500);
      echo json_encode(['error' => 'DB connection failed', 'detail' => $e->getMessage()]);
      exit;
    }
  }
  return $pdo;
}

function ok($data) { echo json_encode(['success' => true, 'data' => $data]); exit; }
function fail($msg, $code = 400) { http_response_code($code); echo json_encode(['success' => false, 'error' => $msg]); exit; }
function body() { return json_decode(file_get_contents('php://input'), true) ?? []; }
