<?php
require_once __DIR__ . '/layout.php';

$newViolations = fetch_all("
    SELECT violation_id, ticket_number, plate_number, violation_type, violation_location, created_at
    FROM violations
    ORDER BY created_at DESC
    LIMIT 5
");

$dueSoon = fetch_all("
    SELECT violation_id, ticket_number, plate_number, violation_date
    FROM violations
    WHERE status IN ('pending', 'overdue')
      AND violation_date >= CURDATE()
      AND violation_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)
    ORDER BY violation_date ASC
    LIMIT 5
");

$dueSoonCount = count($dueSoon);

$recentCompletedPayments = fetch_all("
    SELECT p.payment_id, p.amount_paid, p.payment_date, v.plate_number
    FROM payments p
    JOIN violations v ON v.violation_id = p.violation_id
    WHERE p.payment_status = 'completed'
    ORDER BY p.payment_date DESC
    LIMIT 5
");

$systemAlerts = fetch_all("
    SELECT alert_id, message, severity, generated_at
    FROM monitoring_alerts
    WHERE status = 'active'
    ORDER BY generated_at DESC
    LIMIT 5
");

// Anything timestamped after the treasurer's last visit to this page is "unread".
// Read this BEFORE overwriting it so the current render still shows the correct state.
$lastReadAt = (string)($_SESSION['notif_last_read_at'] ?? '1970-01-01 00:00:00');
$_SESSION['notif_last_read_at'] = date('Y-m-d H:i:s');

function is_unread(string $datetime, string $lastReadAt): bool {
    return strtotime($datetime) > strtotime($lastReadAt);
}

function unread_dot(bool $unread): string {
    return $unread ? '<span class="unread-dot" aria-hidden="true"></span>' : '';
}

function unread_label(bool $unread): string {
    return $unread ? '<span class="unread-label">New</span>' : '';
}

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hr ago';
    if ($diff < 172800) return 'Yesterday';
    return floor($diff / 86400) . ' days ago';
}

page_start('Notifications', 'notifications', 'Search notifications...', 'System alerts and updates relevant to collections');
?>


<?php if ($dueSoonCount > 0): ?>
  <div class="notif-card tone-warning">
    <div class="d-flex justify-content-between">
      <strong>Payment Due Soon</strong>
      <small class="text-muted"><?= time_ago($dueSoon[0]['violation_date']) ?></small>
    </div>
    <div class="text-muted small mt-1"><?= num($dueSoonCount) ?> violation<?= $dueSoonCount === 1 ? '' : 's' ?> approaching the due date within 3 days.</div>
  </div>
<?php endif; ?>

<?php foreach ($newViolations as $v): $unread = is_unread($v['created_at'], $lastReadAt); ?>
  <div class="notif-card tone-info<?= $unread ? ' unread' : '' ?>">
    <div class="d-flex justify-content-between align-items-start">
      <strong><?= unread_dot($unread) ?>New Violation Recorded<?= unread_label($unread) ?></strong>
      <small class="text-muted"><?= esc(time_ago($v['created_at'])) ?></small>
    </div>
    <div class="text-muted small mt-1">Ticket <?= esc($v['ticket_number']) ?> &mdash; plate <?= esc($v['plate_number']) ?> flagged for <?= esc($v['violation_type']) ?> at <?= esc($v['violation_location']) ?>.</div>
  </div>
<?php endforeach; ?>

<?php foreach ($recentCompletedPayments as $p): $unread = is_unread($p['payment_date'], $lastReadAt); ?>
  <div class="notif-card tone-success<?= $unread ? ' unread' : '' ?>">
    <div class="d-flex justify-content-between align-items-start">
      <strong><?= unread_dot($unread) ?>Payment Completed<?= unread_label($unread) ?></strong>
      <small class="text-muted"><?= esc(time_ago($p['payment_date'])) ?></small>
    </div>
    <div class="text-muted small mt-1"><?= esc(payment_reference((int)$p['payment_id'])) ?> processed successfully for <?= peso($p['amount_paid']) ?> (plate <?= esc($p['plate_number']) ?>).</div>
  </div>
<?php endforeach; ?>

<?php foreach ($systemAlerts as $a): $unread = is_unread($a['generated_at'], $lastReadAt); ?>
  <div class="notif-card tone-danger<?= $unread ? ' unread' : '' ?>">
    <div class="d-flex justify-content-between align-items-start">
      <strong><?= unread_dot($unread) ?>System Alert<?= unread_label($unread) ?></strong>
      <small class="text-muted"><?= esc(time_ago($a['generated_at'])) ?></small>
    </div>
    <div class="text-muted small mt-1"><?= esc($a['message']) ?></div>
  </div>
<?php endforeach; ?>

<?php if (!$newViolations && !$recentCompletedPayments && !$systemAlerts && $dueSoonCount === 0): ?>
  <?php empty_state('You are all caught up. No new notifications.'); ?>
<?php endif; ?>

<?php page_end(); ?>