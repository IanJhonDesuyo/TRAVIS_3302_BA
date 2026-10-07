<?php
declare(strict_types=1);

function travis_ensure_audit_table(mysqli $conn): bool
{
    return (bool)$conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
        audit_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NULL,
        actor_name VARCHAR(150) NOT NULL DEFAULT 'System',
        actor_role VARCHAR(80) NULL,
        action VARCHAR(80) NOT NULL,
        module VARCHAR(80) NOT NULL,
        entity_type VARCHAR(80) NULL,
        entity_id VARCHAR(100) NULL,
        description VARCHAR(500) NOT NULL,
        outcome ENUM('success','failed','warning') NOT NULL DEFAULT 'success',
        ip_address VARCHAR(45) NULL,
        user_agent VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (audit_id),
        KEY idx_audit_created_at (created_at),
        KEY idx_audit_user_id (user_id),
        KEY idx_audit_module_action (module, action),
        CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function travis_audit_log(
    mysqli $conn,
    string $action,
    string $module,
    string $description,
    string $outcome = 'success',
    ?string $entityType = null,
    int|string|null $entityId = null,
    ?array $actor = null
): void {
    try {
        if (!travis_ensure_audit_table($conn)) return;
        $actor ??= $_SESSION['user'] ?? null;
        $userId = !empty($actor['id']) ? (int)$actor['id'] : null;
        $actorName = trim((string)($actor['name'] ?? 'System')) ?: 'System';
        $actorRole = trim((string)($actor['role'] ?? '')) ?: null;
        $outcome = in_array($outcome, ['success', 'failed', 'warning'], true) ? $outcome : 'success';
        $entityId = $entityId === null ? null : (string)$entityId;
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null;
        $agent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;
        $stmt = $conn->prepare('INSERT INTO audit_logs (user_id, actor_name, actor_role, action, module, entity_type, entity_id, description, outcome, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        if (!$stmt) return;
        $stmt->bind_param('issssssssss', $userId, $actorName, $actorRole, $action, $module, $entityType, $entityId, $description, $outcome, $ip, $agent);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $exception) {
        error_log('Audit log write failed: ' . $exception->getMessage());
    }
}
