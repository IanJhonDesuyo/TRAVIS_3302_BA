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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
