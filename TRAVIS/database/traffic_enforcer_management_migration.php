<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/Web_app/Admin/db_connect.php';

$columns = [
    'middle_name' => 'VARCHAR(100) NULL AFTER first_name',
    'suffix' => 'VARCHAR(20) NULL AFTER last_name',
    'email' => 'VARCHAR(150) NULL AFTER contact_number',
    'address' => 'VARCHAR(255) NULL AFTER email',
    'birth_date' => 'DATE NULL AFTER address',
    'hire_date' => 'DATE NULL AFTER birth_date',
    'emergency_contact_name' => 'VARCHAR(150) NULL AFTER hire_date',
    'emergency_contact_number' => 'VARCHAR(30) NULL AFTER emergency_contact_name',
    'updated_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
];

foreach ($columns as $column => $definition) {
    $escapedColumn = $conn->real_escape_string($column);
    $result = $conn->query("SHOW COLUMNS FROM officers LIKE '{$escapedColumn}'");
    if ($result && $result->num_rows === 0) {
        if (!$conn->query("ALTER TABLE officers ADD COLUMN {$column} {$definition}")) {
            throw new RuntimeException("Unable to add {$column}: " . $conn->error);
        }
    }
}

$indexes = [
    'uq_officers_badge_number' => 'UNIQUE INDEX uq_officers_badge_number (badge_number)',
    'uq_officers_email' => 'UNIQUE INDEX uq_officers_email (email)',
];

foreach ($indexes as $indexName => $definition) {
    $escapedIndex = $conn->real_escape_string($indexName);
    $result = $conn->query("SHOW INDEX FROM officers WHERE Key_name = '{$escapedIndex}'");
    if ($result && $result->num_rows === 0) {
        if (!$conn->query("ALTER TABLE officers ADD {$definition}")) {
            throw new RuntimeException("Unable to create {$indexName}: " . $conn->error);
        }
    }
}

echo "Traffic enforcer management schema ready.\n";
