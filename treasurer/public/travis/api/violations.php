<?php
// GET /api/violations.php  -> list violations (with optional ?status=&type=&search=)
require_once __DIR__ . '/db.php';

$status = $_GET['status'] ?? null;
$type   = $_GET['type']   ?? null;
$search = $_GET['search'] ?? null;

$sql = "SELECT id, plate_number, vehicle_type, violation_type, location,
               violation_datetime AS dt, fine_amount, payment_status
        FROM violations WHERE 1=1";
$params = [];
if ($status) { $sql .= " AND payment_status = ?"; $params[] = $status; }
if ($type)   { $sql .= " AND violation_type = ?"; $params[] = $type; }
if ($search) { $sql .= " AND (plate_number LIKE ? OR id LIKE ? OR location LIKE ?)";
               $s = "%$search%"; array_push($params, $s, $s, $s); }
$sql .= " ORDER BY violation_datetime DESC LIMIT 200";

$stmt = db()->prepare($sql);
$stmt->execute($params);
ok($stmt->fetchAll());
