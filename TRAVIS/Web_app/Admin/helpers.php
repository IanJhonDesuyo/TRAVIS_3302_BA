<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (empty($_SESSION['user']['id'])) {
    header('Location: ../auth/index.php');
    exit;
}

if (strcasecmp(trim((string)($_SESSION['user']['role'] ?? '')), 'Administrator') !== 0) {
    header('Location: ../treasurer/dashboard.php');
    exit;
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

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function traffic_violation_types(): array {
    return [
        "No Driver's License",
        "Failure to Carry Driver's License",
        "Invalid / Delinquent Driver's License",
        'Unregistered Motor Vehicle',
        'Nuisance Muffler',
        'Disregarding Traffic Sign / Officer',
        'Reckless Driving',
        'Colorum',
        'Illegal Parking',
        'Illegal Terminal',
        'Obstruction',
        'OR / CR Not Carried',
        'No Canvas Cover',
        'Operating Out of Line',
        'Overloading',
        'Overcharging',
        'Loading / Unloading in Prohibited Zone',
        'Refusal to Convey Passenger',
        'Driving with Sleeveless Shirt / Shorts',
        'Not Wearing Shoes',
        'No Side Mirror',
        'Arrogant Driver',
        'Driving Under the Influence of Liquor',
        'Coding Violation',
        'Other Traffic Violation',
    ];
}

function traffic_penalty_fees(): array {
    return [100, 200, 300, 500, 1000, 1500, 2000, 2500, 3000, 5000];
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

function monthly_violation_counts(): array {
    $year = date('Y');
    $rows = fetch_all("SELECT MONTH(violation_date) AS m, COUNT(*) AS total FROM violations WHERE YEAR(violation_date) = ? GROUP BY MONTH(violation_date)", [$year]);
    $data = array_fill(1, 12, 0);
    foreach ($rows as $r) $data[(int)$r['m']] = (int)$r['total'];
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
