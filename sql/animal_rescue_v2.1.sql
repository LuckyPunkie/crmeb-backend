-- =====================================================
-- 流浪动物救助模块 v2.1 增量迁移
-- 日期: 2026-07-29
-- 说明: 救助站商户 + 拨款审核 + 救助站月捐结算
-- =====================================================

-- 1. 商户表：救助站冗余字段
ALTER TABLE `eb_merchant`
  ADD COLUMN `shelter_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0-非救助站 1-救助站' AFTER `type_id`,
  ADD COLUMN `shelter_certified_at` datetime DEFAULT NULL COMMENT '认证为救助站的时间' AFTER `shelter_status`;

-- 2. 帖子表：商户关联 + 拨款状态
ALTER TABLE `eb_animal_rescue_post`
  ADD COLUMN `mer_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '关联商户ID(救助站)' AFTER `uid`,
  ADD COLUMN `fund_status` tinyint(2) NOT NULL DEFAULT 0 COMMENT '拨款状态:0=不适用 1=进行中 2=待提交凭证 3=审核中 4=待拨款 5=已拨款 6=已退款 -1=审核拒绝' AFTER `status`,
  ADD COLUMN `audit_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '关联拨款审核记录ID' AFTER `fund_status`,
  ADD KEY `idx_mer_id` (`mer_id`),
  ADD KEY `idx_fund_status` (`fund_status`);

-- 3. 参与记录：月捐结算月 + 退款标记
ALTER TABLE `eb_animal_rescue_participant`
  ADD COLUMN `settlement_month` varchar(7) NOT NULL DEFAULT '' COMMENT '结算月份 YYYY-MM' AFTER `status`,
  ADD COLUMN `is_refunded` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0-未退款 1-已退款' AFTER `settlement_month`,
  ADD KEY `idx_settlement_month` (`settlement_month`);

-- 4. 云养/月捐订单：结算月份
ALTER TABLE `eb_cloud_adoption_order`
  ADD COLUMN `settlement_month` varchar(7) NOT NULL DEFAULT '' COMMENT '所属结算月 YYYY-MM' AFTER `is_anonymous`;

-- 5. 拨款审核记录表
CREATE TABLE IF NOT EXISTS `eb_post_fund_audit` (
  `audit_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `post_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '帖子ID',
  `uid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '发布人UID',
  `submitted_at` datetime DEFAULT NULL COMMENT '凭证提交时间',
  `cost_list` text COMMENT '费用清单',
  `invoice_images` text COMMENT '票据图片JSON',
  `other_files` text COMMENT '其他材料JSON',
  `remark` varchar(200) NOT NULL DEFAULT '' COMMENT '发布人备注',
  `actual_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '实际消费金额',
  `refund_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '退款总金额',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0-待审核 1-审核通过 2-审核拒绝',
  `reject_reason` varchar(200) NOT NULL DEFAULT '' COMMENT '拒绝原因',
  `auditor` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '审核人UID',
  `audited_at` datetime DEFAULT NULL COMMENT '审核时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='救助帖拨款审核记录';

-- 6. 月捐结算记录表
CREATE TABLE IF NOT EXISTS `eb_settlement_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `post_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '月捐帖子ID',
  `merchant_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '救助站商户ID',
  `settlement_month` varchar(7) NOT NULL DEFAULT '' COMMENT '结算月份',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '上月捐款总额',
  `transferred_at` datetime DEFAULT NULL COMMENT '转入商家钱包时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1-已结算 2-已提现',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_post_month` (`post_id`,`settlement_month`),
  KEY `idx_merchant` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='救助站月捐结算记录';

-- 7. 店铺类型：救助站（幂等）
INSERT INTO `eb_merchant_type` (`type_name`, `type_info`, `is_margin`, `margin`, `description`, `mark`)
SELECT '救助站', '认证救助站（公益性质免保证金）', 0, 0.00, '平台认证救助站，可发布月捐并享有认证标识', 'shelter'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_merchant_type` WHERE `type_name` = '救助站' OR `mark` = 'shelter');

-- 7.1 拷贝店铺类型菜单权限（与旗舰店一致，避免商户后台无权限）
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`)
SELECT t.mer_type_id, r.right_id, 'mer_auth'
FROM `eb_merchant_type` t
JOIN `eb_relevance` r ON r.type = 'mer_auth' AND r.left_id = (
  SELECT mer_type_id FROM `eb_merchant_type` WHERE `mark` <> 'shelter' ORDER BY mer_type_id ASC LIMIT 1
)
WHERE (t.type_name = '救助站' OR t.mark = 'shelter')
  AND NOT EXISTS (
    SELECT 1 FROM `eb_relevance` x WHERE x.type = 'mer_auth' AND x.left_id = t.mer_type_id LIMIT 1
  );

-- 8. 已有救助类型进行中帖子：初始化 fund_status=1
UPDATE `eb_animal_rescue_post`
SET `fund_status` = 1
WHERE `type` = 1 AND `status` = 1 AND `fund_status` = 0 AND `is_del` = 0;
