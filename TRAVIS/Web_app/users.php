<?php
require_once __DIR__ . '/layout.php';
$totalUsers = scalar("SELECT COUNT(*) FROM users", 0);
$active = scalar("SELECT COUNT(*) FROM users WHERE status='active'", 0);
$inactive = scalar("SELECT COUNT(*) FROM users WHERE status='inactive'", 0);
$suspended = scalar("SELECT COUNT(*) FROM users WHERE status='suspended'", 0);
$users = fetch_all("SELECT * FROM users ORDER BY created_at DESC LIMIT 50");
page_start('Users', 'users', 'Search users...');
?>
<div class="d-flex justify-content-between flex-wrap mb-4 gap-2"><div><h3 class="page-title">User Management</h3><p class="page-sub">Manage administrators, TMO and Treasury personnel</p></div><button class="btn btn-primary" disabled><i class="bi bi-person-plus me-1"></i>Add User</button></div>
<div class="row g-3 mb-4"><div class="col-md-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-people"></i></div><div class="stat-label">Total Users</div><div class="stat-value"><?= num($totalUsers) ?></div></div></div><div class="col-md-3"><div class="stat-card"><div class="stat-icon tone-success"><i class="bi bi-person-check"></i></div><div class="stat-label">Active</div><div class="stat-value"><?= num($active) ?></div></div></div><div class="col-md-3"><div class="stat-card"><div class="stat-icon tone-warning"><i class="bi bi-person-dash"></i></div><div class="stat-label">Inactive</div><div class="stat-value"><?= num($inactive) ?></div></div></div><div class="col-md-3"><div class="stat-card"><div class="stat-icon tone-danger"><i class="bi bi-person-x"></i></div><div class="stat-label">Suspended</div><div class="stat-value"><?= num($suspended) ?></div></div></div></div>
<div class="section-card"><div class="section-head"><h6>System Users</h6></div><?php if (!$users): empty_state('No user accounts found. Add users in the database or user module.'); else: ?><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th></tr></thead><tbody><?php foreach($users as $u): ?><tr><td><div class="d-flex align-items-center gap-2"><span class="avatar"><?= esc(initials($u['full_name'])) ?></span><span class="fw-semibold"><?= esc($u['full_name']) ?></span></div></td><td><?= esc($u['email']) ?></td><td><?= esc($u['role']) ?></td><td><span class="tag <?= tag_class($u['status']) ?>"><?= esc($u['status']) ?></span></td><td class="text-muted"><?= esc($u['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
<?php page_end(); ?>
