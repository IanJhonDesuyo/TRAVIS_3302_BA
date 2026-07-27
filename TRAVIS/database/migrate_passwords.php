<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../Web_app/Admin/db_connect.php';
$result = $conn->query('SELECT user_id, password FROM users');
if (!$result) exit(1);

$conn->begin_transaction();
$migrated = 0;
try {
    $update = $conn->prepare('UPDATE users SET password = ? WHERE user_id = ?');
    if (!$update) throw new RuntimeException('Unable to prepare migration.');
    while ($row = $result->fetch_assoc()) {
        $stored = (string)$row['password'];
        if ((password_get_info($stored)['algo'] ?? 0) !== 0) continue;
        $hash = password_hash($stored, PASSWORD_DEFAULT);
        if ($hash === false) throw new RuntimeException('Unable to hash password.');
        $userId = (int)$row['user_id'];
        $update->bind_param('si', $hash, $userId);
        if (!$update->execute()) throw new RuntimeException('Unable to update password.');
        $migrated++;
    }
    $conn->commit();
    echo "Migrated {$migrated} legacy password(s).\n";
} catch (Throwable $exception) {
    $conn->rollback();
    fwrite(STDERR, "Password migration failed.\n");
    exit(1);
}
