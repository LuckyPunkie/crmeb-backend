-- =====================================================
-- 盲盒分享分销 / 免费开盒 / 专属商品
-- 日期: 2026-08-05
-- =====================================================

-- 盲盒店：商家免费开盒中/不中概率（0-100，默认0，全站共用）
ALTER TABLE `eb_merchant`
ADD COLUMN `blindbox_mer_free_win_rate` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '商家分享免费开盒中奖率0-100' AFTER `blindbox_recycle_coupon_num`;

-- 商品：商家专属盲盒（仅分享场景展示，整单不分销）
ALTER TABLE `eb_store_product`
ADD COLUMN `is_blindbox_exclusive` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否商家专属盲盒商品' AFTER `is_show`;

-- 订单：专属盲盒标记
ALTER TABLE `eb_store_order`
ADD COLUMN `is_blindbox_exclusive` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否商家专属盲盒订单(整单不分销)' AFTER `is_blindbox_order`;

-- 每日免费开盒记录
CREATE TABLE IF NOT EXISTS `eb_blindbox_free_open` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL DEFAULT '0',
  `share_mer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分享商家',
  `open_date` date NOT NULL COMMENT '资格日期',
  `is_win` tinyint(1) NOT NULL DEFAULT '0',
  `product_id` int(10) unsigned NOT NULL DEFAULT '0',
  `attr_value_id` int(10) unsigned NOT NULL DEFAULT '0',
  `sku_unique` varchar(64) NOT NULL DEFAULT '',
  `cabinet_id` int(10) unsigned NOT NULL DEFAULT '0',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_uid_mer_date` (`uid`,`share_mer_id`,`open_date`),
  KEY `idx_share_mer` (`share_mer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='盲盒商家分享每日免费开盒';
