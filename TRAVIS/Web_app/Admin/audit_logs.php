<?php
declare(strict_types=1);
require_once __DIR__ . '/layout.php';

if (strcasecmp((string)($_SESSION['user']['role'] ?? ''), 'Administrator') !== 0) {
    http_response_code(403);
    $role = (string)($_SESSION['user']['role'] ?? 'Unknown role');
    $name = (string)($_SESSION['user']['name'] ?? 'Signed-in user');
    $destination = strcasecmp($role, 'Treasury Personnel') === 0
        ? '../Treasurer/dashboard.php'
        : '../auth/index.php';
    ?><!doctype html>
    <html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>TRAVIS — Access Restricted</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
      *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;font-family:Poppins,Arial,sans-serif;color:#102f49;background:linear-gradient(rgba(239,248,247,.88),rgba(239,248,247,.94)),url('../../assets/images/nasugbu-municipal-hall.jpg') center/cover}
      .access-card{width:min(100%,520px);padding:34px;text-align:center;background:rgba(255,255,255,.94);border:1px solid rgba(16,47,73,.15);border-radius:24px;box-shadow:0 24px 65px rgba(16,47,73,.18)}
      .access-icon{display:grid;width:68px;height:68px;margin:0 auto 18px;place-items:center;color:#b4232f;background:#fdecee;border-radius:20px;font-size:1.8rem}.eyebrow{color:#087d78;font-size:.75rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase}h1{margin:8px 0;font-size:1.55rem}p{margin:0 auto 18px;color:#61756f;line-height:1.65}.session{padding:12px;margin:0 0 20px;background:#f5f8f7;border-radius:12px;color:#425b55;font-size:.86rem}.actions{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}.actions form{margin:0}.btn{display:inline-flex;align-items:center;gap:7px;min-height:42px;padding:0 16px;color:#fff;text-decoration:none;background:#087d78;border:0;border-radius:11px;font:700 .86rem Poppins,Arial,sans-serif;cursor:pointer}.btn.secondary{color:#102f49;background:#fff;border:1px solid rgba(16,47,73,.18)}
    </style></head><body><main class="access-card">
      <div class="access-icon"><i class="bi bi-shield-lock"></i></div><div class="eyebrow">Protected administration module</div>
      <h1>Administrator access is required</h1>
      <p>The Audit Log contains security-sensitive system activity and is available only to accounts with the Administrator role.</p>
      <div class="session">Currently signed in as <strong><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></strong> · <?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></div>
      <div class="actions"><a class="btn" href="<?= htmlspecialchars($destination, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-arrow-left"></i>Return to your portal</a><form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"><button class="btn secondary" type="submit"><i class="bi bi-person-lock"></i>Use an Admin account</button></form></div>
    </main></body></html><?php
    exit;
}

travis_ensure_audit_table($conn);

$search = trim((string)($_GET['search'] ?? ''));
$module = trim((string)($_GET['module'] ?? ''));
$outcome = trim((string)($_GET['outcome'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));

$where = ['1=1'];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(a.actor_name LIKE ? OR a.action LIKE ? OR a.description LIKE ? OR a.entity_id LIKE ? OR a.ip_address LIKE ?)';
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
    $types .= 'sssss';
}
if ($module !== '') { $where[] = 'a.module = ?'; $params[] = $module; $types .= 's'; }
if (in_array($outcome, ['success', 'failed', 'warning'], true)) { $where[] = 'a.outcome = ?'; $params[] = $outcome; $types .= 's'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) { $where[] = 'DATE(a.created_at) >= ?'; $params[] = $dateFrom; $types .= 's'; }
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) { $where[] = 'DATE(a.created_at) <= ?'; $params[] = $dateTo; $types .= 's'; }

$sql = 'SELECT a.* FROM audit_logs a WHERE ' . implode(' AND ', $where) . ' ORDER BY a.created_at DESC, a.audit_id DESC LIMIT 500';
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$modules = fetch_all('SELECT DISTINCT module FROM audit_logs ORDER BY module');
$todayCount = scalar("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at)=CURDATE()", 0);
$failedCount = scalar("SELECT COUNT(*) FROM audit_logs WHERE outcome='failed'", 0);
$userCount = scalar('SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE user_id IS NOT NULL', 0);
$totalCount = scalar('SELECT COUNT(*) FROM audit_logs', 0);

page_start('Audit Log', 'audit-logs', 'Search audit records...', 'Review security-sensitive and administrative activity.');
?>

