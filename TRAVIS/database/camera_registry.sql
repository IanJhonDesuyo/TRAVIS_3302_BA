-- TRAVIS camera registry extension.
-- Camera passwords stay on the assigned edge laptop and are never stored here.

ALTER TABLE cameras
    ADD COLUMN IF NOT EXISTS camera_username VARCHAR(120) NULL AFTER ip_address,
    ADD COLUMN IF NOT EXISTS stream_quality ENUM('stream1','stream2') NOT NULL DEFAULT 'stream2' AFTER camera_username,
    ADD COLUMN IF NOT EXISTS calibration_profile VARCHAR(190) NULL AFTER stream_quality,
    ADD COLUMN IF NOT EXISTS edge_worker_id VARCHAR(100) NULL AFTER calibration_profile,
    ADD COLUMN IF NOT EXISTS is_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER edge_worker_id,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at;

