<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../auth/session.php';
travis_session_start();
if (!travis_is_authenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

require_once __DIR__ . '/../db_connect.php';

$date = trim((string)($_GET['date'] ?? date('Y-m-d')));
$parsed = DateTime::createFromFormat('!Y-m-d', $date);
if (!$parsed || $parsed->format('Y-m-d') !== $date) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Select a valid date.']);
    exit;
}

$dailyTotalsSql = "
    SELECT recorded_date, SUM(inbound_total + outbound_total) AS vehicle_total,
           SUM(inbound_total) AS inbound_total, SUM(outbound_total) AS outbound_total
    FROM (
        SELECT DATE(recorded_at) AS recorded_date, camera_id,
               MAX(inbound_count) AS inbound_total, MAX(outbound_count) AS outbound_total
        FROM camera_monitoring_logs
        GROUP BY DATE(recorded_at), camera_id
    ) daily_camera
    GROUP BY recorded_date
";

$statement = $pdo->prepare("SELECT * FROM ($dailyTotalsSql) totals WHERE recorded_date = ? LIMIT 1");
$statement->execute([$date]);
$selected = $statement->fetch(PDO::FETCH_ASSOC) ?: ['vehicle_total' => 0, 'inbound_total' => 0, 'outbound_total' => 0];

$summary = $pdo->query("SELECT COALESCE(AVG(vehicle_total), 0) AS daily_average, COUNT(*) AS recorded_days FROM ($dailyTotalsSql) totals")->fetch(PDO::FETCH_ASSOC);
$busiest = $pdo->query("SELECT recorded_date, vehicle_total FROM ($dailyTotalsSql) totals ORDER BY vehicle_total DESC, recorded_date DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$total = (int)$selected['vehicle_total'];
$average = (float)($summary['daily_average'] ?? 0);
$differencePercent = $average > 0 ? (($total - $average) / $average) * 100 : 0;

echo json_encode([
    'success' => true,
    'data' => [
        'date' => $date,
        'date_label' => date('F j, Y', strtotime($date)),
        'vehicle_total' => $total,
        'inbound_total' => (int)$selected['inbound_total'],
        'outbound_total' => (int)$selected['outbound_total'],
        'daily_average' => (int)round($average),
        'difference_percent' => round($differencePercent, 1),
        'recorded_days' => (int)($summary['recorded_days'] ?? 0),
        'busiest_date' => $busiest ? date('F j, Y', strtotime((string)$busiest['recorded_date'])) : null,
        'busiest_total' => (int)($busiest['vehicle_total'] ?? 0),
    ],
], JSON_UNESCAPED_SLASHES);
