-- TRAFFIQ Database Schema
-- Database: travis

DROP DATABASE IF EXISTS `travis`;
CREATE DATABASE `travis` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `travis`;

-- User Management
CREATE TABLE `users` (
  `user_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('Administrator','TMO Personnel','Treasury Personnel') NOT NULL DEFAULT 'TMO Personnel',
  `status` ENUM('active','inactive','suspended','pending') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Camera and Computer Vision Monitoring
CREATE TABLE `cameras` (
  `camera_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `camera_name` VARCHAR(120) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `status` ENUM('online','offline','maintenance','decommissioned') NOT NULL DEFAULT 'offline',
  `installed_at` DATE NULL,
  `last_maintenance_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`camera_id`),
  UNIQUE KEY `uq_cameras_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `camera_monitoring_logs` (
  `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `camera_id` BIGINT UNSIGNED NOT NULL,
  `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `vehicle_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `inbound_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `outbound_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `congestion_level` ENUM('none','low','moderate','heavy','severe') NOT NULL DEFAULT 'none',
  `officer_presence` ENUM('none','detected','multiple','unknown') NOT NULL DEFAULT 'unknown',
  `potential_collision` ENUM('none','possible','confirmed') NOT NULL DEFAULT 'none',
  `incident_notes` TEXT NULL,
  `alert_generated` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `idx_camera_monitoring_logs_camera_id` (`camera_id`),
  CONSTRAINT `fk_monitoring_logs_camera` FOREIGN KEY (`camera_id`) REFERENCES `cameras`(`camera_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `monitoring_alerts` (
  `alert_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `camera_log_id` BIGINT UNSIGNED NULL,
  `alert_type` ENUM('congestion','collision','incident','system') NOT NULL,
  `severity` ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
  `message` TEXT NOT NULL,
  `status` ENUM('active','acknowledged','resolved') NOT NULL DEFAULT 'active',
  `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `acknowledged_by` BIGINT UNSIGNED NULL,
  `acknowledged_at` DATETIME NULL,
  PRIMARY KEY (`alert_id`),
  CONSTRAINT `fk_alerts_camera_log` FOREIGN KEY (`camera_log_id`) REFERENCES `camera_monitoring_logs`(`log_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_alerts_acknowledged_by` FOREIGN KEY (`acknowledged_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Officer Presence and Duty Schedule
CREATE TABLE `officer_zones` (
  `zone_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `zone_name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(255) NULL,
  `location_details` VARCHAR(255) NULL,
  PRIMARY KEY (`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `officers` (
  `officer_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `badge_number` VARCHAR(50) NULL,
  `rank` VARCHAR(80) NULL,
  `contact_number` VARCHAR(30) NULL,
  `zone_id` BIGINT UNSIGNED NULL,
  `status` ENUM('active','inactive','retired','suspended') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`officer_id`),
  KEY `idx_officers_zone_id` (`zone_id`),
  CONSTRAINT `fk_officers_zone` FOREIGN KEY (`zone_id`) REFERENCES `officer_zones`(`zone_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `officer_duty_schedules` (
  `schedule_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `officer_id` BIGINT UNSIGNED NOT NULL,
  `duty_date` DATE NOT NULL,
  `shift_start` TIME NULL,
  `shift_end` TIME NULL,
  `status` ENUM('active duty','break','lunch break','off-duty') NOT NULL DEFAULT 'active duty',
  `notes` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`schedule_id`),
  KEY `idx_officer_duty_schedules_officer_id` (`officer_id`),
  CONSTRAINT `fk_duty_schedules_officer` FOREIGN KEY (`officer_id`) REFERENCES `officers`(`officer_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `officer_presence_logs` (
  `presence_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `officer_id` BIGINT UNSIGNED NOT NULL,
  `zone_id` BIGINT UNSIGNED NULL,
  `presence_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('on_duty','break','lunch_break','off_duty','not_present') NOT NULL DEFAULT 'not_present',
  `recorded_by` BIGINT UNSIGNED NULL,
  `remarks` VARCHAR(255) NULL,
  PRIMARY KEY (`presence_id`),
  KEY `idx_officer_presence_officer_id` (`officer_id`),
  KEY `idx_officer_presence_zone_id` (`zone_id`),
  CONSTRAINT `fk_presence_officer` FOREIGN KEY (`officer_id`) REFERENCES `officers`(`officer_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_presence_zone` FOREIGN KEY (`zone_id`) REFERENCES `officer_zones`(`zone_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_presence_recorded_by` FOREIGN KEY (`recorded_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Violation and Payment Recording
CREATE TABLE `violations` (
  `violation_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ticket_number` VARCHAR(80) NOT NULL,
  `driver_name` VARCHAR(150) NOT NULL,
  `license_number` VARCHAR(80) NOT NULL,
  `plate_number` VARCHAR(50) NOT NULL,
  `vehicle_type` ENUM('Motorcycle','Car','SUV','Truck','Bus','Other') NOT NULL,
  `violation_type` VARCHAR(120) NOT NULL,
  `violation_location` VARCHAR(255) NOT NULL,
  `violation_date` DATE NOT NULL,
  `violation_time` TIME NOT NULL,
  `penalty_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `input_method` ENUM('manual','ocr') NOT NULL DEFAULT 'manual',
  `encoded_by` BIGINT UNSIGNED NULL,
  `status` ENUM('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`violation_id`),
  UNIQUE KEY `uq_violations_ticket_number` (`ticket_number`),
  KEY `idx_violations_encoded_by` (`encoded_by`),
  CONSTRAINT `fk_violations_encoded_by` FOREIGN KEY (`encoded_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `payments` (
  `payment_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `violation_id` BIGINT UNSIGNED NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL,
  `payment_status` ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'completed',
  `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `received_by` BIGINT UNSIGNED NULL,
  `payment_method` ENUM('cash','card','online','cheque','mobile_wallet','other') NOT NULL DEFAULT 'cash',
  `receipt_reference` VARCHAR(120) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`payment_id`),
  KEY `idx_payments_violation_id` (`violation_id`),
  KEY `idx_payments_received_by` (`received_by`),
  CONSTRAINT `fk_payments_violation` FOREIGN KEY (`violation_id`) REFERENCES `violations`(`violation_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_payments_received_by` FOREIGN KEY (`received_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Machine Learning and Analytics
CREATE TABLE `ml_predictions` (
  `prediction_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prediction_type` ENUM('season-based','time-based','high-violation-period','other') NOT NULL,
  `predicted_result` VARCHAR(255) NOT NULL,
  `confidence_score` DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
  `location` VARCHAR(255) NULL,
  `violation_type` VARCHAR(120) NULL,
  `frequency_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `risk_level` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `prediction_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT NULL,
  PRIMARY KEY (`prediction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `violation_hotspots` (
  `hotspot_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cluster_label` VARCHAR(80) NOT NULL,
  `location` VARCHAR(255) NOT NULL,
  `violation_type` VARCHAR(120) NULL,
  `frequency_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `risk_level` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `model_type` ENUM('k-means','other') NOT NULL DEFAULT 'k-means',
  `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `additional_info` TEXT NULL,
  PRIMARY KEY (`hotspot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reports
CREATE TABLE `reports` (
  `report_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `report_type` ENUM('traffic monitoring','violation','payment','alert','prediction','hotspot','custom') NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NULL,
  `generated_by` BIGINT UNSIGNED NULL,
  `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `period_start` DATE NULL,
  `period_end` DATE NULL,
  `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `file_path` VARCHAR(255) NULL,
  PRIMARY KEY (`report_id`),
  KEY `idx_reports_generated_by` (`generated_by`),
  CONSTRAINT `fk_reports_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Public Website
CREATE TABLE `public_announcements` (
  `announcement_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `announcement_type` ENUM('public announcement','traffic advisory','tmo activity','public notice') NOT NULL,
  `publish_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expiry_date` DATETIME NULL,
  `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`announcement_id`),
  KEY `idx_announcements_created_by` (`created_by`),
  CONSTRAINT `fk_announcements_created_by` FOREIGN KEY (`created_by`) REFERENCES `users`(`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Indexes for performance and referential integrity
CREATE INDEX `idx_monitoring_logs_recorded_at` ON `camera_monitoring_logs` (`recorded_at`);
CREATE INDEX `idx_officer_presence_date` ON `officer_presence_logs` (`presence_date`);
CREATE INDEX `idx_violations_date` ON `violations` (`violation_date`);
CREATE INDEX `idx_payments_date` ON `payments` (`payment_date`);
CREATE INDEX `idx_reports_generated_at` ON `reports` (`generated_at`);

-- Example data seeding (optional)
-- INSERT INTO `users` (`full_name`, `email`, `password`, `role`, `status`) VALUES
--   ('System Administrator', 'admin@traffiq.local', 'hashed_password_here', 'Administrator', 'active');
