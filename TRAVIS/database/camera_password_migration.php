<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/Web_app/Admin/db_connect.php';
require_once dirname(__DIR__) . '/Web_app/api/camera_credentials.php';

camera_ensure_password_column($conn);
echo "Encrypted camera credential schema ready.\n";

