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
    // __DIR__ is Web_app/treasurer; assets are stored in the project root.
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
    if (in_array($v, ['active', 'completed', 'paid', 'published', 'resolved', 'low'], true)) return 'tag-success';
    if (in_array($v, ['pending', 'warning', 'moderate', 'medium', 'acknowledged', 'draft'], true)) return 'tag-warning';
    if (in_array($v, ['critical', 'severe', 'high', 'overdue', 'failed', 'cancelled', 'refunded'], true)) return 'tag-danger';
    return 'tag-info';
}

function current_admin(): array {
    return [
        'full_name' => (string)($_SESSION['user']['name'] ?? 'Treasury Personnel'),
        'role' => (string)($_SESSION['user']['role'] ?? 'Treasurer'),
    ];
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $a = strtoupper(substr($parts[0] ?? 'T', 0, 1));
    $b = strtoupper(substr($parts[1] ?? 'R', 0, 1));
    return $a . $b;
}

function month_labels(): array {
    return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
}

function payment_method_options(): array {
    return [
        'cash' => 'Cash',
        'card' => 'Card',
        'online' => 'Online / Bank Transfer',
        'cheque' => 'Cheque',
        'mobile_wallet' => 'Mobile Wallet (GCash)',
        'other' => 'Other',
    ];
}

function payment_method_label(string $method): string {
    $options = payment_method_options();
    return $options[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

function payment_reference(int $paymentId): string {
    return 'OR-' . date('Y') . '-' . str_pad((string)$paymentId, 6, '0', STR_PAD_LEFT);
}

/**
 * Daily collection totals (completed payments) for the last N days, oldest first.
 */
function daily_collection_trend(int $days = 7): array {
    $rows = fetch_all("
        SELECT DATE(payment_date) AS d, COALESCE(SUM(amount_paid), 0) AS total
        FROM payments
        WHERE payment_status = 'completed'
          AND payment_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(payment_date)
        ORDER BY d ASC
    ", [$days - 1]);

    $byDate = [];
    foreach ($rows as $r) {
        $byDate[$r['d']] = (float)$r['total'];
    }

    $labels = [];
    $data = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} day"));
        $labels[] = date('D', strtotime($date));
        $data[] = $byDate[$date] ?? 0;
    }

    return ['labels' => $labels, 'data' => $data];
}

/**
 * Monthly collection totals (completed payments) for the current calendar year.
 */
function monthly_collection_totals(): array {
    $year = date('Y');
    $rows = fetch_all("
        SELECT MONTH(payment_date) AS m, COALESCE(SUM(amount_paid), 0) AS total
        FROM payments
        WHERE payment_status = 'completed'
          AND YEAR(payment_date) = ?
        GROUP BY MONTH(payment_date)
    ", [$year]);

    $data = array_fill(1, 12, 0.0);
    foreach ($rows as $r) {
        $data[(int)$r['m']] = (float)$r['total'];
    }

    return array_values($data);
}

/**
 * Returns ['label' => '+8.2%', 'direction' => 'up'|'down'|'flat'] comparing
 * a current value against a prior-period value, for stat card trend chips.
 */
function trend_label(float $current, float $previous): array {
    if ($previous <= 0.0) {
        if ($current <= 0.0) {
            return ['label' => '0.0%', 'direction' => 'flat'];
        }
        return ['label' => '+100.0%', 'direction' => 'up'];
    }
    $change = (($current - $previous) / $previous) * 100;
    $direction = $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat');
    $sign = $change > 0 ? '+' : '';
    return ['label' => $sign . number_format($change, 1) . '%', 'direction' => $direction];
}

function payment_status_breakdown(): array {
    $rows = fetch_all("
        SELECT status, COUNT(*) AS total
        FROM violations
        GROUP BY status
    ");
    $breakdown = ['paid' => 0, 'pending' => 0, 'overdue' => 0, 'cancelled' => 0];
    foreach ($rows as $r) {
        $key = strtolower((string)$r['status']);
        if (isset($breakdown[$key])) {
            $breakdown[$key] = (int)$r['total'];
        }
    }
    return $breakdown;
}
