-- 消费送股 平台后台菜单
-- 执行库：cermb
-- pid 取「营销」或顶级，若不存在则插入为顶级菜单

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT 0, '', 'el-icon-s-finance', '消费送股', '/equity', '', 40, 1, 0, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/equity' AND `is_mer` = 0);

-- 已存在时纠正名称（避免侧栏显示不全时被误改）
UPDATE `eb_system_menu` SET `menu_name` = '消费送股' WHERE `route` = '/equity' AND `is_mer` = 0 AND `pid` = 0;

SET @equity_pid = (SELECT `menu_id` FROM `eb_system_menu` WHERE `route` = '/equity' AND `is_mer` = 0 LIMIT 1);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT @equity_pid, '', '', '待开业管理', '/equity/pending', '', 1, 1, 0, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/equity/pending' AND `is_mer` = 0);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT @equity_pid, '', '', '充值退款审核', '/equity/refunds', '', 2, 1, 0, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/equity/refunds' AND `is_mer` = 0);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT @equity_pid, '', '', '分红公告', '/equity/notices', '', 3, 1, 0, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/equity/notices' AND `is_mer` = 0);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT @equity_pid, '', '', '执行分红', '/equity/dividend', '', 4, 1, 0, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/equity/dividend' AND `is_mer` = 0);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT @equity_pid, '', '', '财报录入', '/equity/finance', '', 5, 1, 0, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/equity/finance' AND `is_mer` = 0);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT @equity_pid, '', '', '员工激励池', '/equity/staff-pool', '', 6, 1, 0, 1, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/equity/staff-pool' AND `is_mer` = 0);
