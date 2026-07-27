<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

session_start();

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (strcasecmp(trim((string)($_SESSION['role'] ?? '')), 'Administrator') !== 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Only administrators can generate reports']);
    exit;
}

require_once '../Web_app/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

$reportType = $input['report_type'] ?? 'violations';
$dateFrom = $input['date_from'] ?? date('Y-m-01');
$dateTo = $input['date_to'] ?? date('Y-m-d');
$statusFilter = $input['status'] ?? '';
$locationFilter = $input['location'] ?? '';

// Build query based on report type
$sql = "";
$params = [];

switch ($reportType) {
    case 'violations':
        $sql = "SELECT * FROM violations WHERE DATE(created_at) BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];
        if (!empty($statusFilter)) {
            $sql .= " AND status = ?";
            $params[] = $statusFilter;
        }
        if (!empty($locationFilter)) {
            $sql .= " AND violation_location LIKE ?";
            $params[] = "%$locationFilter%";
        }
        $sql .= " ORDER BY created_at DESC";
        break;
        
    case 'payments':
        $sql = "SELECT p.*, v.ticket_number, v.driver_name, v.plate_number, v.violation_type 
                FROM payments p 
                JOIN violations v ON p.violation_id = v.violation_id 
                WHERE DATE(p.payment_date) BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];
        $sql .= " ORDER BY p.payment_date DESC";
        break;
        
    case 'monitoring':
        $sql = "SELECT * FROM camera_monitoring_logs WHERE DATE(recorded_at) BETWEEN ? AND ?";
        $params = [$dateFrom, $dateTo];
        if (!empty($statusFilter)) {
            $sql .= " AND congestion_level = ?";
            $params[] = $statusFilter;
        }
        $sql .= " ORDER BY recorded_at DESC";
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid report type']);
        exit;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll();

// Generate summary
$summary = [];
if ($reportType === 'violations') {
    $summary['Total Records'] = count($data);
    $summary['Paid Violations'] = count(array_filter($data, fn($v) => $v['status'] === 'paid'));
    $summary['Pending / Unpaid'] = count(array_filter($data, fn($v) => in_array($v['status'], ['pending', 'overdue'])));
    $totalPenalty = array_sum(array_column($data, 'penalty_amount'));
    $summary['Total Penalties'] = '₱' . number_format($totalPenalty, 2);
} elseif ($reportType === 'payments') {
    $summary['Total Transactions'] = count($data);
    $summary['Completed Payments'] = count(array_filter($data, fn($p) => $p['payment_status'] === 'completed'));
    $totalCollected = array_sum(array_column($data, 'amount_paid'));
    $summary['Total Collected'] = '₱' . number_format($totalCollected, 2);
} else {
    $summary['Monitoring Records'] = count($data);
    $summary['Vehicle Observations'] = array_sum(array_column($data, 'vehicle_count'));
    $summary['High Congestion Records'] = count(array_filter($data, fn($d) => in_array($d['congestion_level'], ['heavy', 'severe'])));
}

echo json_encode([
    'success' => true,
    'data' => $data,
    'summary' => $summary,
    'meta' => [
        'report_type' => $reportType,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'generated_at' => date('Y-m-d H:i:s')
    ]
]);
?>
