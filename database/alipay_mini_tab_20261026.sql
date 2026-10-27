-- 支付宝小程序支付单独成 tab：「支付配置 → 支付宝小程序支付配置」，app_id / 支付宝公钥 / 支付密钥 与 App 分开配
-- 执行库：cermb；须在 alipay_mini_pay_20261026.sql 之后执行
--
-- 支付宝公钥预填已验证可用的 …ApsmO3X…；支付密钥复制当前 App 的 alipay_private_key（两应用上传的是同一个应用公钥）
-- 线上 Redis 有 merchant_sys_config_configFlag 时 systemConfig() 只读缓存，执行后还要把两个新 key SETNX 进 Redis：
--   merchant_sys_config_0_alipay_mini_public_key / merchant_sys_config_0_alipay_mini_private_key（值取 eb_system_config_value）

INSERT INTO `eb_system_config_classify` (`pid`, `classify_name`, `classify_key`, `info`, `sort`, `create_time`, `icon`, `status`)
SELECT 79, '支付宝小程序支付配置', 'tab_pay_alipay_mini', '', 0, NOW(), '', 1
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config_classify` WHERE `classify_key` = 'tab_pay_alipay_mini');

SET @mini_classify_id = (SELECT `config_classify_id` FROM `eb_system_config_classify` WHERE `classify_key` = 'tab_pay_alipay_mini');

UPDATE `eb_system_config` SET `config_classify_id` = @mini_classify_id, `sort` = 3,
    `info` = '支付宝小程序在开放平台的 app_id（与 App 的支付宝 app_id 不同）。不填则小程序无法使用支付宝支付。'
WHERE `config_key` = 'alipay_mini_app_id';

INSERT INTO `eb_system_config` (`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`, `required`, `info`, `sort`, `user_type`, `status`, `create_time`)
SELECT @mini_classify_id, '支付宝小程序支付宝公钥', 'alipay_mini_public_key', 'input', '', '', 0, '开放平台小程序应用「接口加签方式」里的支付宝公钥（不是应用公钥），用于验签。', 2, 0, 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'alipay_mini_public_key');

INSERT INTO `eb_system_config` (`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`, `required`, `info`, `sort`, `user_type`, `status`, `create_time`)
SELECT @mini_classify_id, '支付宝小程序支付密钥', 'alipay_mini_private_key', 'input', '', '', 0, '小程序应用的应用私钥，与开放平台上传的应用公钥成对，用于请求签名，请妥善保管。', 1, 0, 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'alipay_mini_private_key');

INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`, `create_time`)
SELECT 'alipay_mini_public_key', 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEApsmO3XGDqv84i+dz2zIL8q6iid1/LbdaJQ4oDEMCgvpLfScuM9o65DhD7JboUnbnhrmQsiDiEaAx8tZ4QYACxpJjUt6HxWU4IQL7HcpAVqZfZMxHLfu3iTDZFea1Jp4gCU8VdBEApKC+r6aFEBOKYdZSb23489Wc86eJVv2sv0I56QvotAIoqyRFJtz0PNcwpw4Vg8agYdLLwR7j10Xu0aceWHOMuVFicUjTdXF17YXK+w+IQRdXK8A1fuyke2LpbElk6LqArDbcitTk7bfTC1P+w+XtiB33LfyFbTuiDo3xr+YfTX07+ev7Dp4qaujz+2JAm8K3l1TqcKIbWiCIbwIDAQAB', 0, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config_value` WHERE `config_key` = 'alipay_mini_public_key' AND `mer_id` = 0);

INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`, `create_time`)
SELECT 'alipay_mini_private_key', `value`, 0, NOW() FROM `eb_system_config_value`
WHERE `config_key` = 'alipay_private_key' AND `mer_id` = 0
  AND NOT EXISTS (SELECT 1 FROM `eb_system_config_value` WHERE `config_key` = 'alipay_mini_private_key' AND `mer_id` = 0)
LIMIT 1;

-- 2026-10-27：原「支付宝支付配置」tab 改名，和「支付宝小程序支付配置」区分
UPDATE `eb_system_config_classify` SET `classify_name` = '支付宝支付APP配置' WHERE `classify_key` = 'tab_pay_ail';
