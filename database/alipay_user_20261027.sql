-- 支付宝用户身份表（对标 eb_wechat_user；每个第三方平台各一张表，以后抖音照此建 eb_douyin_user）
-- 执行库：cermb
--
-- 一期只在付款时存身份，不绑账号（uid 先为 0，二期做支付宝登录时再填）：
--   支付宝小程序：付款前 alipay.system.oauth.token 换到的 open_id + union_id
--   App：付款回调里的 buyer_open_id（回调不带 union_id）
-- 同一个人在支付宝小程序和 App 下 open_id 不同，union_id 相同（同主体且已开通 UnionID）

CREATE TABLE IF NOT EXISTS `eb_alipay_user` (
  `alipay_user_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '支付宝用户id',
  `app_id` varchar(32) NOT NULL DEFAULT '' COMMENT '支付宝应用 app_id',
  `user_type` varchar(16) NOT NULL DEFAULT '' COMMENT 'routine=支付宝小程序 app=App',
  `open_id` varchar(64) NOT NULL DEFAULT '' COMMENT '用户在该应用下的 OpenID',
  `union_id` varchar(64) NOT NULL DEFAULT '' COMMENT '同主体各应用通用的 UnionID，回调拿不到时为空',
  `uid` int(10) unsigned NOT NULL DEFAULT 0 COMMENT '绑定的用户 uid，未绑定为 0',
  `nickname` varchar(64) NOT NULL DEFAULT '' COMMENT '昵称（二期授权登录时填）',
  `avatar` varchar(256) NOT NULL DEFAULT '' COMMENT '头像（二期授权登录时填）',
  `create_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`alipay_user_id`),
  UNIQUE KEY `app_open_id` (`app_id`, `open_id`),
  KEY `union_id` (`union_id`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='支付宝用户表';

-- 买单订单记付款的支付宝用户（eb_alipay_user.alipay_user_id），二期按它给游客单补归属
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'eb_nearby_shop_bill_order' AND COLUMN_NAME = 'alipay_user_id');
SET @ddl = IF(@col_exists = 0,
  'ALTER TABLE `eb_nearby_shop_bill_order` ADD COLUMN `alipay_user_id` int(10) unsigned NOT NULL DEFAULT 0 COMMENT ''付款的支付宝用户 eb_alipay_user.alipay_user_id'' AFTER `uid`',
  'SELECT 1');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
