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

$violationId = filter_var($input['violation_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$paymentMethod = 'cash';
$amountInput = $input['amount_paid'] ?? null;

if ($violationId === false || !is_scalar($amountInput)) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid violation ID and amount are required']);
    exit;
}

$amountText = trim((string)$amountInput);
if (!preg_match('/^(?:0|[1-9]\d{0,7})(?:\.\d{1,2})?$/', $amountText)) {
    http_response_code(400);
    echo json_encode(['error' => 'Amount must be a positive monetary value with at most two decimal places']);
    exit;
}

$amountPaid = number_format((float)$amountText, 2, '.', '');
if ($amountPaid === '0.00') {
    http_response_code(400);
    echo json_encode(['error' => 'Amount must be greater than zero']);
    exit;
}

// Start transaction
$pdo->beginTransaction();

try {
    // Get violation details
    $stmt = $pdo->prepare("SELECT penalty_amount FROM violations WHERE violation_id = ? AND status IN ('pending', 'overdue') FOR UPDATE");
    $stmt->execute([$violationId]);
    $violation = $stmt->fetch();
    
    if (!$violation) {
        throw new Exception('Invalid violation or already paid');
    }

    $penaltyAmount = number_format((float)$violation['penalty_amount'], 2, '.', '');
    if ($amountPaid !== $penaltyAmount) {
        throw new Exception('The amount paid must exactly match the recorded penalty amount');
    }

    $stmt = $pdo->prepare("SELECT payment_id FROM payments WHERE violation_id = ? LIMIT 1");
    $stmt->execute([$violationId]);
    if ($stmt->fetch()) {
        throw new Exception('A payment already exists for this violation');
    }

    $receiptReference = 'TRV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
    
    // Insert payment
    $sql = "INSERT INTO payments (
        violation_id, amount_paid, payment_method, payment_status, 
        received_by, payment_date, receipt_reference
    ) VALUES (?, ?, ?, 'completed', ?, NOW(), ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $violationId,
        $amountPaid,
        $paymentMethod,
        $_SESSION['user_id'],
        $receiptReference
    ]);
    
    $paymentId = $pdo->lastInsertId();
    
    // Update violation status
    $stmt = $pdo->prepare("UPDATE violations SET status = 'paid' WHERE violation_id = ? AND status IN ('pending', 'overdue')");
    $stmt->execute([$violationId]);
    if ($stmt->rowCount() !== 1) {
        throw new Exception('The violation status changed while the payment was being processed');
    }
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Payment processed successfully',
        'payment_id' => $paymentId,
        'receipt_reference' => $receiptReference
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
