-- 消费送股（equity）全量表
-- 执行库：cermb

CREATE TABLE IF NOT EXISTS `eb_merchant_equity_config` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '商家ID(老店)',
  `enabled` tinyint(1) NOT NULL DEFAULT 1 COMMENT '开关 1开 0关',
  `consume_equity_percent` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '消费送股百分比 如1.02表示1.02%',
  `target_equity_amount` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '新店目标股本(元)',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mer` (`mer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商家消费送股配置';

CREATE TABLE IF NOT EXISTS `eb_equity_project` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '老店商家ID',
  `new_store_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '新店商家ID(绑定后)',
  `round_no` int(11) unsigned NOT NULL DEFAULT 1 COMMENT '轮次从1递增',
  `target_amount` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '目标股本金额',
  `total_consumer_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '消费者累计股本金',
  `total_equity` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '总股本=消费者池/0.9',
  `shareholder_count` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '股东用户数',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1筹集中 2待开业 3营业中',
  `reached_at` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '达成目标时间',
  `opened_at` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '开业时间',
  `expected_open_at` varchar(32) NOT NULL DEFAULT '' COMMENT '预计开业时间文案',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mer_status` (`mer_id`,`status`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='消费送股项目';

CREATE TABLE IF NOT EXISTS `eb_equity_shareholder` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) unsigned NOT NULL DEFAULT 0,
  `uid` int(11) unsigned NOT NULL DEFAULT 0,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '个人累计股本金',
  `invest_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '充值入股累计(可退部分)',
  `share_ratio` decimal(18,6) NOT NULL DEFAULT 0.000000 COMMENT '个人占股比例',
  `last_consume_time` int(11) unsigned NOT NULL DEFAULT 0,
  `last_dividend_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_dividend_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_project_uid` (`project_id`,`uid`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='消费送股股东';

CREATE TABLE IF NOT EXISTS `eb_equity_transaction` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) unsigned NOT NULL DEFAULT 0,
  `uid` int(11) unsigned NOT NULL DEFAULT 0,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '正增负减',
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '变动后个人余额',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1消费送股 2充值入股 3退款扣减',
  `order_id` varchar(64) NOT NULL DEFAULT '' COMMENT '关联订单号/支付单号',
  `order_type` varchar(20) NOT NULL DEFAULT '' COMMENT 'bill|order|invest|refund',
  `source_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '关联消费/充值/退款原金额',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_uid` (`project_id`,`uid`),
  KEY `idx_order` (`order_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='入股明细';

CREATE TABLE IF NOT EXISTS `eb_equity_invest_order` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_sn` varchar(32) NOT NULL DEFAULT '',
  `project_id` int(11) unsigned NOT NULL DEFAULT 0,
  `mer_id` int(11) unsigned NOT NULL DEFAULT 0,
  `uid` int(11) unsigned NOT NULL DEFAULT 0,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid` tinyint(1) NOT NULL DEFAULT 0,
  `pay_type` varchar(20) NOT NULL DEFAULT '',
  `pay_time` int(11) unsigned NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0待支付 1已支付 2已退款',
  `refunded` tinyint(1) NOT NULL DEFAULT 0,
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_sn` (`order_sn`),
  KEY `idx_project_uid` (`project_id`,`uid`),
  KEY `idx_uid_paid` (`uid`,`paid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值入股支付单';

CREATE TABLE IF NOT EXISTS `eb_equity_invest_refund` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) unsigned NOT NULL DEFAULT 0,
  `uid` int(11) unsigned NOT NULL DEFAULT 0,
  `invest_order_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '0表示全额汇总退',
  `refund_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1待审核 2已通过 3已拒绝',
  `apply_reason` varchar(255) NOT NULL DEFAULT '',
  `audit_reason` varchar(255) NOT NULL DEFAULT '',
  `admin_id` int(11) unsigned NOT NULL DEFAULT 0,
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `audited_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_project_uid` (`project_id`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='充值入股退款申请';

CREATE TABLE IF NOT EXISTS `eb_equity_dividend` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) unsigned NOT NULL DEFAULT 0,
  `batch_no` varchar(32) NOT NULL DEFAULT '' COMMENT '同批分红号',
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '本批总分红',
  `uid` int(11) unsigned NOT NULL DEFAULT 0,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `role_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1消费者 2原商家 3平台 4员工池',
  `period` varchar(64) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 2 COMMENT '1待发放 2已发放',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_uid` (`project_id`,`uid`),
  KEY `idx_batch` (`batch_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分红记录';

CREATE TABLE IF NOT EXISTS `eb_equity_dividend_notice` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) unsigned NOT NULL DEFAULT 0,
  `title` varchar(128) NOT NULL DEFAULT '',
  `period` varchar(64) NOT NULL DEFAULT '',
  `expected_date` varchar(20) NOT NULL DEFAULT '',
  `expected_amount` decimal(12,2) DEFAULT NULL,
  `content` text,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1草稿 2已发布 3已撤回',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `published_at` int(11) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_project_status` (`project_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='分红公告';

CREATE TABLE IF NOT EXISTS `eb_equity_financial_report` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) unsigned NOT NULL DEFAULT 0,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `cash_income` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '现金收款手工录入',
  `expense_json` text COMMENT '支出分类JSON',
  `cost_json` text COMMENT '成本分类JSON',
  `staff_count` int(11) NOT NULL DEFAULT 0,
  `staff_wage_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `staff_wage_avg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `staff_wage_structure` varchar(255) NOT NULL DEFAULT '',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `admin_id` int(11) unsigned NOT NULL DEFAULT 0,
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_date` (`project_id`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='店铺财报录入';

CREATE TABLE IF NOT EXISTS `eb_equity_staff_pool` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `project_id` int(11) unsigned NOT NULL DEFAULT 0,
  `staff_name` varchar(64) NOT NULL DEFAULT '' COMMENT '展示名(不暴露隐私)',
  `staff_uid` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '可选绑定用户',
  `pool_ratio` decimal(8,4) NOT NULL DEFAULT 0.0000 COMMENT '占员工激励池比例0-100',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='员工激励池分配';
