-- 用户社交档案：微信号及付费解锁
ALTER TABLE `eb_user_profile`
  ADD COLUMN `wechat_id` varchar(64) NOT NULL DEFAULT '' COMMENT '微信号' AFTER `job_title`,
  ADD COLUMN `wechat_unlock_price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '微信号解锁价格，0=免费公开' AFTER `wechat_id`;

CREATE TABLE IF NOT EXISTS `eb_user_wechat_unlock` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `buyer_uid` int(10) unsigned NOT NULL COMMENT '解锁者',
  `target_uid` int(10) unsigned NOT NULL COMMENT '被查看者',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '订单号',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '支付金额',
  `pay_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=已支付',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_buyer_target` (`buyer_uid`,`target_uid`),
  KEY `idx_target_uid` (`target_uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='微信号解锁记录';
