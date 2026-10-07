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

// Kunin ang mga metrics
$today = date('Y-m-d');

// Bilang ng violations ngayon
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE DATE(created_at) = ?");
$stmt->execute([$today]);
$violationsToday = (int)$stmt->fetchColumn();

// Bilang ng active alerts
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM monitoring_alerts WHERE status = 'active'");
$stmt->execute();
$activeAlerts = (int)$stmt->fetchColumn();

// Bilang ng pending violations
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM violations WHERE status IN ('pending', 'overdue')");
$stmt->execute();
$pendingViolations = (int)$stmt->fetchColumn();

// Determine risk level
$riskScore = ($violationsToday * 2) + ($activeAlerts * 3) + ($pendingViolations * 1);
$riskLevel = 'Low';
$confidence = 0;

if ($riskScore < 50) {
    $riskLevel = 'Low';
    $confidence = 70 + ($riskScore / 50) * 20;
    $recommendations = [
        'Continue routine monitoring',
        'Use periodic observation instead of a permanent checkpoint',
        'No immediate action required'
    ];
} elseif ($riskScore < 100) {
    $riskLevel = 'Medium';
    $confidence = 75 + (($riskScore - 50) / 50) * 15;
    $recommendations = [
        'Run a focused 14-day intervention for the leading issue',
        'Use targeted reminders, signage, or random compliance checks',
        'Compare results twice each week'
    ];
} elseif ($riskScore < 200) {
    $riskLevel = 'High';
    $confidence = 80 + (($riskScore - 100) / 100) * 10;
    $recommendations = [
        'Run a targeted 7-day intervention at the priority location and peak period',
        'Match the response to the leading violation or congestion cause',
        'Review outcomes daily and coordinate with relevant local stakeholders'
    ];
} else {
    $riskLevel = 'Critical';
    $confidence = 90 + (($riskScore - 200) / 100) * 5;
    $recommendations = [
        'Verify current road conditions and activate temporary traffic control when required',
        'Coordinate with the LGU and emergency services for serious incidents',
        'Issue a time-specific public advisory or rerouting notice',
        'Review the intervention after 24 hours'
    ];
}

$confidence = min(round($confidence, 0), 98);

$month = date('F Y');

echo json_encode([
    'success' => true,
    'riskLevel' => $riskLevel,
    'confidence' => $confidence,
    'month' => $month,
    'recommendations' => array_slice($recommendations, 0, 3),
    'metrics' => [
        'violationsToday' => $violationsToday,
        'activeAlerts' => $activeAlerts,
        'pendingViolations' => $pendingViolations,
        'riskScore' => $riskScore
    ]
]);
?>
