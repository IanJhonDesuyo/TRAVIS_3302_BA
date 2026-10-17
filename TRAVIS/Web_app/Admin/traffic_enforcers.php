<?php
declare(strict_types=1);

require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';

function enforcer_post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function enforcer_name(array $officer): string
{
    $given = trim(implode(' ', array_filter([
        trim((string)($officer['first_name'] ?? '')),
        trim((string)($officer['middle_name'] ?? '')),
    ], static fn(string $part): bool => $part !== '')));
    $last = trim((string)($officer['last_name'] ?? ''));
    $suffix = trim((string)($officer['suffix'] ?? ''));
    return trim($last . ', ' . $given . ($suffix !== '' ? ' ' . $suffix : ''), ', ');
}

function enforcer_status_class(string $status): string
{
    return match ($status) {
        'active' => 'tag-success',
        'inactive' => 'tag-warning',
        'suspended' => 'tag-danger',
        default => 'tag-info',
    };
}

function enforcer_valid_status(string $status): bool
{
    return in_array($status, ['active', 'inactive', 'retired', 'suspended'], true);
}

function enforcer_valid_date(string $date): bool
{
    if ($date === '') return true;
    $value = DateTimeImmutable::createFromFormat('Y-m-d', $date);
    return $value !== false && $value->format('Y-m-d') === $date;
}

function enforcer_admin_password_valid(mysqli $conn, int $userId, string $password): bool
{
    $statement = $conn->prepare('SELECT password FROM users WHERE user_id = ? LIMIT 1');
    $statement->bind_param('i', $userId);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();
    $statement->close();
    return $user && password_verify($password, (string)$user['password']);
}

