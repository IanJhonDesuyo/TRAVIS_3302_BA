<?php
declare(strict_types=1);

require_once __DIR__ . '/../Admin/helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$chartPeriod = strtolower(trim((string)($_GET['chart_scope'] ?? 'year')));
$allowedPeriods = ['all', 'week', 'month', 'year'];
if (!in_array($chartPeriod, $allowedPeriods, true)) $chartPeriod = 'year';

$chartValue = trim((string)($_GET['chart_period_value'] ?? ''));
$chartDateCondition = '1=1';
$chartLabel = 'Overall';
$today = new DateTimeImmutable('today');

if (in_array($chartPeriod, ['week', 'month'], true)) {
    $currentMonth = $today->format('Y-m');
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $chartValue) || $chartValue > $currentMonth) {
        $chartValue = $currentMonth;
    }
    $selectedMonth = new DateTimeImmutable($chartValue . '-01');
    $chartDateCondition = "DATE_FORMAT(%s, '%%Y-%%m') = '{$chartValue}'";
    $chartLabel = $selectedMonth->format('F Y');
} elseif ($chartPeriod === 'year') {
    $currentYear = (int)$today->format('Y');
    $selectedYear = preg_match('/^\d{4}$/', $chartValue) ? (int)$chartValue : $currentYear;
    $selectedYear = max(2000, min($currentYear, $selectedYear));
    $chartValue = (string)$selectedYear;
    $chartDateCondition = "YEAR(%s) = {$selectedYear}";
    $chartLabel = $chartValue;
} else {
    $chartValue = '';
}

$conditionFor = static function (string $column) use ($chartDateCondition): string {
    $placeholderCount = substr_count($chartDateCondition, '%s');
    return $placeholderCount > 0
        ? vsprintf($chartDateCondition, array_fill(0, $placeholderCount, $column))
        : $chartDateCondition;
};

$labels = [];
$violations = [];
$collections = [];
$violationRows = [];
$collectionRows = [];

if ($chartPeriod === 'week') {
    $weeksInMonth = (int)ceil(((int)$selectedMonth->format('t')) / 7);
    for ($week = 1; $week <= $weeksInMonth; $week++) {
        $labels[$week] = 'Week ' . $week;
        $violations[$week] = 0;
        $collections[$week] = 0.0;
    }
    $violationRows = fetch_all("
        SELECT FLOOR((DAY(violation_date) - 1) / 7) + 1 AS bucket, COUNT(*) AS total
        FROM violations
        WHERE LOWER(status) <> 'cancelled' AND " . $conditionFor('violation_date') . "
        GROUP BY FLOOR((DAY(violation_date) - 1) / 7) + 1
    ");
    $collectionRows = fetch_all("
        SELECT FLOOR((DAY(payment_date) - 1) / 7) + 1 AS bucket, COALESCE(SUM(amount_paid), 0) AS total
        FROM payments
        WHERE LOWER(payment_status) = 'completed' AND " . $conditionFor('DATE(payment_date)') . "
        GROUP BY FLOOR((DAY(payment_date) - 1) / 7) + 1
    ");
} elseif ($chartPeriod === 'month') {
    $daysInMonth = (int)$selectedMonth->format('t');
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $labels[$day] = (string)$day;
        $violations[$day] = 0;
        $collections[$day] = 0.0;
    }
    $violationRows = fetch_all("
        SELECT DAY(violation_date) AS bucket, COUNT(*) AS total
        FROM violations
        WHERE LOWER(status) <> 'cancelled' AND " . $conditionFor('violation_date') . "
        GROUP BY DAY(violation_date)
    ");
    $collectionRows = fetch_all("
        SELECT DAY(payment_date) AS bucket, COALESCE(SUM(amount_paid), 0) AS total
        FROM payments
        WHERE LOWER(payment_status) = 'completed' AND " . $conditionFor('DATE(payment_date)') . "
        GROUP BY DAY(payment_date)
    ");
} elseif ($chartPeriod === 'year') {
    foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $index => $monthName) {
        $bucket = $index + 1;
        $labels[$bucket] = $monthName;
        $violations[$bucket] = 0;
        $collections[$bucket] = 0.0;
    }
    $violationRows = fetch_all("
        SELECT MONTH(violation_date) AS bucket, COUNT(*) AS total
        FROM violations
        WHERE LOWER(status) <> 'cancelled' AND " . $conditionFor('violation_date') . "
        GROUP BY MONTH(violation_date)
    ");
    $collectionRows = fetch_all("
        SELECT MONTH(payment_date) AS bucket, COALESCE(SUM(amount_paid), 0) AS total
        FROM payments
        WHERE LOWER(payment_status) = 'completed' AND " . $conditionFor('DATE(payment_date)') . "
        GROUP BY MONTH(payment_date)
    ");
} else {
    $violationRows = fetch_all("
        SELECT YEAR(violation_date) AS bucket, COUNT(*) AS total
        FROM violations
        WHERE LOWER(status) <> 'cancelled'
        GROUP BY YEAR(violation_date)
    ");
    $collectionRows = fetch_all("
        SELECT YEAR(payment_date) AS bucket, COALESCE(SUM(amount_paid), 0) AS total
        FROM payments
        WHERE LOWER(payment_status) = 'completed'
        GROUP BY YEAR(payment_date)
    ");
    $availableYears = array_merge(
        array_map(static fn(array $row): int => (int)$row['bucket'], $violationRows),
        array_map(static fn(array $row): int => (int)$row['bucket'], $collectionRows)
    );
    if (!$availableYears) $availableYears[] = (int)$today->format('Y');
    for ($year = min($availableYears); $year <= max($availableYears); $year++) {
        $labels[$year] = (string)$year;
        $violations[$year] = 0;
        $collections[$year] = 0.0;
    }
}

foreach ($violationRows as $row) {
    $bucket = (int)($row['bucket'] ?? 0);
    if (array_key_exists($bucket, $violations)) $violations[$bucket] = (int)$row['total'];
}
foreach ($collectionRows as $row) {
    $bucket = (int)($row['bucket'] ?? 0);
    if (array_key_exists($bucket, $collections)) $collections[$bucket] = (float)$row['total'];
}

$description = $chartPeriod === 'week'
    ? 'Weekly records · ' . $chartLabel
    : ($chartPeriod === 'month'
        ? 'Daily records · ' . $chartLabel
        : ($chartPeriod === 'year' ? 'Monthly records · ' . $chartLabel : 'Yearly records · Overall'));

echo json_encode([
    'success' => true,
    'scope' => $chartPeriod,
    'value' => $chartValue,
    'description' => $description,
    'labels' => array_values($labels),
    'violations' => array_values($violations),
    'collections' => array_values($collections),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
