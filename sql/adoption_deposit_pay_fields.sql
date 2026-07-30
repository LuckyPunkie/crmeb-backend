-- 领养保证金支付字段（幂等）
SET @db := DATABASE();

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='eb_adoption_deposit' AND COLUMN_NAME='pay_type');
SET @sql := IF(@exists=0, 'ALTER TABLE `eb_adoption_deposit` ADD COLUMN `pay_type` varchar(20) NOT NULL DEFAULT '''' COMMENT ''支付方式'' AFTER `order_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='eb_adoption_deposit' AND COLUMN_NAME='transaction_id');
SET @sql := IF(@exists=0, 'ALTER TABLE `eb_adoption_deposit` ADD COLUMN `transaction_id` varchar(64) DEFAULT '''' COMMENT ''交易号'' AFTER `pay_type`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='eb_adoption_deposit' AND COLUMN_NAME='pay_time');
SET @sql := IF(@exists=0, 'ALTER TABLE `eb_adoption_deposit` ADD COLUMN `pay_time` datetime DEFAULT NULL COMMENT ''支付成功时间'' AFTER `transaction_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
