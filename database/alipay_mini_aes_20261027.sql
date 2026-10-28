-- 支付宝小程序「接口内容加密方式」AES 密钥：获取会员手机号（my.getPhoneNumber）返回密文，后端用它解密
-- 执行库：cermb；须在 alipay_mini_tab_20261026.sql 之后执行
--
-- 密钥值不进仓库：执行后到后台「支付配置 → 支付宝小程序支付配置」填写；
-- 或直接写 eb_system_config_value，并 SETNX 进 Redis merchant_sys_config_0_alipay_mini_aes_key（configFlag 存在时 systemConfig() 只读缓存）

SET @mini_classify_id = (SELECT `config_classify_id` FROM `eb_system_config_classify` WHERE `classify_key` = 'tab_pay_alipay_mini');

INSERT INTO `eb_system_config` (`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`, `required`, `info`, `sort`, `user_type`, `status`, `create_time`)
SELECT @mini_classify_id, '支付宝小程序接口内容加密密钥', 'alipay_mini_aes_key', 'input', '', '', 0, '开放平台小程序「开发设置 → 接口内容加密方式」的 AES 密钥，用于解密会员手机号。', 0, 0, 1, NOW()
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'alipay_mini_aes_key');
