<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$configFile = dirname(__DIR__, 2) . '/computer_vision/camera_config.json';
$config = is_file($configFile) ? json_decode((string)file_get_contents($configFile), true) : [];

echo json_encode([
    'success' => true,
    'data' => [
        'host' => (string)($config['host'] ?? ''),
        'username' => (string)($config['username'] ?? ''),
        'stream' => ($config['stream'] ?? 'stream2') === 'stream1' ? 'stream1' : 'stream2',
        'has_saved_password' => !empty($config['password']),
    ],
]);
