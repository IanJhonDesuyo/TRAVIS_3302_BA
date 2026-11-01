<?php
declare(strict_types=1);

/** Persistent, revocable sessions used only by the installed mobile app. */

function travis_mobile_ensure_session_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS mobile_sessions (
        mobile_session_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        last_used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        revoked_at DATETIME NULL,
        INDEX idx_mobile_sessions_user (user_id),
        INDEX idx_mobile_sessions_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function travis_mobile_request_token(): string
{
    return trim((string)($_SERVER['HTTP_X_TRAVIS_MOBILE_TOKEN'] ?? ''));
}

function travis_mobile_issue_session(PDO $pdo, int $userId, int $lifetimeDays = 30): array
{
    travis_mobile_ensure_session_table($pdo);
    $token = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $hash = hash('sha256', $token);
    $expiresAt = (new DateTimeImmutable("+{$lifetimeDays} days"))->format('Y-m-d H:i:s');
    $statement = $pdo->prepare(
        'INSERT INTO mobile_sessions (user_id, token_hash, expires_at) VALUES (?, ?, ?)'
    );
    $statement->execute([$userId, $hash, $expiresAt]);
    return ['token' => $token, 'expires_at' => $expiresAt];
}

function travis_mobile_find_session(PDO $pdo, string $token): ?array
{
    if ($token === '') return null;
    travis_mobile_ensure_session_table($pdo);
    $statement = $pdo->prepare(
        "SELECT ms.mobile_session_id, ms.user_id, ms.expires_at,
                u.full_name, u.email, u.role, u.status
         FROM mobile_sessions ms
         INNER JOIN users u ON u.user_id = ms.user_id
         WHERE ms.token_hash = ? AND ms.revoked_at IS NULL AND ms.expires_at > NOW()
         LIMIT 1"
    );
    $statement->execute([hash('sha256', $token)]);
    $session = $statement->fetch(PDO::FETCH_ASSOC);
    return $session ?: null;
}

function travis_mobile_revoke_session(PDO $pdo, string $token): void
{
    if ($token === '') return;
    travis_mobile_ensure_session_table($pdo);
    $statement = $pdo->prepare('UPDATE mobile_sessions SET revoked_at = NOW() WHERE token_hash = ?');
    $statement->execute([hash('sha256', $token)]);
}
