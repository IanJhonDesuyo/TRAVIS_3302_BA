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

$normalizedRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
if (!in_array($normalizedRole, ['administrator', 'treasurer', 'treasury personnel'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have permission to process payments']);
    exit;
}

require_once '../Web_app/db_connect.php';

$input = json_decode(file_get_contents('php://input'), true);

$violationId = $input['violation_id'] ?? null;
$paymentMethod = 'cash';
$amountPaid = $input['amount_paid'] ?? null;

if (!$violationId || !$amountPaid) {
    http_response_code(400);
    echo json_encode(['error' => 'Violation ID and amount are required']);
    exit;
}

// Start transaction
$pdo->beginTransaction();

try {
    // Get violation details
    $stmt = $pdo->prepare("SELECT penalty_amount FROM violations WHERE violation_id = ? AND status IN ('pending', 'overdue')");
    $stmt->execute([$violationId]);
    $violation = $stmt->fetch();
    
    if (!$violation) {
        throw new Exception('Invalid violation or already paid');
    }
    
    // Insert payment
    $sql = "INSERT INTO payments (
        violation_id, amount_paid, payment_method, payment_status, 
        received_by, payment_date
    ) VALUES (?, ?, ?, 'completed', ?, NOW())";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $violationId,
        $amountPaid,
        $paymentMethod,
        $_SESSION['user_id']
    ]);
    
    $paymentId = $pdo->lastInsertId();
    
    // Update violation status
    $stmt = $pdo->prepare("UPDATE violations SET status = 'paid' WHERE violation_id = ?");
    $stmt->execute([$violationId]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'payment_id' => $paymentId
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