<div class="row g-3 mb-4">
  <?php foreach ([['Total Events',$totalCount,'bi-list-check','tone-primary'],['Events Today',$todayCount,'bi-calendar-check','tone-success'],['Recorded Users',$userCount,'bi-people','tone-navy'],['Failed Actions',$failedCount,'bi-exclamation-triangle','tone-danger']] as [$label,$value,$icon,$tone]): ?>
    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon <?= $tone ?>"><i class="bi <?= $icon ?>"></i></div><div class="stat-label"><?= esc($label) ?></div><div class="stat-value"><?= num($value) ?></div></div></div>
  <?php endforeach; ?>
</div>

<div class="section-card mb-4">
  <div class="section-head"><div><h6 class="mb-0">Audit Trail</h6><small class="text-muted">Newest activity appears first. Audit records are read-only.</small></div></div>
  <form method="get" class="row g-2 align-items-end mb-3">
    <div class="col-lg-4"><label class="form-label">Search</label><input class="form-control" name="search" value="<?= esc($search) ?>" placeholder="User, action, description, target, or IP"></div>
    <div class="col-sm-6 col-lg-2"><label class="form-label">Module</label><select class="form-select" name="module"><option value="">All modules</option><?php foreach ($modules as $item): $value=(string)$item['module']; ?><option value="<?= esc($value) ?>" <?= $module===$value?'selected':'' ?>><?= esc(ucwords(str_replace('_',' ',$value))) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-6 col-lg-2"><label class="form-label">Outcome</label><select class="form-select" name="outcome"><option value="">All outcomes</option><?php foreach (['success','warning','failed'] as $value): ?><option value="<?= $value ?>" <?= $outcome===$value?'selected':'' ?>><?= ucfirst($value) ?></option><?php endforeach; ?></select></div>
    <div class="col-sm-6 col-lg-2"><label class="form-label">From</label><input type="date" class="form-control" name="date_from" value="<?= esc($dateFrom) ?>"></div>
    <div class="col-sm-6 col-lg-2"><label class="form-label">To</label><input type="date" class="form-control" name="date_to" value="<?= esc($dateTo) ?>"></div>
    <div class="col-12 d-flex gap-2"><button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Apply Filters</button><a class="btn btn-light" href="audit_logs.php"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a></div>
  </form>

  <?php if (!$logs): ?>
    <?php empty_state('No audit activity matches the selected filters.'); ?>
  <?php else: ?>
    <div class="table-responsive table-scroll" style="max-height:620px">
      <table class="table align-middle mb-0">
        <thead><tr><th>Date & Time</th><th>User</th><th>Module</th><th>Action</th><th>Activity</th><th>Outcome</th><th>IP Address</th></tr></thead>
        <tbody><?php foreach ($logs as $log): ?><tr>
          <td class="text-nowrap"><strong><?= esc(date('M j, Y', strtotime($log['created_at']))) ?></strong><small class="d-block text-muted"><?= esc(date('g:i:s A', strtotime($log['created_at']))) ?></small></td>
          <td><span class="fw-semibold"><?= esc($log['actor_name']) ?></span><small class="d-block text-muted"><?= esc($log['actor_role'] ?: 'Unknown role') ?></small></td>
          <td><?= esc(ucwords(str_replace('_',' ',$log['module']))) ?></td>
          <td><span class="tag tag-info"><?= esc(ucwords(str_replace('_',' ',$log['action']))) ?></span></td>
          <td style="min-width:260px"><?= esc($log['description']) ?><?php if ($log['entity_type']): ?><small class="d-block text-muted"><?= esc($log['entity_type']) ?><?= $log['entity_id'] ? ' #' . esc($log['entity_id']) : '' ?></small><?php endif; ?></td>
          <td><span class="tag <?= $log['outcome']==='success'?'tag-success':($log['outcome']==='failed'?'tag-danger':'tag-warning') ?>"><?= esc(ucfirst($log['outcome'])) ?></span></td>
          <td class="text-nowrap"><?= esc($log['ip_address'] ?: 'N/A') ?></td>
        </tr><?php endforeach; ?></tbody>
      </table>
    </div>
    <small class="text-muted d-block mt-2">Showing <?= num(count($logs)) ?> of <?= num($totalCount) ?> recorded event(s). Latest 500 matching events are displayed.</small>
  <?php endif; ?>
</div>

<?php page_end(); ?>
