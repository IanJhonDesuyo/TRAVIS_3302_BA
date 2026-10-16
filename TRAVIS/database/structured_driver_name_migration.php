<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/Web_app/Admin/db_connect.php';

$columns = [
    'driver_last_name' => "VARCHAR(100) NULL AFTER driver_name",
    'driver_first_name' => "VARCHAR(100) NULL AFTER driver_last_name",
    'driver_middle_name' => "VARCHAR(100) NULL AFTER driver_first_name",
    'driver_suffix' => "VARCHAR(20) NULL AFTER driver_middle_name",
];

foreach ($columns as $column => $definition) {
    $escapedColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM violations LIKE '{$escapedColumn}'");
    if ($result && $result->num_rows === 0) {
        if (!$conn->query("ALTER TABLE violations ADD COLUMN {$column} {$definition}")) {
            throw new RuntimeException("Unable to add {$column}: " . $conn->error);
        }
    }
}

$index = $conn->query("SHOW INDEX FROM violations WHERE Key_name = 'idx_violations_driver_structured'");
if ($index && $index->num_rows === 0) {
    if (!$conn->query('ALTER TABLE violations ADD INDEX idx_violations_driver_structured (driver_last_name, driver_first_name, date_of_birth)')) {
        throw new RuntimeException('Unable to create structured driver name index: ' . $conn->error);
    }
}

echo "Structured driver name schema ready.\n";
