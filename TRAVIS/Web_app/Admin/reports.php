<?php
require_once __DIR__ . '/layout.php';

$message = '';
$messageType = 'info';
$previewRows = [];
$previewColumns = [];
$summary = [];
$reportTitle = '';

$reportType = trim((string)($_GET['report_type'] ?? 'violations'));
$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$statusFilter = trim((string)($_GET['status'] ?? ''));
$locationFilter = trim((string)($_GET['location'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));

$allowedReportTypes = ['violations', 'payments'];
if (!in_array($reportType, $allowedReportTypes, true)) {
    $reportType = 'violations';
}

$locationOptions = [
    'violations' => array_column(fetch_all("
        SELECT DISTINCT TRIM(violation_location) AS location
        FROM violations
        WHERE violation_location IS NOT NULL AND TRIM(violation_location) <> ''
        ORDER BY location ASC
    "), 'location'),
    'payments' => [],
];

if ($reportType === 'payments') {
    $locationFilter = '';
} elseif ($locationFilter !== '' && !in_array($locationFilter, $locationOptions[$reportType], true)) {
    $locationFilter = '';
}

// On first load (and after Reset), cover the complete available dataset so the
// administrator immediately sees a useful report instead of an empty page.
$reportDateSources = [
    'violations' => ['table' => 'violations', 'column' => 'violation_date'],
    'payments' => ['table' => 'payments', 'column' => 'payment_date'],
];
$dateSource = $reportDateSources[$reportType];
$availableRange = fetch_one(
    "SELECT DATE(MIN({$dateSource['column']})) AS first_date, " .
    "DATE(MAX({$dateSource['column']})) AS last_date FROM {$dateSource['table']}"
);
$defaultDateFrom = (string)($availableRange['first_date'] ?? date('Y-m-d'));
$defaultDateTo = (string)($availableRange['last_date'] ?? date('Y-m-d'));
if ($defaultDateFrom === '') $defaultDateFrom = date('Y-m-d');
if ($defaultDateTo === '') $defaultDateTo = date('Y-m-d');

function report_safe_date(string $value, string $fallback): string
{
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : $fallback;
}

function report_filename(string $title, string $dateFrom, string $dateTo, string $extension): string
{
    $base = strtolower(trim((string)preg_replace('/[^a-z0-9]+/i', '_', $title), '_'));
    return $base . '_' . $dateFrom . '_to_' . $dateTo . '.' . $extension;
}

function report_excel_value(mixed $value): string
{
    $text = (string)($value ?? '');
    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
        $text = "'" . $text;
    }
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function report_pdf_text(mixed $value): string
{
    $text = str_replace(['₱', '–', '—', '•'], ['PHP ', '-', '-', '-'], (string)($value ?? ''));
    $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
    $text = $converted !== false ? $converted : $text;
    return preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
}

function report_pdf_escape(string $text): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
}

function report_pdf_fit_text(mixed $value, float $width, float $fontSize, int $maxLines = 2): array
{
    $text = trim((string)preg_replace('/\s+/', ' ', report_pdf_text($value)));
    if ($text === '') return [''];

    $maxChars = max(3, (int)floor(($width - 7) / max(1, $fontSize * 0.49)));
    $lines = [];
    while ($text !== '' && count($lines) < $maxLines) {
        if (mb_strlen($text) <= $maxChars) {
            $lines[] = $text;
            $text = '';
            break;
        }

        $candidate = mb_substr($text, 0, $maxChars + 1);
        $breakAt = mb_strrpos($candidate, ' ');
        if ($breakAt === false || $breakAt < (int)floor($maxChars * 0.55)) {
            $breakAt = $maxChars;
        }
        $lines[] = rtrim(mb_substr($text, 0, $breakAt));
        $text = ltrim(mb_substr($text, $breakAt));
    }

    if ($text !== '' && $lines) {
        $last = count($lines) - 1;
        $lines[$last] = rtrim(mb_strimwidth($lines[$last], 0, max(1, $maxChars - 3), '')) . '...';
    }
    return $lines ?: [''];
}

function report_pdf_text_command(
    float $x,
    float $y,
    string $text,
    float $size = 6,
    string $font = 'F1',
    string $color = '0.08 0.14 0.18'
): string {
    return sprintf(
        "BT /%s %.2F Tf %s rg %.2F %.2F Td (%s) Tj ET\n",
        $font,
        $size,
        $color,
        $x,
        $y,
        report_pdf_escape(report_pdf_text($text))
    );
}

function build_report_pdf(
    string $title,
    string $dateFrom,
    string $dateTo,
    array $columns,
    array $rows,
    array $summary,
    string $preparedBy
): string {
    $columnKeys = array_keys($columns);
    $pageWidth = 842.0;
    $pageHeight = 595.0;
    $marginX = 24.0;
    $tableWidth = $pageWidth - ($marginX * 2);
    $headerHeight = 27.0;
    $rowHeight = 21.0;
    $rowsPerPage = 20;

    $widthWeights = [
        'ticket_number' => 1.45, 'payment_reference' => 1.25,
        'driver_name' => 1.35, 'plate_number' => 0.78,
        'vehicle_type' => 0.78, 'violation_type' => 1.40,
        'violation_location' => 1.20, 'location' => 1.18,
        'violation_date' => 0.84, 'violation_time' => 0.70,
        'penalty_amount' => 0.88, 'amount_paid' => 0.88,
        'status' => 0.70, 'payment_status' => 0.76,
        'payment_method' => 0.92, 'payment_date' => 1.15,
        'received_by_name' => 1.10, 'camera_name' => 1.05,
        'vehicle_count' => 0.78, 'inbound_count' => 0.68,
        'outbound_count' => 0.72, 'congestion_level' => 0.88,
        'officer_presence' => 0.92, 'potential_collision' => 1.00,
        'recorded_at' => 1.18,
    ];
    $weights = [];
    foreach ($columnKeys as $key) $weights[$key] = $widthWeights[$key] ?? 1.0;
    $weightTotal = max(1.0, array_sum($weights));
    $columnWidths = [];
    foreach ($weights as $key => $weight) $columnWidths[$key] = $tableWidth * ($weight / $weightTotal);

    $chunks = $rows ? array_chunk($rows, $rowsPerPage) : [[]];
    $pageCount = count($chunks);
    $objects = [
        1 => '',
        2 => '',
        3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
    ];
    $pageIds = [];
    $nextId = 5;
    foreach ($chunks as $pageIndex => $pageRows) {
        $pageId = $nextId++;
        $contentId = $nextId++;
        $pageIds[] = $pageId;

        $commands = "q 1 1 1 rg 0 0 {$pageWidth} {$pageHeight} re f Q\n";
        $commands .= report_pdf_text_command($marginX, 566, 'TRAVIS', 8, 'F2', '0.04 0.48 0.45');
        $commands .= report_pdf_text_command($marginX, 546, $title, 16, 'F2');
        $commands .= report_pdf_text_command(
            $marginX,
            531,
            'Period: ' . $dateFrom . ' to ' . $dateTo . '   |   Prepared by: ' . $preparedBy . '   |   Generated: ' . date('M j, Y g:i A'),
            7,
            'F1',
            '0.32 0.40 0.43'
        );
        $commands .= "q 0.04 0.48 0.45 RG 1 w {$marginX} 523 m " . ($pageWidth - $marginX) . " 523 l S Q\n";

        $summaryCount = max(1, count($summary));
        $summaryGap = 8.0;
        $summaryWidth = ($tableWidth - (($summaryCount - 1) * $summaryGap)) / $summaryCount;
        $summaryX = $marginX;
        foreach ($summary as $label => $value) {
            $commands .= sprintf("q 0.94 0.98 0.97 rg %.2F 484 %.2F 31 re f Q\n", $summaryX, $summaryWidth);
            $commands .= sprintf("q 0.72 0.84 0.82 RG 0.6 w %.2F 484 %.2F 31 re S Q\n", $summaryX, $summaryWidth);
            $commands .= report_pdf_text_command($summaryX + 7, 503, strtoupper((string)$label), 5.4, 'F2', '0.28 0.39 0.38');
            $commands .= report_pdf_text_command($summaryX + 7, 490, (string)$value, 8.5, 'F2');
            $summaryX += $summaryWidth + $summaryGap;
        }

        $tableTop = 470.0;
        $headerBottom = $tableTop - $headerHeight;
        $commands .= sprintf("q 0.04 0.25 0.30 rg %.2F %.2F %.2F %.2F re f Q\n", $marginX, $headerBottom, $tableWidth, $headerHeight);
        $x = $marginX;
        foreach ($columns as $key => $label) {
            $width = $columnWidths[$key];
            $commands .= sprintf("q 0.72 0.82 0.84 RG 0.45 w %.2F %.2F %.2F %.2F re S Q\n", $x, $headerBottom, $width, $headerHeight);
            $headerLines = report_pdf_fit_text($label, $width, 5.7, 2);
            $firstY = count($headerLines) > 1 ? $headerBottom + 16 : $headerBottom + 10;
            foreach ($headerLines as $lineIndex => $line) {
                $commands .= report_pdf_text_command($x + 4, $firstY - ($lineIndex * 7), $line, 5.7, 'F2', '1 1 1');
            }
            $x += $width;
        }

        if (!$pageRows) {
            $commands .= sprintf("q 0.98 0.99 0.99 rg %.2F %.2F %.2F %.2F re f Q\n", $marginX, $headerBottom - 35, $tableWidth, 35.0);
            $commands .= sprintf("q 0.72 0.82 0.84 RG 0.45 w %.2F %.2F %.2F %.2F re S Q\n", $marginX, $headerBottom - 35, $tableWidth, 35.0);
            $commands .= report_pdf_text_command($marginX + 8, $headerBottom - 21, 'No records matched the selected filters.', 7, 'F1', '0.32 0.40 0.43');
        }

        foreach ($pageRows as $rowIndex => $row) {
            $rowTop = $headerBottom - ($rowIndex * $rowHeight);
            $rowBottom = $rowTop - $rowHeight;
            if ($rowIndex % 2 === 1) {
                $commands .= sprintf("q 0.96 0.98 0.98 rg %.2F %.2F %.2F %.2F re f Q\n", $marginX, $rowBottom, $tableWidth, $rowHeight);
            }
            $x = $marginX;
            foreach ($columnKeys as $key) {
                $width = $columnWidths[$key];
                $commands .= sprintf("q 0.72 0.78 0.80 RG 0.35 w %.2F %.2F %.2F %.2F re S Q\n", $x, $rowBottom, $width, $rowHeight);
                $value = $row[$key] ?? '';
                if (in_array($key, ['penalty_amount', 'amount_paid'], true)) {
                    $value = 'PHP ' . number_format((float)$value, 2, '.', ',');
                } elseif (in_array($key, ['status', 'payment_status', 'congestion_level'], true)) {
                    $value = ucfirst(strtolower((string)$value));
                }
                $cellLines = report_pdf_fit_text($value, $width, 5.5, 2);
                $firstY = count($cellLines) > 1 ? $rowBottom + 12.5 : $rowBottom + 7.5;
                foreach ($cellLines as $lineIndex => $line) {
                    $commands .= report_pdf_text_command($x + 3.5, $firstY - ($lineIndex * 7), $line, 5.5);
                }
                $x += $width;
            }
        }

        $footer = 'TRAVIS Report  |  Page ' . ($pageIndex + 1) . ' of ' . $pageCount;
        $commands .= report_pdf_text_command($pageWidth - 150, 18, $footer, 6, 'F1', '0.36 0.43 0.45');
        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentId} 0 R >>";
        $objects[$contentId] = "<< /Length " . strlen($commands) . ">>\nstream\n{$commands}\nendstream";
    }
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn($id) => $id . ' 0 R', $pageIds)) . '] /Count ' . count($pageIds) . ' >>';
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= 'xref' . "\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= count($objects); $id++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$id]);
    }
    $pdf .= 'trailer' . "\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
    return $pdf;
}

