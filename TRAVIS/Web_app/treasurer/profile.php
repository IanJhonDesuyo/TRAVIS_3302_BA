<?php
require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';
$userId = (int)($_SESSION['user']['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    http_response_code(403);
    $message = 'Treasurer profiles are view-only. Contact an administrator to update account details or reset a password.';
    $messageType = 'warning';
}

$user = fetch_one("SELECT full_name, email, role, status, created_at FROM users WHERE user_id = ?", [(string)$userId]) ?: [];
$totalProcessed = scalar("SELECT COUNT(*) FROM payments WHERE received_by = ? AND payment_status = 'completed'", 0, [(string)$userId]);
$totalCollected = scalar("SELECT COALESCE(SUM(amount_paid), 0) FROM payments WHERE received_by = ? AND payment_status = 'completed'", 0, [(string)$userId]);

page_start('My Profile', 'profile', 'Search...', 'View your account information', false);
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
      <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" value="<?= esc($user['full_name'] ?? '') ?>" readonly></div>
      <div class="mb-3"><label class="form-label">Email / Username</label><input class="form-control" value="<?= esc($user['email'] ?? '') ?>" readonly></div>
      <div class="mb-3"><label class="form-label">Access Role</label><input class="form-control" value="<?= esc($user['role'] ?? '') ?>" readonly></div>
      <div class="mb-3"><label class="form-label">Account Status</label><br><span class="tag <?= tag_class($user['status'] ?? '') ?>"><?= esc(ucfirst($user['status'] ?? 'active')) ?></span></div>
      <div><label class="form-label">Member Since</label><input class="form-control" value="<?= esc(!empty($user['created_at']) ? date('F j, Y', strtotime((string)$user['created_at'])) : 'Not recorded') ?>" readonly></div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="section-card h-100">
      <div class="section-head"><h6 class="mb-0">Account Security</h6></div>
      <div class="d-flex align-items-start gap-3 mb-4">
        <div class="stat-icon tone-success flex-shrink-0"><i class="bi bi-shield-check"></i></div>
        <div>
          <h6 class="mb-1">Protected account</h6>
          <p class="text-muted mb-0">Your password is securely stored and cannot be viewed or changed from the Treasurer portal.</p>
        </div>
      </div>
      <div class="mb-3"><label class="form-label">Password</label><input class="form-control" value="••••••••••••" readonly aria-label="Password hidden"></div>
      <div class="alert alert-light border mb-0"><i class="bi bi-info-circle me-2"></i>Contact an administrator if your username, profile information, or password needs to be changed.</div>
    </div>
  </div>
</div>

<?php page_end(); ?>
