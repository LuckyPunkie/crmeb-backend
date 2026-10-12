-- 找人付费查询（2026-10-08）
-- plan：1=单次查询（绑定 field_key + keyword，换关键词需重新付费）；2=365天无限制查询（expire_time 内免费查）
-- 订单号前缀 FM，支付回调 attach=find_me

CREATE TABLE IF NOT EXISTS `eb_user_find_me_order` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(32) NOT NULL COMMENT '订单号',
  `uid` int(10) unsigned NOT NULL COMMENT '查询者',
  `plan` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '1=单次 2=365天',
  `field_key` varchar(32) NOT NULL DEFAULT '' COMMENT '查询类型（单次）',
  `keyword` varchar(64) NOT NULL DEFAULT '' COMMENT '规范化后的查询值（单次）',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '支付金额',
  `pay_type` varchar(16) NOT NULL DEFAULT '' COMMENT '支付方式',
  `pay_status` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '0=待支付 1=已支付',
  `pay_time` int(10) unsigned NOT NULL DEFAULT 0,
  `expire_time` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '年卡到期时间',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_uid_plan` (`uid`,`plan`,`pay_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='找人付费查询订单';
