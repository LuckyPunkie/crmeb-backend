-- 买单页好物推荐 / 公益店铺
-- 执行库：cermb

ALTER TABLE `eb_merchant`
  ADD COLUMN `is_welfare_shop` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否公益店铺(平台开关)' AFTER `is_blindbox`,
  ADD COLUMN `mer_welfare_money` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '公益分销可提现余额' AFTER `mer_money`;

ALTER TABLE `eb_store_product`
  ADD COLUMN `hit_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '公益命中金额' AFTER `price`,
  ADD COLUMN `welfare_commission` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '公益分销金额' AFTER `hit_amount`;

ALTER TABLE `eb_nearby_shop_bill_order`
  ADD COLUMN `bill_scene` varchar(20) NOT NULL DEFAULT 'direct' COMMENT 'direct直接付款/welfare网购享免单' AFTER `pay_type`,
  ADD COLUMN `welfare_product_id` int(11) NOT NULL DEFAULT 0 COMMENT '关联公益商品ID' AFTER `bill_scene`,
  ADD COLUMN `welfare_order_id` int(11) NOT NULL DEFAULT 0 COMMENT '关联公益商品订单ID' AFTER `welfare_product_id`,
  ADD COLUMN `welfare_commission` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '公益分成/分销金额' AFTER `welfare_order_id`,
  ADD COLUMN `scan_mer_id` int(11) NOT NULL DEFAULT 0 COMMENT '扫码商户ID(冗余)' AFTER `welfare_commission`;

ALTER TABLE `eb_store_order`
  ADD COLUMN `is_welfare_order` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否公益免单商品订单' AFTER `is_blindbox_order`,
  ADD COLUMN `welfare_bill_sn` varchar(64) NOT NULL DEFAULT '' COMMENT '关联买单单号' AFTER `is_welfare_order`,
  ADD COLUMN `welfare_bill_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '关联买单消费金额' AFTER `welfare_bill_sn`,
  ADD COLUMN `welfare_scan_mer_id` int(11) NOT NULL DEFAULT 0 COMMENT '扫码商户ID' AFTER `welfare_bill_amount`,
  ADD COLUMN `welfare_commission` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '扫码商户分销金额' AFTER `welfare_scan_mer_id`;

-- 支付完成页 Banner 组合数据组（若不存在则插入）
INSERT INTO `eb_system_group` (`name`, `info`, `group_key`, `fields`, `user_id`, `create_time`)
SELECT '支付完成页Banner', '扫码买单/点单支付完成页三张Banner', 'pay_success_banner',
  '[{"title":"图片","name":"pic","type":"upload","param":""},{"title":"跳转链接","name":"url","type":"input","param":""},{"title":"排序","name":"sort","type":"input","param":""}]',
  0, NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `eb_system_group` WHERE `group_key` = 'pay_success_banner' LIMIT 1);
