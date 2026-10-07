<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../Web_app/db_connect.php';

$period = strtolower(trim((string)($_GET['period'] ?? 'all')));
$day = trim((string)($_GET['dashboard_day'] ?? date('Y-m-d')));
$month = trim((string)($_GET['dashboard_month'] ?? date('Y-m')));
$year = trim((string)($_GET['dashboard_year'] ?? date('Y')));
$parsedDay = DateTime::createFromFormat('!Y-m-d', $day);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || !$parsedDay || $parsedDay->format('Y-m-d') !== $day) $day = date('Y-m-d');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
if (!preg_match('/^\d{4}$/', $year) || (int)$year < 2000 || (int)$year > 2100) $year = date('Y');
$periodConditions = [
    'day' => "DATE(p.payment_date) = '{$day}'",
    'month' => "DATE_FORMAT(p.payment_date, '%Y-%m') = '{$month}'",
    'year' => "YEAR(p.payment_date) = {$year}",
    'all' => '1=1',
];
if (!isset($periodConditions[$period])) $period = 'all';

// Get payments with violation details
$sql = "SELECT p.*, v.ticket_number, v.driver_name, v.plate_number, v.violation_type,
               u.full_name AS received_by_name
        FROM payments p 
        JOIN violations v ON p.violation_id = v.violation_id 
        LEFT JOIN users u ON p.received_by = u.user_id
        WHERE {$periodConditions[$period]}
        ORDER BY p.payment_date DESC 
        LIMIT 50";

$stmt = $pdo->query($sql);
$payments = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'data' => $payments
]);
?>
