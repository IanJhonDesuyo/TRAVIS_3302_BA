<?php
require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';

function user_post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function valid_user_role(string $role): bool
{
    return in_array($role, ['Administrator', 'Treasury Personnel'], true);
}

function valid_user_status(string $status): bool
{
    return in_array($status, ['active', 'inactive', 'suspended', 'pending'], true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add_user') {
        $fullName = user_post('full_name');
        $email = strtolower(user_post('email'));
        $password = (string)($_POST['password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');
        $role = user_post('role');
        $status = user_post('status', 'active');

        if (
            $fullName === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($password) < 8 ||
            $password !== $confirmPassword ||
            !valid_user_role($role) ||
            !valid_user_status($status)
        ) {
            $message = 'Please complete all fields correctly. Passwords must match and contain at least 8 characters.';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();

            if ($stmt->get_result()->fetch_assoc()) {
                $message = 'That email address is already assigned to another user.';
                $messageType = 'warning';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO users (full_name, email, password, role, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('sssss', $fullName, $email, $passwordHash, $role, $status);

                if ($stmt->execute()) {
                    $message = 'User account created successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to create the user account.';
                    $messageType = 'danger';
                }
            }
        }
    }

    if ($action === 'update_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $fullName = user_post('full_name');
        $email = strtolower(user_post('email'));
        $role = user_post('role');
        $status = user_post('status');

        if (
            $userId <= 0 ||
            $fullName === '' ||
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            !valid_user_role($role) ||
            !valid_user_status($status)
        ) {
            $message = 'Invalid user information.';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare("
                SELECT user_id
                FROM users
                WHERE email = ?
                  AND user_id <> ?
                LIMIT 1
            ");
            $stmt->bind_param('si', $email, $userId);
            $stmt->execute();

            if ($stmt->get_result()->fetch_assoc()) {
                $message = 'That email address is already used by another account.';
                $messageType = 'warning';
            } else {
                $stmt = $conn->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, role = ?, status = ?
                    WHERE user_id = ?
                ");
                $stmt->bind_param('ssssi', $fullName, $email, $role, $status, $userId);

                if ($stmt->execute()) {
                    $message = 'User account updated successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Failed to update the user account.';
                    $messageType = 'danger';
                }
            }
        }
    }

    if ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPassword = (string)($_POST['new_password'] ?? '');
        $confirmPassword = (string)($_POST['confirm_password'] ?? '');

        if ($userId <= 0 || strlen($newPassword) < 8 || $newPassword !== $confirmPassword) {
            $message = 'The new passwords must match and contain at least 8 characters.';
            $messageType = 'danger';
        } else {
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param('si', $passwordHash, $userId);

            if ($stmt->execute()) {
                $message = 'Password reset successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to reset the password.';
                $messageType = 'danger';
            }
        }
    }

    if ($action === 'change_status') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $status = user_post('status');

        if ($userId <= 0 || !valid_user_status($status)) {
            $message = 'Invalid account status request.';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
            $stmt->bind_param('si', $status, $userId);

            if ($stmt->execute()) {
                $message = 'Account status updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to update the account status.';
                $messageType = 'danger';
            }
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$roleFilter = trim((string)($_GET['role'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(full_name LIKE ? OR email LIKE ?)";
    $like = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

if ($roleFilter !== '') {
    $where[] = 'role = ?';
    $params[] = $roleFilter;
    $types .= 's';
}

if ($statusFilter !== '') {
    $where[] = 'status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$sql = "
    SELECT
        user_id,
        full_name,
        email,
        role,
        status,
        created_at,
        updated_at
    FROM users
    {$whereSql}
    ORDER BY created_at DESC
    LIMIT 100
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $users = fetch_all($sql);
}

$totalUsers = scalar("SELECT COUNT(*) FROM users", 0);
$active = scalar("SELECT COUNT(*) FROM users WHERE status = 'active'", 0);
$inactive = scalar("SELECT COUNT(*) FROM users WHERE status = 'inactive'", 0);
$suspended = scalar("SELECT COUNT(*) FROM users WHERE status = 'suspended'", 0);

page_start('Users', 'users', 'Search users...');
?>

<style>
/* ============================================================
   TRAVIS USERS — NAVY GLASS THEME
   ============================================================ */

@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap');

:root{
    --navy-950:#060f1e;
    --navy-900:#0a1a30;
    --navy-800:#0f2544;
    --border-glass:rgba(255,255,255,.10);
    --blue-accent:#38bdf8;
    --blue-accent-2:#2563eb;
    --cyan-glow:#4fc3f7;
    --text-soft:#c9d8ea;
}

body{
    font-family:'Poppins', sans-serif !important;
    background:
        radial-gradient(circle at 10% 10%, rgba(56,189,248,.08), transparent 30%),
        radial-gradient(circle at 90% 80%, rgba(37,99,235,.08), transparent 35%),
        linear-gradient(160deg, var(--navy-950) 0%, var(--navy-900) 45%, var(--navy-800) 100%) !important;
    color:#fff !important;
}

/* ==== Topbar alignment to navy theme ==== */
.topbar,
.app-topbar,
.top-header,
.dashboard-topbar,
header.topbar,
.navbar-top{
    background:var(--navy-900) !important;
    border-bottom:1px solid var(--border-glass) !important;
    box-shadow:none !important;
}

.topbar input,
.app-topbar input,
.top-header input,
.dashboard-topbar input,
.navbar-top input{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
    box-shadow:none !important;
}

.topbar input::placeholder,
.app-topbar input::placeholder,
.top-header input::placeholder,
.dashboard-topbar input::placeholder,
.navbar-top input::placeholder{
    color:var(--text-soft) !important;
}

.topbar .bi-search,
.app-topbar .bi-search,
.top-header .bi-search,
.dashboard-topbar .bi-search,
.navbar-top .bi-search{
    color:var(--text-soft) !important;
}

.topbar .bi-bell,
.app-topbar .bi-bell,
.top-header .bi-bell,
.dashboard-topbar .bi-bell,
.navbar-top .bi-bell,
.topbar .notif-icon,
.app-topbar .notif-icon{
    color:var(--text-soft) !important;
}

.topbar .btn-icon,
.app-topbar .btn-icon,
.top-header .btn-icon,
.dashboard-topbar .btn-icon{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
}

.topbar .datetime,
.app-topbar .datetime,
.top-header .datetime,
.dashboard-topbar .datetime{
    color:var(--text-soft) !important;
}

.topbar .user-avatar,
.app-topbar .user-avatar,
.top-header .user-avatar,
.dashboard-topbar .user-avatar{
    background:var(--blue-accent-2) !important;
    color:#fff !important;
}

.topbar .user-name,
.app-topbar .user-name,
.top-header .user-name,
.dashboard-topbar .user-name{
    color:#fff !important;
}

/* ==== Reports / Open Monitoring buttons: exact size fit ==== */
.btn-light,
.btn-primary{
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:4px;
    width:auto !important;
    height:32px !important;
    min-width:0 !important;
    padding:0 12px !important;
    font-size:.75rem !important;
    font-weight:600 !important;
    line-height:1 !important;
    white-space:nowrap !important;
    border-radius:6px !important;
}

.btn-light i,
.btn-primary i{
    font-size:.80rem;
    margin:0 !important;
    line-height:1;
    display:inline-flex;
    align-items:center;
}

.btn-sm{height:28px !important;padding:0 10px !important;font-size:.70rem !important;border-radius:5px !important;}
.btn-sm i{font-size:.75rem !important;}

.dashboard-eyebrow{
    display:inline-block;color:var(--cyan-glow) !important;font-weight:700;
    letter-spacing:.06em;font-size:.72rem;text-transform:uppercase;margin-bottom:8px;
}
.page-title{color:#fff !important;font-weight:800 !important;margin-bottom:6px}
.page-sub{color:var(--text-soft) !important;margin-bottom:0}

.btn-light{background:rgba(255,255,255,.06) !important;border:1px solid var(--border-glass) !important;color:#fff !important;}
.btn-light:hover{background:rgba(255,255,255,.14) !important;color:#fff !important}
.btn-primary{
    background:linear-gradient(90deg,var(--blue-accent-2),var(--cyan-glow)) !important;
    border:none !important;color:#fff !important;
    box-shadow:0 12px 26px rgba(37,99,235,.32) !important;
}
.btn-primary:hover{filter:brightness(1.08)}
.btn-success{
    background:linear-gradient(90deg,#15803d,#34d399) !important;
    border:none !important;color:#fff !important;
    box-shadow:0 12px 26px rgba(21,128,61,.32) !important;
}
.btn-success:hover{filter:brightness(1.08);color:#fff !important}

.stat-card,.dashboard-stat-card{
    background:rgba(255,255,255,.03) !important;
    border:1px solid var(--border-glass) !important;
    border-radius:18px !important;
    padding:20px !important;
    box-shadow:0 14px 30px rgba(0,0,0,.28) !important;
    color:#fff !important;
}
.stat-icon{
    width:44px;height:44px;border-radius:12px;
    display:flex;align-items:center;justify-content:center;
    margin-bottom:14px;font-size:18px;
}
.stat-icon.tone-primary{background:rgba(56,189,248,.14) !important;color:var(--cyan-glow) !important}
.stat-icon.tone-warning{background:rgba(251,191,36,.14) !important;color:#fbbf24 !important}
.stat-icon.tone-success{background:rgba(52,211,153,.14) !important;color:#34d399 !important}
.stat-icon.tone-danger{background:rgba(248,113,113,.14) !important;color:#f87171 !important}
.stat-label{color:var(--text-soft) !important;font-size:.8rem;margin-bottom:4px}
.stat-value{color:#fff !important;font-size:1.7rem;font-weight:800;line-height:1.2}

.section-card{
    background:rgba(255,255,255,.03) !important;
    border:1px solid var(--border-glass) !important;
    border-radius:18px !important;
    padding:20px !important;
    box-shadow:0 14px 30px rgba(0,0,0,.28) !important;
    color:#fff !important;
}
.section-head{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;margin-bottom:16px}
.section-head h6{color:#fff !important;font-weight:700;margin:0}
.section-card small,.section-card .text-muted{color:var(--text-soft) !important}
.section-head a{color:var(--cyan-glow) !important}

.tag{
    display:inline-block;padding:4px 12px;border-radius:999px;
    font-size:.72rem;font-weight:700;text-transform:capitalize;
    background:rgba(255,255,255,.08);color:var(--text-soft);
    border:1px solid var(--border-glass);
}
.tag-success,.tag-online,.tag-paid,.tag-completed,.tag-active,.tag-low{
    background:rgba(52,211,153,.14) !important;color:#34d399 !important;border-color:rgba(52,211,153,.3) !important;
}
.tag-danger,.tag-offline,.tag-overdue,.tag-high,.tag-critical,.tag-suspended{
    background:rgba(248,113,113,.14) !important;color:#f87171 !important;border-color:rgba(248,113,113,.3) !important;
}
.tag-warning,.tag-pending,.tag-unpaid,.tag-medium{
    background:rgba(251,191,36,.14) !important;color:#fbbf24 !important;border-color:rgba(251,191,36,.3) !important;
}
.tag-info{
    background:rgba(56,189,248,.14) !important;color:var(--cyan-glow) !important;border-color:rgba(56,189,248,.3) !important;
}
.tag-muted,.tag-inactive{
    background:rgba(255,255,255,.06) !important;color:var(--text-soft) !important;
}

.empty-state{
    background:rgba(255,255,255,.03) !important;
    border:1px solid var(--border-glass) !important;
    border-radius:14px;
    color:var(--text-soft) !important;
    text-align:center;
    padding:26px 10px;
    font-size:.9rem;
}
.empty-state i,.empty-state svg{color:var(--text-soft) !important;fill:var(--text-soft) !important;opacity:.7}

.border-bottom{border-color:var(--border-glass) !important}
.alert-light{background:rgba(255,255,255,.03) !important;border:1px solid var(--border-glass) !important;color:var(--text-soft) !important}
.alert-success{background:rgba(52,211,153,.12) !important;border:1px solid rgba(52,211,153,.3) !important;color:#34d399 !important}
.alert-danger{background:rgba(248,113,113,.12) !important;border:1px solid rgba(248,113,113,.3) !important;color:#f87171 !important}
.alert-warning{background:rgba(251,191,36,.12) !important;border:1px solid rgba(251,191,36,.3) !important;color:#fbbf24 !important}

a{color:var(--cyan-glow)}
a:hover{color:#fff}

.table{color:#fff !important}
.table thead th{color:var(--text-soft) !important;font-size:.72rem;text-transform:uppercase;letter-spacing:.03em;border-color:var(--border-glass) !important;font-weight:600}
.table td,.table th{border-color:var(--border-glass) !important;vertical-align:middle}
.table-responsive{border-radius:12px}
.table-hover tbody tr:hover{background:rgba(255,255,255,.04) !important;}

.form-control{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
}
.form-control:focus{
    background:rgba(255,255,255,.09) !important;
    border-color:var(--blue-accent) !important;
    color:#fff !important;
    box-shadow:0 0 0 .2rem rgba(56,189,248,.18) !important;
}
.form-control::placeholder{color:var(--text-soft) !important;}
.form-control:disabled{opacity:.5;}
.form-select{
    background:rgba(255,255,255,.06) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
}
.form-select:focus{
    background:rgba(255,255,255,.09) !important;
    border-color:var(--blue-accent) !important;
    color:#fff !important;
    box-shadow:0 0 0 .2rem rgba(56,189,248,.18) !important;
}
.form-select option{background:var(--navy-800);color:#fff;}
.form-label{color:var(--text-soft) !important;font-weight:600;font-size:.8rem;margin-bottom:4px;}

/* Avatar */
.avatar{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    width:36px;
    height:36px;
    border-radius:50%;
    background:linear-gradient(135deg,var(--blue-accent-2),var(--cyan-glow));
    color:#fff;
    font-weight:700;
    font-size:.8rem;
    flex-shrink:0;
}

/* User table scroll */
.user-table-scroll {
  max-height: 590px;
  overflow: auto;
  border: 1px solid var(--border-glass);
  border-radius: .75rem;
}

.user-table-scroll thead th {
  position: sticky;
  top: 0;
  z-index: 5;
  background: var(--navy-800);
  box-shadow: inset 0 -1px 0 var(--border-glass);
  white-space: nowrap;
  color: var(--text-soft) !important;
}

.user-table-scroll::-webkit-scrollbar { width: 7px; height: 7px; }
.user-table-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.04); border-radius: 20px; }
.user-table-scroll::-webkit-scrollbar-thumb { background: rgba(56,189,248,.35); border-radius: 20px; }
.user-table-scroll::-webkit-scrollbar-thumb:hover { background: rgba(56,189,248,.65); }

/* Modal */
.modal-content{
    background:var(--navy-900) !important;
    color:#fff !important;
    border:1px solid var(--border-glass) !important;
}
.modal-header{
    border-bottom:1px solid var(--border-glass) !important;
}
.modal-header .btn-close{
    filter:invert(1) brightness(200%);
}
.modal-footer{
    border-top:1px solid var(--border-glass) !important;
}
.modal-title{color:#fff !important;}
.modal-body strong{color:#fff !important;}

/* ==== Catch-all: any remaining white cards ==== */
.card,
.badge,
.rounded-pill,
.bg-white,
.bg-light,
[class*="card"]{
    background-color:rgba(255,255,255,.03) !important;
    color:#fff !important;
    border-color:var(--border-glass) !important;
}

.card *:not(.tag):not(.avatar),
[class*="card"] *:not(.tag):not(.avatar){
    color:inherit;
}

.card small,
[class*="card"] small,
.card .text-muted,
[class*="card"] .text-muted{
    color:var(--text-soft) !important;
}

.rounded-pill:not(.tag),
span[style*="border-radius:999px"]:not(.tag),
span[style*="border-radius: 999px"]:not(.tag),
div[style*="border-radius:999px"]:not(.tag),
div[style*="border-radius: 999px"]:not(.tag){
    background:rgba(255,255,255,.05) !important;
    border:1px solid var(--border-glass) !important;
    color:#fff !important;
}

.progress{
    background:rgba(255,255,255,.08) !important;
}

.dropdown-menu,
.popover,
.tooltip-inner{
    background:var(--navy-800) !important;
    color:#fff !important;
    border:1px solid var(--border-glass) !important;
}

.dropdown-item{
    color:var(--text-soft) !important;
}

.dropdown-item:hover,
.dropdown-item:focus{
    background:rgba(255,255,255,.06) !important;
    color:#fff !important;
}
</style>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <span class="dashboard-eyebrow">TRAVIS USERS MODULE</span>
    <h3 class="page-title">User Management</h3>
    <p class="page-sub">Manage Administrator and Treasury Personnel accounts.</p>
  </div>

  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
    <i class="bi bi-person-plus me-1"></i>Add User
  </button>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-people"></i></div>
      <div class="stat-label">Total Users</div>
      <div class="stat-value"><?= num($totalUsers) ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-person-check"></i></div>
      <div class="stat-label">Active</div>
      <div class="stat-value"><?= num($active) ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-person-dash"></i></div>
      <div class="stat-label">Inactive</div>
      <div class="stat-value"><?= num($inactive) ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-danger"><i class="bi bi-person-x"></i></div>
      <div class="stat-label">Suspended</div>
      <div class="stat-value"><?= num($suspended) ?></div>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-head flex-wrap gap-2">
    <div>
      <h6 class="mb-0">System Users</h6>
      <small class="text-muted">Passwords are stored as secure hashes and are never displayed.</small>
    </div>

    <form method="get" class="d-flex flex-wrap gap-2">
      <input
        class="form-control form-control-sm"
        name="search"
        value="<?= esc($search) ?>"
        placeholder="Search name or email..."
        style="width:200px;"
      >

      <select class="form-select form-select-sm" name="role" style="width:160px;">
        <option value="">All Roles</option>
        <option value="Administrator" <?= $roleFilter === 'Administrator' ? 'selected' : '' ?>>Administrator</option>
        <option value="Treasury Personnel" <?= $roleFilter === 'Treasury Personnel' ? 'selected' : '' ?>>Treasury Personnel</option>
      </select>

      <select class="form-select form-select-sm" name="status" style="width:140px;">
        <option value="">All Statuses</option>
        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
      </select>

      <button class="btn btn-sm btn-primary">Filter</button>

      <?php if ($search !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
        <a class="btn btn-sm btn-light" href="<?= esc(app_url('users.php')) ?>">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$users): ?>
    <?php empty_state('No user accounts matched your current search and filters.'); ?>
  <?php else: ?>
    <div class="user-table-scroll">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>User</th>
            <th>Email</th>
            <th>Role</th>
            <th>Status</th>
            <th>Created</th>
            <th>Last Updated</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($users as $user): ?>
            <tr>
              <td>
                <div class="d-flex align-items-center gap-2">
                  <span class="avatar"><?= esc(initials($user['full_name'])) ?></span>
                  <div>
                    <div class="fw-semibold"><?= esc($user['full_name']) ?></div>
                    <small class="text-muted">User ID: <?= (int)$user['user_id'] ?></small>
                  </div>
                </div>
              </td>

              <td><?= esc($user['email']) ?></td>
              <td><?= esc($user['role']) ?></td>

              <td>
                <span class="tag <?= tag_class($user['status']) ?>">
                  <?= esc(ucfirst($user['status'])) ?>
                </span>
              </td>

              <td class="text-muted"><?= esc($user['created_at']) ?></td>
              <td class="text-muted"><?= esc($user['updated_at']) ?></td>

              <td class="text-end text-nowrap">
                <button
                  class="btn btn-sm btn-light"
                  data-bs-toggle="modal"
                  data-bs-target="#editUser<?= (int)$user['user_id'] ?>"
                  title="Edit user"
                >
                  <i class="bi bi-pencil-square"></i>
                </button>

                <button
                  class="btn btn-sm btn-light"
                  data-bs-toggle="modal"
                  data-bs-target="#resetPassword<?= (int)$user['user_id'] ?>"
                  title="Reset password"
                >
                  <i class="bi bi-key"></i>
                </button>

                <?php if ($user['status'] === 'active'): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Deactivate this user account?');">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                    <input type="hidden" name="status" value="inactive">
                    <button class="btn btn-sm btn-light text-warning" title="Deactivate">
                      <i class="bi bi-person-dash"></i>
                    </button>
                  </form>
                <?php else: ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Activate this user account?');">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                    <input type="hidden" name="status" value="active">
                    <button class="btn btn-sm btn-light text-success" title="Activate">
                      <i class="bi bi-person-check"></i>
                    </button>
                  </form>
                <?php endif; ?>

                <?php if ($user['status'] !== 'suspended'): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Suspend this user account?');">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                    <input type="hidden" name="status" value="suspended">
                    <button class="btn btn-sm btn-light text-danger" title="Suspend">
                      <i class="bi bi-person-x"></i>
                    </button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <div>
            <h5 class="modal-title">Add User Account</h5>
            <small class="text-muted">Create an Administrator or Treasury Personnel account.</small>
          </div>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="action" value="add_user">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="name@nasugbu.gov.ph" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Role</label>
              <select name="role" class="form-select" required>
                <option value="">Select role</option>
                <option value="Administrator">Administrator</option>
                <option value="Treasury Personnel">Treasury Personnel</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Initial Status</label>
              <select name="status" class="form-select" required>
                <option value="active">Active</option>
                <option value="pending">Pending</option>
                <option value="inactive">Inactive</option>
                <option value="suspended">Suspended</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Temporary Password</label>
              <input type="password" name="password" class="form-control" minlength="8" required>
              <small class="text-muted">Use at least 8 characters.</small>
            </div>

            <div class="col-md-6">
              <label class="form-label">Confirm Password</label>
              <input type="password" name="confirm_password" class="form-control" minlength="8" required>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i>Create User
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php foreach ($users as $user): ?>
  <div class="modal fade" id="editUser<?= (int)$user['user_id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form method="post">
          <div class="modal-header">
            <div>
              <h5 class="modal-title">Edit User Account</h5>
              <small class="text-muted"><?= esc($user['email']) ?></small>
            </div>
            <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <input type="hidden" name="action" value="update_user">
            <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">

            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" name="full_name" class="form-control" value="<?= esc($user['full_name']) ?>" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= esc($user['email']) ?>" required>
              </div>

              <div class="col-md-6">
                <label class="form-label">Role</label>
                <select name="role" class="form-select" required>
                  <option value="Administrator" <?= $user['role'] === 'Administrator' ? 'selected' : '' ?>>Administrator</option>
                  <option value="Treasury Personnel" <?= $user['role'] === 'Treasury Personnel' ? 'selected' : '' ?>>Treasury Personnel</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" required>
                  <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                  <option value="suspended" <?= $user['status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                  <option value="pending" <?= $user['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                </select>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary">
              <i class="bi bi-save me-1"></i>Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="resetPassword<?= (int)$user['user_id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form method="post">
          <div class="modal-header">
            <div>
              <h5 class="modal-title">Reset Password</h5>
              <small class="text-muted"><?= esc($user['full_name']) ?></small>
            </div>
            <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>

          <div class="modal-body">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">

            <div class="mb-3">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control" minlength="8" required>
            </div>

            <div>
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" minlength="8" required>
            </div>
          </div>

          <div class="modal-footer">
            <button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary">
              <i class="bi bi-key me-1"></i>Reset Password
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php page_end(); ?>