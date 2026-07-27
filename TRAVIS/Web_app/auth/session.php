<?php
declare(strict_types=1);

function travis_session_start(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

function travis_login_user(array $databaseUser): void
{
    travis_session_start();
    session_regenerate_id(true);
    $_SESSION['authenticated'] = true;
    $_SESSION['authenticated_at'] = time();
    $_SESSION['user'] = [
        'id' => (int)$databaseUser['user_id'],
        'name' => (string)$databaseUser['full_name'],
        'email' => (string)$databaseUser['email'],
        'role' => (string)$databaseUser['role'],
    ];

    // Compatibility aliases for endpoints being migrated. Identity always
    // originates from the canonical authenticated + user structure above.
    $_SESSION['logged_in'] = true;
    $_SESSION['user_id'] = $_SESSION['user']['id'];
    $_SESSION['full_name'] = $_SESSION['user']['name'];
    $_SESSION['email'] = $_SESSION['user']['email'];
    $_SESSION['role'] = $_SESSION['user']['role'];
}

function travis_is_authenticated(): bool
{
    travis_session_start();
    return ($_SESSION['authenticated'] ?? false) === true
        && (int)($_SESSION['user']['id'] ?? 0) > 0;
}

function travis_current_user(): ?array
{
    return travis_is_authenticated() ? $_SESSION['user'] : null;
}

function travis_logout_user(): void
{
    travis_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool)$params['secure'],
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    session_destroy();
}
