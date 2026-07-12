<?php
require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';
$userId = (int)($_SESSION['user']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfOk = hash_equals((string)($_SESSION['csrf_token'] ?? ''), (string)($_POST['csrf_token'] ?? ''));
    $action = $_POST['action'] ?? '';

    if (!$csrfOk) {
        $message = 'Your session expired. Please try again.';
        $messageType = 'danger';
    } elseif ($action === 'update_profile') {
        $fullName = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));

        if ($fullName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please provide a valid name and email address.';
            $messageType = 'danger';
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ? WHERE user_id = ?");
            $stmt->bind_param('ssi', $fullName, $email, $userId);
            if ($stmt->execute()) {
                $_SESSION['user']['name'] = $fullName;
                $_SESSION['user']['email'] = $email;
                $message = 'Profile updated successfully.';
                $messageType = 'success';
            } else {
                $message = 'Failed to update profile. The email may already be in use.';
                $messageType = 'danger';
            }
        }
    } elseif ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        $row = fetch_one("SELECT password FROM users WHERE user_id = ?", [(string)$userId]);
        $valid = $row && password_verify($current, (string)$row['password']);

        if (!$valid) {
            $message = 'Current password is incorrect.';
            $messageType = 'danger';
        } elseif (strlen($new) < 8) {
            $message = 'New password must be at least 8 characters long.';
            $messageType = 'danger';
        } elseif ($new !== $confirm) {
            $message = 'New password and confirmation do not match.';
            $messageType = 'danger';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $stmt->bind_param('si', $hash, $userId);
            $stmt->execute();
            $message = 'Password changed successfully.';
            $messageType = 'success';
        }
    }
}

$user = fetch_one("SELECT full_name, email, role, status, created_at FROM users WHERE user_id = ?", [(string)$userId]) ?: [];
$totalProcessed = scalar("SELECT COUNT(*) FROM payments WHERE received_by = ? AND payment_status = 'completed'", 0, [(string)$userId]);
$totalCollected = scalar("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE received_by = ? AND payment_status = 'completed'", 0, [(string)$userId]);

page_start('My Profile', 'profile', 'Search...', 'Manage your account and security');
?>

<div class="section-card profile-hero mb-4 d-flex flex-wrap align-items-center gap-3">
  <span class="avatar" style="width:64px;height:64px;font-size:1.4rem;"><?= esc(initials($user['full_name'] ?? 'Treasury Personnel')) ?></span>
  <div class="flex-grow-1">
    <h4 class="mb-1"><?= esc($user['full_name'] ?? 'Treasury Personnel') ?></h4>
    <div class="opacity-75"><?= esc($user['role'] ?? 'Treasury Personnel') ?> &middot; TRAVIS Treasurer Portal</div>
  </div>
  <div class="text-end">
    <div class="fs-5 fw-semibold"><?= num($totalProcessed) ?></div>
    <small class="opacity-75">Payments Processed</small>
  </div>
  <div class="text-end">
    <div class="fs-5 fw-semibold"><?= short_money($totalCollected) ?></div>
    <small class="opacity-75">Total Collected</small>
  </div>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head"><h6 class="mb-0">Personal Information</h6></div>
      <form method="post">
        <input type="hidden" name="action" value="update_profile">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
        <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= esc($user['full_name'] ?? '') ?>" required></div>
        <div class="mb-3"><label class="form-label">Email Address</label><input class="form-control" type="email" name="email" value="<?= esc($user['email'] ?? '') ?>" required></div>
        <div class="mb-3"><label class="form-label">Access Role</label><input class="form-control" value="<?= esc($user['role'] ?? '') ?>" disabled></div>
        <div class="mb-3"><label class="form-label">Account Status</label><br><span class="tag <?= tag_class($user['status'] ?? '') ?>"><?= esc(ucfirst($user['status'] ?? 'active')) ?></span></div>
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
      </form>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head"><h6 class="mb-0">Change Password</h6></div>
      <form method="post">
        <input type="hidden" name="action" value="change_password">
        <input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>">
        <div class="mb-3"><label class="form-label">Current Password</label><input class="form-control" type="password" name="current_password" required></div>
        <div class="mb-3"><label class="form-label">New Password</label><input class="form-control" type="password" name="new_password" minlength="8" required></div>
        <div class="mb-3"><label class="form-label">Confirm New Password</label><input class="form-control" type="password" name="confirm_password" minlength="8" required></div>
        <button class="btn btn-outline-secondary"><i class="bi bi-shield-lock me-1"></i>Update Password</button>
      </form>
    </div>
  </div>
</div>

<?php page_end(); ?>
