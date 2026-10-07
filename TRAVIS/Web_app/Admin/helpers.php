<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../auth/session.php';
require_once __DIR__ . '/../auth/audit.php';
require_once __DIR__ . '/../traffic_rules.php';
travis_session_start();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!travis_is_authenticated() || travis_session_has_expired()) {
    travis_logout_user();
    header('Location: ../auth/index.php');
    exit;
}

// Revalidate the account on every Admin page request. A copied URL or stale
// session must not bypass account deactivation, deletion, or role changes.
$authenticatedUserId = (int)($_SESSION['user']['id'] ?? 0);
$accountStatement = $conn->prepare(
    'SELECT user_id, full_name, email, role, status FROM users WHERE user_id = ? LIMIT 1'
);
$authenticatedAccount = null;
if ($accountStatement) {
    $accountStatement->bind_param('i', $authenticatedUserId);
    $accountStatement->execute();
    $accountResult = $accountStatement->get_result();
    $authenticatedAccount = $accountResult ? $accountResult->fetch_assoc() : null;
    $accountStatement->close();
}

if (!$authenticatedAccount || strcasecmp((string)$authenticatedAccount['status'], 'active') !== 0) {
    travis_logout_user();
    header('Location: ../auth/index.php');
    exit;
}

$authenticatedRole = strtolower(trim((string)$authenticatedAccount['role']));
if (!in_array($authenticatedRole, ['administrator', 'admin'], true)) {
    header('Location: ../Treasurer/dashboard.php');
    exit;
}

$_SESSION['user'] = [
    'id' => (int)$authenticatedAccount['user_id'],
    'name' => (string)$authenticatedAccount['full_name'],
    'email' => (string)$authenticatedAccount['email'],
    'role' => (string)$authenticatedAccount['role'],
];
travis_touch_session();

/**
 * Start the local ML API for authenticated dashboard sessions when needed.
 *
 * The TCP probe prevents duplicate processes. A short lock/throttle also
 * protects against several page requests arriving while Flask is starting.
 */
function ensure_ml_api_running(): bool {
    $host = '127.0.0.1';
    $port = 5001;

    $connection = @fsockopen($host, $port, $errorCode, $errorMessage, 0.15);
    if (is_resource($connection)) {
        fclose($connection);
        return true;
    }

    $projectRoot = dirname(__DIR__, 2);
    $python = $projectRoot . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'pythonw.exe';
    $apiScript = $projectRoot . DIRECTORY_SEPARATOR . 'Machine_Learning' . DIRECTORY_SEPARATOR . 'api.py';

    if (PHP_OS_FAMILY !== 'Windows' || !is_file($python) || !is_file($apiScript)) {
        error_log('TRAVIS ML API auto-start skipped: Python runtime or api.py is unavailable.');
        return false;
    }

    $lockPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'travis_ml_api_start.lock';
    $lock = @fopen($lockPath, 'c+');
    if (!is_resource($lock) || !flock($lock, LOCK_EX | LOCK_NB)) {
        if (is_resource($lock)) fclose($lock);
        return false;
    }

    $lastAttempt = (int) trim((string) stream_get_contents($lock));
    if ($lastAttempt > 0 && (time() - $lastAttempt) < 15) {
        flock($lock, LOCK_UN);
        fclose($lock);
        return false;
    }

    rewind($lock);
    ftruncate($lock, 0);
    fwrite($lock, (string) time());
    fflush($lock);

    // All command parts come from fixed application paths, not request input.
    $command = 'cmd /c start "" /B "' . str_replace('"', '""', $python) . '" "' . str_replace('"', '""', $apiScript) . '"';
    $process = @popen($command, 'r');
    $started = is_resource($process);
    if ($started) pclose($process);

    flock($lock, LOCK_UN);
    fclose($lock);

    if (!$started) {
        error_log('TRAVIS ML API auto-start failed: unable to launch api.py.');
    }

    return $started;
}

function web_app_base_url(): string {
    $documentRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    $dir = str_replace('\\', '/', __DIR__);
    $relative = trim(str_replace($documentRoot, '', $dir), '/');
    if ($relative === '') {
        return '/';
    }
    return '/' . $relative . '/';
}

function project_base_url(): string {
    $documentRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
    $dir = str_replace('\\', '/', dirname(__DIR__, 2));
    $relative = trim(str_replace($documentRoot, '', $dir), '/');
    if ($relative === '') {
        return '/';
    }
    return '/' . $relative . '/';
}

function app_url(string $path): string {
    return web_app_base_url() . ltrim($path, '/');
}

function asset_url(string $path): string {
    return project_base_url() . ltrim($path, '/');
}