$statuses = ['active', 'inactive', 'retired', 'suspended'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = enforcer_post('action');
    $submittedCsrf = enforcer_post('csrf_token');

    if (!hash_equals(csrf_token(), $submittedCsrf)) {
        $message = 'Your session token expired. Refresh the page and try again.';
        $messageType = 'danger';
    } elseif ($action === 'add_zone') {
        $zoneName = enforcer_post('zone_name');
        $description = enforcer_post('description');
        $locationDetails = enforcer_post('location_details');

        if ($zoneName === '' || mb_strlen($zoneName) > 120 || mb_strlen($description) > 255 || mb_strlen($locationDetails) > 255) {
            $message = 'Enter a valid assignment location.';
            $messageType = 'danger';
        } else {
            $check = $conn->prepare('SELECT zone_id FROM officer_zones WHERE LOWER(zone_name) = LOWER(?) LIMIT 1');
            $check->bind_param('s', $zoneName);
            $check->execute();
            if ($check->get_result()->fetch_assoc()) {
                $message = 'That assignment location already exists.';
                $messageType = 'warning';
            } else {
                $statement = $conn->prepare("INSERT INTO officer_zones (zone_name, description, location_details) VALUES (?, NULLIF(?, ''), NULLIF(?, ''))");
                $statement->bind_param('sss', $zoneName, $description, $locationDetails);
                $statement->execute();
                $zoneId = (int)$conn->insert_id;
                $message = 'Assignment location added successfully.';
                $messageType = 'success';
                audit_log('create', 'traffic_enforcers', 'Created assignment location ' . $zoneName . '.', 'success', 'officer_zone', $zoneId);
            }
        }
    } elseif (in_array($action, ['add_enforcer', 'update_enforcer'], true)) {
        $officerId = $action === 'update_enforcer' ? (int)($_POST['officer_id'] ?? 0) : 0;
        $firstName = enforcer_post('first_name');
        $middleName = enforcer_post('middle_name');
        $lastName = enforcer_post('last_name');
        $suffix = enforcer_post('suffix');
        $badgeNumber = strtoupper(enforcer_post('badge_number'));
        $rank = enforcer_post('rank');
        $contactNumber = enforcer_post('contact_number');
        $email = strtolower(enforcer_post('email'));
        $address = enforcer_post('address');
        $birthDate = enforcer_post('birth_date');
        $hireDate = enforcer_post('hire_date');
        $emergencyName = enforcer_post('emergency_contact_name');
        $emergencyNumber = enforcer_post('emergency_contact_number');
        $zoneId = (int)($_POST['zone_id'] ?? 0);
        $status = enforcer_post('status', 'active');

        $validZone = true;
        if ($zoneId > 0) {
            $zoneStatement = $conn->prepare('SELECT zone_id FROM officer_zones WHERE zone_id = ? LIMIT 1');
            $zoneStatement->bind_param('i', $zoneId);
            $zoneStatement->execute();
            $validZone = (bool)$zoneStatement->get_result()->fetch_assoc();
        }

        $invalid = $firstName === '' || $lastName === '' || $badgeNumber === '' || $rank === '' ||
            mb_strlen($firstName) > 100 || mb_strlen($middleName) > 100 || mb_strlen($lastName) > 100 ||
            mb_strlen($suffix) > 20 || mb_strlen($badgeNumber) > 50 || mb_strlen($rank) > 80 ||
            mb_strlen($contactNumber) > 30 || mb_strlen($email) > 150 || mb_strlen($address) > 255 ||
            mb_strlen($emergencyName) > 150 || mb_strlen($emergencyNumber) > 30 ||
            ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) ||
            !enforcer_valid_date($birthDate) || !enforcer_valid_date($hireDate) ||
            !enforcer_valid_status($status) || !$validZone || ($action === 'update_enforcer' && $officerId <= 0);

        if ($invalid) {
            $message = 'Complete the required enforcer fields with valid information.';
            $messageType = 'danger';
        } else {
            $duplicate = $conn->prepare("SELECT officer_id FROM officers WHERE (badge_number = ? OR (? <> '' AND email = ?)) AND officer_id <> ? LIMIT 1");
            $duplicate->bind_param('sssi', $badgeNumber, $email, $email, $officerId);
            $duplicate->execute();
            if ($duplicate->get_result()->fetch_assoc()) {
                $message = 'The employee ID/badge number or email is already assigned to another enforcer.';
                $messageType = 'warning';
            } elseif ($action === 'add_enforcer') {
                $statement = $conn->prepare("
                    INSERT INTO officers (
                        first_name, middle_name, last_name, suffix, badge_number, rank, contact_number,
                        email, address, birth_date, hire_date, emergency_contact_name,
                        emergency_contact_number, zone_id, status
                    ) VALUES (?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, ?, NULLIF(?, ''), NULLIF(?, ''),
                              NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, 0), ?)
                ");
                $statement->bind_param(
                    'sssssssssssssis',
                    $firstName, $middleName, $lastName, $suffix, $badgeNumber, $rank, $contactNumber,
                    $email, $address, $birthDate, $hireDate, $emergencyName, $emergencyNumber, $zoneId, $status
                );
                $statement->execute();
                $officerId = (int)$conn->insert_id;
                $message = 'Traffic enforcer added successfully.';
                $messageType = 'success';
                audit_log('create', 'traffic_enforcers', 'Created traffic enforcer ' . $firstName . ' ' . $lastName . '.', 'success', 'officer', $officerId);
            } else {
                $statement = $conn->prepare("
                    UPDATE officers SET
                        first_name = ?, middle_name = NULLIF(?, ''), last_name = ?, suffix = NULLIF(?, ''),
                        badge_number = ?, rank = ?, contact_number = NULLIF(?, ''), email = NULLIF(?, ''),
                        address = NULLIF(?, ''), birth_date = NULLIF(?, ''), hire_date = NULLIF(?, ''),
                        emergency_contact_name = NULLIF(?, ''), emergency_contact_number = NULLIF(?, ''),
                        zone_id = NULLIF(?, 0), status = ?
                    WHERE officer_id = ?
                ");
                $statement->bind_param(
                    'sssssssssssssisi',
                    $firstName, $middleName, $lastName, $suffix, $badgeNumber, $rank, $contactNumber,
                    $email, $address, $birthDate, $hireDate, $emergencyName, $emergencyNumber, $zoneId, $status, $officerId
                );
                $statement->execute();
                if ($statement->affected_rows === 0) {
                    $exists = $conn->prepare('SELECT officer_id FROM officers WHERE officer_id = ? LIMIT 1');
                    $exists->bind_param('i', $officerId);
                    $exists->execute();
                    if (!$exists->get_result()->fetch_assoc()) {
                        $message = 'The selected enforcer record no longer exists.';
                        $messageType = 'warning';
                    } else {
                        $message = 'No enforcer information changed.';
                        $messageType = 'info';
                    }
                } else {
                    $message = 'Traffic enforcer updated successfully.';
                    $messageType = 'success';
                    audit_log('update', 'traffic_enforcers', 'Updated traffic enforcer ' . $firstName . ' ' . $lastName . '.', 'success', 'officer', $officerId);
                }
            }
        }
    } elseif ($action === 'delete_enforcer') {
        $officerId = (int)($_POST['officer_id'] ?? 0);
        $adminPassword = (string)($_POST['admin_password'] ?? '');
        $currentAdminId = (int)($_SESSION['user']['id'] ?? 0);

        if ($officerId <= 0 || $adminPassword === '') {
            $message = 'Enter your administrator password to confirm deletion.';
            $messageType = 'danger';
        } elseif (!enforcer_admin_password_valid($conn, $currentAdminId, $adminPassword)) {
            $message = 'The administrator password is incorrect. The enforcer was not deleted.';
            $messageType = 'danger';
            audit_log('delete', 'traffic_enforcers', 'Rejected traffic enforcer deletion because password confirmation failed.', 'failed', 'officer', $officerId);
        } else {
            $lookup = $conn->prepare('SELECT first_name, middle_name, last_name, suffix, badge_number FROM officers WHERE officer_id = ? LIMIT 1');
            $lookup->bind_param('i', $officerId);
            $lookup->execute();
            $officer = $lookup->get_result()->fetch_assoc();
            if (!$officer) {
                $message = 'The selected enforcer record no longer exists.';
                $messageType = 'warning';
            } else {
                $statement = $conn->prepare('DELETE FROM officers WHERE officer_id = ?');
                $statement->bind_param('i', $officerId);
                $statement->execute();
                $message = 'Traffic enforcer deleted successfully.';
                $messageType = 'success';
                audit_log('delete', 'traffic_enforcers', 'Deleted traffic enforcer ' . enforcer_name($officer) . ' (' . (string)$officer['badge_number'] . ').', 'success', 'officer', $officerId);
            }
        }
    }
}