function build_report(
    mysqli $conn,
    string $reportType,
    string $dateFrom,
    string $dateTo,
    string $statusFilter,
    string $locationFilter
): array {
    $rows = [];
    $columns = [];
    $summary = [];
    $title = '';

    if ($reportType === 'violations') {
        $title = 'Traffic Violation Report';
        $columns = [
            'ticket_number' => 'Ticket Number',
            'driver_name' => 'Driver Name',
            'plate_number' => 'Plate Number',
            'vehicle_type' => 'Vehicle Type',
            'violation_type' => 'Violation Type',
            'violation_location' => 'Location',
            'violation_date' => 'Date',
            'violation_time' => 'Time',
            'penalty_amount' => 'Penalty Amount',
            'status' => 'Status',
        ];

        $where = ['v.violation_date BETWEEN ? AND ?'];
        $params = [$dateFrom, $dateTo];
        $types = 'ss';

        if ($statusFilter !== '') {
            $where[] = 'v.status = ?';
            $params[] = $statusFilter;
            $types .= 's';
        }

        if ($locationFilter !== '') {
            $where[] = 'TRIM(v.violation_location) = ?';
            $params[] = $locationFilter;
            $types .= 's';
        }

        $sql = "
            SELECT v.ticket_number, v.driver_name, v.plate_number,
                   v.vehicle_type, v.violation_type, v.violation_location,
                   v.violation_date, v.violation_time, v.penalty_amount, v.status
            FROM violations v
            WHERE " . implode(' AND ', $where) . "
            ORDER BY v.violation_date DESC, v.violation_time DESC
            LIMIT 1000
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $totalPenalty = 0.0;
        $paid = 0;
        $pending = 0;
        foreach ($rows as $row) {
            $totalPenalty += (float)$row['penalty_amount'];
            $state = strtolower((string)$row['status']);
            if ($state === 'paid') $paid++;
            if (in_array($state, ['pending', 'overdue', 'unpaid'], true)) $pending++;
        }

        $summary = [
            'Total Records' => count($rows),
            'Paid Violations' => $paid,
            'Pending / Unpaid' => $pending,
            'Total Penalties' => '₱' . number_format($totalPenalty, 2),
        ];
    }

    if ($reportType === 'payments') {
        $title = 'Payment Collection Report';
        $columns = [
            'payment_reference' => 'Payment Reference',
            'ticket_number' => 'Ticket Number',
            'driver_name' => 'Driver Name',
            'plate_number' => 'Plate Number',
            'violation_type' => 'Violation Type',
            'amount_paid' => 'Amount Paid',
            'payment_method' => 'Payment Method',
            'payment_status' => 'Status',
            'payment_date' => 'Payment Date',
            'received_by_name' => 'Received By',
        ];

        $where = ['DATE(p.payment_date) BETWEEN ? AND ?'];
        $params = [$dateFrom, $dateTo];
        $types = 'ss';

        if ($statusFilter !== '') {
            $where[] = 'p.payment_status = ?';
            $params[] = $statusFilter;
            $types .= 's';
        }

        $sql = "
            SELECT CONCAT('PAY-', LPAD(p.payment_id, 6, '0')) AS payment_reference,
                   v.ticket_number, v.driver_name, v.plate_number, v.violation_type,
                   p.amount_paid, p.payment_method, p.payment_status, p.payment_date,
                   COALESCE(u.full_name, 'Not recorded') AS received_by_name
            FROM payments p
            JOIN violations v ON v.violation_id = p.violation_id
            LEFT JOIN users u ON u.user_id = p.received_by
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.payment_date DESC, p.payment_id DESC
            LIMIT 1000
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $totalCollected = 0.0;
        $completed = 0;
        foreach ($rows as $row) {
            if (strtolower((string)$row['payment_status']) === 'completed') {
                $completed++;
                $totalCollected += (float)$row['amount_paid'];
            }
        }

        $summary = [
            'Total Transactions' => count($rows),
            'Completed Payments' => $completed,
            'Other Statuses' => count($rows) - $completed,
            'Total Collected' => '₱' . number_format($totalCollected, 2),
        ];
    }

    return [$rows, $columns, $summary, $title];
}

