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

        $allowedViolations = traffic_violation_types();
        $allowedFees = array_map('floatval', traffic_penalty_fees());

        if (
            $driver === '' || $license === '' || $plate === '' ||
            $vehicle === '' || $type === '' || $location === '' ||
            $date === '' || $time === '' || $amount <= 0
        ) {
            $message = 'Please complete all required fields and enter a valid penalty amount.';
            $messageType = 'danger';
        } elseif (!in_array($type, $allowedViolations, true)) {
            $message = 'Please select a valid violation from the traffic ticket list.';
            $messageType = 'danger';
        } elseif (!in_array($amount, $allowedFees, true)) {
            $message = 'Please select a valid penalty fee from the available amounts.';
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
  <?php feedback_notice($message, $messageType); ?>
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
                  <form method="post" class="d-inline" data-confirm="The violation will be marked as cancelled but retained for audit history. Paid records cannot be cancelled." data-confirm-title="Cancel this violation?" data-confirm-label="Cancel violation" data-confirm-eyebrow="Record status" data-confirm-tone="danger">
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
  <div class="modal fade violation-detail-modal" id="view<?= (int)$v['violation_id'] ?>" tabindex="-1" aria-labelledby="viewTitle<?= (int)$v['violation_id'] ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="violation-detail-accent" aria-hidden="true"></div>
        <div class="modal-header violation-detail-header">
          <div class="violation-detail-heading">
            <span class="violation-detail-icon"><i class="bi bi-file-earmark-text"></i></span>
            <div>
              <span class="violation-detail-eyebrow">Enforcement record</span>
              <h5 class="modal-title" id="viewTitle<?= (int)$v['violation_id'] ?>">Violation Details</h5>
            </div>
          </div>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Close violation details"></button>
        </div>

        <div class="modal-body violation-detail-body">
          <div class="violation-detail-summary">
            <div class="violation-ticket-summary">
              <span class="violation-summary-label">Ticket number</span>
              <strong><?= esc($v['ticket_number']) ?></strong>
              <span class="tag <?= tag_class($v['status']) ?>"><?= esc(ucfirst($v['status'])) ?></span>
            </div>
            <div class="violation-penalty-summary">
              <span class="violation-summary-label">Total penalty</span>
              <strong><?= peso($v['penalty_amount']) ?></strong>
              <small><i class="bi bi-shield-check"></i> Official recorded amount</small>
            </div>
          </div>

          <div class="violation-detail-section">
            <div class="violation-section-title">
              <span><i class="bi bi-person-vcard"></i></span>
              <div><strong>Driver & Vehicle</strong><small>Registered identity and vehicle information</small></div>
            </div>
            <div class="violation-detail-grid">
              <div class="violation-detail-item"><span>Driver</span><strong><?= esc($v['driver_name']) ?></strong></div>
              <div class="violation-detail-item"><span>License number</span><strong><?= esc($v['license_number']) ?></strong></div>
              <div class="violation-detail-item"><span>Plate number</span><strong class="violation-plate"><?= esc($v['plate_number']) ?></strong></div>
              <div class="violation-detail-item"><span>Vehicle type</span><strong><?= esc($v['vehicle_type']) ?></strong></div>
            </div>
          </div>

          <div class="violation-detail-section">
            <div class="violation-section-title">
              <span><i class="bi bi-geo-alt"></i></span>
              <div><strong>Incident Information</strong><small>Violation classification, place, and occurrence</small></div>
            </div>
            <div class="violation-detail-grid">
              <div class="violation-detail-item"><span>Violation type</span><strong><?= esc($v['violation_type']) ?></strong></div>
              <div class="violation-detail-item"><span>Date & time</span><strong><?= esc($v['violation_date'] . ' ' . $v['violation_time']) ?></strong></div>
              <div class="violation-detail-item violation-detail-wide"><span>Location</span><strong><?= esc($v['violation_location']) ?></strong></div>
            </div>
          </div>

          <div class="violation-detail-section violation-audit-section">
            <div class="violation-section-title">
              <span><i class="bi bi-clock-history"></i></span>
              <div><strong>Record Audit</strong><small>Source and creation information</small></div>
            </div>
            <div class="violation-detail-grid violation-audit-grid">
              <div class="violation-detail-item"><span>Input method</span><strong><?= esc($v['input_method']) ?></strong></div>
              <div class="violation-detail-item"><span>Encoded by</span><strong><?= esc($v['encoded_by_name'] ?? 'System / Mobile App') ?></strong></div>
              <div class="violation-detail-item"><span>Created at</span><strong><?= esc($v['created_at']) ?></strong></div>
            </div>
          </div>
        </div>

        <div class="modal-footer violation-detail-footer">
          <button class="btn violation-close-btn" data-bs-dismiss="modal"><i class="bi bi-x-lg"></i><span>Close</span></button>
          <div class="violation-detail-actions">
          <?php if (in_array($v['status'], ['pending', 'overdue'], true)): ?>
            <a class="btn violation-payment-btn" href="<?= esc(app_url('payments.php?violation_id=' . (int)$v['violation_id'])) ?>"><i class="bi bi-cash-coin"></i><span>Proceed to Payment</span></a>
          <?php endif; ?>
            <button class="btn violation-print-btn" type="button" onclick="window.print()"><i class="bi bi-printer"></i><span>Print</span></button>
          </div>
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
            <div class="col-md-6">
              <label class="form-label" for="violationTypeInput">Violation Type</label>
              <input type="text" id="violationTypeInput" name="violation_type" class="form-control" list="violationTypeOptions" placeholder="Type to search ticket violations..." autocomplete="off" required>
              <datalist id="violationTypeOptions">
                <?php foreach (traffic_violation_types() as $violationType): ?>
                  <option value="<?= esc($violationType) ?>"></option>
                <?php endforeach; ?>
              </datalist>
              <small class="text-muted">Start typing to filter the available violations.</small>
            </div>
            <div class="col-md-6"><label class="form-label">Violation Location</label><input type="text" name="violation_location" class="form-control" placeholder="Nasugbu, Batangas location" required></div>
            <div class="col-md-3"><label class="form-label">Date</label><input type="date" name="violation_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
            <div class="col-md-3"><label class="form-label">Time</label><input type="time" name="violation_time" class="form-control" value="<?= date('H:i') ?>" required></div>
            <div class="col-md-6">
              <label class="form-label" for="penaltyFeeInput">Penalty Fee</label>
              <input type="number" id="penaltyFeeInput" name="penalty_amount" class="form-control" list="penaltyFeeOptions" min="100" step="100" placeholder="Type or select a fee..." autocomplete="off" required>
              <datalist id="penaltyFeeOptions">
                <?php foreach (traffic_penalty_fees() as $penaltyFee): ?>
                  <option value="<?= esc($penaltyFee) ?>" label="<?= peso($penaltyFee) ?>"></option>
                <?php endforeach; ?>
              </datalist>
              <small class="text-muted">Type to filter, then choose the fee indicated by the issuing office.</small>
            </div>
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
