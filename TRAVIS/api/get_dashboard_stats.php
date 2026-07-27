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

$today = date('Y-m-d');

// Get today's violations
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$violationsToday = $stmt->fetchColumn();

// Get pending violations
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE status IN ('pending', 'overdue')");
$stmt->execute();
$pendingViolations = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(penalty_amount), 0) FROM violations WHERE status IN ('pending', 'overdue')");
$pendingAmount = $stmt->fetchColumn();

// Get paid violations today
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM payments WHERE DATE(payment_date) = ?");
$stmt->execute([$today]);
$paidToday = $stmt->fetchColumn();

// Get total collected today
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) as total FROM payments WHERE DATE(payment_date) = ?");
$stmt->execute([$today]);
$collectedToday = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'completed' AND YEARWEEK(payment_date, 1) = YEARWEEK(CURDATE(), 1)");
$collectedThisWeek = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE payment_status = 'completed' AND YEAR(payment_date) = YEAR(CURDATE()) AND MONTH(payment_date) = MONTH(CURDATE())");
$collectedThisMonth = $stmt->fetchColumn();

// Get active alerts
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM monitoring_alerts WHERE status = 'active'");
$stmt->execute();
$activeAlerts = $stmt->fetchColumn();

// Get online cameras
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cameras WHERE status = 'online'");
$stmt->execute();
$onlineCameras = $stmt->fetchColumn();

// Get total cameras
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cameras");
$stmt->execute();
$totalCameras = $stmt->fetchColumn();

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
        'total_cameras' => (int)$totalCameras
    ]
]);
?>
