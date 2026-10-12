-- 消费送股 / 充值买股 平台总开关 + 商家开关
-- 执行库：cermb（只执行一次；invest_enabled 已存在时跳过 ADD COLUMN 那句）
--
-- 平台总开关（eb_system_config_value, mer_id=0，代码直读不走 systemConfig 缓存）：
--   0 任何店铺都不显示 / 1 初始不显示、商家可手动开启 / 2 全部显示、商家可手动关闭
-- 商家开关（eb_merchant_equity_config.enabled / invest_enabled）：
--   NULL 未手动设置（跟随平台：模式1=关、模式2=开） / 1 开 / 0 关

ALTER TABLE `eb_merchant_equity_config`
  MODIFY COLUMN `enabled` tinyint(1) NULL DEFAULT NULL COMMENT '商家消费送股开关 NULL未设置(跟随平台) 1开 0关';

ALTER TABLE `eb_merchant_equity_config`
  ADD COLUMN `invest_enabled` tinyint(1) NULL DEFAULT NULL COMMENT '商家充值买股开关 NULL未设置(跟随平台) 1开 0关' AFTER `enabled`;

-- 默认值与上线前行为一致：配置过的商家显示消费送股（enabled=1 视为已手动开启），充值入股对所有显示的店铺开放
INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`)
SELECT 'equity_consume_mode', '1', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config_value` WHERE `config_key` = 'equity_consume_mode' AND `mer_id` = 0);

INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`)
SELECT 'equity_invest_mode', '2', 0 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config_value` WHERE `config_key` = 'equity_invest_mode' AND `mer_id` = 0);

-- 平台后台菜单：消费送股 > 消费送股设置
SET @equity_pid = (SELECT `menu_id` FROM `eb_system_menu` WHERE `route` = '/equity' AND `is_mer` = 0 LIMIT 1);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT @equity_pid, '', '', '消费送股设置', '/equity/setting', '', 10, 1, 0, 1, 0
FROM DUAL WHERE @equity_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/equity/setting' AND `is_mer` = 0);
