-- 商家端 消费送股菜单（营销下）
-- 执行库：cermb

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT 106, '/106/', '', '消费送股', '/marketing/equity', '', 10, 1, 1, 1, 0
FROM DUAL WHERE NOT EXISTS (
  SELECT 1 FROM `eb_system_menu` WHERE `route` = '/marketing/equity' AND `is_mer` = 1
);

SET @equity_mer_pid = (SELECT `menu_id` FROM `eb_system_menu` WHERE `route` = '/marketing/equity' AND `is_mer` = 1 LIMIT 1);

INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT @equity_mer_pid, CONCAT('/106/', @equity_mer_pid, '/'), '', '权限', '/marketing/equity', '', 0, 1, 1, 0, 0
FROM DUAL WHERE @equity_mer_pid IS NOT NULL AND NOT EXISTS (
  SELECT 1 FROM `eb_system_menu` WHERE `pid` = @equity_mer_pid AND `menu_name` = '权限' AND `is_mer` = 1
);

-- 店铺类型授权（与「营销」同批绑定）
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`)
SELECT DISTINCT r.left_id, @equity_mer_pid, 'mer_auth'
FROM `eb_relevance` r
WHERE r.type = 'mer_auth' AND r.right_id = 106
AND @equity_mer_pid IS NOT NULL
AND NOT EXISTS (
  SELECT 1 FROM `eb_relevance` x WHERE x.left_id = r.left_id AND x.right_id = @equity_mer_pid AND x.type = 'mer_auth'
);
