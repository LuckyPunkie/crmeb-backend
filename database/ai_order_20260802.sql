-- AI 点餐（按商家独立计量计费）
-- 执行库：cermb
-- 分支：feat/ai-order

-- 商家 AI 预充值余额（与 mer_money 结算余额隔离）
ALTER TABLE `eb_merchant`
  ADD COLUMN `ai_balance` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'AI点餐预充值余额' AFTER `mer_money`;

-- 订单关联 AI 通话总结
ALTER TABLE `eb_store_order`
  ADD COLUMN `ai_session_id` varchar(64) NOT NULL DEFAULT '' COMMENT 'AI点餐会话ID' AFTER `scan_table_label`,
  ADD COLUMN `ai_order_summary` text COMMENT 'AI点餐需求总结' AFTER `ai_session_id`;

-- 商户 AI 点餐配置
CREATE TABLE IF NOT EXISTS `eb_ai_order_config` (
  `mer_id` int(11) unsigned NOT NULL COMMENT '商户ID',
  `enable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否开启AI点餐',
  `dialect` varchar(32) NOT NULL DEFAULT 'mandarin' COMMENT '方言',
  `style` varchar(32) NOT NULL DEFAULT 'friendly' COMMENT '服务风格',
  `avatar` varchar(512) NOT NULL DEFAULT '' COMMENT '服务员头像URL',
  `update_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`mer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI点餐商户配置';

-- 通话会话（计量凭证，必含 mer_id）
CREATE TABLE IF NOT EXISTS `eb_ai_order_session` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_no` varchar(64) NOT NULL DEFAULT '' COMMENT '会话号',
  `mer_id` int(11) unsigned NOT NULL DEFAULT 0,
  `uid` int(11) unsigned NOT NULL DEFAULT 0,
  `table_id` int(11) unsigned NOT NULL DEFAULT 0,
  `table_label` varchar(32) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0进行中 1已结束 2失败',
  `usage_tokens` int(11) NOT NULL DEFAULT 0 COMMENT '消耗token',
  `usage_seconds` int(11) NOT NULL DEFAULT 0 COMMENT '通话秒数',
  `fee` decimal(12,4) NOT NULL DEFAULT 0.0000 COMMENT '扣费金额',
  `rate` decimal(12,4) NOT NULL DEFAULT 0.0000 COMMENT '当时费率(元/千token)',
  `summary` text COMMENT '需求总结',
  `provider_request_id` varchar(128) NOT NULL DEFAULT '' COMMENT '豆包侧请求ID',
  `deducted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已扣费',
  `start_time` int(11) NOT NULL DEFAULT 0,
  `end_time` int(11) NOT NULL DEFAULT 0,
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_session_no` (`session_no`),
  KEY `idx_mer_status` (`mer_id`,`status`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI点餐通话会话';

-- AI 余额流水（按商家隔离）
CREATE TABLE IF NOT EXISTS `eb_ai_balance_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int(11) unsigned NOT NULL DEFAULT 0,
  `type` varchar(20) NOT NULL DEFAULT '' COMMENT 'recharge|deduct|adjust',
  `amount` decimal(12,4) NOT NULL DEFAULT 0.0000 COMMENT '变动金额，扣费为负',
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '变动后余额',
  `session_no` varchar(64) NOT NULL DEFAULT '' COMMENT '关联会话',
  `usage_tokens` int(11) NOT NULL DEFAULT 0,
  `remark` varchar(255) NOT NULL DEFAULT '',
  `admin_id` int(11) NOT NULL DEFAULT 0 COMMENT '平台操作人',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_session_type` (`session_no`,`type`),
  KEY `idx_mer_time` (`mer_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='AI点餐余额流水';

-- 平台费率等配置（system_config）
INSERT INTO `eb_system_config`
(`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`,
 `required`, `info`, `sort`, `user_type`, `status`, `create_time`, `linked_status`, `linked_id`, `linked_value`)
SELECT 108, 'AI点餐费率(元/千token)', 'ai_order_rate_per_1k', 'number', '', '', 0,
       'AI点餐按商家token消耗扣费的平台单价', 20, 0, 1, NOW(), 0, 0, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'ai_order_rate_per_1k');

INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`)
SELECT 'ai_order_rate_per_1k', '0.10', 0 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `eb_system_config_value` WHERE `config_key` = 'ai_order_rate_per_1k' AND `mer_id` = 0
);

INSERT INTO `eb_system_config`
(`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`,
 `required`, `info`, `sort`, `user_type`, `status`, `create_time`, `linked_status`, `linked_id`, `linked_value`)
SELECT 108, 'AI点餐最低开聊余额', 'ai_order_min_balance', 'number', '', '', 0,
       '商家AI余额低于该值时禁止发起通话', 21, 0, 1, NOW(), 0, 0, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'ai_order_min_balance');

INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`)
SELECT 'ai_order_min_balance', '1', 0 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `eb_system_config_value` WHERE `config_key` = 'ai_order_min_balance' AND `mer_id` = 0
);

INSERT INTO `eb_system_config`
(`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`,
 `required`, `info`, `sort`, `user_type`, `status`, `create_time`, `linked_status`, `linked_id`, `linked_value`)
SELECT 108, 'AI点餐总开关', 'ai_order_open', 'switches', '', '', 0,
       '平台总开关，关闭后所有商家不可用AI点餐', 19, 0, 1, NOW(), 0, 0, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'ai_order_open');

INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`)
SELECT 'ai_order_open', '1', 0 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `eb_system_config_value` WHERE `config_key` = 'ai_order_open' AND `mer_id` = 0
);
