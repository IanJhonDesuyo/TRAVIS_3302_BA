<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/session.php';

header('Cache-Control: no-store');

if (!travis_is_authenticated() || travis_session_has_expired()) {
    http_response_code(401);
    exit;
}

http_response_code(204);
