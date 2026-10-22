-- 订单来源 order_source（与订单类别 is_behalf 用户下单/代客下单 分开）
-- 1 扫码下单（有台号） 2 线上下单 3 线下买单（详情页买单/扫收款码） 4 公益商品 5 盲盒商品 6 商城下单（默认，含平台分红）
-- 常量见 StoreOrderRepository::ORDER_SOURCE_*

ALTER TABLE `eb_store_order`
    ADD COLUMN `order_source` tinyint(1) unsigned NOT NULL DEFAULT 6 COMMENT '订单来源 1扫码下单 2线上下单 3线下买单 4公益商品 5盲盒商品 6商城下单' AFTER `is_behalf`,
    ADD INDEX `idx_order_source` (`order_source`);

-- 历史数据回填（先后顺序即优先级）
UPDATE `eb_store_order` SET `order_source` = 5 WHERE `is_blindbox_order` = 1;
UPDATE `eb_store_order` SET `order_source` = 4 WHERE `is_welfare_order` = 1;
UPDATE `eb_store_order` SET `order_source` = 3 WHERE `remark` = '扫码买单' AND `mark` LIKE '扫码买单:%';
UPDATE `eb_store_order` SET `order_source` = 2 WHERE `is_scan_order` = 2;
UPDATE `eb_store_order` SET `order_source` = 1 WHERE `is_scan_order` = 1;

-- 线下买单订单收货人：「扫码顾客」改为用户昵称
UPDATE `eb_store_order` o JOIN `eb_user` u ON u.uid = o.uid
SET o.real_name = u.nickname
WHERE o.order_source = 3 AND o.real_name = '扫码顾客' AND u.nickname <> '';
UPDATE `eb_store_group_order` g JOIN `eb_store_order` o ON o.group_order_id = g.group_order_id
SET g.real_name = o.real_name
WHERE o.order_source = 3 AND g.real_name = '扫码顾客';
