-- 爱心救助后台菜单补齐：拨款审核 / 保证金托管 / 月捐结算
INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT 95361, '/9361/95361/', '', '拨款审核', '/animal_rescue/fund_audit', '', 5, 1, 0, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/animal_rescue/fund_audit' AND `is_mer` = 0);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT 95361, '/9361/95361/', '', '保证金托管', '/animal_rescue/deposit', '', 4, 1, 0, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/animal_rescue/deposit' AND `is_mer` = 0);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT 95361, '/9361/95361/', '', '月捐结算', '/animal_rescue/settlement', '', 3, 1, 0, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/animal_rescue/settlement' AND `is_mer` = 0);
