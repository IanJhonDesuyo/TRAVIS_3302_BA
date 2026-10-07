CREATE TABLE IF NOT EXISTS public_contacts (
    contact_id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    office_name VARCHAR(150) NOT NULL,
    phone_number VARCHAR(50) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    office_hours VARCHAR(120) NOT NULL DEFAULT '',
    category ENUM('emergency', 'office') NOT NULL DEFAULT 'office',
    display_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_public_contacts_display (is_active, display_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
