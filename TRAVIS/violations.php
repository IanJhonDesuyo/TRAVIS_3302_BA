<?php
require_once __DIR__ . '/layout.php';

$message = '';

function post($key, $default = '') {
    return trim($_POST[$key] ?? $default);
}

function generateTicketNumber($conn) {
    $prefix = 'TRV-' . date('Ymd') . '-';

    $stmt = $conn->prepare("
        SELECT ticket_number
        FROM violations
        WHERE ticket_number LIKE CONCAT(?, '%')
        ORDER BY violation_id DESC
        LIMIT 1
    ");

    $stmt->bind_param("s", $prefix);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $lastNumber = (int) substr($row['ticket_number'], -6);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    return $prefix . str_pad((string) $nextNumber, 6, '0', STR_PAD_LEFT);
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_violation') {
        $stmt = $conn->prepare("
            INSERT INTO violations
            (ticket_number, driver_name, license_number, plate_number, vehicle_type, violation_type, violation_location, violation_date, violation_time, penalty_amount, input_method, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', 'pending')
        ");

        $ticket = generateTicketNumber($conn);
        $driver = post('driver_name');
        $license = post('license_number');
        $plate = post('plate_number');
        $vehicle = post('vehicle_type');
        $type = post('violation_type');
        $location = post('violation_location');
        $date = post('violation_date');
        $time = post('violation_time');
        $amount = (float) post('penalty_amount', '0');

        $stmt->bind_param(
            "sssssssssd",
            $ticket,
            $driver,
            $license,
            $plate,
            $vehicle,
            $type,
            $location,
            $date,
            $time,
            $amount
        );

        $message = $stmt->execute()
            ? 'Violation record added successfully. Ticket No.: ' . $ticket
            : 'Failed to add violation record. Ticket number may already exist.';
    }

    if ($action === 'delete_violation') {
        $id = (int) ($_POST['violation_id'] ?? 0);

        $stmt = $conn->prepare("DELETE FROM violations WHERE violation_id = ?");
        $stmt->bind_param("i", $id);

        $message = $stmt->execute()
            ? 'Violation record deleted successfully.'
            : 'Failed to delete violation record.';
    }

    if ($action === 'mark_paid') {
        $id = (int) ($_POST['violation_id'] ?? 0);
        $amount = (float) ($_POST['amount_paid'] ?? 0);

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("UPDATE violations SET status = 'paid' WHERE violation_id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();

            $stmt = $conn->prepare("
                INSERT INTO payments
                (violation_id, amount_paid, payment_status, payment_method)
                VALUES (?, ?, 'completed', 'cash')
            ");
            $stmt->bind_param("id", $id, $amount);
            $stmt->execute();

            $conn->commit();
            $message = 'Payment recorded successfully.';
        } catch (Exception $e) {
            $conn->rollback();
            $message = 'Failed to record payment.';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(ticket_number LIKE ? OR driver_name LIKE ? OR plate_number LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($status !== '') {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$totalToday = scalar("SELECT COUNT(*) FROM violations WHERE violation_date = CURDATE()", 0);
$unpaid = scalar("SELECT COUNT(*) FROM violations WHERE status IN ('pending','overdue')", 0);
$paid = scalar("SELECT COUNT(*) FROM violations WHERE status = 'paid'", 0);
$pending = scalar("SELECT COUNT(*) FROM violations WHERE status = 'pending'", 0);

$sql = "
    SELECT v.*, u.full_name AS encoded_by_name
    FROM violations v
    LEFT JOIN users u ON u.user_id = v.encoded_by
    $whereSql
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
    <h3 class="page-title">Violations & Manual Encoding</h3>
    <p class="page-sub">Web-based manual recording of issued paper tickets. OCR scanning will be handled by the mobile application.</p>
  </div>

  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addViolationModal">
    <i class="bi bi-keyboard me-1"></i>Manual Input
  </button>
</div>

<?php if ($message): ?>
<div class="alert alert-info"><?= esc($message) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-cone-striped"></i></div>
      <div class="stat-label">Total Today</div>
      <div class="stat-value"><?= num($totalToday) ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-danger"><i class="bi bi-exclamation"></i></div>
      <div class="stat-label">Unpaid</div>
      <div class="stat-value"><?= num($unpaid) ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-check2-circle"></i></div>
      <div class="stat-label">Paid</div>
      <div class="stat-value"><?= num($paid) ?></div>
    </div>
  </div>

  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-clock-history"></i></div>
      <div class="stat-label">Pending Review</div>
      <div class="stat-value"><?= num($pending) ?></div>
    </div>
  </div>
</div>

<div class="section-card">
  <div class="section-head">
    <h6>Violation Records</h6>

    <form method="get" class="d-flex gap-2">
      <input class="form-control form-control-sm" name="search" value="<?= esc($search) ?>" placeholder="Search plate, ticket, or driver...">

      <select class="form-select form-select-sm" name="status">
        <option value="">All Status</option>
        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
        <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
        <option value="overdue" <?= $status === 'overdue' ? 'selected' : '' ?>>Overdue</option>
        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
      </select>

      <button class="btn btn-sm btn-primary">Filter</button>
    </form>
  </div>

  <?php if (!$violations): ?>
    <?php empty_state('No violation records found. Web records will appear here after manual input. Mobile OCR records will also appear here after saving to the database.'); ?>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Ticket</th>
            <th>Driver</th>
            <th>Plate</th>
            <th>Violation</th>
            <th>Location</th>
            <th>Date/Time</th>
            <th>Status</th>
            <th>Fine</th>
            <th>Actions</th>
          </tr>
        </thead>

        <tbody>
          <?php foreach ($violations as $v): ?>
          <tr>
            <td class="fw-semibold"><?= esc($v['ticket_number']) ?></td>
            <td>
              <?= esc($v['driver_name']) ?><br>
              <small class="text-muted"><?= esc($v['license_number']) ?></small>
            </td>
            <td>
              <?= esc($v['plate_number']) ?><br>
              <small class="text-muted"><?= esc($v['vehicle_type']) ?></small>
            </td>
            <td><?= esc($v['violation_type']) ?></td>
            <td><?= esc($v['violation_location']) ?></td>
            <td><?= esc($v['violation_date'] . ' ' . $v['violation_time']) ?></td>
            <td>
              <span class="tag <?= tag_class($v['status']) ?>">
                <?= esc($v['status']) ?>
              </span>
            </td>
            <td class="fw-semibold"><?= peso($v['penalty_amount']) ?></td>
            <td>
              <button class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#view<?= $v['violation_id'] ?>">
                <i class="bi bi-eye"></i>
              </button>

              <?php if ($v['status'] !== 'paid'): ?>
              <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#pay<?= $v['violation_id'] ?>">
                <i class="bi bi-cash"></i>
              </button>
              <?php endif; ?>

              <form method="post" class="d-inline" onsubmit="return confirm('Delete this violation record?');">
                <input type="hidden" name="action" value="delete_violation">
                <input type="hidden" name="violation_id" value="<?= $v['violation_id'] ?>">
                <button class="btn btn-sm btn-light text-danger">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
            </td>
          </tr>

          <div class="modal fade" id="view<?= $v['violation_id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
              <div class="modal-content">
                <div class="modal-header">
                  <h5 class="modal-title">Violation Details</h5>
                  <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-md-6"><strong>Ticket Number:</strong><br><?= esc($v['ticket_number']) ?></div>
                    <div class="col-md-6"><strong>Status:</strong><br><?= esc($v['status']) ?></div>
                    <div class="col-md-6"><strong>Driver:</strong><br><?= esc($v['driver_name']) ?></div>
                    <div class="col-md-6"><strong>License:</strong><br><?= esc($v['license_number']) ?></div>
                    <div class="col-md-6"><strong>Plate:</strong><br><?= esc($v['plate_number']) ?></div>
                    <div class="col-md-6"><strong>Vehicle Type:</strong><br><?= esc($v['vehicle_type']) ?></div>
                    <div class="col-md-6"><strong>Violation:</strong><br><?= esc($v['violation_type']) ?></div>
                    <div class="col-md-6"><strong>Location:</strong><br><?= esc($v['violation_location']) ?></div>
                    <div class="col-md-6"><strong>Date/Time:</strong><br><?= esc($v['violation_date'] . ' ' . $v['violation_time']) ?></div>
                    <div class="col-md-6"><strong>Penalty:</strong><br><?= peso($v['penalty_amount']) ?></div>
                    <div class="col-md-6"><strong>Input Method:</strong><br><?= esc($v['input_method']) ?></div>
                    <div class="col-md-6"><strong>Encoded By:</strong><br><?= esc($v['encoded_by_name'] ?? 'System / Mobile App') ?></div>
                  </div>
                </div>

                <div class="modal-footer">
                  <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
                  <button class="btn btn-primary" onclick="window.print()">Print</button>
                </div>
              </div>
            </div>
          </div>

          <div class="modal fade" id="pay<?= $v['violation_id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
              <div class="modal-content">
                <form method="post">
                  <div class="modal-header">
                    <h5 class="modal-title">Record Payment</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                  </div>

                  <div class="modal-body">
                    <input type="hidden" name="action" value="mark_paid">
                    <input type="hidden" name="violation_id" value="<?= $v['violation_id'] ?>">

                    <p class="mb-2"><strong>Ticket:</strong> <?= esc($v['ticket_number']) ?></p>
                    <p class="mb-3"><strong>Penalty:</strong> <?= peso($v['penalty_amount']) ?></p>

                    <label class="form-label">Amount Paid</label>
                    <input type="number" step="0.01" name="amount_paid" class="form-control" value="<?= esc($v['penalty_amount']) ?>" required>
                  </div>

                  <div class="modal-footer">
                    <button class="btn btn-light" data-bs-dismiss="modal" type="button">Cancel</button>
                    <button class="btn btn-success">Confirm Payment</button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<div class="modal fade" id="addViolationModal" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title">Manual Violation Input</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" name="action" value="add_violation">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Ticket Number</label>
              <input type="text" class="form-control" value="Automatically generated by the system" readonly>
              <small class="text-muted">Format: TRV-YYYYMMDD-000001</small>
            </div>

            <div class="col-md-6">
              <label class="form-label">Driver Name</label>
              <input type="text" name="driver_name" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">License Number</label>
              <input type="text" name="license_number" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Plate Number</label>
              <input type="text" name="plate_number" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Vehicle Type</label>
              <select name="vehicle_type" class="form-select" required>
                <option value="">Select vehicle type</option>
                <option>Motorcycle</option>
                <option>Car</option>
                <option>SUV</option>
                <option>Truck</option>
                <option>Bus</option>
                <option>Other</option>
              </select>
            </div>

            <div class="col-md-6">
              <label class="form-label">Violation Type</label>
              <input type="text" name="violation_type" class="form-control" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Violation Location</label>
              <input type="text" name="violation_location" class="form-control" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Date</label>
              <input type="date" name="violation_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-md-3">
              <label class="form-label">Time</label>
              <input type="time" name="violation_time" class="form-control" value="<?= date('H:i') ?>" required>
            </div>

            <div class="col-md-6">
              <label class="form-label">Penalty Amount</label>
              <input type="number" step="0.01" name="penalty_amount" class="form-control" required>
            </div>
          </div>

          <small class="text-muted d-block mt-3">
            OCR input is handled by the mobile application. Records saved from mobile OCR will also appear in this table.
          </small>
        </div>

        <div class="modal-footer">
          <button class="btn btn-light" data-bs-dismiss="modal" type="button">Cancel</button>
          <button class="btn btn-primary">Save Violation</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php page_end(); ?>