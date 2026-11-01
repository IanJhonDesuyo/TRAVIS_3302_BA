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
    if (!in_array($period, ['day', 'week', 'month', 'year', 'all'], true)) {
        $period = 'day';
    }

    $today = new DateTimeImmutable('today');
    $dateValue = trim((string)($_GET['date'] ?? $today->format('Y-m-d')));
    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $dateValue) {
        $parsedDate = $today;
        $dateValue = $today->format('Y-m-d');
    }

    $monthValue = trim((string)($_GET['month'] ?? $parsedDate->format('Y-m')));
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $monthValue)) {
        $monthValue = $parsedDate->format('Y-m');
    }

    $yearValue = (int)($_GET['year'] ?? $parsedDate->format('Y'));
    if ($yearValue < 2000 || $yearValue > 2100) {
        $yearValue = (int)$parsedDate->format('Y');
    }

    $dailySql = "
        SELECT recorded_date,
               SUM(inbound_total) AS inbound_total,
               SUM(outbound_total) AS outbound_total,
               SUM(inbound_total + outbound_total) AS vehicle_total
        FROM (
            SELECT DATE(recorded_at) AS recorded_date, camera_id,
                   MAX(inbound_count) AS inbound_total,
                   MAX(outbound_count) AS outbound_total
            FROM camera_monitoring_logs
            GROUP BY DATE(recorded_at), camera_id
        ) daily_camera
        GROUP BY recorded_date
    ";

    $condition = '1=1';
    $label = 'All records';
    $groupExpression = 'YEAR(recorded_date)';
    $labelExpression = 'YEAR(recorded_date)';

    if ($period === 'day') {
        $condition = "recorded_date = " . $pdo->quote($dateValue);
        $label = $parsedDate->format('F j, Y');
        $groupExpression = 'recorded_date';
        $labelExpression = "DATE_FORMAT(recorded_date, '%b %e')";
    } elseif ($period === 'week') {
        $weekStart = $parsedDate->modify('monday this week');
        $weekEnd = $weekStart->modify('+6 days');
        $condition = "recorded_date BETWEEN " . $pdo->quote($weekStart->format('Y-m-d')) . " AND " . $pdo->quote($weekEnd->format('Y-m-d'));
        $label = $weekStart->format('M j') . ' - ' . $weekEnd->format('M j, Y');
        $groupExpression = 'recorded_date';
        $labelExpression = "DATE_FORMAT(recorded_date, '%a')";
    } elseif ($period === 'month') {
        $condition = "DATE_FORMAT(recorded_date, '%Y-%m') = " . $pdo->quote($monthValue);
        $label = (new DateTimeImmutable($monthValue . '-01'))->format('F Y');
        $groupExpression = 'recorded_date';
        $labelExpression = "DATE_FORMAT(recorded_date, '%b %e')";
    } elseif ($period === 'year') {
        $condition = "YEAR(recorded_date) = {$yearValue}";
        $label = (string)$yearValue;
        $groupExpression = 'MONTH(recorded_date)';
        $labelExpression = "DATE_FORMAT(recorded_date, '%b')";
    }

    $summary = $pdo->query("
        SELECT COALESCE(SUM(vehicle_total), 0) AS vehicle_total,
               COALESCE(SUM(inbound_total), 0) AS inbound_total,
               COALESCE(SUM(outbound_total), 0) AS outbound_total,
               COUNT(*) AS recorded_days,
               COALESCE(AVG(vehicle_total), 0) AS daily_average
        FROM ({$dailySql}) daily_totals
        WHERE {$condition}
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $busiest = $pdo->query("
        SELECT recorded_date, vehicle_total
        FROM ({$dailySql}) daily_totals
        WHERE {$condition}
        ORDER BY vehicle_total DESC, recorded_date DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC) ?: [];

    $series = $pdo->query("
        SELECT {$labelExpression} AS label,
               SUM(vehicle_total) AS vehicle_total,
               SUM(inbound_total) AS inbound_total,
               SUM(outbound_total) AS outbound_total,
               MIN(recorded_date) AS sort_date
        FROM ({$dailySql}) daily_totals
        WHERE {$condition}
        GROUP BY {$groupExpression}, {$labelExpression}
        ORDER BY sort_date
    ")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => [
            'period' => $period,
            'period_label' => $label,
            'vehicle_total' => (int)($summary['vehicle_total'] ?? 0),
            'inbound_total' => (int)($summary['inbound_total'] ?? 0),
            'outbound_total' => (int)($summary['outbound_total'] ?? 0),
            'recorded_days' => (int)($summary['recorded_days'] ?? 0),
            'daily_average' => (int)round((float)($summary['daily_average'] ?? 0)),
            'busiest_date' => isset($busiest['recorded_date']) ? date('F j, Y', strtotime((string)$busiest['recorded_date'])) : null,
            'busiest_total' => (int)($busiest['vehicle_total'] ?? 0),
            'series' => array_map(static fn(array $row): array => [
                'label' => (string)$row['label'],
                'vehicle_total' => (int)$row['vehicle_total'],
                'inbound_total' => (int)$row['inbound_total'],
                'outbound_total' => (int)$row['outbound_total'],
            ], $series),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('get_traffic_analytics.php: ' . $error->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load traffic analytics']);
}
