<?php
// GET /api/stats.php -> dashboard summary
require_once __DIR__ . '/db.php';

$today = date('Y-m-d');
$monthStart = date('Y-m-01');

$q = fn($sql, $params=[]) => (function() use ($sql, $params) {
  $s = db()->prepare($sql); $s->execute($params); return $s->fetchColumn();
})();

ok([
  'totalViolations'    => (int) $q("SELECT COUNT(*) FROM violations"),
  'pendingPayments'    => (int) $q("SELECT COUNT(*) FROM violations WHERE payment_status='Pending'"),
  'paidViolations'     => (int) $q("SELECT COUNT(*) FROM violations WHERE payment_status='Paid'"),
  'todaysCollections'  => (float) $q("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date=?", [$today]),
  'monthlyCollections' => (float) $q("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_date>=?", [$monthStart]),
]);
