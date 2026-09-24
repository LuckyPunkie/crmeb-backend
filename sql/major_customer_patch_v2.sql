-- 大客户卡补丁：推荐人补填规则 + 平台配置键（已上线库 ALTER）
-- 已存在列时会报错，可忽略
ALTER TABLE `eb_major_customer_config`
  ADD COLUMN `referrer_fill_closed` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '1=开通阶段已确认推荐人(含未填)，不可再补填' AFTER `first_batch_done`;

INSERT INTO `eb_system_config` (`config_key`, `config_name`, `config_type`, `config_rule`, `info`, `sort`, `user_type`, `status`)
SELECT 'major_customer_commission_rate', '大客户卡佣金比例X', 'text', '', '0~1 小数，如 0.05 表示 5%', 0, 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'major_customer_commission_rate' LIMIT 1);

INSERT INTO `eb_system_config` (`config_key`, `config_name`, `config_type`, `config_rule`, `info`, `sort`, `user_type`, `status`)
SELECT 'major_customer_balance_low_ratio', '大客户卡余额接近0阈值比例', 'text', '', '相对最低档方案(A+B)的比例，默认0.1', 0, 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'major_customer_balance_low_ratio' LIMIT 1);

INSERT INTO `eb_system_config` (`config_key`, `config_name`, `config_type`, `config_rule`, `info`, `sort`, `user_type`, `status`)
SELECT 'major_customer_balance_cap_multiple', '大客户卡账户余额上限倍数', 'text', '', '相对最大方案(A+B)的倍数，默认5', 0, 0, 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'major_customer_balance_cap_multiple' LIMIT 1);
