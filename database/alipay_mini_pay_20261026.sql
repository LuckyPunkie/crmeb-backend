-- 支付宝小程序支付：后台「支付配置 → 支付宝支付配置」新增「支付宝小程序app_id」
-- 执行库：cermb
--
-- 小程序与 App 在开放平台上传的是同一个应用公钥，私钥 / 支付宝公钥沿用 alipay_private_key / alipay_public_key
-- 线上 Redis 有 merchant_sys_config_configFlag 时 systemConfig() 只读缓存，执行后还要：
--   redis-cli -a 111111 SETNX merchant_sys_config_0_alipay_mini_app_id 2021007100670836

INSERT INTO `eb_system_config` (`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`, `required`, `info`, `sort`, `user_type`, `status`, `create_time`)
SELECT 82, '支付宝小程序app_id', 'alipay_mini_app_id', 'input', '', '', 0, '支付宝小程序在开放平台的 app_id，用于小程序内支付宝支付；私钥和支付宝公钥与上方共用（小程序须上传同一个应用公钥）。不填则小程序无法使用支付宝支付。', 0, 0, 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'alipay_mini_app_id');

INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`, `create_time`)
SELECT 'alipay_mini_app_id', '2021007100670836', 0, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config_value` WHERE `config_key` = 'alipay_mini_app_id' AND `mer_id` = 0);
