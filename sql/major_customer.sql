-- 大客户卡 V1.4（系统虚拟充值 + 真实消费结算）
-- 表前缀以项目 config 为准，默认 eb_

CREATE TABLE IF NOT EXISTS `eb_major_customer_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int unsigned NOT NULL DEFAULT 0 COMMENT '商户ID',
  `status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '0关 1开',
  `referrer_phone` varchar(20) NOT NULL DEFAULT '' COMMENT '推荐人手机号',
  `referrer_uid` int unsigned NOT NULL DEFAULT 0 COMMENT '推荐人用户ID',
  `share_status` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '共享开关 0关 1开',
  `opened_at` datetime DEFAULT NULL COMMENT '开通时间',
  `share_opened_at` datetime DEFAULT NULL COMMENT '共享开启时间',
  `first_batch_done` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '首批虚拟充值是否完成',
  `referrer_fill_closed` tinyint unsigned NOT NULL DEFAULT 0 COMMENT '1=开通阶段已确认推荐人(含未填)，不可再补填',
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mer_id` (`mer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='大客户卡商户配置';

CREATE TABLE IF NOT EXISTS `eb_major_customer_plan` (
  `plan_id` int unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int unsigned NOT NULL DEFAULT 0,
  `amount_a` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '充值本金A',
  `amount_b` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '赠送B',
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
  `sort` int NOT NULL DEFAULT 0,
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`plan_id`),
  KEY `idx_mer_id` (`mer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='大客户卡充值方案';

CREATE TABLE IF NOT EXISTS `eb_major_customer_virtual_account` (
  `account_id` int unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int unsigned NOT NULL DEFAULT 0,
  `company_code` varchar(32) NOT NULL DEFAULT '' COMMENT '虚拟公司代号(商家端匿名)',
  `company_name` varchar(128) NOT NULL DEFAULT '' COMMENT '平台可见真实名称',
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_recharge_a` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_gift_b` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_consume` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '1正常 0作废',
  `create_time` datetime DEFAULT NULL,
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`account_id`),
  KEY `idx_mer_id` (`mer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='虚拟大客户账户';

CREATE TABLE IF NOT EXISTS `eb_major_customer_virtual_recharge` (
  `recharge_id` int unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int unsigned NOT NULL DEFAULT 0,
  `account_id` int unsigned NOT NULL DEFAULT 0,
  `plan_id` int unsigned NOT NULL DEFAULT 0,
  `amount_a` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_b` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '最新存入金额(本金A)',
  `balance_after` decimal(12,2) NOT NULL DEFAULT 0.00,
  `trigger_type` varchar(32) NOT NULL DEFAULT '' COMMENT 'first_week|balance_low|scheduled',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0待执行 1已完成 2失败 3取消',
  `scheduled_at` datetime DEFAULT NULL,
  `executed_at` datetime DEFAULT NULL,
  `week_batch` varchar(16) NOT NULL DEFAULT '',
  `create_time` datetime DEFAULT NULL,
  PRIMARY KEY (`recharge_id`),
  KEY `idx_mer_sched` (`mer_id`,`status`,`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='虚拟充值记录/任务';

CREATE TABLE IF NOT EXISTS `eb_major_customer_prestore` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int unsigned NOT NULL DEFAULT 0,
  `account_id` int unsigned NOT NULL DEFAULT 0,
  `latest_deposit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `period_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_cap` decimal(12,2) NOT NULL DEFAULT 0.00,
  `deposit_date` date DEFAULT NULL,
  `week_batch` varchar(16) NOT NULL DEFAULT '',
  `update_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_mer_account` (`mer_id`,`account_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商家端预存展示快照';

CREATE TABLE IF NOT EXISTS `eb_major_customer_settlement` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int unsigned NOT NULL DEFAULT 0,
  `uid` int unsigned NOT NULL DEFAULT 0,
  `bill_order_id` int unsigned NOT NULL DEFAULT 0,
  `order_sn` varchar(64) NOT NULL DEFAULT '',
  `pay_amount_c` decimal(10,2) NOT NULL DEFAULT 0.00,
  `plan_id` int unsigned NOT NULL DEFAULT 0,
  `amount_a` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_b` decimal(10,2) NOT NULL DEFAULT 0.00,
  `commission_x` decimal(8,4) NOT NULL DEFAULT 0.0000,
  `merchant_d` decimal(10,2) NOT NULL DEFAULT 0.00,
  `referrer_y` decimal(10,2) NOT NULL DEFAULT 0.00,
  `referrer_uid` int unsigned NOT NULL DEFAULT 0,
  `virtual_account_id` int unsigned NOT NULL DEFAULT 0,
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1已结算',
  `settled_at` datetime DEFAULT NULL,
  `create_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bill_order` (`bill_order_id`),
  KEY `idx_mer_sn` (`mer_id`,`order_sn`),
  KEY `idx_referrer` (`referrer_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='真实消费结算D/Y';

CREATE TABLE IF NOT EXISTS `eb_major_customer_rebate` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int unsigned NOT NULL DEFAULT 0,
  `uid` int unsigned NOT NULL DEFAULT 0,
  `bill_order_id` int unsigned NOT NULL DEFAULT 0,
  `order_sn` varchar(64) NOT NULL DEFAULT '',
  `original_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `rebate_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0待返还 1已返还 2失败',
  `scheduled_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `create_time` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_bill_rebate` (`bill_order_id`),
  KEY `idx_uid_status` (`uid`,`status`,`scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='共享折扣差额返还';
