<?php
require_once __DIR__ . '/layout.php';

$search = trim((string)($_GET['search'] ?? ''));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$method = trim((string)($_GET['method'] ?? ''));

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(v.ticket_number LIKE ? OR v.plate_number LIKE ? OR p.payment_id LIKE ? OR p.receipt_reference LIKE ?)";
    $like = "%{$search}%";
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}

if ($dateFrom !== '') {
    $where[] = 'DATE(p.payment_date) >= ?';
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '') {
    $where[] = 'DATE(p.payment_date) <= ?';
    $params[] = $dateTo;
    $types .= 's';
}

if ($method !== '') {
    $where[] = 'p.payment_method = ?';
    $params[] = $method;
    $types .= 's';
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

if (($_GET['action'] ?? '') === 'export_excel') {
    $exportSql = "
        SELECT p.payment_id, v.ticket_number, v.plate_number, p.amount_paid,
               p.payment_date, p.payment_method, p.payment_status, u.full_name AS received_by_name
        FROM payments p
        JOIN violations v ON v.violation_id = p.violation_id
        LEFT JOIN users u ON u.user_id = p.received_by
        {$whereSql}
        ORDER BY p.payment_date DESC, p.payment_id DESC
    ";
    if ($params) {
        $stmt = $conn->prepare($exportSql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $exportRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $exportRows = fetch_all($exportSql);
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="travis-payment-history-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Receipt No.', 'Violation ID', 'Plate Number', 'Amount', 'Payment Date', 'Method', 'Status', 'Processed By']);
    foreach ($exportRows as $row) {
        fputcsv($out, [
            payment_reference((int)$row['payment_id']),
            $row['ticket_number'],
            $row['plate_number'],
            $row['amount_paid'],
            $row['payment_date'],
            payment_method_label($row['payment_method']),
            ucfirst($row['payment_status']),
            $row['received_by_name'] ?? 'Not recorded',
        ]);
    }
    fclose($out);
    exit;
}

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$totalCount = (int)scalar("
    SELECT COUNT(*)
    FROM payments p
    JOIN violations v ON v.violation_id = p.violation_id
    {$whereSql}
", 0, $params);
$totalPages = max(1, (int)ceil($totalCount / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT p.*, v.ticket_number, v.plate_number, v.violation_type, v.violation_location,
           v.driver_name, u.full_name AS received_by_name
    FROM payments p
    JOIN violations v ON v.violation_id = p.violation_id
    LEFT JOIN users u ON u.user_id = p.received_by
    {$whereSql}
    ORDER BY p.payment_date DESC, p.payment_id DESC
    LIMIT {$perPage} OFFSET {$offset}
";

if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $history = fetch_all($sql);
}

$methodOptions = payment_method_options();

page_start('Payment History', 'history', 'Search receipt, plate...', 'Recorded payments and receipts', false);
?>

<div class="section-card">
  <div class="section-head flex-wrap gap-2">
    <div><h6 class="mb-0">Transactions</h6><small class="text-muted"><?= num($totalCount) ?> total &middot; page <?= num($page) ?> of <?= num($totalPages) ?></small></div>
    <form method="get" class="d-flex flex-wrap gap-2 align-items-center">
      <input class="form-control form-control-sm" style="width:220px" name="search" value="<?= esc($search) ?>" placeholder="Search receipt, plate, ID...">
      <input class="form-control form-control-sm" style="width:150px" type="date" name="date_from" value="<?= esc($dateFrom) ?>">
      <input class="form-control form-control-sm" style="width:150px" type="date" name="date_to" value="<?= esc($dateTo) ?>">
      <select class="form-select form-select-sm" style="width:160px" name="method">
        <option value="">All Methods</option>
        <?php foreach ($methodOptions as $value => $label): ?>
          <option value="<?= esc($value) ?>" <?= $method === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-sm btn-teal">Apply</button>
      <?php if ($search !== '' || $dateFrom !== '' || $dateTo !== '' || $method !== ''): ?>
        <a class="btn btn-sm btn-light" href="<?= esc(app_url('history.php')) ?>">Clear</a>
      <?php endif; ?>
    </form>
  </div>

  <?php if (!$history): ?>
    <?php empty_state('No payment transactions matched your current filters.'); ?>
  <?php else: ?>
    <div class="table-responsive table-scroll">
      <table class="table align-middle">
        <thead><tr><th>Receipt No.</th><th>Violation ID</th><th>Plate Number</th><th>Amount</th><th>Payment Date</th><th>Processed By</th><th>Status</th><th class="text-end">Action</th></tr></thead>
        <tbody>
          <?php foreach ($history as $h): ?>
            <tr>
              <td class="fw-semibold"><?= esc(payment_reference((int)$h['payment_id'])) ?></td>
              <td><?= esc($h['ticket_number']) ?></td>
              <td><?= esc($h['plate_number']) ?></td>
              <td class="fw-semibold"><?= peso($h['amount_paid']) ?></td>
              <td><?= esc($h['payment_date']) ?></td>
              <td><?= esc($h['received_by_name'] ?? 'Not recorded') ?></td>
              <td><span class="tag <?= tag_class($h['payment_status']) ?>"><?= esc(ucfirst($h['payment_status'])) ?></span></td>
              <td class="text-end">
                <button class="icon-link" data-bs-toggle="modal" data-bs-target="#receipt<?= (int)$h['payment_id'] ?>">
                  <i class="bi bi-printer"></i> Receipt
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ($totalPages > 1): ?>
    <?php
      $pageUrl = fn(int $p) => esc(app_url('history.php?' . http_build_query(array_merge($_GET, ['page' => $p]))));
    ?>
    <nav class="d-flex justify-content-center align-items-center gap-1 mt-3">
      <a class="btn btn-sm btn-light <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $pageUrl(max(1, $page - 1)) ?>"><i class="bi bi-chevron-left"></i> Previous</a>
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <a class="btn btn-sm <?= $i === $page ? 'btn-teal' : 'btn-light' ?>" href="<?= $pageUrl($i) ?>"><?= $i ?></a>
      <?php endfor; ?>
      <a class="btn btn-sm btn-light <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $pageUrl(min($totalPages, $page + 1)) ?>">Next <i class="bi bi-chevron-right"></i></a>
    </nav>
  <?php endif; ?>
</div>

<?php foreach ($history as $h): ?>
  <div class="modal fade" id="receipt<?= (int)$h['payment_id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div><h5 class="modal-title">Official Receipt</h5><small class="text-muted"><?= esc(payment_reference((int)$h['payment_id'])) ?></small></div>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6"><strong>Ticket Number</strong><br><?= esc($h['ticket_number']) ?></div>
            <div class="col-6"><strong>Plate Number</strong><br><?= esc($h['plate_number']) ?></div>
            <div class="col-6"><strong>Driver</strong><br><?= esc($h['driver_name']) ?></div>
            <div class="col-6"><strong>Violation</strong><br><?= esc($h['violation_type']) ?></div>
            <div class="col-6"><strong>Amount Paid</strong><br><span class="fs-5 fw-semibold"><?= peso($h['amount_paid']) ?></span></div>
            <div class="col-6"><strong>Method</strong><br><?= esc(payment_method_label($h['payment_method'])) ?></div>
            <div class="col-6"><strong>Date</strong><br><?= esc($h['payment_date']) ?></div>
            <div class="col-6"><strong>Received By</strong><br><?= esc($h['received_by_name'] ?? 'Not recorded') ?></div>
            <?php if (!empty($h['notes'])): ?>
              <div class="col-12"><strong>Notes</strong><br><?= nl2br(esc($h['notes'])) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-light" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print</button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php page_end(); ?>