function esc(mixed $value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function peso(mixed $amount): string {
    return '₱' . number_format((float)($amount ?? 0), 2);
}

function payment_method_label(string $method): string {
    return match ($method) {
        'cash' => 'Cash',
        'card' => 'Card',
        'gcash', 'mobile_wallet' => 'Mobile Wallet (GCash)',
        'bank_transfer', 'online' => 'Online / Bank Transfer',
        'cheque' => 'Cheque',
        'other' => 'Other',
        default => ucfirst(str_replace('_', ' ', $method)),
    };
}

function payment_method_options(): array {
    return [
        'cash' => 'Cash',
    ];
}

function payment_reference(int $paymentId): string {
    return 'OR-' . date('Y') . '-' . str_pad((string)$paymentId, 6, '0', STR_PAD_LEFT);
}

function num(mixed $value): string {
    return number_format((float)($value ?? 0));
}

function short_money(mixed $amount): string {
    $amount = (float)($amount ?? 0);
    if ($amount >= 1000000) return '₱' . number_format($amount / 1000000, 1) . 'M';
    if ($amount >= 1000) return '₱' . number_format($amount / 1000, 1) . 'K';
    return peso($amount);
}

function fetch_one(string $sql, array $params = []): ?array {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return null;
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) return null;
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row ?: null;
}

function fetch_all(string $sql, array $params = []): array {
    global $conn;
    $stmt = $conn->prepare($sql);
    if (!$stmt) return [];
    if ($params) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    if (!$stmt->execute()) return [];
    $res = $stmt->get_result();
    $rows = [];
    while ($res && ($row = $res->fetch_assoc())) $rows[] = $row;
    $stmt->close();
    return $rows;
}

function scalar(string $sql, mixed $default = 0, array $params = []): mixed {
    $row = fetch_one($sql, $params);
    if (!$row) return $default;
    $value = array_values($row)[0] ?? $default;
    return $value ?? $default;
}

function tag_class(string $value): string {
    $v = strtolower($value);
    if (in_array($v, ['active','online','completed','paid','published','resolved','detected','low'], true)) return 'tag-success';
    if (in_array($v, ['pending','warning','moderate','medium','acknowledged','draft','possible'], true)) return 'tag-warning';
    if (in_array($v, ['critical','severe','heavy','high','overdue','failed','active alert','active'], true)) return 'tag-danger';
    return 'tag-info';
}

function current_admin(): array {
    return [
        'full_name' => (string)($_SESSION['user']['name'] ?? 'System Admin'),
        'role' => (string)($_SESSION['user']['role'] ?? 'Administrator'),
    ];
}

function audit_log(string $action, string $module, string $description, string $outcome = 'success', ?string $entityType = null, int|string|null $entityId = null): void {
    global $conn;
    travis_audit_log($conn, $action, $module, $description, $outcome, $entityType, $entityId);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function traffic_violation_types(): array {
    return travis_violation_types();
}

function traffic_penalty_fees(): array {
    return travis_penalty_fees();
}

function traffic_violation_category(string $type): string {
    return travis_violation_category($type);
}

function traffic_offense_analysis(mysqli $conn, string $driverName, string $violationType, string $licenseNumber = '', ?string $dateOfBirth = null): array {
    return travis_mysqli_offense_analysis($conn, $driverName, $violationType, $licenseNumber, $dateOfBirth);
}

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $a = strtoupper(substr($parts[0] ?? 'S', 0, 1));
    $b = strtoupper(substr($parts[1] ?? 'A', 0, 1));
    return $a . $b;
}

function month_labels(): array {
    return ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
}

function monthly_violation_counts(?int $year = null): array {
    $year ??= (int)date('Y');
    $rows = fetch_all("SELECT MONTH(violation_date) AS m, COUNT(*) AS total FROM violations WHERE YEAR(violation_date) = ? GROUP BY MONTH(violation_date)", [$year]);
    $data = array_fill(1, 12, 0);
    foreach ($rows as $r) $data[(int)$r['m']] = (int)$r['total'];
    return array_values($data);
}

function monthly_collection_totals(?int $year = null): array {
    $year ??= (int)date('Y');
    $rows = fetch_all("
        SELECT MONTH(payment_date) AS m, COALESCE(SUM(amount_paid), 0) AS total
        FROM payments
        WHERE payment_status = 'completed' AND YEAR(payment_date) = ?
        GROUP BY MONTH(payment_date)
    ", [$year]);
    $data = array_fill(1, 12, 0.0);
    foreach ($rows as $r) $data[(int)$r['m']] = (float)$r['total'];
    return array_values($data);
}

function vehicle_distribution(): array {
    $rows = fetch_all("SELECT vehicle_type, COUNT(*) AS total FROM violations GROUP BY vehicle_type ORDER BY total DESC");
    if (!$rows) return ['labels' => [], 'data' => []];
    return ['labels' => array_column($rows, 'vehicle_type'), 'data' => array_map('intval', array_column($rows, 'total'))];
}

function daily_traffic_volume(): array {
    $rows = fetch_all("SELECT HOUR(recorded_at) AS hr, SUM(vehicle_count) AS total FROM camera_monitoring_logs WHERE DATE(recorded_at) = CURDATE() GROUP BY HOUR(recorded_at) ORDER BY hr");
    $labels = [];
    $data = [];
    foreach ($rows as $r) {
        $labels[] = date('gA', strtotime(sprintf('%02d:00:00', (int)$r['hr'])));
        $data[] = (int)$r['total'];
    }
    return ['labels' => $labels, 'data' => $data];
}
