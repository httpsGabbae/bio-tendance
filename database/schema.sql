CREATE DATABASE IF NOT EXISTS `bio_attendance` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bio_attendance`;
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL DEFAULT '',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `department_code` VARCHAR(20) NOT NULL UNIQUE,
  `department_name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `schedule_name` VARCHAR(100) NOT NULL,
  `time_in` TIME NOT NULL,
  `time_out` TIME NOT NULL,
  `grace_minutes` INT NOT NULL DEFAULT 10,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_no` VARCHAR(20) NOT NULL UNIQUE,
  `first_name` VARCHAR(50) NOT NULL,
  `middle_name` VARCHAR(50) NULL,
  `last_name` VARCHAR(50) NOT NULL,
  `email` VARCHAR(100) NULL UNIQUE,
  `department_id` INT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_emp_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `employee_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `schedule_id` INT NOT NULL,
  `effective_date` DATE NULL,
  UNIQUE KEY `uq_emp_eff` (`employee_id`,`effective_date`),
  CONSTRAINT `fk_es_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_es_sched` FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `devices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_code` VARCHAR(30) NOT NULL UNIQUE,
  `device_name` VARCHAR(100) NOT NULL,
  `device_type` VARCHAR(50) NOT NULL DEFAULT 'BIOMETRIC',
  `vendor_type` ENUM('ZKTeco','Suprema','Anviz','Hikvision','Generic') NOT NULL DEFAULT 'Generic',
  `location` VARCHAR(100) NOT NULL DEFAULT '',
  `api_key_hash` CHAR(64) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_seen_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `attendance_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `device_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `device_code` VARCHAR(30) NOT NULL,
  `employee_no` VARCHAR(20) NOT NULL,
  `event_time` DATETIME NOT NULL,
  `verification_method` ENUM('FINGERPRINT','FACE','CARD','PIN') NOT NULL,
  `direction` ENUM('IN','OUT') NOT NULL,
  `vendor_reference` VARCHAR(100) NOT NULL DEFAULT '',
  `event_hash` CHAR(64) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_event_hash` (`event_hash`),
  KEY `idx_emp_time` (`employee_id`,`event_time`),
  CONSTRAINT `fk_ev_dev` FOREIGN KEY (`device_id`) REFERENCES `devices`(`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ev_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `attendance_daily` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `employee_id` INT NOT NULL,
  `work_date` DATE NOT NULL,
  `time_in` DATETIME NULL,
  `time_out` DATETIME NULL,
  `status` ENUM('PRESENT','LATE','ABSENT','EARLY_OUT','ON_LEAVE','REST_DAY','HOLIDAY') NOT NULL DEFAULT 'PRESENT',
  `late_minutes` INT NOT NULL DEFAULT 0,
  `early_out_minutes` INT NOT NULL DEFAULT 0,
  `schedule_id` INT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_emp_date` (`employee_id`,`work_date`),
  CONSTRAINT `fk_daily_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_daily_sched` FOREIGN KEY (`schedule_id`) REFERENCES `schedules`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT NULL,
  `action` VARCHAR(100) NOT NULL,
  `details` TEXT NULL,
  `ip_address` VARCHAR(45) NOT NULL DEFAULT '',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
-- Seed master data (admin seeded via PHP on first login; device secret test-device-secret-001 sha256)
INSERT INTO `departments` (`department_code`,`department_name`) VALUES ('CCTE','College of Computer and Technology Education')
ON DUPLICATE KEY UPDATE department_name=VALUES(department_name);
INSERT INTO `schedules` (`schedule_name`,`time_in`,`time_out`,`grace_minutes`) VALUES ('Day Shift','08:00:00','17:00:00',10)
ON DUPLICATE KEY UPDATE schedule_name=VALUES(schedule_name);
INSERT INTO `devices` (`device_code`,`device_name`,`location`,`api_key_hash`,`status`) VALUES ('DEVICE-001','Main Entrance','Building A', SHA2(UUID(),256),'inactive')
ON DUPLICATE KEY UPDATE device_name=VALUES(device_name);


CREATE TABLE IF NOT EXISTS `login_attempts` (`id` INT AUTO_INCREMENT PRIMARY KEY, `ip_address` VARCHAR(45) NOT NULL, `username` VARCHAR(50) NOT NULL DEFAULT '', `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY `idx_ip` (`ip_address`,`created_at`)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `holidays` (`id` INT AUTO_INCREMENT PRIMARY KEY, `holiday_date` DATE NOT NULL UNIQUE, `name` VARCHAR(100) NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `employee_leaves` (`id` INT AUTO_INCREMENT PRIMARY KEY, `employee_id` INT NOT NULL, `start_date` DATE NOT NULL, `end_date` DATE NOT NULL, `reason` VARCHAR(150) NOT NULL DEFAULT '', KEY `idx_emp` (`employee_id`,`start_date`,`end_date`), CONSTRAINT `fk_lv_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE) ENGINE=InnoDB;
ALTER TABLE `schedules` ADD COLUMN IF NOT EXISTS `rest_days` VARCHAR(20) NOT NULL DEFAULT '6,7'; -- ISO weekdays: 1=Mon ... 7=Sun
-- Disable the publicly documented seed device key; re-enable it from Devices -> Reset key.
UPDATE `devices` SET `api_key_hash`=SHA2(CONCAT(UUID(),RAND()),256), `status`='inactive' WHERE `api_key_hash`=SHA2('test-device-secret-001',256);
CREATE TABLE IF NOT EXISTS `face_profiles` (`employee_id` INT PRIMARY KEY, `consent_at` DATETIME NOT NULL, `enrolled_by` INT NULL, `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT `fk_fp_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `face_templates` (`id` INT AUTO_INCREMENT PRIMARY KEY, `employee_id` INT NOT NULL, `descriptor` TEXT NOT NULL, KEY `idx_ft_emp` (`employee_id`), CONSTRAINT `fk_ft_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE) ENGINE=InnoDB;
