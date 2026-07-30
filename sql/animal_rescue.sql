-- =====================================================
-- CRMEB 流浪动物救助模块 - 数据库迁移SQL
-- 创建日期: 2026-06-21
-- 字符集: utf8mb4
-- 说明: 创建6张核心表（带 eb_ 前缀，适配本项目 database.prefix）
-- =====================================================

-- 1. 流浪动物救助帖子表
DROP TABLE IF EXISTS `eb_animal_rescue_post`;
CREATE TABLE `eb_animal_rescue_post` (
  `post_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '帖子ID',
  `uid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '发布者UID',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型:1=救助,2=领养,3=云养',
  `title` varchar(120) NOT NULL DEFAULT '' COMMENT '标题',
  `animal_name` varchar(60) NOT NULL DEFAULT '' COMMENT '动物名字',
  `animal_type` varchar(20) NOT NULL DEFAULT 'dog' COMMENT '动物类型:dog/cat/rabbit/other',
  `city_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '城市ID(关联city_area)',
  `phone` varchar(20) NOT NULL DEFAULT '' COMMENT '联系电话',
  `target_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '目标金额(救助/云养)',
  `raised_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '已筹金额',
  `deposit_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '保证金金额(领养)',
  `deposit_thaw_months` tinyint(2) NOT NULL DEFAULT 0 COMMENT '保证金解冻月数:1/3/6/12',
  `content` text COMMENT '详细描述',
  `images` text COMMENT '图片(逗号分隔,最多9张)',
  `participant_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '参与人数(救助/云养)',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态:0=审核中,1=进行中,2=已完成,3=已关闭,-1=审核驳回',
  `is_show` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否显示:0=否,1=是',
  `status_time` datetime DEFAULT NULL COMMENT '状态变更时间',
  `end_time` datetime DEFAULT NULL COMMENT '筹款截止时间(创建时自动设为NOW()+30天)',
  `animal_age` varchar(20) DEFAULT '' COMMENT '动物年龄',
  `animal_health` varchar(100) DEFAULT '' COMMENT '健康状况',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `is_del` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否删除:0=否,1=是',
  PRIMARY KEY (`post_id`),
  KEY `idx_uid` (`uid`),
  KEY `idx_type_status` (`type`,`status`,`is_show`),
  KEY `idx_city` (`city_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='流浪动物救助帖子表';

-- 2. 救助捐款订单表
DROP TABLE IF EXISTS `eb_animal_rescue_order`;
CREATE TABLE `eb_animal_rescue_order` (
  `order_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '订单ID',
  `order_sn` varchar(32) NOT NULL DEFAULT '' COMMENT '订单编号',
  `uid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '捐款人UID',
  `post_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '帖子ID',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '捐款金额',
  `pay_type` varchar(20) NOT NULL DEFAULT 'weixin' COMMENT '支付方式:weixin/alipay/bank',
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否匿名:0=否,1=是',
  `message` varchar(200) DEFAULT '' COMMENT '留言/祝福',
  `paid` tinyint(1) NOT NULL DEFAULT 0 COMMENT '支付状态:0=未支付,1=已支付',
  `pay_time` datetime DEFAULT NULL COMMENT '支付时间',
  `transaction_id` varchar(64) DEFAULT '' COMMENT '第三方交易号',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`order_id`),
  UNIQUE KEY `uk_order_sn` (`order_sn`),
  KEY `idx_uid` (`uid`),
  KEY `idx_post_id` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='救助捐款订单表';

-- 3. 参与记录表
DROP TABLE IF EXISTS `eb_animal_rescue_participant`;
CREATE TABLE `eb_animal_rescue_participant` (
  `participant_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '参与记录ID',
  `uid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '用户UID',
  `post_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '帖子ID',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型:1=救助捐款,2=领养保证金,3=云养月捐',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '金额(捐款/保证金/月捐)',
  `order_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '关联订单ID',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:1=已完成,2=进行中,3=已解冻',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`participant_id`),
  KEY `idx_uid` (`uid`),
  KEY `idx_post` (`post_id`),
  KEY `idx_uid_type` (`uid`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='救助参与记录表';

-- 4. 领养申请表
DROP TABLE IF EXISTS `eb_adoption_application`;
CREATE TABLE `eb_adoption_application` (
  `application_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '申请ID',
  `uid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '申请人UID',
  `post_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '帖子ID',
  `real_name` varchar(60) NOT NULL DEFAULT '' COMMENT '真实姓名',
  `phone` varchar(20) NOT NULL DEFAULT '' COMMENT '联系电话',
  `id_card` varchar(30) DEFAULT '' COMMENT '身份证号',
  `address` varchar(255) DEFAULT '' COMMENT '家庭地址',
  `income_info` varchar(255) DEFAULT '' COMMENT '收入情况',
  `housing_type` varchar(30) DEFAULT '' COMMENT '住房类型:owned/rented',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:1=审核中,2=审核通过,3=已领养,4=已完成,-1=审核拒绝',
  `remark` varchar(500) DEFAULT '' COMMENT '审核备注',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`application_id`),
  KEY `idx_uid` (`uid`),
  KEY `idx_post` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='领养申请表';

-- 5. 领养保证金表
DROP TABLE IF EXISTS `eb_adoption_deposit`;
CREATE TABLE `eb_adoption_deposit` (
  `deposit_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '保证金ID',
  `uid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '领养人UID',
  `application_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '领养申请ID',
  `post_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '帖子ID',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '保证金金额',
  `thaw_months` tinyint(2) NOT NULL DEFAULT 6 COMMENT '解冻月数',
  `thaw_time` datetime DEFAULT NULL COMMENT '预期解冻时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:1=冻结中,2=已解冻,3=已扣除(违约)',
  `order_sn` varchar(32) NOT NULL DEFAULT '' COMMENT '支付订单号',
  `order_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '关联支付订单ID',
  `refund_order_id` varchar(64) DEFAULT '' COMMENT '退款交易号',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`deposit_id`),
  UNIQUE KEY `uk_order_sn` (`order_sn`),
  KEY `idx_uid` (`uid`),
  KEY `idx_status` (`status`),
  KEY `idx_thaw_time` (`thaw_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='领养保证金表';

-- 6. 云养订单表
DROP TABLE IF EXISTS `eb_cloud_adoption_order`;
CREATE TABLE `eb_cloud_adoption_order` (
  `cloud_order_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '云养订单ID',
  `order_sn` varchar(32) NOT NULL DEFAULT '' COMMENT '订单编号(支付回调匹配)',
  `uid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '用户UID',
  `post_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '帖子ID',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '月捐金额',
  `pay_type` varchar(20) NOT NULL DEFAULT 'weixin' COMMENT '支付方式',
  `is_subscribe` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否自动续费:0=否,1=是',
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否匿名捐赠:0=否,1=是',
  `paid` tinyint(1) NOT NULL DEFAULT 0 COMMENT '支付状态:0=未支付,1=已支付',
  `pay_time` datetime DEFAULT NULL COMMENT '支付时间',
  `transaction_id` varchar(64) DEFAULT '' COMMENT '第三方交易号',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`cloud_order_id`),
  UNIQUE KEY `uk_order_sn` (`order_sn`),
  KEY `idx_uid` (`uid`),
  KEY `idx_post` (`post_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='云养月捐订单表';

-- 7. 模块开关配置定义 + 值（systemConfig 需 eb_system_config + eb_system_config_value）
INSERT INTO `eb_system_config`
(`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`, `required`, `info`, `sort`, `user_type`, `status`, `create_time`, `linked_status`, `linked_id`, `linked_value`)
SELECT 70, '流浪动物救助开关', 'animal_rescue_status', 'switch', '', '', 0, '1开启0关闭', 0, 0, 1, NOW(), 0, 0, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'animal_rescue_status');

INSERT INTO `eb_system_config`
(`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`, `required`, `info`, `sort`, `user_type`, `status`, `create_time`, `linked_status`, `linked_id`, `linked_value`)
SELECT 70, '流浪动物救助审核', 'animal_rescue_audit', 'switch', '', '', 0, '1需审核0不需', 0, 0, 1, NOW(), 0, 0, 0
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'animal_rescue_audit');

INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`)
SELECT 'animal_rescue_status', '1', 0 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `eb_system_config_value` WHERE `config_key` = 'animal_rescue_status' AND `mer_id` = 0
);

INSERT INTO `eb_system_config_value` (`config_key`, `value`, `mer_id`)
SELECT 'animal_rescue_audit', '0', 0 FROM DUAL
WHERE NOT EXISTS (
  SELECT 1 FROM `eb_system_config_value` WHERE `config_key` = 'animal_rescue_audit' AND `mer_id` = 0
);

UPDATE `eb_system_config_value` SET `value` = '1' WHERE `config_key` = 'animal_rescue_status' AND `mer_id` = 0;
UPDATE `eb_system_config_value` SET `value` = '0' WHERE `config_key` = 'animal_rescue_audit' AND `mer_id` = 0;
