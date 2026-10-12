-- 店铺详情页「线上下单」「买单」按钮：平台总开关 + 商家开关
-- 执行库：cermb
--
-- 平台总开关（eb_system_config_value, mer_id=0）：nearby_online_order_mode / nearby_bill_mode
--   0 任何店铺都不显示 / 1 初始不显示、商家可手动开启 / 2 全部显示、商家可手动关闭（未设置时默认 2，与上线前一致）
-- 商家开关（eb_system_config_value, mer_id=商户ID）：nearby_online_order_switch / nearby_bill_switch
--   无记录 = 未手动设置（跟随平台：模式1=关、模式2=开） / 1 开 / 0 关
-- 不需要建表、不需要预置值

-- 平台后台菜单：店铺 > 买单下单设置（排在区域代理之后，sort 越大越靠前）
SET @mer_pid = (SELECT `menu_id` FROM `eb_system_menu` WHERE `pid` = 0 AND `route` = '/mer' AND `is_mer` = 0 AND `is_agent` = 0 LIMIT 1);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT @mer_pid, CONCAT('/', @mer_pid, '/'), '', '买单下单设置', '/merchant/action-switch', '', -1, 1, 0, 1, 0
FROM DUAL WHERE @mer_pid IS NOT NULL AND NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/merchant/action-switch' AND `is_mer` = 0);
