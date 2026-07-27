<?php
declare(strict_types=1);
require __DIR__ . '/../Web_app/db_connect.php';

$pdo->exec('ALTER TABLE violations MODIFY violation_type VARCHAR(1000) NOT NULL');
$pdo->exec("CREATE TABLE IF NOT EXISTS violation_items (
    violation_item_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    violation_id BIGINT UNSIGNED NOT NULL,
    violation_type VARCHAR(120) NOT NULL,
    penalty_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    ocr_confidence DECIMAL(5,4) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (violation_item_id),
    UNIQUE KEY uq_violation_item_type (violation_id, violation_type),
    CONSTRAINT fk_violation_items_violation FOREIGN KEY (violation_id) REFERENCES violations(violation_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("INSERT IGNORE INTO violation_items (violation_id, violation_type, penalty_amount)
    SELECT violation_id, violation_type, penalty_amount FROM violations");
echo "Multi-violation schema ready.\n";
