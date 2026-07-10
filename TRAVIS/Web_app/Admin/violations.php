<?php
require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';

function violation_post(string $key, string $default = ''): string
{
    return trim((string)($_POST[$key] ?? $default));
}

function generateTicketNumber(mysqli $conn): string
{
    $prefix = 'TRV-' . date('Ymd') . '-';
    $stmt = $conn->prepare("
        SELECT ticket_number
        FROM violations
        WHERE ticket_number LIKE CONCAT(?, '%')
        ORDER BY violation_id DESC
        LIMIT 1
    ");
    $stmt->bind_param('s', $prefix);
    $stmt->execute();
    $result = $stmt->get_result();

    $nextNumber = 1;
    if ($row = $result->fetch_assoc()) {
        $nextNumber = ((int)substr((string)$row['ticket_number'], -6)) + 1;
    }

    return $prefix . str_pad((string)$nextNumber, 6, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_violation') {
        $driver = violation_post('driver_name');
        $license = violation_post('license_number');
        $plate = strtoupper(violation_post('plate_number'));
        $vehicle = violation_post('vehicle_type');
        $type = violation_post('violation_type');
        $location = violation_post('violation_location');
        $date = violation_post('violation_date');
        $time = violation_post('violation_time');
        $amount = (float)violation_post('penalty_amount', '0');

        if (
            $driver === '' || $license === '' || $plate === '' ||
            $vehicle === '' || $type === '' || $location === '' ||
            $date === '' || $time === '' || $amount <= 0
        ) {
            $message = 'Please complete all required fields and enter a valid penalty amount.';
            $messageType = 'danger';
        } else {
            $ticket = generateTicketNumber($conn);

            $stmt = $conn->prepare("
                INSERT INTO violations (
                    ticket_number, driver_name, license_number, plate_number,
                    vehicle_type, violation_type, violation_location,
                    violation_date, violation_time, penalty_amount,
                    input_method, status
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', 'pending')
            ");

            $stmt->bind_param(
                'sssssssssd',
                $ticket, $driver, $license, $plate, $vehicle,
                $type, $location, $date, $time, $amount
            );

            if ($stmt->execute()) {
                $message = 'Violation record added successfully. Ticket No.: ' . $ticket;
                $messageType = 'success';
            } else {
                $message = 'Failed to add the violation record.';
                $messageType = 'danger';
            }
        }
    }

    if ($action === 'cancel_violation') {
        $id = (int)($_POST['violation_id'] ?? 0);

        $stmt = $conn->prepare("
            UPDATE violations
            SET status = 'cancelled'
            WHERE violation_id = ?
              AND status <> 'paid'
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $message = 'Violation record was cancelled. The record remains available for audit purposes.';
            $messageType = 'success';
        } else {
            $message = 'The violation could not be cancelled. Paid records cannot be cancelled.';
            $messageType = 'warning';
        }
    }
}

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(v.ticket_number LIKE ? OR v.driver_name LIKE ? OR v.plate_number LIKE ? OR v.violation_type LIKE ? OR v.violation_location LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like, $like);
    $types .= 'sssss';
}

if ($status !== '') {
    $where[] = 'v.status = ?';
    $params[] = $status;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalToday = scalar("SELECT COUNT(*) FROM violations WHERE violation_date = CURDATE()", 0);
$unpaid = scalar("SELECT COUNT(*) FROM violations WHERE status IN ('pending', 'overdue')", 0);
$paid = scalar("SELECT COUNT(*) FROM violations WHERE status = 'paid'", 0);
$cancelled = scalar("SELECT COUNT(*) FROM violations WHERE status = 'cancelled'", 0);

$sql = "
    SELECT v.*, u.full_name AS encoded_by_name
    FROM violations v
    LEFT JOIN users u ON u.user_id = v.encoded_by
    {$whereSql}
    ORDER BY v.created_at DESC
    LIMIT 100
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $violations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $violations = fetch_all($sql);
}

page_start('Violations', 'violations', 'Search plate or ticket...');
?>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <h3 class="page-title">Violation Records</h3>
    <p class="page-sub">Record, review, and route unpaid traffic violations to the payment module.</p>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addViolationModal">
    <i class="bi bi-plus-lg me-1"></i>Add Violation
  </button>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-warning"><i class="bi bi-calendar-day"></i></div><div class="stat-label">Recorded Today</div><div class="stat-value"><?= num($totalToday) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-danger"><i class="bi bi-hourglass-split"></i></div><div class="stat-label">Awaiting Payment</div><div class="stat-value"><?= num($unpaid) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-success"><i class="bi bi-check2-circle"></i></div><div class="stat-label">Paid</div><div class="stat-value"><?= num($paid) ?></div></div></div>
  <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon tone-primary"><i class="bi bi-slash-circle"></i></div><div class="stat-label">Cancelled</div><div class="stat-value"><?= num($cancelled) ?></div></div></div>
</div>

<div class="section-card">
  <div class="section-head flex-wrap gap-2">
    <div>
      <h6 class="mb-0">Violation Records</h6>
      <small class="text-muted">Payment processing is handled in the Payments page.</small>
    </div>

    <form method="get" class="d-flex flex-wrap gap-2">
      <input class="form-control form-control-sm" name="search" value="<?= esc($search) ?>" placeholder="Ticket, driver, plate, violation, location...">
      <select class="form-select form-select-sm" name="status">
        <option value="">All Statuses</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
      </select>
      <button class="btn btn-sm btn-primary">Filter</button>
      <?php if ($search !== '' || $status !== ''): ?>
        <a class="btn btn-sm btn-light" href="<?= esc(app_url('violations.php')) ?>">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$violations): ?>
    <?php empty_state('No violation records matched your search or filter.'); ?>
  <?php else: ?>
    <div class="table-responsive table-scroll">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Ticket</th><th>Driver / License</th><th>Vehicle</th><th>Violation</th>
            <th>Location</th><th>Date & Time</th><th>Fine</th><th>Status</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($violations as $v): ?>
            <tr>
              <td class="fw-semibold"><?= esc($v['ticket_number']) ?></td>
              <td><?= esc($v['driver_name']) ?><br><small class="text-muted"><?= esc($v['license_number']) ?></small></td>
              <td><?= esc($v['plate_number']) ?><br><small class="text-muted"><?= esc($v['vehicle_type']) ?></small></td>
              <td><?= esc($v['violation_type']) ?></td>
              <td><?= esc($v['violation_location']) ?></td>
              <td><?= esc($v['violation_date']) ?><br><small class="text-muted"><?= esc($v['violation_time']) ?></small></td>
              <td class="fw-semibold"><?= peso($v['penalty_amount']) ?></td>
              <td><span class="tag <?= tag_class($v['status']) ?>"><?= esc(ucfirst($v['status'])) ?></span></td>
              <td class="text-end text-nowrap">
                <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#view<?= (int)$v['violation_id'] ?>" title="View details"><i class="bi bi-eye"></i></button>

                <?php if (in_array($v['status'], ['pending', 'overdue'], true)): ?>
                  <a class="btn btn-sm btn-success" href="<?= esc(app_url('payments.php?violation_id=' . (int)$v['violation_id'])) ?>" title="Proceed to payment"><i class="bi bi-cash-coin"></i></a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Cancel this violation record? The record will not be deleted.');">
                    <input type="hidden" name="action" value="cancel_violation">
                    <input type="hidden" name="violation_id" value="<?= (int)$v['violation_id'] ?>">
                    <button class="btn btn-sm btn-light text-danger" title="Cancel record"><i class="bi bi-slash-circle"></i></button>
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

<?php foreach ($violations as $v): ?>
  <div class="modal fade" id="view<?= (int)$v['violation_id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div><h5 class="modal-title">Violation Details</h5><small class="text-muted"><?= esc($v['ticket_number']) ?></small></div>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><strong>Status</strong><br><span class="tag <?= tag_class($v['status']) ?>"><?= esc(ucfirst($v['status'])) ?></span></div>
            <div class="col-md-6"><strong>Penalty</strong><br><?= peso($v['penalty_amount']) ?></div>
            <div class="col-md-6"><strong>Driver</strong><br><?= esc($v['driver_name']) ?></div>
            <div class="col-md-6"><strong>License Number</strong><br><?= esc($v['license_number']) ?></div>
            <div class="col-md-6"><strong>Plate Number</strong><br><?= esc($v['plate_number']) ?></div>
            <div class="col-md-6"><strong>Vehicle Type</strong><br><?= esc($v['vehicle_type']) ?></div>
            <div class="col-md-6"><strong>Violation Type</strong><br><?= esc($v['violation_type']) ?></div>
            <div class="col-md-6"><strong>Location</strong><br><?= esc($v['violation_location']) ?></div>
            <div class="col-md-6"><strong>Date & Time</strong><br><?= esc($v['violation_date'] . ' ' . $v['violation_time']) ?></div>
            <div class="col-md-6"><strong>Input Method</strong><br><?= esc($v['input_method']) ?></div>
            <div class="col-md-6"><strong>Encoded By</strong><br><?= esc($v['encoded_by_name'] ?? 'System / Mobile App') ?></div>
            <div class="col-md-6"><strong>Created At</strong><br><?= esc($v['created_at']) ?></div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
          <?php if (in_array($v['status'], ['pending', 'overdue'], true)): ?>
            <a class="btn btn-success" href="<?= esc(app_url('payments.php?violation_id=' . (int)$v['violation_id'])) ?>"><i class="bi bi-cash-coin me-1"></i>Proceed to Payment</a>
          <?php endif; ?>
          <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<div class="modal fade" id="addViolationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <div><h5 class="modal-title">Manual Violation Input</h5><small class="text-muted">For manually encoded paper ticket records</small></div>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="add_violation">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Ticket Number</label><input type="text" class="form-control" value="Automatically generated" readonly><small class="text-muted">Format: TRV-YYYYMMDD-000001</small></div>
            <div class="col-md-6"><label class="form-label">Driver Name</label><input type="text" name="driver_name" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">License Number</label><input type="text" name="license_number" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Plate Number</label><input type="text" name="plate_number" class="form-control text-uppercase" required></div>
            <div class="col-md-6"><label class="form-label">Vehicle Type</label><select name="vehicle_type" class="form-select" required><option value="">Select vehicle type</option><option>Motorcycle</option><option>Car</option><option>SUV</option><option>Jeepney</option><option>Tricycle</option><option>Van</option><option>Truck</option><option>Bus</option><option>Other</option></select></div>
            <div class="col-md-6"><label class="form-label">Violation Type</label><input type="text" name="violation_type" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Violation Location</label><input type="text" name="violation_location" class="form-control" placeholder="Nasugbu, Batangas location" required></div>
            <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="violation_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-3"><label class="form-label">Time</label><input type="time" name="violation_time" class="form-control" value="<?= date('H:i') ?>" required></div>
            <div class="col-md-6"><label class="form-label">Penalty Amount</label><input type="number" min="0.01" step="0.01" name="penalty_amount" class="form-control" required></div>
          </div>
          <small class="text-muted d-block mt-3">OCR scanning will be handled by the mobile application. Mobile records saved to the same database will also appear here.</small>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Violation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php page_end(); ?>
