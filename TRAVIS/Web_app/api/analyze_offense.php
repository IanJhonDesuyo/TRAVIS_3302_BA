<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Admin/helpers.php';

header('Content-Type: application/json; charset=utf-8');

$license = trim((string)($_GET['license_number'] ?? ''));
$plate = strtoupper(trim((string)($_GET['plate_number'] ?? '')));
$violationType = trim((string)($_GET['violation_type'] ?? ''));

if ($violationType === '' || !in_array($violationType, traffic_violation_types(), true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Select a valid violation type first.']);
    exit;
}

if ($license === '' && $plate === '') {
    echo json_encode(['success' => true, 'data' => ['previous_offenses' => 0, 'suggested_offense' => 1, 'matched_by' => null]]);
    exit;
}

if ($license !== '') {
    $stmt = $conn->prepare("SELECT violation_type, violation_date, ticket_number FROM violations WHERE license_number = ? AND status <> 'cancelled' ORDER BY violation_date DESC, violation_id DESC");
    $stmt->bind_param('s', $license);
    $matchedBy = 'license number';
} else {
    $stmt = $conn->prepare("SELECT violation_type, violation_date, ticket_number FROM violations WHERE plate_number = ? AND status <> 'cancelled' ORDER BY violation_date DESC, violation_id DESC");
    $stmt->bind_param('s', $plate);
    $matchedBy = 'plate number';
}

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$category = traffic_violation_category($violationType);
$matching = array_values(array_filter($rows, static fn(array $row): bool => traffic_violation_category((string)$row['violation_type']) === $category));
$previous = count($matching);
$maximum = $category === 'coding' ? 4 : 3;
$suggested = min($maximum, $previous + 1);
$atMaximum = $previous >= $maximum;

echo json_encode([
    'success' => true,
    'data' => [
        'category' => $category,
        'previous_offenses' => $previous,
        'suggested_offense' => $suggested,
        'maximum_offense' => $maximum,
        'at_maximum' => $atMaximum,
        'matched_by' => $matchedBy,
        'last_violation_date' => $matching[0]['violation_date'] ?? null,
        'last_ticket_number' => $matching[0]['ticket_number'] ?? null,
    ],
]);
