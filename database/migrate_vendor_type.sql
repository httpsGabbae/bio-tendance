USE `bio_attendance`;
ALTER TABLE `devices`
ADD COLUMN IF NOT EXISTS `vendor_type` ENUM('ZKTeco','Suprema','Anviz','Hikvision','Generic') NOT NULL DEFAULT 'Generic' AFTER `device_type`;
UPDATE `devices` SET `vendor_type`='Generic' WHERE `vendor_type`='Generic';
