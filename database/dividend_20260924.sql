-- 2026-09-24：商家分红功能
-- 平台输入总金额+起止时间，按各商户在该时间段内已支付订单数占比分账，
-- 生成买单形式的订单进商户订单中心，钱直接进商户余额（提现时按商户费率扣手续费，复用现有提现机制）。

CREATE TABLE IF NOT EXISTS `eb_merchant_dividend_batch` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `batch_sn` varchar(32) NOT NULL COMMENT '批次编号',
    `total_amount` decimal(10,2) unsigned NOT NULL COMMENT '管理员输入的总分红金额',
    `allocated_amount` decimal(10,2) unsigned NOT NULL DEFAULT 0.00 COMMENT '实际分给商户的总金额（因为只截取到分、不四舍五入，会略小于等于total_amount，差额留平台不分配）',
    `start_time` datetime NOT NULL COMMENT '统计时间范围-起',
    `end_time` datetime NOT NULL COMMENT '统计时间范围-止',
    `total_paid_order_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '全平台参与计算的已支付订单总数快照',
    `status` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '1=已发放',
    `admin_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '发起的平台管理员ID',
    `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
    `create_time` datetime NOT NULL,
    `update_time` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `batch_sn` (`batch_sn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商家分红批次表';

CREATE TABLE IF NOT EXISTS `eb_merchant_dividend_detail` (
    `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
    `batch_id` int(11) unsigned NOT NULL COMMENT '关联批次',
    `mer_id` int(11) unsigned NOT NULL COMMENT '商户ID',
    `paid_order_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '该商户在区间内已支付订单数（人次）',
    `ratio` decimal(10,6) unsigned NOT NULL DEFAULT 0 COMMENT '占比快照',
    `amount` decimal(10,2) unsigned NOT NULL DEFAULT 0.00 COMMENT '该商户实际分得金额',
    `order_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '生成的买单订单ID',
    `order_sn` varchar(32) NOT NULL DEFAULT '' COMMENT '生成的订单号',
    `create_time` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `batch_id` (`batch_id`),
    KEY `mer_id` (`mer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商家分红明细表';

-- 2026-09-24 更正：admin 侧边栏菜单树是从 eb_system_menu 表动态拉取的（不是纯静态路由，之前判断错了），
-- "财务"下面"商圈代理/店铺结算/用户结算/资金流水/发票管理"这几个分组名字就是这张表里的 menu_name。
-- 参照"资金流水"（menu_id=791, pid=515, route=/accounts/capitalFlow）这条现成的叶子节点菜单原样复制一条，
-- pid 指向同一个"财务"父节点(515)，route 换成前端刚加的 /accounts/dividend。
INSERT INTO `eb_system_menu` (`pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `is_agent`)
SELECT 515, '/515/', '', '商家分红', '/accounts/dividend', '[]', 5, 1, 0, 1, 0
FROM DUAL
WHERE EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 515)
  AND NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `route` = '/accounts/dividend' AND `is_mer` = 0);
