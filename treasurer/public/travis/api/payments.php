<?php
// POST /api/payments.php  -> save a payment
// GET  /api/payments.php  -> list payments
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $d = body();
  foreach (['violation_id','plate_number','fine_amount','receipt_no','payment_date','payment_method'] as $k) {
    if (empty($d[$k])) fail("Missing field: $k");
  }
  $stmt = db()->prepare("INSERT INTO payments
    (receipt_no, violation_id, plate_number, amount, payment_date, payment_method, notes, processed_by)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
  $stmt->execute([
    $d['receipt_no'], $d['violation_id'], $d['plate_number'],
    $d['fine_amount'], $d['payment_date'], $d['payment_method'],
    $d['notes'] ?? null, $d['processed_by'] ?? 'Treasurer'
  ]);
  db()->prepare("UPDATE violations SET payment_status='Paid' WHERE id=?")
      ->execute([$d['violation_id']]);
  ok(['receipt_no' => $d['receipt_no']]);
}

// GET (with optional ?from=&to=&search=)
$sql = "SELECT receipt_no, violation_id, plate_number, amount, payment_date, processed_by
        FROM payments WHERE 1=1";
$params = [];
if (!empty($_GET['from'])) { $sql .= " AND payment_date >= ?"; $params[] = $_GET['from']; }
if (!empty($_GET['to']))   { $sql .= " AND payment_date <= ?"; $params[] = $_GET['to']; }
if (!empty($_GET['search'])){$sql .= " AND (receipt_no LIKE ? OR plate_number LIKE ?)";
  $s = "%".$_GET['search']."%"; array_push($params, $s, $s); }
$sql .= " ORDER BY payment_date DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
ok($stmt->fetchAll());