$dateFrom = report_safe_date($dateFrom, $defaultDateFrom);
$dateTo = report_safe_date($dateTo, $defaultDateTo);

if ($dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    $message = 'The date range was corrected because the start date was later than the end date.';
    $messageType = 'warning';
}

// Always build the selected report. Filters refine this initial full-record preview.
try {
    [$previewRows, $previewColumns, $summary, $reportTitle] = build_report(
        $conn,
        $reportType,
        $dateFrom,
        $dateTo,
        $statusFilter,
        $locationFilter
    );
} catch (Throwable $e) {
    $message = 'Unable to generate the report: ' . $e->getMessage();
    $messageType = 'danger';
}

if ($action === 'excel' && $reportTitle !== '') {
    $filename = report_filename($reportTitle, $dateFrom, $dateTo, 'xls');
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo "\xEF\xBB\xBF";
    ?>
<!doctype html>
<html><head><meta charset="utf-8"><style>
body{font-family:Arial,sans-serif;color:#102f49}h1{font-size:20px;margin-bottom:4px}.meta{margin-bottom:16px;color:#526b64}.summary td{font-weight:bold;background:#eaf7f5}table{border-collapse:collapse}th,td{border:1px solid #9fb1bd;padding:6px 8px;vertical-align:top}th{background:#0b3a4a;color:#fff;font-weight:bold}.text{mso-number-format:"\@"}.number{mso-number-format:"0.00"}
</style></head><body>
<h1><?= report_excel_value($reportTitle) ?></h1>
<div class="meta">Period: <?= report_excel_value($dateFrom) ?> to <?= report_excel_value($dateTo) ?><br>Generated: <?= report_excel_value(date('F j, Y g:i A')) ?><br>Prepared by: <?= report_excel_value($_SESSION['full_name'] ?? 'TRAVIS Administrator') ?></div>
<table class="summary"><tr><?php foreach ($summary as $label => $value): ?><td><?= report_excel_value($label) ?><br><?= report_excel_value($value) ?></td><?php endforeach; ?></tr></table><br>
<table><thead><tr><?php foreach ($previewColumns as $label): ?><th><?= report_excel_value($label) ?></th><?php endforeach; ?></tr></thead><tbody>
<?php foreach ($previewRows as $row): ?><tr><?php foreach (array_keys($previewColumns) as $key): $value = $row[$key] ?? ''; $numeric = in_array($key, ['penalty_amount', 'amount_paid'], true); ?><td class="<?= $numeric ? 'number' : 'text' ?>"><?= report_excel_value($numeric ? number_format((float)$value, 2, '.', '') : $value) ?></td><?php endforeach; ?></tr><?php endforeach; ?>
</tbody></table></body></html>
    <?php
    exit;
}

if ($action === 'pdf' && $reportTitle !== '') {
    $filename = report_filename($reportTitle, $dateFrom, $dateTo, 'pdf');
    $pdf = build_report_pdf(
        $reportTitle,
        $dateFrom,
        $dateTo,
        $previewColumns,
        $previewRows,
        $summary,
        (string)($_SESSION['full_name'] ?? 'TRAVIS Administrator')
    );
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdf));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $pdf;
    exit;
}

