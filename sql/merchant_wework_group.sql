-- =====================================================
-- 商户/分店企业微信顾客群配置
-- 日期: 2026-07-29
-- 说明: mer_id + branch_id 唯一；branch_id=0 表示总店
-- =====================================================

CREATE TABLE IF NOT EXISTS `eb_merchant_wework_group` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '商户ID',
  `branch_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '0=总店；分店上线后为分店ID',
  `corp_id` varchar(64) NOT NULL DEFAULT '' COMMENT '企业微信CorpID',
  `group_name` varchar(50) NOT NULL DEFAULT '' COMMENT '群名称',
  `group_num` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '群人数',
  `group_last_msg` varchar(100) NOT NULL DEFAULT '' COMMENT '最新消息预览',
  `qrcode_url` varchar(255) NOT NULL DEFAULT '' COMMENT '群活码图片',
  `group_link` varchar(255) NOT NULL DEFAULT '' COMMENT '群活码跳转链接',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1启用 0禁用',
  `create_time` datetime DEFAULT NULL COMMENT '创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mer_branch` (`mer_id`,`branch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商户/分店企业微信顾客群配置';
