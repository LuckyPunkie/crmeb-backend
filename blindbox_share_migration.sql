-- =====================================================
-- 盲盒店铺入口归因（普通店分享进盲盒）
-- 日期: 2026-07-23
-- 说明: 用户从哪个普通店铺点进盲盒，记 share_mer_id，供后续分销规则使用
-- 部署: 在业务库执行；已存在列时请跳过对应语句
-- =====================================================

-- 订单：分享来源商户
ALTER TABLE `eb_store_order`
ADD COLUMN `share_mer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分享来源商户ID（普通店盲盒入口归因）';

-- 购物车：加购时带上分享来源
ALTER TABLE `eb_store_cart`
ADD COLUMN `share_mer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '分享来源商户ID（盲盒入口归因）';
