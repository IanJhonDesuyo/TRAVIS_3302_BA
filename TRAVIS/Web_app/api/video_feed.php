<?php
declare(strict_types=1);

// Same-origin MJPEG proxy. Mobile devices can already reach Apache, while the
// Python stream port may be blocked by Windows Firewall.
$client = strtolower((string)($_GET['client'] ?? ''));
if (!in_array($client, ['web', 'mobile'], true)) {
    http_response_code(422);
    exit('Invalid monitoring client.');
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    exit('PHP cURL is required for the monitoring stream.');
}

set_time_limit(0);
ignore_user_abort(true);
while (ob_get_level() > 0) ob_end_clean();
ob_implicit_flush(true);
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
    @apache_setenv('dont-vary', '1');
}

header('Content-Type: multipart/x-mixed-replace; boundary=frame');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Accel-Buffering: no');
header('Access-Control-Allow-Origin: *');

$curl = curl_init('http://127.0.0.1:5000/video_feed');
curl_setopt_array($curl, [
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_RETURNTRANSFER => false,
    CURLOPT_BUFFERSIZE => 16384,
    CURLOPT_TCP_NODELAY => true,
    CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk): int {
        if (connection_aborted()) return 0;
        echo $chunk;
        flush();
        return strlen($chunk);
    },
]);
curl_exec($curl);
curl_close($curl);
