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
.user-table-scroll {
  max-height: 590px;
  overflow: auto;
  border: 1px solid var(--bs-border-color, #dee2e6);
  border-radius: .75rem;
}
.user-table-scroll thead th {
  position: sticky;
  top: 0;
  z-index: 5;
  background: #fff;
  box-shadow: inset 0 -1px 0 #dee2e6;
  white-space: nowrap;
}
.user-table-scroll::-webkit-scrollbar {
  width: 9px;
  height: 9px;
}
.user-table-scroll::-webkit-scrollbar-thumb {
  background: #b8c0cc;
  border-radius: 10px;
}
.user-table-scroll::-webkit-scrollbar-track {
  background: #f1f3f5;
}
</style>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <h3 class="page-title">User Management</h3>
    <p class="page-sub">Manage Administrator and Treasury Personnel accounts.</p>
  </div>

  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
    <i class="bi bi-person-plus me-1"></i>Add User
  </button>
</div>

<?php if ($message): ?>
  <?php feedback_notice($message, $messageType); ?>
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
      >

      <select class="form-select form-select-sm" name="role">
        <option value="">All Roles</option>
        <option value="Administrator" <?= $roleFilter === 'Administrator' ? 'selected' : '' ?>>Administrator</option>
        <option value="Treasury Personnel" <?= $roleFilter === 'Treasury Personnel' ? 'selected' : '' ?>>Treasury Personnel</option>
      </select>

      <select class="form-select form-select-sm" name="status">
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
                  <form method="post" class="d-inline" data-confirm="This user will lose access until the account is activated again." data-confirm-title="Deactivate this account?" data-confirm-label="Deactivate account" data-confirm-eyebrow="Account access" data-confirm-tone="warning">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                    <input type="hidden" name="status" value="inactive">
                    <button class="btn btn-sm btn-light text-warning" title="Deactivate">
                      <i class="bi bi-person-dash"></i>
                    </button>
                  </form>
                <?php else: ?>
                  <form method="post" class="d-inline" data-confirm="This user will regain access based on the assigned role and permissions." data-confirm-title="Activate this account?" data-confirm-label="Activate account" data-confirm-eyebrow="Account access" data-confirm-tone="success">
                    <input type="hidden" name="action" value="change_status">
                    <input type="hidden" name="user_id" value="<?= (int)$user['user_id'] ?>">
                    <input type="hidden" name="status" value="active">
                    <button class="btn btn-sm btn-light text-success" title="Activate">
                      <i class="bi bi-person-check"></i>
                    </button>
                  </form>
                <?php endif; ?>

                <?php if ($user['status'] !== 'suspended'): ?>
                  <form method="post" class="d-inline" data-confirm="This account will be suspended immediately and the user will no longer be permitted to access the system." data-confirm-title="Suspend this account?" data-confirm-label="Suspend account" data-confirm-eyebrow="Security action" data-confirm-tone="danger">
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
