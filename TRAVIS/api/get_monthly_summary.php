<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

session_start();
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../Web_app/db_connect.php';

$month = trim((string)($_GET['month'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = date('Y-m');
}

$start = new DateTimeImmutable($month . '-01');
$end = $start->modify('+1 month');
$previousStart = $start->modify('-1 month');
$dateParams = [$start->format('Y-m-d'), $end->format('Y-m-d')];
$previousParams = [$previousStart->format('Y-m-d'), $start->format('Y-m-d')];

$countStatement = $pdo->prepare('SELECT COUNT(*) FROM violations WHERE violation_date >= ? AND violation_date < ?');
$countStatement->execute($dateParams);
$violationCount = (int)$countStatement->fetchColumn();
$countStatement->execute($previousParams);
$previousViolationCount = (int)$countStatement->fetchColumn();

$assessmentStatement = $pdo->prepare('SELECT COALESCE(SUM(penalty_amount), 0) FROM violations WHERE violation_date >= ? AND violation_date < ?');
$assessmentStatement->execute($dateParams);
$assessedAmount = (float)$assessmentStatement->fetchColumn();

$pendingStatement = $pdo->prepare("SELECT COALESCE(SUM(penalty_amount), 0) FROM violations WHERE LOWER(status) IN ('pending', 'unpaid', 'overdue') AND violation_date >= ? AND violation_date < ?");
$pendingStatement->execute($dateParams);
$pendingAmount = (float)$pendingStatement->fetchColumn();

$collectionStatement = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'completed' AND payment_date >= ? AND payment_date < ?");
$collectionStatement->execute($dateParams);
$collectedAmount = (float)$collectionStatement->fetchColumn();

$topStatement = $pdo->prepare('SELECT violation_type AS label, COUNT(*) AS total FROM violations WHERE violation_date >= ? AND violation_date < ? GROUP BY violation_type ORDER BY total DESC, label ASC LIMIT 1');
$topStatement->execute($dateParams);
$topViolation = $topStatement->fetch(PDO::FETCH_ASSOC) ?: ['label' => 'No data', 'total' => 0];

$locationStatement = $pdo->prepare("SELECT COALESCE(NULLIF(TRIM(violation_location), ''), 'Unspecified') AS label, COUNT(*) AS total FROM violations WHERE violation_date >= ? AND violation_date < ? GROUP BY label ORDER BY total DESC, label ASC LIMIT 1");
$locationStatement->execute($dateParams);
$topLocation = $locationStatement->fetch(PDO::FETCH_ASSOC) ?: ['label' => 'No data', 'total' => 0];

$peakStatement = $pdo->prepare("SELECT DAYNAME(violation_date) AS peak_day, HOUR(created_at) AS peak_hour, COUNT(*) AS total FROM violations WHERE violation_date >= ? AND violation_date < ? GROUP BY peak_day, peak_hour ORDER BY total DESC LIMIT 1");
$peakStatement->execute($dateParams);
$peak = $peakStatement->fetch(PDO::FETCH_ASSOC) ?: [];

$changePercent = $previousViolationCount > 0
    ? round((($violationCount - $previousViolationCount) / $previousViolationCount) * 100, 1)
    : ($violationCount > 0 ? 100.0 : 0.0);
$collectionRate = $assessedAmount > 0 ? round(($collectedAmount / $assessedAmount) * 100, 1) : 0.0;

$status = 'Stable';
if ($changePercent >= 25 || ($assessedAmount > 0 && $collectionRate < 50)) {
    $status = 'Critical';
} elseif ($changePercent > 0 || ($assessedAmount > 0 && $collectionRate < 75)) {
    $status = 'Needs Attention';
} elseif ($changePercent < 0) {
    $status = 'Improved';
}

$direction = $changePercent > 0 ? 'higher' : ($changePercent < 0 ? 'lower' : 'unchanged');
$peakHour = isset($peak['peak_hour']) ? date('g:00 A', mktime((int)$peak['peak_hour'])) : 'No data';
$collectionSummary = $pendingAmount > 0
    ? '₱' . number_format($pendingAmount, 2) . ' remains unpaid.'
    : ($collectedAmount > 0 ? 'There is no unpaid balance for the month.' : 'No payments were recorded for the month.');
$summary = sprintf(
    '%s recorded %d violations, %.1f%% %s than %s. %s was the leading offense, while %s had the highest activity. %s',
    $start->format('F Y'),
    $violationCount,
    abs($changePercent),
    $direction,
    $previousStart->format('F'),
    (string)$topViolation['label'],
    (string)$topLocation['label'],
    $collectionSummary
);

echo json_encode([
    'success' => true,
    'data' => [
        'month' => $month,
        'month_label' => $start->format('F Y'),
        'status' => $status,
        'summary' => $summary,
        'violations' => $violationCount,
        'previous_violations' => $previousViolationCount,
        'change_percent' => $changePercent,
        'collected_amount' => $collectedAmount,
        'assessed_amount' => $assessedAmount,
        'pending_amount' => $pendingAmount,
        'collection_rate' => $collectionRate,
        'top_violation' => $topViolation,
        'top_location' => $topLocation,
        'peak_day' => $peak['peak_day'] ?? 'No data',
        'peak_hour' => $peakHour,
    ],
]);