$reportHistory = fetch_all("
    SELECT r.*, u.full_name AS generated_by_name
    FROM reports r
    LEFT JOIN users u ON u.user_id = r.generated_by
    ORDER BY r.generated_at DESC
    LIMIT 50
");

$totalReports = scalar("SELECT COUNT(*) FROM reports", 0);
$reportsToday = scalar("SELECT COUNT(*) FROM reports WHERE DATE(generated_at) = CURDATE()", 0);
$reportsThisMonth = scalar("
    SELECT COUNT(*) FROM reports
    WHERE YEAR(generated_at) = YEAR(CURDATE())
      AND MONTH(generated_at) = MONTH(CURDATE())
", 0);
$lastGenerated = fetch_one("SELECT generated_at FROM reports ORDER BY generated_at DESC LIMIT 1");

$statusOptions = [
    'violations' => [
        '' => 'All Statuses',
        'pending' => 'Pending',
        'overdue' => 'Overdue',
        'paid' => 'Paid',
        'cancelled' => 'Cancelled',
    ],
    'payments' => [
        '' => 'All Statuses',
        'completed' => 'Completed',
        'pending' => 'Pending',
        'failed' => 'Failed',
    ],
];

$queryBase = [
    'report_type' => $reportType,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'status' => $statusFilter,
    'location' => $locationFilter,
    'generate' => '1',
];

page_start('Reports', 'reports', 'Search reports...');
?>

<style>
/* ============================================================
   TRAVIS REPORTS — NAVY GLASS THEME
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

.btn-success{
    background:linear-gradient(90deg,#15803d,#34d399) !important;
    border:none !important;
    color:#fff !important;
    box-shadow:0 12px 26px rgba(21,128,61,.32) !important;
}
.btn-success:hover{filter:brightness(1.08);color:#fff !important}

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

/* Keep the report export labels crisp and readable on their colored buttons. */
#reportPreview > .section-head .no-print .btn{
    color:#fff !important;
    font-family:Arial, Helvetica, sans-serif !important;
    font-size:.8rem !important;
    font-weight:700 !important;
}

.tag{
    display:inline-block;padding:4px 12px;border-radius:999px;
    font-size:.72rem;font-weight:700;text-transform:capitalize;
    background:rgba(255,255,255,.08);color:var(--text-soft);
    border:1px solid var(--border-glass);
}
.tag-success,.tag-online,.tag-paid,.tag-completed,.tag-active,.tag-low{
    background:rgba(52,211,153,.14) !important;color:#34d399 !important;border-color:rgba(52,211,153,.3) !important;
}
.tag-danger,.tag-offline,.tag-overdue,.tag-high,.tag-critical{
    background:rgba(248,113,113,.14) !important;color:#f87171 !important;border-color:rgba(248,113,113,.3) !important;
}
.tag-warning,.tag-pending,.tag-unpaid,.tag-medium{
    background:rgba(251,191,36,.14) !important;color:#fbbf24 !important;border-color:rgba(251,191,36,.3) !important;
}
.tag-info{
    background:rgba(56,189,248,.14) !important;color:var(--cyan-glow) !important;border-color:rgba(56,189,248,.3) !important;
}
.tag-muted{
    background:rgba(255,255,255,.06) !important;color:var(--text-soft) !important;
}
.tag-cancelled{
    background:rgba(255,255,255,.06) !important;color:var(--text-soft) !important;border-color:var(--border-glass) !important;
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
.table-active{background:rgba(56,189,248,.08) !important;}

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

/* Report table scroll */
.report-table-scroll {
  max-height: 560px;
  overflow: auto;
  border: 1px solid var(--border-glass);
  border-radius: .75rem;
}

.report-table-scroll thead th {
  position: sticky;
  top: 0;
  z-index: 5;
  background: var(--navy-800);
  box-shadow: inset 0 -1px 0 var(--border-glass);
  white-space: nowrap;
  color: var(--text-soft) !important;
}

.report-table-scroll::-webkit-scrollbar { width: 7px; height: 7px; }
.report-table-scroll::-webkit-scrollbar-track { background: rgba(255,255,255,.04); border-radius: 20px; }
.report-table-scroll::-webkit-scrollbar-thumb { background: rgba(56,189,248,.35); border-radius: 20px; }
.report-table-scroll::-webkit-scrollbar-thumb:hover { background: rgba(56,189,248,.65); }

.rounded-3{border-radius:12px !important;}
.border{border-color:var(--border-glass) !important;}

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

.card *:not(.tag),
[class*="card"] *:not(.tag){
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

.modal-content{
    background:var(--navy-900) !important;
    color:#fff !important;
    border:1px solid var(--border-glass) !important;
}

.report-preview-modal .modal-dialog{max-width:min(1280px,calc(100vw - 2rem));}
.report-preview-modal .modal-content{height:calc(100vh - 2rem);overflow:hidden;background:#f8fafc!important;color:#10202c!important;border:1px solid rgba(16,47,73,.2)!important;border-radius:18px;box-shadow:0 24px 70px rgba(8,28,43,.32);}
.report-preview-modal .modal-header,.report-preview-modal .modal-footer{flex:0 0 auto;background:#fff;border-color:#dbe5e8;}
.report-preview-modal .modal-header{padding:1rem 1.25rem;}
.report-preview-modal .modal-title{color:#10202c;font-weight:800;}
.report-preview-modal .modal-body{min-height:0;overflow:auto;padding:1.25rem;background:#edf3f4;}
.report-preview-modal .modal-footer{padding:.8rem 1.25rem;}
.report-preview-sheet{min-width:900px;padding:1.25rem;background:#fff;border:1px solid #d8e3e6;border-radius:14px;box-shadow:0 8px 24px rgba(16,47,73,.08);}
.report-preview-meta{display:flex;flex-wrap:wrap;gap:.35rem 1.5rem;margin-bottom:1rem;color:#60716d;font-size:.78rem;}
.report-preview-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;margin-bottom:1rem;}
.report-preview-metric{padding:.8rem .9rem;border:1px solid #cfe0df;border-radius:10px;background:#f2faf8;color:#10202c!important;}
.report-preview-metric small{display:block;margin-bottom:.25rem;color:#617570!important;font-size:.66rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;}
.report-preview-metric strong{font-size:1rem;color:#10202c!important;}
.report-preview-table-wrap{overflow:auto;border:1px solid #cad7da;border-radius:10px;}
.report-preview-table{width:100%;margin:0;border-collapse:collapse;font-size:.72rem;color:#20333d;}
.report-preview-table th{position:sticky;top:0;z-index:1;padding:.65rem .7rem;background:#0b404b;color:#fff;white-space:nowrap;border-right:1px solid rgba(255,255,255,.18);}
.report-preview-table td{padding:.58rem .7rem;border-right:1px solid #dce5e7;border-bottom:1px solid #dce5e7;vertical-align:top;}
.report-preview-table tbody tr:nth-child(even){background:#f5f8f8;}
.report-preview-table tbody tr:last-child td{border-bottom:0;}
.report-preview-empty{padding:2.5rem;text-align:center;color:#647670;}
@media(max-width:767.98px){
  .report-preview-modal .modal-dialog{max-width:none;height:100%;margin:0;}
  .report-preview-modal .modal-content{height:100%;border-radius:0;}
}

/* Print styles */
.official-print-header,.official-print-footer{display:none}
@media print {
  @page { size: A4 landscape; margin: 14mm 12mm 16mm; }
  .sidebar, .topbar, .btn, .report-generator, .report-history, .no-print { display: none !important; }
  .content > *:not(#reportPreview){display:none!important}
  #reportPreview{display:block!important;padding:0!important;border:0!important}
  .official-print-header,.official-print-footer{display:block!important}
  .official-print-header{text-align:center;border-bottom:2px solid #17324d;padding-bottom:10px;margin-bottom:16px}
  .official-print-header .republic{font:11px Georgia,serif;letter-spacing:.08em;text-transform:uppercase}
  .official-print-header h1{font:700 20px Georgia,serif;margin:3px 0;color:#102f49!important}
  .official-print-header p{font:11px Arial,sans-serif;margin:2px 0;color:#374151!important}
  .official-print-meta{display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;margin-top:12px;text-align:left;font:11px Arial,sans-serif}
  .official-print-footer{margin-top:24px;page-break-inside:avoid;font:11px Arial,sans-serif}
  .official-signatures{display:grid;grid-template-columns:1fr 1fr;gap:80px;margin-top:38px}
  .official-signatures div{border-top:1px solid #111827;padding-top:5px;text-align:center}
  .report-table-scroll { max-height: none; overflow: visible; border: 0; }
  .report-table-scroll thead th { position: static; background: #f8f9fa !important; color: #1a2a3a !important; }
  body { background: #fff !important; color: #1a2a3a !important; }
  .section-card { background: #fff !important; border: 1px solid #dee2e6 !important; box-shadow: none !important; }
  .section-card .text-muted { color: #6c757d !important; }
  .page-title { color: #1a2a3a !important; }
  .page-sub { color: #6c757d !important; }
  .tag { background: #f8f9fa !important; color: #1a2a3a !important; border: 1px solid #dee2e6 !important; }
  .stat-card { background: #f8f9fa !important; border: 1px solid #dee2e6 !important; box-shadow: none !important; }
  .stat-label { color: #6c757d !important; }
  .stat-value { color: #1a2a3a !important; }
  .stat-icon { background: #e9ecef !important; }
  .table { color: #1a2a3a !important; }
  .table thead th { color: #495057 !important; border-color: #dee2e6 !important; }
  .table td, .table th { border-color: #dee2e6 !important; }
  .border { border-color: #dee2e6 !important; }
  .fs-5 { color: #1a2a3a !important; }
  #reportPreview>.section-head{display:none!important}
  #reportPreview .row{margin-bottom:14px!important}
  #reportPreview .border{padding:8px!important;border-radius:0!important}
  #reportPreview table{font-size:9px}
  #reportPreview thead{display:table-header-group}
  #reportPreview tr{page-break-inside:avoid}
}
</style>

<div class="d-flex justify-content-between flex-wrap mb-4 gap-2">
  <div>
    <span class="dashboard-eyebrow">TRAVIS REPORTS MODULE</span>
    <h3 class="page-title">Reports</h3>
    <p class="page-sub">Generate, preview, print, and export operational records.</p>
  </div>
</div>

<?php if ($message): ?>
  <div class="alert alert-<?= esc($messageType) ?>"><?= esc($message) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-files"></i></div>
      <div class="stat-label">Total Saved Reports</div>
      <div class="stat-value"><?= num($totalReports) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-success"><i class="bi bi-calendar-check"></i></div>
      <div class="stat-label">Generated Today</div>
      <div class="stat-value"><?= num($reportsToday) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-warning"><i class="bi bi-calendar3"></i></div>
      <div class="stat-label">This Month</div>
      <div class="stat-value"><?= num($reportsThisMonth) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="stat-card">
      <div class="stat-icon tone-primary"><i class="bi bi-clock-history"></i></div>
      <div class="stat-label">Last Generated</div>
      <div class="fs-6 fw-semibold mt-2"><?= esc($lastGenerated['generated_at'] ?? 'No saved report') ?></div>
    </div>
  </div>
</div>

<div class="section-card mb-4 report-generator">
  <div class="section-head">
    <div><h6 class="mb-0">Report Generator</h6><small class="text-muted">The complete available record is shown automatically. Use the filters to narrow the report.</small></div>
  </div>

  <form method="get" class="row g-3">
    <div class="col-md-6 col-xl-3">
      <label class="form-label">Report Type</label>
      <select class="form-select" name="report_type" id="reportType" required>
        <option value="violations" <?= $reportType === 'violations' ? 'selected' : '' ?>>Violation Report</option>
        <option value="payments" <?= $reportType === 'payments' ? 'selected' : '' ?>>Payment Collection Report</option>
      </select>
    </div>

    <div class="col-md-6 col-xl-2"><label class="form-label">Start Date</label><input type="date" class="form-control" name="date_from" value="<?= esc($dateFrom) ?>" required></div>
    <div class="col-md-6 col-xl-2"><label class="form-label">End Date</label><input type="date" class="form-control" name="date_to" value="<?= esc($dateTo) ?>" required></div>

    <div class="col-md-6 col-xl-2">
      <label class="form-label" id="statusLabel">Status</label>
      <select class="form-select" name="status" id="statusFilter">
        <?php foreach ($statusOptions[$reportType] as $value => $label): ?>
          <option value="<?= esc($value) ?>" <?= $statusFilter === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-md-6 col-xl-3">
      <label class="form-label" for="locationFilter">Location</label>
      <select class="form-select" name="location" id="locationFilter" <?= $reportType === 'payments' ? 'disabled' : '' ?>>
        <option value="">All Locations</option>
        <?php foreach ($locationOptions[$reportType] as $locationOption): ?>
          <option value="<?= esc((string)$locationOption) ?>" <?= $locationFilter === $locationOption ? 'selected' : '' ?>><?= esc((string)$locationOption) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-12 d-flex flex-wrap gap-2">
      <button class="btn btn-primary" name="generate" value="1"><i class="bi bi-funnel me-1"></i>Apply Filters</button>
      <a class="btn btn-light" href="<?= esc(app_url('reports.php')) ?>"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset</a>
    </div>
  </form>
</div>

<?php if ($reportTitle !== ''): ?>
  <div class="section-card mb-4 print-area" id="reportPreview">
    <header class="official-print-header">
      <div class="republic">Republic of the Philippines</div>
      <h1>Municipality of Nasugbu</h1>
      <p>Traffic Management Office</p>
      <p><strong>Traffic Violation Recognition and AI Surveillance (TRAVIS)</strong></p>
      <div class="official-print-meta">
        <div><strong>Report:</strong> <?= esc($reportTitle) ?></div>
        <div><strong>Date generated:</strong> <?= esc(date('F j, Y g:i A')) ?></div>
        <div><strong>Covered period:</strong> <?= esc(date('F j, Y', strtotime($dateFrom))) ?> – <?= esc(date('F j, Y', strtotime($dateTo))) ?></div>
        <div><strong>Prepared by:</strong> <?= esc($_SESSION['full_name'] ?? 'TRAVIS Administrator') ?></div>
      </div>
    </header>
    <div class="section-head flex-wrap gap-2">
      <div>
        <h5 class="mb-1"><?= esc($reportTitle) ?></h5>
        <small class="text-muted">
          Period: <?= esc($dateFrom) ?> to <?= esc($dateTo) ?>
          <?php if ($statusFilter !== ''): ?> • Filter: <?= esc(ucwords(str_replace('_', ' ', $statusFilter))) ?><?php endif; ?>
          <?php if ($locationFilter !== '' && $reportType !== 'payments'): ?> • Location: <?= esc($locationFilter) ?><?php endif; ?>
        </small>
      </div>

      <div class="d-flex flex-wrap gap-2 no-print">
        <button
          class="btn btn-success btn-sm report-format-preview-button"
          type="button"
          data-bs-toggle="modal"
          data-bs-target="#reportExportPreviewModal"
          data-export-format="excel"
        ><i class="bi bi-file-earmark-excel me-1"></i>Preview Excel</button>
        <button
          class="btn btn-danger btn-sm report-format-preview-button"
          type="button"
          data-bs-toggle="modal"
          data-bs-target="#reportExportPreviewModal"
          data-export-format="pdf"
        ><i class="bi bi-file-earmark-pdf me-1"></i>Preview PDF</button>
      </div>
    </div>

    <div class="row g-3 mb-4">
      <?php foreach ($summary as $label => $value): ?>
        <div class="col-sm-6 col-xl-3">
          <div class="border rounded-3 p-3 h-100">
            <small class="text-muted"><?= esc($label) ?></small>
            <div class="fs-5 fw-semibold mt-1"><?= esc((string)$value) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if (!$previewRows): ?>
      <?php empty_state('No records matched the selected report filters.'); ?>
    <?php else: ?>
      <div class="report-table-scroll">
        <table class="table align-middle mb-0">
          <thead><tr><?php foreach ($previewColumns as $label): ?><th><?= esc($label) ?></th><?php endforeach; ?></tr></thead>
          <tbody>
            <?php foreach ($previewRows as $row): ?>
              <tr>
                <?php foreach ($previewColumns as $key => $label): ?>
                  <td>
                    <?php if (in_array($key, ['penalty_amount', 'amount_paid'], true)): ?>
                      <?= peso($row[$key] ?? 0) ?>
                    <?php elseif (in_array($key, ['status', 'payment_status', 'congestion_level'], true)): ?>
                      <span class="tag <?= tag_class($row[$key] ?? '') ?>"><?= esc(ucwords(str_replace('_', ' ', (string)($row[$key] ?? '')))) ?></span>
                    <?php elseif ($key === 'payment_method'): ?>
                      <?= esc(ucwords(str_replace('_', ' ', (string)($row[$key] ?? '')))) ?>
                    <?php else: ?>
                      <?= esc((string)($row[$key] ?? '')) ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <small class="text-muted d-block mt-2">Showing <?= num(count($previewRows)) ?> record(s). Preview and export are limited to 1,000 records.</small>
    <?php endif; ?>
    <footer class="official-print-footer">
      <p>I certify that this report was generated from the official TRAVIS records for the period stated above.</p>
      <div class="official-signatures"><div>Prepared by / Date</div><div>Reviewed and Approved by / Date</div></div>
    </footer>
  </div>

  <div class="modal fade report-preview-modal no-print" id="reportExportPreviewModal" tabindex="-1" aria-labelledby="reportExportPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-1" id="reportExportPreviewTitle">Report Preview</h5>
            <small class="text-muted" id="reportExportPreviewDescription">Review the report before downloading it.</small>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="report-preview-sheet">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
              <div>
                <small class="dashboard-eyebrow">TRAVIS REPORT</small>
                <h5 class="mb-1"><?= esc($reportTitle) ?></h5>
              </div>
              <span class="tag"><?= num(count($previewRows)) ?> records</span>
            </div>
            <div class="report-preview-meta">
              <span><strong>Period:</strong> <?= esc($dateFrom) ?> to <?= esc($dateTo) ?></span>
              <span><strong>Prepared by:</strong> <?= esc($_SESSION['full_name'] ?? 'TRAVIS Administrator') ?></span>
              <?php if ($statusFilter !== ''): ?><span><strong>Filter:</strong> <?= esc(ucwords(str_replace('_', ' ', $statusFilter))) ?></span><?php endif; ?>
              <?php if ($locationFilter !== '' && $reportType !== 'payments'): ?><span><strong>Location:</strong> <?= esc($locationFilter) ?></span><?php endif; ?>
            </div>
            <div class="report-preview-summary">
              <?php foreach ($summary as $label => $value): ?>
                <div class="report-preview-metric"><small><?= esc($label) ?></small><strong><?= esc((string)$value) ?></strong></div>
              <?php endforeach; ?>
            </div>
            <div class="report-preview-table-wrap">
              <?php if (!$previewRows): ?>
                <div class="report-preview-empty">No records matched the selected report filters.</div>
              <?php else: ?>
                <table class="report-preview-table">
                  <thead><tr><?php foreach ($previewColumns as $label): ?><th><?= esc($label) ?></th><?php endforeach; ?></tr></thead>
                  <tbody>
                    <?php foreach ($previewRows as $row): ?><tr>
                      <?php foreach ($previewColumns as $key => $label): ?><td>
                        <?php if (in_array($key, ['penalty_amount', 'amount_paid'], true)): ?>
                          <?= peso($row[$key] ?? 0) ?>
                        <?php elseif (in_array($key, ['status', 'payment_status', 'congestion_level'], true)): ?>
                          <span class="tag <?= tag_class($row[$key] ?? '') ?>"><?= esc(ucwords(str_replace('_', ' ', (string)($row[$key] ?? '')))) ?></span>
                        <?php elseif ($key === 'payment_method'): ?>
                          <?= esc(ucwords(str_replace('_', ' ', (string)($row[$key] ?? '')))) ?>
                        <?php else: ?>
                          <?= esc((string)($row[$key] ?? '')) ?>
                        <?php endif; ?>
                      </td><?php endforeach; ?>
                    </tr><?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-outline-primary format-pdf-action d-none" id="printReportButton"><i class="bi bi-printer me-1"></i>Print</button>
          <a class="btn btn-success format-excel-action d-none" href="<?= esc(app_url('reports.php?' . http_build_query(array_merge($queryBase, ['action' => 'excel'])))) ?>"><i class="bi bi-download me-1"></i>Download Excel</a>
          <a class="btn btn-danger format-pdf-action d-none" href="<?= esc(app_url('reports.php?' . http_build_query(array_merge($queryBase, ['action' => 'pdf'])))) ?>"><i class="bi bi-download me-1"></i>Download PDF</a>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="section-card report-history">
  <div class="section-head"><div><h6 class="mb-0">Saved Report History</h6><small class="text-muted">Metadata previously stored in the reports table.</small></div></div>

  <?php if (!$reportHistory): ?>
    <?php empty_state('No saved report metadata was found. Generated previews are not automatically saved to the reports table.'); ?>
  <?php else: ?>
    <div class="report-table-scroll">
      <table class="table align-middle mb-0">
        <thead><tr><th>Title</th><th>Type</th><th>Period</th><th>Status</th><th>Generated By</th><th>Generated At</th></tr></thead>
        <tbody>
          <?php foreach ($reportHistory as $report): ?>
            <tr>
              <td class="fw-semibold"><?= esc($report['title']) ?></td>
              <td><?= esc(ucwords(str_replace('_', ' ', $report['report_type']))) ?></td>
              <td><?= esc(($report['period_start'] ?? 'N/A') . ' - ' . ($report['period_end'] ?? 'N/A')) ?></td>
              <td><span class="tag <?= tag_class($report['status']) ?>"><?= esc(ucfirst($report['status'])) ?></span></td>
              <td><?= esc($report['generated_by_name'] ?? 'N/A') ?></td>
              <td><?= esc($report['generated_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<script>
const statusOptions = <?= json_encode($statusOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const locationOptions = <?= json_encode($locationOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const reportTypeSelect = document.getElementById('reportType');
const statusSelect = document.getElementById('statusFilter');
const statusLabel = document.getElementById('statusLabel');
const locationSelect = document.getElementById('locationFilter');

function rebuildStatusOptions() {
  const reportType = reportTypeSelect.value;
  const options = statusOptions[reportType] || {};
  statusSelect.innerHTML = '';

  Object.entries(options).forEach(([value, label]) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = label;
    statusSelect.appendChild(option);
  });

  statusLabel.textContent = 'Status';

  if (locationSelect) {
    locationSelect.innerHTML = '';
    const allLocationsOption = document.createElement('option');
    allLocationsOption.value = '';
    allLocationsOption.textContent = 'All Locations';
    locationSelect.appendChild(allLocationsOption);

    (locationOptions[reportType] || []).forEach((location) => {
      const option = document.createElement('option');
      option.value = location;
      option.textContent = location;
      locationSelect.appendChild(option);
    });

    locationSelect.disabled = reportType === 'payments';
  }
}

reportTypeSelect.addEventListener('change', rebuildStatusOptions);

const printReportButton = document.getElementById('printReportButton');
if (printReportButton) printReportButton.addEventListener('click', () => window.print());

const reportExportPreviewModal = document.getElementById('reportExportPreviewModal');
if (reportExportPreviewModal) {
  reportExportPreviewModal.addEventListener('show.bs.modal', (event) => {
    const trigger = event.relatedTarget;
    const format = trigger?.dataset?.exportFormat === 'excel' ? 'excel' : 'pdf';
    const title = document.getElementById('reportExportPreviewTitle');
    const description = document.getElementById('reportExportPreviewDescription');

    if (title) title.textContent = format === 'excel' ? 'Excel Report Preview' : 'PDF Report Preview';
    if (description) {
      description.textContent = format === 'excel'
        ? 'Review the table before downloading the Excel workbook.'
        : 'Review the report before printing or downloading the PDF.';
    }

    reportExportPreviewModal.querySelectorAll('.format-excel-action').forEach((element) => {
      element.classList.toggle('d-none', format !== 'excel');
    });
    reportExportPreviewModal.querySelectorAll('.format-pdf-action').forEach((element) => {
      element.classList.toggle('d-none', format !== 'pdf');
    });
  });
}
</script>

<?php page_end(); ?>
