<?php
declare(strict_types=1);

function travis_is_edge_host(): bool
{
    return PHP_OS_FAMILY === 'Windows';
}

function travis_ensure_job_table(mysqli $conn): void
{
    $sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS hybrid_jobs (
    job_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_type VARCHAR(40) NOT NULL,
    payload_json LONGTEXT NOT NULL,
    status ENUM('pending','claimed','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
    result_json LONGTEXT NULL,
    error_message VARCHAR(1000) NULL,
    requested_by BIGINT UNSIGNED NULL,
    worker_id VARCHAR(100) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    claimed_at DATETIME NULL,
    completed_at DATETIME NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (job_id),
    KEY idx_hybrid_jobs_queue (status, job_type, created_at),
    KEY idx_hybrid_jobs_worker (worker_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
    if (!$conn->query($sql)) {
        throw new RuntimeException('Unable to initialize the hybrid worker queue.');
    }
}

function travis_enqueue_job(mysqli $conn, string $type, array $payload): int
{
    travis_ensure_job_table($conn);
    $allowed = ['ml_monthly', 'ml_hotspots', 'cv_start', 'cv_stop'];
    if (!in_array($type, $allowed, true)) {
        throw new InvalidArgumentException('Unsupported hybrid job type.');
    }

    // Reuse an in-flight CV command. A browser retry must not enqueue a
    // second video download/start while the first worker still owns the file.
    if (in_array($type, ['cv_start', 'cv_stop'], true)) {
        $existing = $conn->prepare("
            SELECT job_id
            FROM hybrid_jobs
            WHERE job_type = ?
              AND status IN ('pending', 'claimed')
              AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MINUTE)
            ORDER BY job_id DESC
            LIMIT 1
        ");
        if ($existing) {
            $existing->bind_param('s', $type);
            $existing->execute();
            $row = $existing->get_result()->fetch_assoc();
            $existing->close();
            if ($row) return (int)$row['job_id'];
        }
    }
    $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $requestedBy = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    $stmt = $conn->prepare('INSERT INTO hybrid_jobs (job_type, payload_json, requested_by) VALUES (?, ?, ?)');
    if (!$stmt) throw new RuntimeException('Unable to prepare the hybrid job.');
    $stmt->bind_param('ssi', $type, $payloadJson, $requestedBy);
    if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Unable to queue the hybrid job.');
    }
    $jobId = (int)$stmt->insert_id;
    $stmt->close();
    return $jobId;
}

function travis_wait_for_job(mysqli $conn, int $jobId, int $timeoutSeconds = 25): array
{
    $deadline = microtime(true) + max(1, min($timeoutSeconds, 90));
    do {
        $stmt = $conn->prepare('SELECT status, result_json, error_message, worker_id, updated_at FROM hybrid_jobs WHERE job_id=?');
        $stmt->bind_param('i', $jobId);
        $stmt->execute();
        $job = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$job) throw new RuntimeException('The queued job could not be found.');
        if ($job['status'] === 'completed') {
            $result = json_decode((string)$job['result_json'], true);
            return is_array($result) ? $result : ['success' => true, 'data' => $result];
        }
        if (in_array($job['status'], ['failed', 'cancelled'], true)) {
            throw new RuntimeException((string)($job['error_message'] ?: 'The laptop worker could not complete the job.'));
        }
        usleep(500000);
    } while (microtime(true) < $deadline);

    return [
        'success' => false,
        'pending' => true,
        'job_id' => $jobId,
        'message' => 'The request is queued. Start the TRAVIS laptop worker and try again.',
    ];
}

function travis_dispatch_edge_job(mysqli $conn, string $type, array $payload, int $timeoutSeconds = 25): array
{
    $jobId = travis_enqueue_job($conn, $type, $payload);
    return travis_wait_for_job($conn, $jobId, $timeoutSeconds);
}
