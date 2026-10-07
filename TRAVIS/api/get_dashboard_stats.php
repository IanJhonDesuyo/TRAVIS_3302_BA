<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../Web_app/db_connect.php';

$period = strtolower(trim((string)($_GET['period'] ?? 'day')));
$day = trim((string)($_GET['dashboard_day'] ?? date('Y-m-d')));
$month = trim((string)($_GET['dashboard_month'] ?? date('Y-m')));
$year = trim((string)($_GET['dashboard_year'] ?? date('Y')));
$parsedDay = DateTime::createFromFormat('!Y-m-d', $day);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) || !$parsedDay || $parsedDay->format('Y-m-d') !== $day) $day = date('Y-m-d');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) $month = date('Y-m');
if (!preg_match('/^\d{4}$/', $year) || (int)$year < 2000 || (int)$year > 2100) $year = date('Y');
if (!in_array($period, ['day', 'month', 'year', 'all'], true)) $period = 'day';
$dateWhere = static function (string $column) use ($period, $day, $month, $year): string {
    return match ($period) {
        'day' => "DATE($column) = '{$day}'",
        'month' => "DATE_FORMAT($column, '%Y-%m') = '{$month}'",
        'year' => "YEAR($column) = {$year}",
        default => '1=1',
    };
};

// Get today's violations
$stmt = $pdo->query("SELECT COUNT(*) FROM violations WHERE " . $dateWhere('violation_date'));
$violationsToday = $stmt->fetchColumn();

// Get pending violations
$stmt = $pdo->query("SELECT COUNT(*) as count FROM violations WHERE status IN ('pending', 'overdue') AND " . $dateWhere('violation_date'));
$pendingViolations = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(penalty_amount), 0) FROM violations WHERE status IN ('pending', 'overdue') AND " . $dateWhere('violation_date'));
$pendingAmount = $stmt->fetchColumn();

// Get paid violations today
$stmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'completed' AND " . $dateWhere('payment_date'));
$paidToday = $stmt->fetchColumn();

// Get total collected today
$stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'completed' AND " . $dateWhere('payment_date'));
$collectedToday = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'completed' AND YEARWEEK(payment_date, 1) = YEARWEEK(CURDATE(), 1)");
$collectedThisWeek = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'completed' AND YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())");
$collectedThisMonth = $stmt->fetchColumn();

// Get active alerts
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM monitoring_alerts WHERE status = 'active'");
$stmt->execute();
$activeAlerts = $stmt->fetchColumn();

// Get total cameras
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cameras");
$stmt->execute();
$totalCameras = $stmt->fetchColumn();

// A camera is online only while the edge worker is publishing fresh frames.
// The cameras.status column is configuration data and can remain "online"
// after the physical camera or analysis process has stopped.
$latestStatusPath = __DIR__ . '/../Web_app/api/latest_status.json';
$analysisStatusPath = __DIR__ . '/../Web_app/api/analysis_status.json';
$latestStatus = is_file($latestStatusPath) ? json_decode((string)file_get_contents($latestStatusPath), true) : [];
$analysisStatus = is_file($analysisStatusPath) ? json_decode((string)file_get_contents($analysisStatusPath), true) : [];
$latestStatus = is_array($latestStatus) ? $latestStatus : [];
$analysisStatus = is_array($analysisStatus) ? $analysisStatus : [];
$frameAge = time() - (int)($latestStatus['updated_at_epoch'] ?? 0);
$analysisState = strtolower((string)($analysisStatus['analysis_status'] ?? 'idle'));
$latestAiState = strtolower((string)($latestStatus['ai_status'] ?? 'offline'));
$cameraLive = $frameAge >= 0 && $frameAge <= 6
    && !in_array($analysisState, ['idle', 'stopped', 'completed', 'error'], true)
    && !in_array($latestAiState, ['offline', 'stopped', 'completed', 'error'], true);
$onlineCameras = $cameraLive ? min(1, (int)$totalCameras) : 0;

echo json_encode([
    'success' => true,
    'data' => [
        'violations_today' => (int)$violationsToday,
        'pending_violations' => (int)$pendingViolations,
        'pending_amount' => (float)$pendingAmount,
        'paid_today' => (int)$paidToday,
        'collected_today' => (float)$collectedToday,
        'collected_this_week' => (float)$collectedThisWeek,
        'collected_this_month' => (float)$collectedThisMonth,
        'active_alerts' => (int)$activeAlerts,
        'online_cameras' => (int)$onlineCameras,
        'total_cameras' => (int)$totalCameras,
        'camera_live' => $cameraLive,
        'analysis_status' => $cameraLive ? 'Running' : ucfirst($analysisState ?: 'idle'),
        'source_type' => (string)($analysisStatus['source_type'] ?? $latestStatus['source_type'] ?? '')
        ,'selected_period' => $period,
        'period_label' => match ($period) {
            'day' => date('F j, Y', strtotime($day)),
            'month' => date('F Y', strtotime($month . '-01')),
            'year' => $year,
            default => 'All records',
        }
    ]
]);
?>
