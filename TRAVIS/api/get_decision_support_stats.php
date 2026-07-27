<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
session_start();
if (($_SESSION['logged_in'] ?? false) !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../Web_app/db_connect.php';

try {
    $period = strtolower(trim((string)($_GET['period'] ?? 'day')));
    $day = trim((string)($_GET['analytics_day'] ?? date('Y-m-d')));
    $month = trim((string)($_GET['analytics_month'] ?? date('Y-m')));
    $year = trim((string)($_GET['analytics_year'] ?? date('Y')));
    $parsedDay = DateTime::createFromFormat('!Y-m-d', $day);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || !$parsedDay || $parsedDay->format('Y-m-d') !== $day) $day = date('Y-m-d');
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
    if (!preg_match('/^\d{4}$/', $year) || (int)$year < 2000 || (int)$year > 2100) $year = date('Y');
    $periods = [
        'day' => ["record.violation_date = '{$day}'", date('F j, Y', strtotime($day))],
        'month' => ["DATE_FORMAT(record.violation_date, '%Y-%m') = '{$month}'", date('F Y', strtotime($month . '-01'))],
        'year' => ["YEAR(record.violation_date) = {$year}", $year],
        'all' => ['1=1', 'All Records'],
    ];
    if (!isset($periods[$period])) {
        http_response_code(422); echo json_encode(['success' => false, 'error' => 'Invalid analytics period']); exit;
    }
    [$dateCondition, $periodLabel] = $periods[$period];
    $activeCondition = "({$dateCondition}) AND record.status <> 'cancelled'";
    $paymentPeriods = [
        'day' => "DATE(payment_date) = '{$day}'",
        'month' => "DATE_FORMAT(payment_date, '%Y-%m') = '{$month}'",
        'year' => "YEAR(payment_date) = {$year}",
        'all' => '1=1',
    ];
    $collection = $pdo->query("SELECT COUNT(*) payment_count, COALESCE(SUM(amount_paid),0) collected_amount FROM payments WHERE {$paymentPeriods[$period]} AND payment_status='completed'")->fetch();

    $summary = $pdo->query("SELECT COUNT(*) violations_today, COALESCE(SUM(record.penalty_amount),0) penalties_today, COUNT(DISTINCT record.violation_location) affected_locations FROM violations record WHERE {$activeCondition}")->fetch();
    $topViolation = $pdo->query("SELECT item.violation_type, COUNT(*) total FROM violation_items item JOIN violations record ON record.violation_id=item.violation_id WHERE {$activeCondition} GROUP BY item.violation_type ORDER BY total DESC LIMIT 1")->fetch() ?: [];
    $topLocation = $pdo->query("SELECT record.violation_location, COUNT(*) total FROM violations record WHERE {$activeCondition} GROUP BY record.violation_location ORDER BY total DESC LIMIT 1")->fetch() ?: [];
    $peak = $pdo->query("SELECT HOUR(record.created_at) peak_hour, COUNT(*) total FROM violations record WHERE {$activeCondition} GROUP BY HOUR(record.created_at) ORDER BY total DESC LIMIT 1")->fetch() ?: [];
    $monthlyRows = $pdo->query("SELECT MONTH(record.violation_date) month_number, COUNT(*) total FROM violations record WHERE {$activeCondition} GROUP BY MONTH(record.violation_date)")->fetchAll();
    $monthly = array_fill(0, 12, 0);
    foreach ($monthlyRows as $row) $monthly[max(0, min(11, (int)$row['month_number'] - 1))] = (int)$row['total'];
    $types = $pdo->query("SELECT item.violation_type, COUNT(*) total FROM violation_items item JOIN violations record ON record.violation_id=item.violation_id WHERE {$activeCondition} GROUP BY item.violation_type ORDER BY total DESC LIMIT 8")->fetchAll();
    $peakHour = isset($peak['peak_hour']) ? date('g A', mktime((int)$peak['peak_hour'], 0)) : 'No data';

    echo json_encode(['success' => true, 'data' => [
        'violations_today' => (int)($summary['violations_today'] ?? 0),
        'penalties_today' => (float)($summary['penalties_today'] ?? 0),
        'affected_locations' => (int)($summary['affected_locations'] ?? 0),
        'payment_count' => (int)($collection['payment_count'] ?? 0),
        'collected_amount' => (float)($collection['collected_amount'] ?? 0),
        'peak_hour' => $peakHour,
        'top_violation' => ['name' => (string)($topViolation['violation_type'] ?? 'No data'), 'total' => (int)($topViolation['total'] ?? 0)],
        'top_location' => ['name' => (string)($topLocation['violation_location'] ?? 'No data'), 'total' => (int)($topLocation['total'] ?? 0)],
        'monthly_trend' => $monthly,
        'top_violation_types' => $types,
        'year' => (int)date('Y'),
        'period' => $period,
        'period_label' => $periodLabel,
        'last_updated' => date(DATE_ATOM),
    ]]);
} catch (Throwable $exception) {
    error_log('get_decision_support_stats.php: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load decision-support database analytics']);
}
