<?php
declare(strict_types=1);

/**
 * Build the database-backed Decision Support analytics for one timeline.
 * The caller must load helpers.php (or layout.php) before calling this.
 */
function decision_support_analytics_data(string $requestedScope, string $requestedValue = ''): array
{
    $today = new DateTimeImmutable('today');
    $scope = strtolower(trim($requestedScope));
    if (!in_array($scope, ['all', 'week', 'month', 'year'], true)) {
        $scope = 'year';
    }

    $value = trim($requestedValue);
    $dateCondition = '1=1';
    $periodLabel = 'All Records';
    $weekStart = null;
    $selectedMonth = null;

    if ($scope === 'week') {
        $weekStart = $today->modify('monday this week');
        $weekEnd = $weekStart->modify('+6 days');
        $startValue = $weekStart->format('Y-m-d');
        $endValue = $weekEnd->format('Y-m-d');
        $dateCondition = "%s BETWEEN '{$startValue}' AND '{$endValue}'";
        $periodLabel = $weekStart->format('M j') . ' – ' . $weekEnd->format('M j, Y');
        $value = '';
    } elseif ($scope === 'month') {
        $currentMonth = $today->format('Y-m');
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) || $value > $currentMonth) {
            $value = $currentMonth;
        }
        $selectedMonth = new DateTimeImmutable($value . '-01');
        $dateCondition = "DATE_FORMAT(%s, '%%Y-%%m') = '{$value}'";
        $periodLabel = $selectedMonth->format('F Y');
    } elseif ($scope === 'year') {
        $currentYear = (int)$today->format('Y');
        $selectedYear = preg_match('/^\d{4}$/', $value) ? (int)$value : $currentYear;
        $selectedYear = max(2000, min($currentYear, $selectedYear));
        $value = (string)$selectedYear;
        $dateCondition = "YEAR(%s) = {$selectedYear}";
        $periodLabel = $value;
    } else {
        $value = '';
    }

    $conditionFor = static function (string $column) use ($dateCondition): string {
        $placeholderCount = substr_count($dateCondition, '%s');
        return $placeholderCount > 0
            ? vsprintf($dateCondition, array_fill(0, $placeholderCount, $column))
            : $dateCondition;
    };

    $violationWhere = $conditionFor('v.violation_date') . " AND LOWER(v.status) <> 'cancelled'";
    $paymentWhere = $conditionFor('DATE(p.payment_date)') . " AND LOWER(p.payment_status) = 'completed'";

    $violationSummary = fetch_one("
        SELECT COUNT(*) AS violation_count,
               COALESCE(SUM(v.penalty_amount), 0) AS estimated_penalties,
               COUNT(DISTINCT v.violation_location) AS affected_locations
        FROM violations v
        WHERE {$violationWhere}
    ") ?: [];

    $collectionSummary = fetch_one("
        SELECT COUNT(*) AS payment_count, COALESCE(SUM(p.amount_paid), 0) AS collected_amount
        FROM payments p
        WHERE {$paymentWhere}
    ") ?: [];

    $topViolation = fetch_one("
        SELECT item.violation_type, COUNT(*) AS total
        FROM violation_items item
        JOIN violations v ON v.violation_id = item.violation_id
        WHERE {$violationWhere}
        GROUP BY item.violation_type
        ORDER BY total DESC, item.violation_type ASC
        LIMIT 1
    ") ?: [];

    $topLocation = fetch_one("
        SELECT v.violation_location, COUNT(*) AS total
        FROM violations v
        WHERE {$violationWhere}
        GROUP BY v.violation_location
        ORDER BY total DESC, v.violation_location ASC
        LIMIT 1
    ") ?: [];

    $peakHour = fetch_one("
        SELECT HOUR(v.created_at) AS peak_hour, COUNT(*) AS total
        FROM violations v
        WHERE {$violationWhere}
        GROUP BY HOUR(v.created_at)
        ORDER BY total DESC, peak_hour ASC
        LIMIT 1
    ") ?: [];

    $labels = [];
    $violations = [];
    $collections = [];
    $violationRows = [];
    $collectionRows = [];

    if ($scope === 'week' && $weekStart instanceof DateTimeImmutable) {
        $weekStartValue = $weekStart->format('Y-m-d');
        for ($offset = 0; $offset < 7; $offset++) {
            $day = $weekStart->modify("+{$offset} days");
            $labels[$offset] = $day->format('D j');
            $violations[$offset] = 0;
            $collections[$offset] = 0.0;
        }
        $violationRows = fetch_all("
            SELECT DATEDIFF(v.violation_date, '{$weekStartValue}') AS bucket, COUNT(*) AS total
            FROM violations v
            WHERE {$violationWhere}
            GROUP BY DATEDIFF(v.violation_date, '{$weekStartValue}')
        ");
        $collectionRows = fetch_all("
            SELECT DATEDIFF(DATE(p.payment_date), '{$weekStartValue}') AS bucket,
                   COALESCE(SUM(p.amount_paid), 0) AS total
            FROM payments p
            WHERE {$paymentWhere}
            GROUP BY DATEDIFF(DATE(p.payment_date), '{$weekStartValue}')
        ");
    } elseif ($scope === 'month' && $selectedMonth instanceof DateTimeImmutable) {
        $daysInMonth = (int)$selectedMonth->format('t');
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $labels[$day] = (string)$day;
            $violations[$day] = 0;
            $collections[$day] = 0.0;
        }
        $violationRows = fetch_all("
            SELECT DAY(v.violation_date) AS bucket, COUNT(*) AS total
            FROM violations v
            WHERE {$violationWhere}
            GROUP BY DAY(v.violation_date)
        ");
        $collectionRows = fetch_all("
            SELECT DAY(p.payment_date) AS bucket, COALESCE(SUM(p.amount_paid), 0) AS total
            FROM payments p
            WHERE {$paymentWhere}
            GROUP BY DAY(p.payment_date)
        ");
    } elseif ($scope === 'year') {
        foreach (month_labels() as $index => $monthName) {
            $bucket = $index + 1;
            $labels[$bucket] = $monthName;
            $violations[$bucket] = 0;
            $collections[$bucket] = 0.0;
        }
        $violationRows = fetch_all("
            SELECT MONTH(v.violation_date) AS bucket, COUNT(*) AS total
            FROM violations v
            WHERE {$violationWhere}
            GROUP BY MONTH(v.violation_date)
        ");
        $collectionRows = fetch_all("
            SELECT MONTH(p.payment_date) AS bucket, COALESCE(SUM(p.amount_paid), 0) AS total
            FROM payments p
            WHERE {$paymentWhere}
            GROUP BY MONTH(p.payment_date)
        ");
    } else {
        $violationRows = fetch_all("
            SELECT YEAR(v.violation_date) AS bucket, COUNT(*) AS total
            FROM violations v
            WHERE LOWER(v.status) <> 'cancelled'
            GROUP BY YEAR(v.violation_date)
        ");
        $collectionRows = fetch_all("
            SELECT YEAR(p.payment_date) AS bucket, COALESCE(SUM(p.amount_paid), 0) AS total
            FROM payments p
            WHERE LOWER(p.payment_status) = 'completed'
            GROUP BY YEAR(p.payment_date)
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

    $chartPrefix = match ($scope) {
        'week', 'month' => 'Daily',
        'year' => 'Monthly',
        default => 'Yearly',
    };
    $chartPeriodLabel = $scope === 'all' ? 'Overall' : $periodLabel;
    $peakHourValue = isset($peakHour['peak_hour'])
        ? date('g A', mktime((int)$peakHour['peak_hour'], 0))
        : 'No data';

    return [
        'success' => true,
        'scope' => $scope,
        'value' => $value,
        'period_label' => $periodLabel,
        'summary' => 'Operational indicators generated directly from MySQL records · Showing ' . $periodLabel,
        'collected_amount' => (float)($collectionSummary['collected_amount'] ?? 0),
        'payment_count' => (int)($collectionSummary['payment_count'] ?? 0),
        'violation_count' => (int)($violationSummary['violation_count'] ?? 0),
        'estimated_penalties' => (float)($violationSummary['estimated_penalties'] ?? 0),
        'affected_locations' => (int)($violationSummary['affected_locations'] ?? 0),
        'peak_hour' => $peakHourValue,
        'top_violation' => (string)($topViolation['violation_type'] ?? 'No data'),
        'top_violation_count' => (int)($topViolation['total'] ?? 0),
        'top_location' => (string)($topLocation['violation_location'] ?? 'No data'),
        'top_location_count' => (int)($topLocation['total'] ?? 0),
        'violation_chart_description' => "{$chartPrefix} records · {$chartPeriodLabel}",
        'collection_chart_description' => "{$chartPrefix} completed payments · {$chartPeriodLabel}",
        'labels' => array_values($labels),
        'violations' => array_values($violations),
        'collections' => array_values($collections),
    ];
}
