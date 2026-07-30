-- 支付基础配置：模拟支付开关（挂到 pay_base / classify_id=108）
INSERT INTO `eb_system_config`
(`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`,
 `required`, `info`, `sort`, `user_type`, `status`, `create_time`, `linked_status`, `linked_id`, `linked_value`)
SELECT 108, '模拟支付开关', 'pay_mock_open', 'switches', '', '', 0,
       '开发/联调：开启后前端支付列表出现「模拟支付」，正式环境务必关闭', 10, 0, 1, NOW(), 0, 0, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'pay_mock_open');

INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`)
SELECT 'pay_mock_open', '1', 0 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `eb_system_config_value` WHERE `config_key` = 'pay_mock_open' AND `mer_id` = 0
);

-- 开发期默认开启；正式上线改为 0
UPDATE `eb_system_config_value` SET `value` = '1' WHERE `config_key` = 'pay_mock_open' AND `mer_id` = 0;
