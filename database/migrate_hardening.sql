-- AttendX hardening migration (safe to re-run; MariaDB/MySQL via XAMPP)
USE `bio_attendance`;
CREATE TABLE IF NOT EXISTS `login_attempts` (`id` INT AUTO_INCREMENT PRIMARY KEY, `ip_address` VARCHAR(45) NOT NULL, `username` VARCHAR(50) NOT NULL DEFAULT '', `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY `idx_ip` (`ip_address`,`created_at`)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `holidays` (`id` INT AUTO_INCREMENT PRIMARY KEY, `holiday_date` DATE NOT NULL UNIQUE, `name` VARCHAR(100) NOT NULL) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS `employee_leaves` (`id` INT AUTO_INCREMENT PRIMARY KEY, `employee_id` INT NOT NULL, `start_date` DATE NOT NULL, `end_date` DATE NOT NULL, `reason` VARCHAR(150) NOT NULL DEFAULT '', KEY `idx_emp` (`employee_id`,`start_date`,`end_date`), CONSTRAINT `fk_lv_emp` FOREIGN KEY (`employee_id`) REFERENCES `employees`(`id`) ON DELETE CASCADE) ENGINE=InnoDB;
ALTER TABLE `schedules` ADD COLUMN IF NOT EXISTS `rest_days` VARCHAR(20) NOT NULL DEFAULT '6,7'; -- ISO weekdays: 1=Mon ... 7=Sun
-- Disable the publicly documented seed device key; re-enable it from Devices -> Reset key.
UPDATE `devices` SET `api_key_hash`=SHA2(CONCAT(UUID(),RAND()),256), `status`='inactive' WHERE `api_key_hash`=SHA2('test-device-secret-001',256);
