<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/service_auth.php';
require_once __DIR__ . '/../Web_app/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'POST method required.']);
    exit;
}

$rawBody = (string)file_get_contents('php://input');
travis_require_service_request($rawBody);
$input = json_decode($rawBody, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request.']);
    exit;
}

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS hybrid_jobs (
    job_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
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
    KEY idx_hybrid_jobs_queue (status, job_type, created_at),
    KEY idx_hybrid_jobs_worker (worker_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

$action = strtolower(trim((string)($input['action'] ?? '')));
$workerId = substr(trim((string)($input['worker_id'] ?? 'edge-worker')), 0, 100);

try {
    if ($action === 'claim') {
        $supported = array_values(array_intersect(
            is_array($input['supported_types'] ?? null) ? $input['supported_types'] : [],
            ['ml_monthly', 'ml_hotspots', 'cv_start', 'cv_stop']
        ));
        if (!$supported) throw new InvalidArgumentException('No supported job types supplied.');
        $placeholders = implode(',', array_fill(0, count($supported), '?'));
        $pdo->beginTransaction();
        $select = $pdo->prepare("SELECT job_id, job_type, payload_json FROM hybrid_jobs WHERE status='pending' AND job_type IN ($placeholders) ORDER BY job_id LIMIT 1 FOR UPDATE");
        $select->execute($supported);
        $job = $select->fetch();
        if (!$job) {
            $pdo->commit();
            echo json_encode(['success' => true, 'job' => null]);
            exit;
        }
        // The worker already has the payload in memory. Clear the stored copy
        // immediately so transient camera credentials are not retained.
        $claim = $pdo->prepare("UPDATE hybrid_jobs SET status='claimed', worker_id=?, claimed_at=NOW(), payload_json='{}' WHERE job_id=? AND status='pending'");
        $claim->execute([$workerId, (int)$job['job_id']]);
        $pdo->commit();
        echo json_encode(['success' => true, 'job' => [
            'job_id' => (int)$job['job_id'],
            'job_type' => $job['job_type'],
            'payload' => json_decode((string)$job['payload_json'], true) ?: [],
        ]]);
        exit;
    }

    if (in_array($action, ['complete', 'fail'], true)) {
        $jobId = (int)($input['job_id'] ?? 0);
        if ($jobId < 1) throw new InvalidArgumentException('A valid job ID is required.');
        if ($action === 'complete') {
            $resultJson = json_encode($input['result'] ?? null, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $stmt = $pdo->prepare("UPDATE hybrid_jobs SET status='completed', result_json=?, error_message=NULL, completed_at=NOW() WHERE job_id=? AND worker_id=? AND status='claimed'");
            $stmt->execute([$resultJson, $jobId, $workerId]);
        } else {
            $message = substr((string)($input['error'] ?? 'Worker job failed.'), 0, 1000);
            $stmt = $pdo->prepare("UPDATE hybrid_jobs SET status='failed', error_message=?, completed_at=NOW() WHERE job_id=? AND worker_id=? AND status='claimed'");
            $stmt->execute([$message, $jobId, $workerId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    throw new InvalidArgumentException('Unsupported worker action.');
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('TRAVIS worker API: ' . $error->getMessage());
    http_response_code($error instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode(['success' => false, 'message' => $error->getMessage()]);
}
