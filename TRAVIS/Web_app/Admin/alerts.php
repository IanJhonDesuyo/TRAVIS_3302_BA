<?php
require_once __DIR__ . '/layout.php';
$critical = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE severity='critical'", 0);
$warning = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE severity='warning'", 0);
$info = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE severity='info'", 0);
$resolved = scalar("SELECT COUNT(*) FROM monitoring_alerts WHERE status='resolved'", 0);
$alerts = fetch_all("SELECT a.*, u.full_name AS ack_by FROM monitoring_alerts a LEFT JOIN users u ON u.user_id=a.acknowledged_by ORDER BY a.generated_at DESC LIMIT 50");
page_start('Alerts', 'alerts', 'Search alerts...');
?>
<div class="d-flex justify-content-between flex-wrap mb-4 gap-2"><div><h3 class="page-title">Alerts & Notifications</h3><p class="page-sub">Real-time computer vision and system event stream</p></div><button class="btn btn-light" disabled><i class="bi bi-check2-all me-1"></i>Mark all read</button></div>
<div class="row g-3 mb-4"><div class="col-md-3"><div class="stat-card"><div class="stat-icon tone-danger"><i class="bi bi-exclamation-octagon"></i></div><div class="stat-label">Critical</div><div class="stat-value"><?= num($critical) ?></div></div></div><div class="col-md-3"><div class="stat-card"><div class="stat-icon tone-warning"><i class="bi bi-exclamation"></i></div><div class="stat-label">Warning</div><div class="stat-value"><?= num($warning) ?></div></div></div><div class="col-md-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-info-circle"></i></div><div class="stat-label">Info</div><div class="stat-value"><?= num($info) ?></div></div></div><div class="col-md-3"><div class="stat-card"><div class="stat-icon tone-success"><i class="bi bi-check2-circle"></i></div><div class="stat-label">Resolved</div><div class="stat-value"><?= num($resolved) ?></div></div></div></div>
<div class="section-card"><div class="section-head"><h6>Live Event Stream</h6><span class="tag tag-success"><span class="dot" style="background:#16a34a"></span> Database Connected</span></div>
<?php if (!$alerts): empty_state('No alerts found. Computer vision alerts will appear here after records are inserted into monitoring_alerts.'); else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Type</th><th>Message</th><th>Severity</th><th>Status</th><th>Generated</th></tr></thead><tbody><?php foreach($alerts as $a): ?><tr><td><?= esc(ucfirst($a['alert_type'])) ?></td><td><?= esc($a['message']) ?></td><td><span class="tag <?= tag_class($a['severity']) ?>"><?= esc($a['severity']) ?></span></td><td><span class="tag <?= tag_class($a['status']) ?>"><?= esc($a['status']) ?></span></td><td><?= esc($a['generated_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<?php page_end(); ?>