$zones = fetch_all("SELECT z.*, COUNT(o.officer_id) AS assigned_count FROM officer_zones z LEFT JOIN officers o ON o.zone_id = z.zone_id GROUP BY z.zone_id ORDER BY z.zone_name");
$search = trim((string)($_GET['search'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$zoneFilter = (int)($_GET['zone_id'] ?? 0);
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(o.first_name LIKE ? OR o.middle_name LIKE ? OR o.last_name LIKE ? OR o.badge_number LIKE ? OR o.rank LIKE ? OR o.email LIKE ? OR o.contact_number LIKE ? OR z.zone_name LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like, $like, $like, $like, $like);
    $types .= 'ssssssss';
}
if (enforcer_valid_status($statusFilter)) {
    $where[] = 'o.status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}
if ($zoneFilter > 0) {
    $where[] = 'o.zone_id = ?';
    $params[] = $zoneFilter;
    $types .= 'i';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$sql = "
    SELECT o.*, z.zone_name, z.location_details,
           (SELECT COUNT(*) FROM officer_duty_schedules s WHERE s.officer_id = o.officer_id) AS schedule_count,
           (SELECT COUNT(*) FROM officer_presence_logs p WHERE p.officer_id = o.officer_id) AS presence_count
    FROM officers o
    LEFT JOIN officer_zones z ON z.zone_id = o.zone_id
    {$whereSql}
    ORDER BY FIELD(o.status, 'active', 'inactive', 'suspended', 'retired'), o.last_name, o.first_name
";

if ($params) {
    $statement = $conn->prepare($sql);
    $statement->bind_param($types, ...$params);
    $statement->execute();
    $officers = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $officers = fetch_all($sql);
}

$totalOfficers = (int)scalar('SELECT COUNT(*) FROM officers', 0);
$activeOfficers = (int)scalar("SELECT COUNT(*) FROM officers WHERE status = 'active'", 0);
$assignedOfficers = (int)scalar("SELECT COUNT(*) FROM officers WHERE zone_id IS NOT NULL AND status = 'active'", 0);
$unassignedOfficers = (int)scalar("SELECT COUNT(*) FROM officers WHERE zone_id IS NULL AND status = 'active'", 0);

page_start('Traffic Enforcer Management', 'traffic-enforcers', 'Search enforcers...');
?>

<style>
.enforcer-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:22px}
.enforcer-page-actions{display:flex;align-items:center;gap:9px;flex-wrap:wrap}
.enforcer-stat-icon{display:grid;width:44px;height:44px;place-items:center;margin-bottom:13px;border-radius:13px;font-size:19px}
.enforcer-stat-icon.teal{color:#087d78;background:rgba(8,125,120,.11)}
.enforcer-stat-icon.green{color:#138a61;background:rgba(22,163,116,.11)}
.enforcer-stat-icon.blue{color:#2187b7;background:rgba(56,189,248,.12)}
.enforcer-stat-icon.amber{color:#b97908;background:rgba(245,158,11,.13)}
.enforcer-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:16px}
.enforcer-section-head h6{margin:0 0 3px}
.enforcer-filter{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.enforcer-filter .form-control{width:230px}.enforcer-filter .form-select{width:150px}
.enforcer-person{display:flex;align-items:center;gap:10px;min-width:190px}
.enforcer-avatar{display:grid;flex:0 0 38px;width:38px;height:38px;place-items:center;border-radius:11px;color:#087d78;background:rgba(8,125,120,.1);font-weight:800}
.enforcer-person strong,.enforcer-person small{display:block}.enforcer-person small{margin-top:2px}
.enforcer-actions{display:flex;justify-content:flex-end;gap:6px;white-space:nowrap}
.enforcer-actions .btn{display:inline-grid;width:34px;height:32px;padding:0!important;place-items:center}
.enforcer-profile-overview{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:18px;padding:16px;border:1px solid rgba(8,125,120,.16);border-radius:14px;background:linear-gradient(135deg,rgba(8,125,120,.09),rgba(255,255,255,.7))}
.enforcer-profile-identity{display:flex;align-items:center;gap:12px}.enforcer-profile-identity .enforcer-avatar{width:50px;height:50px;flex-basis:50px;font-size:1.05rem}.enforcer-profile-identity small,.enforcer-profile-identity strong{display:block}.enforcer-profile-identity strong{font-size:1rem;color:#10202c}
.enforcer-profile-badges{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
.enforcer-record-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
.enforcer-record-panel{overflow:hidden;border:1px solid rgba(16,47,73,.11);border-radius:14px;background:#fff}
.enforcer-record-panel-head{display:flex;align-items:center;gap:8px;padding:12px 14px;border-bottom:1px solid rgba(16,47,73,.09);color:#102f49;background:rgba(8,125,120,.055)}
.enforcer-record-fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:13px;padding:15px}
.enforcer-record-field{min-width:0;padding-left:10px;border-left:2px solid #dcebed}.enforcer-record-field.wide{grid-column:1/-1}.enforcer-record-field small{display:block;margin-bottom:3px;color:#6b7d78;font-size:.64rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase}.enforcer-record-field strong{display:block;color:#10202c;font-size:.82rem;line-height:1.45;overflow-wrap:anywhere}
.enforcer-form-modal .modal-dialog{width:min(1040px,calc(100vw - 2rem));max-width:1040px}
.enforcer-form-modal .modal-content{overflow:hidden}
.enforcer-form-modal .modal-body{padding:20px;background:linear-gradient(145deg,rgba(247,251,250,.98),rgba(255,252,247,.98))}
.enforcer-form-layout{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:15px 16px}
.enforcer-form-layout>[class*="col-"]{width:auto;max-width:none;padding:0}
.enforcer-form-layout>.col-12{grid-column:1/-1}.enforcer-form-layout>.col-md-8{grid-column:span 8}.enforcer-form-layout>.col-md-6{grid-column:span 6}.enforcer-form-layout>.col-md-4{grid-column:span 4}.enforcer-form-layout>.col-md-3{grid-column:span 3}.enforcer-form-layout>.col-md-2{grid-column:span 2}
.enforcer-form-layout>.col-12:has(.enforcer-form-section){margin-top:3px}
.enforcer-form-layout>.col-12:first-child{margin-top:0}
.enforcer-form-section{display:flex;align-items:center;gap:11px;margin:0;padding:12px 15px;border:1px solid rgba(8,125,120,.14);border-radius:12px;background:linear-gradient(135deg,rgba(8,125,120,.09),rgba(8,125,120,.035))}
.enforcer-form-section i{display:grid;flex:0 0 32px;width:32px;height:32px;place-items:center;border-radius:9px;color:#fff;background:#087d78}.enforcer-form-section strong,.enforcer-form-section small{display:block}.enforcer-form-section strong{color:#102f49;font-size:.82rem}.enforcer-form-section small{margin-top:1px;font-size:.65rem}
.enforcer-form-layout .form-label{display:flex;align-items:center;gap:6px;min-height:18px;margin-bottom:6px;color:#36564f;font-size:.72rem;font-weight:700}
.enforcer-form-layout .form-label small{font-size:.59rem;font-weight:600;text-transform:uppercase;letter-spacing:.04em}
.enforcer-form-layout .form-control,.enforcer-form-layout .form-select{width:100%;min-height:42px;border-radius:10px}
.enforcer-form-layout>div>small.text-muted{display:block;margin-top:5px;font-size:.62rem;line-height:1.4}
.enforcer-danger-note{display:flex;gap:10px;padding:13px;border:1px solid rgba(220,38,38,.16);border-radius:12px;color:#7f1d1d;background:#fff5f5}
@media(max-width:991.98px){.enforcer-record-grid{grid-template-columns:1fr}.enforcer-form-layout>.col-md-8{grid-column:1/-1}.enforcer-form-layout>.col-md-6,.enforcer-form-layout>.col-md-4,.enforcer-form-layout>.col-md-3,.enforcer-form-layout>.col-md-2{grid-column:span 6}}
@media(max-width:767.98px){.enforcer-filter,.enforcer-filter .form-control,.enforcer-filter .form-select{width:100%}.enforcer-profile-overview{align-items:flex-start;flex-direction:column}.enforcer-profile-badges{justify-content:flex-start}.enforcer-record-fields{grid-template-columns:1fr}.enforcer-record-field.wide{grid-column:auto}.enforcer-form-modal .modal-dialog{width:calc(100vw - 1rem);margin:.5rem}.enforcer-form-modal .modal-body{padding:12px}}
@media(max-width:575.98px){.enforcer-form-layout{grid-template-columns:1fr;gap:13px}.enforcer-form-layout>.col-12,.enforcer-form-layout>.col-md-8,.enforcer-form-layout>.col-md-6,.enforcer-form-layout>.col-md-4,.enforcer-form-layout>.col-md-3,.enforcer-form-layout>.col-md-2{grid-column:1/-1}}
</style>

<div class="enforcer-page-head">
  <div>
    <span class="dashboard-eyebrow">TRAVIS PERSONNEL DIRECTORY</span>
    <h3 class="page-title">Traffic Enforcer Management</h3>
    <p class="page-sub">Maintain enforcer profiles, employment status, and assigned traffic locations.</p>
  </div>
  <div class="enforcer-page-actions">
    <button class="btn btn-light" type="button" data-bs-toggle="modal" data-bs-target="#addZoneModal"><i class="bi bi-geo-alt me-1"></i>Add Location</button>
    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addEnforcerModal"><i class="bi bi-person-plus me-1"></i>Add Enforcer</button>
  </div>
</div>

<?php if ($message): ?><div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div><?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="stat-card h-100"><div class="enforcer-stat-icon teal"><i class="bi bi-people"></i></div><div class="stat-label">Total Enforcers</div><div class="stat-value"><?= num($totalOfficers) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card h-100"><div class="enforcer-stat-icon green"><i class="bi bi-person-check"></i></div><div class="stat-label">Active Personnel</div><div class="stat-value"><?= num($activeOfficers) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card h-100"><div class="enforcer-stat-icon blue"><i class="bi bi-geo-alt"></i></div><div class="stat-label">Assigned Active</div><div class="stat-value"><?= num($assignedOfficers) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card h-100"><div class="enforcer-stat-icon amber"><i class="bi bi-person-exclamation"></i></div><div class="stat-label">Unassigned Active</div><div class="stat-value"><?= num($unassignedOfficers) ?></div></div></div>
</div>

<div class="section-card">
  <div class="enforcer-section-head">
    <div><h6>Traffic Enforcer Directory</h6><small class="text-muted">Open a profile to view complete personnel and assignment information.</small></div>
    <form method="get" class="enforcer-filter">
      <input class="form-control form-control-sm" name="search" value="<?= esc($search) ?>" placeholder="Name, ID, position, contact...">
      <select class="form-select form-select-sm" name="zone_id"><option value="0">All Locations</option><?php foreach ($zones as $zone): ?><option value="<?= (int)$zone['zone_id'] ?>" <?= $zoneFilter === (int)$zone['zone_id'] ? 'selected' : '' ?>><?= esc($zone['zone_name']) ?></option><?php endforeach; ?></select>
      <select class="form-select form-select-sm" name="status"><option value="">All Statuses</option><?php foreach ($statuses as $option): ?><option value="<?= esc($option) ?>" <?= $statusFilter === $option ? 'selected' : '' ?>><?= esc(ucfirst($option)) ?></option><?php endforeach; ?></select>
      <button class="btn btn-sm btn-primary">Filter</button>
      <?php if ($search !== '' || $statusFilter !== '' || $zoneFilter > 0): ?><a class="btn btn-sm btn-light" href="<?= esc(app_url('traffic_enforcers.php')) ?>">Clear</a><?php endif; ?>
    </form>
  </div>

  <?php if (!$officers): ?>
    <?php empty_state('No traffic enforcers matched the current search or filters.'); ?>
  <?php else: ?>
    <div class="table-responsive table-scroll">
      <table class="table align-middle">
        <thead><tr><th>Enforcer</th><th>Employee ID</th><th>Position</th><th>Contact</th><th>Assigned Location</th><th>Hire Date</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
        <tbody>
        <?php foreach ($officers as $officer): ?>
          <?php $fullName = enforcer_name($officer); ?>
          <tr>
            <td><div class="enforcer-person"><span class="enforcer-avatar"><?= esc(initials(trim((string)$officer['first_name'] . ' ' . (string)$officer['last_name']))) ?></span><span><strong><?= esc($fullName) ?></strong><small class="text-muted"><?= esc($officer['email'] ?: 'No email recorded') ?></small></span></div></td>
            <td class="fw-semibold"><?= esc($officer['badge_number']) ?></td>
            <td><?= esc($officer['rank']) ?></td>
            <td><?= esc($officer['contact_number'] ?: 'Not provided') ?></td>
            <td><?= esc($officer['zone_name'] ?: 'Unassigned') ?><?php if (!empty($officer['location_details'])): ?><br><small class="text-muted"><?= esc($officer['location_details']) ?></small><?php endif; ?></td>
            <td><?= esc($officer['hire_date'] ?: 'Not provided') ?></td>
            <td><span class="tag <?= enforcer_status_class((string)$officer['status']) ?>"><?= esc(ucfirst((string)$officer['status'])) ?></span></td>
            <td><div class="enforcer-actions">
              <button class="btn btn-sm btn-light" type="button" data-bs-toggle="modal" data-bs-target="#viewEnforcer<?= (int)$officer['officer_id'] ?>" title="View profile"><i class="bi bi-eye"></i></button>
              <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#editEnforcer<?= (int)$officer['officer_id'] ?>" title="Edit enforcer"><i class="bi bi-pencil-square"></i></button>
              <button class="btn btn-sm btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#deleteEnforcer<?= (int)$officer['officer_id'] ?>" title="Delete enforcer"><i class="bi bi-trash"></i></button>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
function enforcer_form_fields(array $officer, array $zones, string $prefix): void
{
    $value = static fn(string $key): string => (string)($officer[$key] ?? '');
    ?>
    <div class="enforcer-form-layout">
      <div class="col-12"><div class="enforcer-form-section"><i class="bi bi-person-vcard"></i><div><strong>Personal Information</strong><small class="text-muted">Identity and contact details</small></div></div></div>
      <div class="col-md-4"><label class="form-label" for="<?= esc($prefix) ?>FirstName">First Name</label><input class="form-control" id="<?= esc($prefix) ?>FirstName" name="first_name" value="<?= esc($value('first_name')) ?>" maxlength="100" required></div>
      <div class="col-md-3"><label class="form-label">Middle Name <small class="text-muted">(Optional)</small></label><input class="form-control" name="middle_name" value="<?= esc($value('middle_name')) ?>" maxlength="100"></div>
      <div class="col-md-3"><label class="form-label">Last Name</label><input class="form-control" name="last_name" value="<?= esc($value('last_name')) ?>" maxlength="100" required></div>
      <div class="col-md-2"><label class="form-label">Suffix</label><input class="form-control" name="suffix" value="<?= esc($value('suffix')) ?>" maxlength="20" placeholder="Jr., Sr., III"></div>
      <div class="col-md-4"><label class="form-label">Birth Date</label><input type="date" class="form-control" name="birth_date" value="<?= esc($value('birth_date')) ?>"></div>
      <div class="col-md-4"><label class="form-label">Contact Number</label><input type="tel" class="form-control" name="contact_number" value="<?= esc($value('contact_number')) ?>" maxlength="30" placeholder="09XX XXX XXXX"></div>
      <div class="col-md-4"><label class="form-label">Email Address</label><input type="email" class="form-control" name="email" value="<?= esc($value('email')) ?>" maxlength="150"></div>
      <div class="col-12"><label class="form-label">Residential Address</label><input class="form-control" name="address" value="<?= esc($value('address')) ?>" maxlength="255" placeholder="House no., street, barangay, municipality"></div>

      <div class="col-12"><div class="enforcer-form-section"><i class="bi bi-person-badge"></i><div><strong>Employment &amp; Assignment</strong><small class="text-muted">Employee identification, position, and assigned location</small></div></div></div>
      <div class="col-md-4"><label class="form-label">Employee ID / Badge Number</label><input class="form-control text-uppercase" name="badge_number" value="<?= esc($value('badge_number')) ?>" maxlength="50" required></div>
      <div class="col-md-4"><label class="form-label">Position / Rank</label><input class="form-control" name="rank" value="<?= esc($value('rank')) ?>" maxlength="80" placeholder="Traffic Enforcer I" required></div>
      <div class="col-md-4"><label class="form-label">Hire Date</label><input type="date" class="form-control" name="hire_date" value="<?= esc($value('hire_date')) ?>"></div>
      <div class="col-md-8"><label class="form-label">Assigned Location</label><select class="form-select" name="zone_id"><option value="0">Unassigned</option><?php foreach ($zones as $zone): ?><option value="<?= (int)$zone['zone_id'] ?>" <?= (int)($officer['zone_id'] ?? 0) === (int)$zone['zone_id'] ? 'selected' : '' ?>><?= esc($zone['zone_name']) ?><?= !empty($zone['location_details']) ? ' — ' . esc($zone['location_details']) : '' ?></option><?php endforeach; ?></select><small class="text-muted">Add a new location from the main module header when needed.</small></div>
      <div class="col-md-4"><label class="form-label">Employment Status</label><select class="form-select" name="status" required><?php foreach (['active', 'inactive', 'retired', 'suspended'] as $status): ?><option value="<?= esc($status) ?>" <?= $value('status') === $status ? 'selected' : '' ?>><?= esc(ucfirst($status)) ?></option><?php endforeach; ?></select><small class="text-muted">Only active enforcers appear in violation forms.</small></div>

      <div class="col-12"><div class="enforcer-form-section"><i class="bi bi-telephone-plus"></i><div><strong>Emergency Contact</strong><small class="text-muted">Optional contact person for personnel records</small></div></div></div>
      <div class="col-md-6"><label class="form-label">Contact Person</label><input class="form-control" name="emergency_contact_name" value="<?= esc($value('emergency_contact_name')) ?>" maxlength="150"></div>
      <div class="col-md-6"><label class="form-label">Emergency Contact Number</label><input type="tel" class="form-control" name="emergency_contact_number" value="<?= esc($value('emergency_contact_number')) ?>" maxlength="30"></div>
    </div>
    <?php
}
?>

<div class="modal fade enforcer-form-modal" id="addEnforcerModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form method="post" autocomplete="off">
  <div class="modal-header"><div><h5 class="modal-title">Add Traffic Enforcer</h5><small class="text-muted">Create a new employee profile and assignment</small></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><input type="hidden" name="action" value="add_enforcer"><input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>"><?php enforcer_form_fields(['status' => 'active'], $zones, 'addEnforcer'); ?></div>
  <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Save Enforcer</button></div>
</form></div></div></div>

<div class="modal fade" id="addZoneModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post">
  <div class="modal-header"><div><h5 class="modal-title">Add Assignment Location</h5><small class="text-muted">Create a location that can be assigned to enforcers</small></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body"><input type="hidden" name="action" value="add_zone"><input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>"><div class="row g-3"><div class="col-12"><label class="form-label">Location Name</label><input class="form-control" name="zone_name" maxlength="120" placeholder="Bucal Intersection" required></div><div class="col-12"><label class="form-label">Location Details</label><input class="form-control" name="location_details" maxlength="255" placeholder="Road, barangay, or checkpoint details"></div><div class="col-12"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" maxlength="255" placeholder="Coverage or assignment notes"></textarea></div></div></div>
  <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-geo-alt me-1"></i>Add Location</button></div>
</form></div></div></div>

<?php foreach ($officers as $officer): $officerId = (int)$officer['officer_id']; $fullName = enforcer_name($officer); ?>
  <div class="modal fade enforcer-modal" id="viewEnforcer<?= $officerId ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h5 class="modal-title">Traffic Enforcer Profile</h5><small class="text-muted">Complete personnel and assignment information</small></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body">
      <div class="enforcer-profile-overview"><div class="enforcer-profile-identity"><span class="enforcer-avatar"><?= esc(initials(trim((string)$officer['first_name'] . ' ' . (string)$officer['last_name']))) ?></span><div><small class="text-muted">Traffic enforcer</small><strong><?= esc($fullName) ?></strong><small class="text-muted"><?= esc($officer['badge_number']) ?> · <?= esc($officer['rank']) ?></small></div></div><div class="enforcer-profile-badges"><span class="tag <?= enforcer_status_class((string)$officer['status']) ?>"><?= esc(ucfirst((string)$officer['status'])) ?></span><span class="tag tag-info"><i class="bi bi-geo-alt me-1"></i><?= esc($officer['zone_name'] ?: 'Unassigned') ?></span></div></div>
      <div class="enforcer-record-grid">
        <section class="enforcer-record-panel"><div class="enforcer-record-panel-head"><i class="bi bi-person-vcard"></i><strong>Personal Information</strong></div><div class="enforcer-record-fields"><div class="enforcer-record-field"><small>Birth date</small><strong><?= esc($officer['birth_date'] ?: 'Not provided') ?></strong></div><div class="enforcer-record-field"><small>Contact number</small><strong><?= esc($officer['contact_number'] ?: 'Not provided') ?></strong></div><div class="enforcer-record-field wide"><small>Email address</small><strong><?= esc($officer['email'] ?: 'Not provided') ?></strong></div><div class="enforcer-record-field wide"><small>Residential address</small><strong><?= esc($officer['address'] ?: 'Not provided') ?></strong></div></div></section>
        <section class="enforcer-record-panel"><div class="enforcer-record-panel-head"><i class="bi bi-person-badge"></i><strong>Employment &amp; Assignment</strong></div><div class="enforcer-record-fields"><div class="enforcer-record-field"><small>Employee ID</small><strong><?= esc($officer['badge_number']) ?></strong></div><div class="enforcer-record-field"><small>Position / rank</small><strong><?= esc($officer['rank']) ?></strong></div><div class="enforcer-record-field"><small>Hire date</small><strong><?= esc($officer['hire_date'] ?: 'Not provided') ?></strong></div><div class="enforcer-record-field"><small>Status</small><strong><?= esc(ucfirst((string)$officer['status'])) ?></strong></div><div class="enforcer-record-field wide"><small>Assigned location</small><strong><?= esc($officer['zone_name'] ?: 'Unassigned') ?><?= !empty($officer['location_details']) ? ' — ' . esc($officer['location_details']) : '' ?></strong></div><div class="enforcer-record-field"><small>Duty schedules</small><strong><?= num((int)$officer['schedule_count']) ?></strong></div><div class="enforcer-record-field"><small>Presence logs</small><strong><?= num((int)$officer['presence_count']) ?></strong></div></div></section>
        <section class="enforcer-record-panel"><div class="enforcer-record-panel-head"><i class="bi bi-telephone-plus"></i><strong>Emergency Contact</strong></div><div class="enforcer-record-fields"><div class="enforcer-record-field"><small>Contact person</small><strong><?= esc($officer['emergency_contact_name'] ?: 'Not provided') ?></strong></div><div class="enforcer-record-field"><small>Contact number</small><strong><?= esc($officer['emergency_contact_number'] ?: 'Not provided') ?></strong></div></div></section>
        <section class="enforcer-record-panel"><div class="enforcer-record-panel-head"><i class="bi bi-clock-history"></i><strong>Record Information</strong></div><div class="enforcer-record-fields"><div class="enforcer-record-field"><small>Profile created</small><strong><?= esc((string)$officer['created_at']) ?></strong></div><div class="enforcer-record-field"><small>Last updated</small><strong><?= esc((string)$officer['updated_at']) ?></strong></div></div></section>
      </div>
    </div>
    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" type="button" data-bs-dismiss="modal" data-bs-toggle="modal" data-bs-target="#editEnforcer<?= $officerId ?>"><i class="bi bi-pencil-square me-1"></i>Edit Profile</button></div>
  </div></div></div>

  <div class="modal fade enforcer-modal enforcer-form-modal" id="editEnforcer<?= $officerId ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><form method="post" autocomplete="off">
    <div class="modal-header"><div><h5 class="modal-title">Edit Traffic Enforcer</h5><small class="text-muted"><?= esc($fullName) ?> · <?= esc($officer['badge_number']) ?></small></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><input type="hidden" name="action" value="update_enforcer"><input type="hidden" name="officer_id" value="<?= $officerId ?>"><input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>"><?php enforcer_form_fields($officer, $zones, 'editEnforcer' . $officerId); ?></div>
    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button></div>
  </form></div></div></div>

  <div class="modal fade" id="deleteEnforcer<?= $officerId ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" autocomplete="off">
    <div class="modal-header"><div><h5 class="modal-title text-danger">Delete Traffic Enforcer</h5><small class="text-muted">This permanently removes the employee profile.</small></div><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><input type="hidden" name="action" value="delete_enforcer"><input type="hidden" name="officer_id" value="<?= $officerId ?>"><input type="hidden" name="csrf_token" value="<?= esc(csrf_token()) ?>"><div class="enforcer-danger-note mb-3"><i class="bi bi-exclamation-triangle-fill"></i><div><strong><?= esc($fullName) ?></strong><br><small><?= esc($officer['badge_number']) ?> · <?= esc($officer['rank']) ?></small></div></div><label class="form-label" for="deleteEnforcerPassword<?= $officerId ?>">Administrator Password</label><input type="password" class="form-control" id="deleteEnforcerPassword<?= $officerId ?>" name="admin_password" autocomplete="current-password" placeholder="Enter your password to confirm" required><small class="text-muted d-block mt-2">Associated duty schedules and presence logs will also be removed. Historical violation tickets keep the recorded officer name.</small></div>
    <div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Cancel</button><button class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete Enforcer</button></div>
  </form></div></div></div>
<?php endforeach; ?>

<script>
document.querySelectorAll('#addEnforcerModal, #addZoneModal, .enforcer-modal, [id^="deleteEnforcer"]').forEach(function (modal) {
  document.body.appendChild(modal);
});
</script>

<?php page_end(); ?>
