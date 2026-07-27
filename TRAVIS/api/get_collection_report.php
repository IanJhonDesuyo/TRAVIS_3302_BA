<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
session_start();

if (($_SESSION['logged_in'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$role = strtolower(trim((string)($_SESSION['role'] ?? '')));
if ($role !== 'administrator') {
    http_response_code(403);
    echo json_encode(['error' => 'Only administrators can generate reports']);
    exit;
}

require_once __DIR__ . '/../Web_app/db_connect.php';

function valid_report_date(mixed $value, string $fallback): string {
    $text = trim((string)$value);
    $date = DateTime::createFromFormat('Y-m-d', $text);
    return ($date && $date->format('Y-m-d') === $text) ? $text : $fallback;
}

$dateFrom = valid_report_date($_GET['date_from'] ?? '', date('Y-m-01'));
$dateTo = valid_report_date($_GET['date_to'] ?? '', date('Y-m-d'));
$reportType = trim((string)($_GET['report_type'] ?? 'summary'));
if (!in_array($reportType, ['summary', 'detailed', 'by_method'], true)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid collection report type']);
    exit;
}
if ($dateFrom > $dateTo) {
    http_response_code(422);
    echo json_encode(['error' => 'From date cannot be later than To date']);
    exit;
}

$stats = $pdo->query("SELECT
    COALESCE(SUM(CASE WHEN DATE(payment_date) = CURDATE() THEN amount_paid ELSE 0 END), 0) daily,
    COALESCE(SUM(CASE WHEN YEARWEEK(payment_date, 1) = YEARWEEK(CURDATE(), 1) THEN amount_paid ELSE 0 END), 0) weekly,
    COALESCE(SUM(CASE WHEN YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE()) THEN amount_paid ELSE 0 END), 0) monthly,
    COALESCE(SUM(CASE WHEN YEAR(payment_date) = YEAR(CURDATE()) THEN amount_paid ELSE 0 END), 0) annual
    FROM payments WHERE payment_status = 'completed'")->fetch();

if ($reportType === 'by_method') {
    $stmt = $pdo->prepare("SELECT payment_method, COUNT(*) total_transactions, COALESCE(SUM(amount_paid), 0) total_amount FROM payments WHERE payment_status = 'completed' AND DATE(payment_date) BETWEEN ? AND ? GROUP BY payment_method ORDER BY total_amount DESC");
} elseif ($reportType === 'detailed') {
    $stmt = $pdo->prepare("SELECT p.payment_id, p.receipt_reference, p.amount_paid, p.payment_method, p.payment_date, v.ticket_number, v.plate_number, v.violation_type FROM payments p JOIN violations v ON v.violation_id = p.violation_id WHERE p.payment_status = 'completed' AND DATE(p.payment_date) BETWEEN ? AND ? ORDER BY p.payment_date DESC LIMIT 500");
} else {
    $stmt = $pdo->prepare("SELECT DATE(payment_date) collection_date, COUNT(*) total_transactions, COALESCE(SUM(amount_paid), 0) total_amount FROM payments WHERE payment_status = 'completed' AND DATE(payment_date) BETWEEN ? AND ? GROUP BY DATE(payment_date) ORDER BY collection_date DESC");
}
$stmt->execute([$dateFrom, $dateTo]);
$rows = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'data' => $rows,
    'stats' => array_map('floatval', $stats ?: []),
    'meta' => ['report_type' => $reportType, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'generated_at' => date('Y-m-d H:i:s')],
]);
