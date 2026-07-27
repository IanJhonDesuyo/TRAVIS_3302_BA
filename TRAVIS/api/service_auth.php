<?php
declare(strict_types=1);

/**
 * Authenticate an internal TRAVIS service request using a timestamped HMAC.
 * Required headers:
 *   X-TRAVIS-Timestamp: Unix timestamp
 *   X-TRAVIS-Signature: HMAC-SHA256(timestamp + "." + raw request body)
 */
function travis_require_service_request(string $rawBody): void
{
    $secret = (string)(getenv('TRAVIS_SERVICE_API_KEY') ?: '');
    if ($secret === '') {
        $localSecretPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'service_api.key';
        if (is_file($localSecretPath)) {
            $secret = trim((string)file_get_contents($localSecretPath));
        }
    }
    if (strlen($secret) < 32) {
        error_log('TRAVIS service API rejected a request because TRAVIS_SERVICE_API_KEY is not configured securely.');
        http_response_code(503);
        echo json_encode(['success' => false, 'message' => 'Service authentication is not configured.']);
        exit;
    }

    $timestampHeader = trim((string)($_SERVER['HTTP_X_TRAVIS_TIMESTAMP'] ?? ''));
    $providedSignature = strtolower(trim((string)($_SERVER['HTTP_X_TRAVIS_SIGNATURE'] ?? '')));
    if (!ctype_digit($timestampHeader) || !preg_match('/^[a-f0-9]{64}$/', $providedSignature)) {
        travis_reject_service_request('missing or malformed authentication headers');
    }

    $timestamp = (int)$timestampHeader;
    if (abs(time() - $timestamp) > 300) {
        travis_reject_service_request('expired request timestamp');
    }

    $expectedSignature = hash_hmac('sha256', $timestampHeader . '.' . $rawBody, $secret);
    if (!hash_equals($expectedSignature, $providedSignature)) {
        travis_reject_service_request('invalid signature');
    }

    travis_enforce_service_rate_limit();
}

function travis_reject_service_request(string $reason): void
{
    $address = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    error_log("TRAVIS service API authentication failure from {$address}: {$reason}");
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized service request.']);
    exit;
}

function travis_enforce_service_rate_limit(int $maximumPerMinute = 120): void
{
    $address = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $bucket = intdiv(time(), 60);
    $key = hash('sha256', $address . '|' . $bucket);
    $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'travis-service-' . $key . '.limit';
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        error_log('TRAVIS service API rate limiter could not open its counter file.');
        return;
    }

    $count = 0;
    if (flock($handle, LOCK_EX)) {
        $stored = stream_get_contents($handle);
        $count = is_string($stored) && ctype_digit(trim($stored)) ? (int)trim($stored) : 0;
        $count++;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string)$count);
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);

    if ($count > $maximumPerMinute) {
        http_response_code(429);
        header('Retry-After: 60');
        echo json_encode(['success' => false, 'message' => 'Service request rate limit exceeded.']);
        exit;
    }
}
