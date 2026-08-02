-- 扫码下单（台号 / 配置 / 商品渠道 / 购物车隔离 / 订单台号）
-- 执行库：cermb

CREATE TABLE IF NOT EXISTS `eb_scan_order_table` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT '商户ID',
  `table_label` varchar(20) NOT NULL DEFAULT '' COMMENT '台号文案',
  `qrcode_name` varchar(64) NOT NULL DEFAULT '' COMMENT '二维码附件名',
  `sort` int(11) NOT NULL DEFAULT 0,
  `is_del` tinyint(1) NOT NULL DEFAULT 0,
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mer_del` (`mer_id`,`is_del`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='扫码下单台号';

CREATE TABLE IF NOT EXISTS `eb_scan_order_config` (
  `mer_id` int(11) unsigned NOT NULL COMMENT '商户ID',
  `need_pay` tinyint(1) NOT NULL DEFAULT 1 COMMENT '提交订单是否需付款',
  `voice_enable` tinyint(1) NOT NULL DEFAULT 1 COMMENT '手机端语音播报',
  `auto_print` tinyint(1) NOT NULL DEFAULT 0 COMMENT '自动打印小票',
  `update_time` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`mer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='扫码下单商户配置';

-- 商品：扫码下单渠道可见（默认开启，老商品无需逐个改）
ALTER TABLE `eb_store_product`
  ADD COLUMN `is_scan_order` tinyint(1) NOT NULL DEFAULT 1 COMMENT '扫码下单渠道可见' AFTER `welfare_commission`;

-- 购物车隔离：mall=平台购物车 scan_order=本店购物车
ALTER TABLE `eb_store_cart`
  ADD COLUMN `cart_scene` varchar(20) NOT NULL DEFAULT 'mall' COMMENT 'mall|scan_order' AFTER `mer_id`;

-- 订单台号信息
ALTER TABLE `eb_store_order`
  ADD COLUMN `is_scan_order` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否扫码下单' AFTER `welfare_commission`,
  ADD COLUMN `scan_table_id` int(11) NOT NULL DEFAULT 0 COMMENT '扫码台号ID' AFTER `is_scan_order`,
  ADD COLUMN `scan_table_label` varchar(20) NOT NULL DEFAULT '' COMMENT '扫码台号文案' AFTER `scan_table_id`;
