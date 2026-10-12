-- 买单支付成功页「店老板请你免费开盲盒」入口：平台总开关 + 商家开关
-- 执行库：cermb
--
-- 平台总开关（eb_system_config_value, mer_id=0）：pay_blindbox_entry_mode
--   0 任何店铺都不显示 / 1 初始不显示、商家可手动开启 / 2 全部显示、商家可手动关闭（未设置时默认 2，与上线前一致）
-- 商家开关（eb_system_config_value, mer_id=商户ID）：pay_blindbox_entry_switch
--   无记录 = 未手动设置（跟随平台：模式1=关、模式2=开） / 1 开 / 0 关
-- 不需要建表、不需要预置值

-- 平台后台菜单：店铺 > 盲盒入口设置（排在买单下单设置之后）
SET @mer_pid = (SELECT `menu_id` FROM `eb_system_menu` WHERE `pid` = 0 AND `route` = '/mer' AND `is_mer` = 0 AND `is_agent` = 0 LIMIT 1);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT @mer_pid, CONCAT('/', @mer_pid, '/'), '', '盲盒入口设置', '/merchant/blindbox-switch', '', -2, 1, 0, 1, 0
FROM DUAL WHERE @mer_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/merchant/blindbox-switch' AND `is_mer` = 0);

-- 商家后台菜单：营销 > 盲盒入口（排在消费送股之后）
INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT 106, '/106/', '', '盲盒入口', '/marketing/blindboxSwitch', '', 9, 1, 1, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/marketing/blindboxSwitch' AND `is_mer` = 1);

SET @bb_mer_menu = (SELECT `menu_id` FROM `eb_system_menu` WHERE `route` = '/marketing/blindboxSwitch' AND `is_mer` = 1 LIMIT 1);

-- 店铺类型授权（与「营销」同批绑定，同消费送股）
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`)
SELECT DISTINCT r.left_id, @bb_mer_menu, 'mer_auth'
FROM `eb_relevance` r
WHERE r.type = 'mer_auth' AND r.right_id = 106
AND @bb_mer_menu IS NOT NULL
AND NOT EXISTS (
  SELECT 1 FROM `eb_relevance` x WHERE x.left_id = r.left_id AND x.right_id = @bb_mer_menu AND x.type = 'mer_auth'
);
