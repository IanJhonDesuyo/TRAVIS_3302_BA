<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$storage = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage';
$path = $storage . DIRECTORY_SEPARATOR . 'service_api.key';
if (!is_dir($storage) && !mkdir($storage, 0700, true) && !is_dir($storage)) {
    fwrite(STDERR, "Unable to create secure storage directory.\n");
    exit(1);
}
if (is_file($path) && strlen(trim((string)file_get_contents($path))) >= 32) {
    echo "Service key already configured.\n";
    exit(0);
}
$secret = bin2hex(random_bytes(32));
if (file_put_contents($path, $secret, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write service key.\n");
    exit(1);
}
@chmod($path, 0600);
echo "Service key configured.\n";
