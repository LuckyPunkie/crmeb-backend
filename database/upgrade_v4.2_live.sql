-- 由 database/tools/gen_upgrade_v42.py 生成，勿手改；可重复执行
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP PROCEDURE IF EXISTS crmeb_v42_add_col;
DELIMITER $$
CREATE PROCEDURE crmeb_v42_add_col(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col) THEN
    SET @crmeb_sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE crmeb_stmt FROM @crmeb_sql;
    EXECUTE crmeb_stmt;
    DEALLOCATE PREPARE crmeb_stmt;
  END IF;
END$$
DELIMITER ;

-- ========== 1. 新表（官方 v4.1 / v4.2 新增） ==========
CREATE TABLE IF NOT EXISTS `eb_delivery_service_apply` (
  `delivery_service_apply_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '配送员申请ID',
  `uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '申请用户UID',
  `service_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '审核通过后关联的配送员ID',
  `avatar` varchar(250) NOT NULL DEFAULT '' COMMENT '配送员头像',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '配送员姓名',
  `phone` varchar(20) NOT NULL DEFAULT '' COMMENT '配送员手机号',
  `certificate_front` varchar(250) NOT NULL DEFAULT '' COMMENT '证件照正面',
  `certificate_back` varchar(250) NOT NULL DEFAULT '' COMMENT '证件照反面',
  `certificate_images` text NOT NULL COMMENT '证明证书，多个图片地址用英文逗号分隔',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '申请备注',
  `extend` text COMMENT '扩展字段，JSON字符串',
  `status` tinyint(3) NOT NULL DEFAULT '0' COMMENT '审核状态：0待审核，1审核通过，-1审核拒绝',
  `audit_admin_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '审核管理员ID',
  `audit_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '审核说明或拒绝原因',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
  `update_time` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`delivery_service_apply_id`) USING BTREE,
  KEY `idx_uid_status` (`uid`,`status`) USING BTREE,
  KEY `idx_status_create_time` (`status`,`create_time`) USING BTREE,
  KEY `idx_service_id` (`service_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_delivery_service_bill` (
  `delivery_service_bill_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '配送员资金流水ID',
  `bill_sn` varchar(32) NOT NULL DEFAULT '' COMMENT '流水编号',
  `service_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '配送员ID',
  `uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '配送员用户UID',
  `delivery_order_id` int(10) unsigned DEFAULT NULL COMMENT '配送单ID，非配送收入流水可为空',
  `order_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商城订单ID',
  `order_sn` varchar(32) NOT NULL DEFAULT '' COMMENT '商城订单编号',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '订单所属商户ID',
  `delivery_type` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '配送类型：0商户配送，3平台配送',
  `bill_type` varchar(32) NOT NULL DEFAULT 'delivery_income' COMMENT '流水类型：delivery_income配送收入',
  `pm` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '收支类型：0支出，1收入',
  `amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '变动金额',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：-1失效，0待结算，1可提现',
  `title` varchar(64) NOT NULL DEFAULT '' COMMENT '流水标题',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '流水备注',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`delivery_service_bill_id`) USING BTREE,
  UNIQUE KEY `uniq_bill_sn` (`bill_sn`) USING BTREE,
  UNIQUE KEY `uniq_delivery_bill` (`delivery_order_id`,`bill_type`) USING BTREE,
  KEY `idx_service_status_time` (`service_id`,`status`,`create_time`) USING BTREE,
  KEY `idx_uid` (`uid`) USING BTREE,
  KEY `idx_order_id` (`order_id`) USING BTREE,
  KEY `idx_mer_delivery_type` (`mer_id`,`delivery_type`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_delivery_service_withdraw` (
  `delivery_service_withdraw_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT COMMENT '配送员提现申请ID',
  `withdraw_sn` varchar(32) NOT NULL DEFAULT '' COMMENT '提现申请编号',
  `service_id` int(11) NOT NULL DEFAULT '0' COMMENT '配送员身份ID',
  `uid` int(11) NOT NULL DEFAULT '0' COMMENT '配送员用户UID',
  `mer_id` int(11) NOT NULL DEFAULT '0' COMMENT '商户ID，平台配送为0',
  `service_type` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '配送员身份类型：0平台，1商户',
  `amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '申请提现金额',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态：-2已取消，-1已拒绝，0待审核，1待打款，2已打款',
  `payment_method` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '收款方式：0银行卡，1微信，2支付宝',
  `payment_info` text NOT NULL COMMENT '收款信息',
  `payment_sn` varchar(64) NOT NULL DEFAULT '' COMMENT '打款流水号',
  `delivery_service_bill_id` bigint(20) unsigned DEFAULT NULL COMMENT '打款成功后关联的配送员资金流水ID',
  `audit_admin_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '审核管理员ID',
  `audit_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '审核说明或拒绝原因',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '提现备注',
  `audit_time` timestamp NULL DEFAULT NULL COMMENT '审核时间',
  `pay_time` timestamp NULL DEFAULT NULL COMMENT '打款时间',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
  `update_time` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `payment_vouchers` text COMMENT '转账凭证图片，JSON数组',
  PRIMARY KEY (`delivery_service_withdraw_id`) USING BTREE,
  UNIQUE KEY `uniq_withdraw_sn` (`withdraw_sn`) USING BTREE,
  UNIQUE KEY `uniq_delivery_service_bill_id` (`delivery_service_bill_id`) USING BTREE,
  KEY `idx_service_status_time` (`service_id`,`status`,`create_time`) USING BTREE,
  KEY `idx_uid_status_time` (`uid`,`status`,`create_time`) USING BTREE,
  KEY `idx_mer_type_status` (`mer_id`,`service_type`,`status`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_merchant_member` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '会员ID',
  `uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户uid',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商户id',
  `level` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '会员等级',
  `financial_status` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '财务状态:0冻结,1正常',
  `is_logoff` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '是否注销',
  `logoff_time` datetime DEFAULT NULL COMMENT '注销时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_uid_mer_id` (`uid`,`mer_id`) USING BTREE,
  KEY `idx_mer_id_level` (`mer_id`,`level`) USING BTREE,
  KEY `idx_mer_id_logoff` (`mer_id`,`is_logoff`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_merchant_member_benefits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '权益ID',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商户id,0为系统预设',
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '权益名称',
  `selected_icon` varchar(256) NOT NULL DEFAULT '' COMMENT '选中状态图标地址',
  `unselected_icon` varchar(256) NOT NULL DEFAULT '' COMMENT '未选中状态图标地址',
  `link` varchar(256) NOT NULL DEFAULT '' COMMENT '权益跳转链接',
  `sort` smallint(6) NOT NULL DEFAULT '0' COMMENT '排序值，数值越小越靠前',
  `benefit_type` tinyint(3) unsigned DEFAULT NULL COMMENT '权益类型',
  `can_del` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '是否允许删除：0=否，1=是',
  `is_del` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '软删除状态：0=未删除，1=已删除',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_mer_id` (`mer_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_merchant_member_level` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '等级ID',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0',
  `level` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '等级序号，从1开始',
  `name` varchar(64) NOT NULL DEFAULT '' COMMENT '等级名称',
  `threshold_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '升级所需累计消费金额',
  `benefits` varchar(255) NOT NULL DEFAULT '' COMMENT '权益ID列表，多个ID使用英文逗号分隔',
  `coupon_ids` varchar(255) NOT NULL DEFAULT '' COMMENT '入会或升级赠送的优惠券ID列表，多个ID使用英文逗号分隔',
  `discount` decimal(5,2) unsigned NOT NULL DEFAULT '100.00' COMMENT '等级默认折扣百分比',
  `is_del` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '软删除状态：0=未删除，1=已删除',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mer_level` (`mer_id`,`level`) USING BTREE,
  KEY `idx_mer_id` (`mer_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_merchant_member_level_record` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户uid',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商户id',
  `before_level` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '变更前等级',
  `after_level` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '变更后等级',
  `change_type` varchar(32) NOT NULL DEFAULT '' COMMENT '变更类型:join,consume,merchant,delete',
  `change_message` varchar(255) NOT NULL DEFAULT '' COMMENT '变更说明',
  `reward_coupon_ids` varchar(255) NOT NULL DEFAULT '' COMMENT '应发优惠券快照',
  `rewarded_coupon_ids` varchar(255) NOT NULL DEFAULT '' COMMENT '已发优惠券',
  `reward_status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '奖励状态:0无奖励,1待发,2已完成',
  `reward_attempts` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '奖励发放尝试次数',
  `reward_error` varchar(500) NOT NULL DEFAULT '' COMMENT '最近一次发放错误',
  `reward_time` datetime DEFAULT NULL COMMENT '奖励完成时间',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_uid_mer_id` (`uid`,`mer_id`) USING BTREE,
  KEY `idx_mer_id_level` (`mer_id`,`after_level`) USING BTREE,
  KEY `idx_reward_status_id` (`reward_status`,`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_merchant_member_product_price` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0',
  `product_id` int(10) unsigned NOT NULL DEFAULT '0',
  `value_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商品规格属性值ID',
  `level` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '适用会员等级',
  `price` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '该等级对应的商品规格会员价',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_member_product_price` (`mer_id`,`product_id`,`value_id`,`level`) USING BTREE,
  KEY `idx_member_product` (`mer_id`,`product_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_shopping_credits_merchant_money_record` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0',
  `link_no` varchar(64) NOT NULL DEFAULT '' COMMENT '关联业务单号',
  `link_type` varchar(48) NOT NULL DEFAULT '' COMMENT '关联业务类型',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '商户余额变动，正数增加、负数扣减',
  `balance` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '变动后商户余额',
  `title` varchar(128) NOT NULL DEFAULT '' COMMENT '流水标题',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_mer_link` (`mer_id`,`link_type`,`link_no`) USING BTREE,
  KEY `idx_mer_id` (`mer_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_shopping_credits_order` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0',
  `uid` int(10) unsigned NOT NULL DEFAULT '0',
  `order_no` varchar(32) NOT NULL DEFAULT '' COMMENT '购物金充值订单号',
  `recharge_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '用户实际支付的充值本金金额',
  `gift_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '充值套餐赠送的购物金金额',
  `pay_type` varchar(32) NOT NULL DEFAULT '' COMMENT '支付方式',
  `pay_channel` varchar(20) NOT NULL DEFAULT '' COMMENT '支付客户端渠道',
  `paid` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '支付状态：0=未支付，1=已支付',
  `pay_time` datetime DEFAULT NULL COMMENT '支付完成时间',
  `out_trade_no` varchar(64) NOT NULL DEFAULT '' COMMENT '支付服务方订单号',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_wechat_shipping` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `refund_status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0未退款,1申请中,2已退款',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_order_no` (`order_no`) USING BTREE,
  KEY `idx_mer_uid` (`mer_id`,`uid`) USING BTREE,
  KEY `idx_pay_type` (`pay_type`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_shopping_credits_package` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '购物金套餐ID',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0',
  `recharge_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '套餐充值本金金额',
  `gift_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '套餐赠送购物金金额',
  `sort` smallint(6) NOT NULL DEFAULT '0',
  `show_status` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_mer_id` (`mer_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_shopping_credits_refund_order` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `refund_order_no` varchar(32) NOT NULL COMMENT '购物金退款单号',
  `order_no` varchar(32) NOT NULL COMMENT '原购物金充值订单号',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0',
  `uid` int(10) unsigned NOT NULL DEFAULT '0',
  `refund_reason` varchar(255) NOT NULL DEFAULT '',
  `is_all` tinyint(3) unsigned NOT NULL DEFAULT '1',
  `refund_status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '0待审核,1拒绝,2已退款,4撤销',
  `refusing_refund_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '用户提交的退款原因',
  `refund_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '退还的充值本金金额',
  `refund_gift_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '退款时收回的赠送购物金金额',
  `audit_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '审核管理员ID',
  `audit_type` tinyint(4) NOT NULL DEFAULT '0' COMMENT '审核结果：-1=拒绝，0=待审核，1=同意',
  `audit_time` datetime DEFAULT NULL COMMENT '审核时间',
  `refund_time` datetime DEFAULT NULL,
  `refund_pay_type` varchar(20) NOT NULL DEFAULT '',
  `mer_remark` varchar(512) NOT NULL DEFAULT '',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_refund_order_no` (`refund_order_no`) USING BTREE,
  KEY `idx_mer_status` (`mer_id`,`refund_status`) USING BTREE,
  KEY `idx_order_no` (`order_no`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_store_product_card_rule` (
  `card_rule_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '卡规则ID',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商户ID',
  `product_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商品ID',
  `value_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商品规格ID store_product_attr_value.value_id',
  `unique` char(12) NOT NULL DEFAULT '' COMMENT '商品规格唯一值',
  `use_limit_type` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '核销次数类型:1固定次数,2有效期内不限次数',
  `total_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '可核销总次数,不限次数为0',
  `validity_type` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '有效期类型:1永久有效,2购买后N天有效,3固定日期范围',
  `valid_days` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '购买后N天有效',
  `valid_start_time` date DEFAULT NULL COMMENT '固定有效期开始时间',
  `valid_end_time` date DEFAULT NULL COMMENT '固定有效期结束时间',
  `refund_rule` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '退款规则:0不支持退款,1未使用可退,2按剩余次数退',
  `service_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '服务方式(1:到店服务,2:上门服务,3:上门+到店服务)',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '状态:0关闭,1开启',
  `is_del` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '是否删除',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`card_rule_id`) USING BTREE,
  UNIQUE KEY `uk_value_id` (`value_id`) USING BTREE,
  KEY `idx_product_id` (`product_id`) USING BTREE,
  KEY `idx_mer_id` (`mer_id`) USING BTREE,
  KEY `idx_unique` (`unique`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='次卡/年卡商品规格规则表';
CREATE TABLE IF NOT EXISTS `eb_store_user_card` (
  `user_card_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户卡ID',
  `card_no` varchar(32) NOT NULL COMMENT '用户卡号',
  `verify_code` char(16) NOT NULL COMMENT '核销码',
  `uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商户ID',
  `card_rule_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '卡规则ID',
  `product_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商品ID',
  `value_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商品规格ID',
  `unique` char(12) NOT NULL DEFAULT '' COMMENT '商品规格唯一值',
  `order_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '订单ID',
  `order_product_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '订单商品ID',
  `order_sn` varchar(32) NOT NULL DEFAULT '' COMMENT '订单号',
  `product_name` varchar(128) NOT NULL DEFAULT '' COMMENT '商品名称快照',
  `sku_name` varchar(128) NOT NULL DEFAULT '' COMMENT '规格名称快照',
  `image` varchar(256) NOT NULL DEFAULT '' COMMENT '商品图片快照',
  `use_limit_type` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '核销次数类型:1固定次数,2有效期内不限次数',
  `total_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '可核销总次数',
  `used_count` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '已核销次数',
  `remaining_count` int(11) NOT NULL DEFAULT '0' COMMENT '剩余次数,-1表示不限次数',
  `validity_type` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '有效期类型:1永久有效,2购买后N天有效,3固定日期范围',
  `valid_start_time` date DEFAULT NULL COMMENT '有效期开始时间',
  `valid_end_time` date DEFAULT NULL COMMENT '有效期结束时间',
  `service_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '服务方式快照(1:到店服务,2:上门服务,3:上门+到店服务)',
  `status` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '状态:1可用,2已用完,3已过期,4已退款作废',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`user_card_id`) USING BTREE,
  UNIQUE KEY `uk_card_no` (`card_no`) USING BTREE,
  UNIQUE KEY `uk_verify_code` (`verify_code`) USING BTREE,
  KEY `idx_uid` (`uid`) USING BTREE,
  KEY `idx_mer_id` (`mer_id`) USING BTREE,
  KEY `idx_order_id` (`order_id`) USING BTREE,
  KEY `idx_order_product_id` (`order_product_id`) USING BTREE,
  KEY `idx_product_id` (`product_id`) USING BTREE,
  KEY `idx_status_valid_time` (`status`,`valid_end_time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='用户次卡/年卡资产表';
CREATE TABLE IF NOT EXISTS `eb_store_user_card_verify_log` (
  `verify_log_id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '核销记录ID',
  `user_card_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户卡ID',
  `card_no` varchar(32) NOT NULL DEFAULT '' COMMENT '用户卡号',
  `uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商户ID',
  `order_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '订单ID',
  `order_product_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '订单商品ID',
  `verify_count` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '本次核销次数',
  `before_count` int(11) NOT NULL DEFAULT '0' COMMENT '核销前剩余次数,-1表示不限次数',
  `after_count` int(11) NOT NULL DEFAULT '0' COMMENT '核销后剩余次数,-1表示不限次数',
  `service_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '核销员工/客服ID',
  `admin_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '后台管理员ID',
  `verify_type` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '核销方式:1扫码,2手动输入,3后台',
  `service_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '本次核销服务方式(1:到店服务,2:上门服务)',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '核销时间',
  PRIMARY KEY (`verify_log_id`) USING BTREE,
  KEY `idx_user_card_id` (`user_card_id`) USING BTREE,
  KEY `idx_card_no` (`card_no`) USING BTREE,
  KEY `idx_uid` (`uid`) USING BTREE,
  KEY `idx_mer_id` (`mer_id`) USING BTREE,
  KEY `idx_order_id` (`order_id`) USING BTREE,
  KEY `idx_create_time` (`create_time`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='用户次卡/年卡核销记录表';
CREATE TABLE IF NOT EXISTS `eb_user_shopping_credits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT '用户购物金账户ID',
  `uid` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '用户ID',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '商户ID',
  `recharge_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '可用充值本金余额',
  `gift_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '可用赠送购物金余额',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `uk_uid_mer_id` (`uid`,`mer_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `eb_user_shopping_credits_record` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` int(10) unsigned NOT NULL DEFAULT '0',
  `mer_id` int(10) unsigned NOT NULL DEFAULT '0',
  `link_no` varchar(64) NOT NULL DEFAULT '' COMMENT '关联业务单号',
  `link_type` varchar(32) NOT NULL DEFAULT '' COMMENT '关联业务类型，如recharge、refund、order、order_refund、merchant_adjust',
  `type` tinyint(3) unsigned NOT NULL DEFAULT '1' COMMENT '1增加,2扣减',
  `title` varchar(64) NOT NULL DEFAULT '' COMMENT '流水标题',
  `recharge_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '充值本金变动金额',
  `gift_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '赠送购物金变动金额',
  `balance_recharge_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '变动后本金余额',
  `balance_gift_amount` decimal(10,2) unsigned NOT NULL DEFAULT '0.00' COMMENT '变动后赠送余额',
  `remark` varchar(255) NOT NULL DEFAULT '',
  `create_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `update_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `idx_uid_mer` (`uid`,`mer_id`) USING BTREE,
  KEY `idx_link_no` (`link_no`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== 2. 已有表新增字段 ==========
CALL crmeb_v42_add_col('eb_circle', 'mer_product_type', '`mer_product_type` varchar(20) DEFAULT NULL COMMENT \'商户的商品类型\' AFTER `business_store_type`');
CALL crmeb_v42_add_col('eb_circle', 'platform_category', '`platform_category` varchar(500) DEFAULT NULL COMMENT \'商户的平台商品分类\' AFTER `mer_product_type`');
CALL crmeb_v42_add_col('eb_circle_agent', 'mer_product_type', '`mer_product_type` varchar(20) DEFAULT NULL COMMENT \'商户的平台商品分类\' AFTER `business_store_type`');
CALL crmeb_v42_add_col('eb_circle_agent', 'platform_category', '`platform_category` varchar(500) DEFAULT NULL COMMENT \'商户的商品类型\' AFTER `mer_product_type`');
CALL crmeb_v42_add_col('eb_delivery_config', 'night_premium_config', '`night_premium_config` text COMMENT \'夜间加价配置\' AFTER `weight_premium_config`');
CALL crmeb_v42_add_col('eb_delivery_order', 'pickup_images', '`pickup_images` text NOT NULL COMMENT \'到店取货图片，多个图片地址用英文逗号分隔\' AFTER `service_fee`');
CALL crmeb_v42_add_col('eb_delivery_order', 'confirm_images', '`confirm_images` text NOT NULL COMMENT \'确认送达图片，多个图片地址用英文逗号分隔\' AFTER `pickup_images`');
CALL crmeb_v42_add_col('eb_delivery_order', 'settlement_fee', '`settlement_fee` decimal(10,2) DEFAULT NULL COMMENT \'配送成本\' AFTER `confirm_images`');
CALL crmeb_v42_add_col('eb_delivery_order', 'pickup_time', '`pickup_time` timestamp NULL DEFAULT NULL COMMENT \'取货时间\' AFTER `settlement_fee`');
CALL crmeb_v42_add_col('eb_delivery_order', 'confirm_time', '`confirm_time` timestamp NULL DEFAULT NULL COMMENT \'确认送达时间\' AFTER `pickup_time`');
CALL crmeb_v42_add_col('eb_delivery_service', 'is_open', '`is_open` tinyint(3) unsigned DEFAULT \'1\' COMMENT \'账号状态\' AFTER `status`');
CALL crmeb_v42_add_col('eb_delivery_service', 'certificate_front', '`certificate_front` varchar(250) DEFAULT NULL COMMENT \'证件照正面\' AFTER `sort`');
CALL crmeb_v42_add_col('eb_delivery_service', 'certificate_back', '`certificate_back` varchar(250) DEFAULT NULL COMMENT \'证件照反面\' AFTER `certificate_front`');
CALL crmeb_v42_add_col('eb_delivery_service', 'certificate_images', '`certificate_images` text COMMENT \'证书证明\' AFTER `certificate_back`');
CALL crmeb_v42_add_col('eb_delivery_service', 'total_amount', '`total_amount` decimal(10,2) unsigned NOT NULL DEFAULT \'0.00\' COMMENT \'累计配送收入\' AFTER `certificate_images`');
CALL crmeb_v42_add_col('eb_delivery_service', 'available_amount', '`available_amount` decimal(10,2) unsigned NOT NULL DEFAULT \'0.00\' COMMENT \'可提现配送费\' AFTER `total_amount`');
CALL crmeb_v42_add_col('eb_delivery_service', 'frozen_amount', '`frozen_amount` decimal(10,2) unsigned NOT NULL DEFAULT \'0.00\' COMMENT \'提现申请处理中\' AFTER `available_amount`');
CALL crmeb_v42_add_col('eb_delivery_service', 'withdrawn_amount', '`withdrawn_amount` decimal(10,2) unsigned NOT NULL DEFAULT \'0.00\' COMMENT \'已提现配送费\' AFTER `frozen_amount`');
CALL crmeb_v42_add_col('eb_merchant', 'mer_start', '`mer_start` tinyint(2) NOT NULL DEFAULT \'3\' COMMENT \'推荐星级\' AFTER `applyment_switch`');
CALL crmeb_v42_add_col('eb_merchant', 'mer_product_type', '`mer_product_type` varchar(20) DEFAULT NULL COMMENT \'店铺可以用的商品类型:普通,卡密等\' AFTER `mer_start`');
CALL crmeb_v42_add_col('eb_merchant', 'platform_category', '`platform_category` varchar(500) DEFAULT NULL COMMENT \'商户可以用的平台商品分类\' AFTER `mer_product_type`');
CALL crmeb_v42_add_col('eb_merchant_intention', 'platform_category', '`platform_category` varchar(500) DEFAULT NULL COMMENT \'商户的平台商品分类\' AFTER `business_id`');
CALL crmeb_v42_add_col('eb_merchant_intention', 'mer_product_type', '`mer_product_type` varchar(20) DEFAULT NULL COMMENT \'商户的商品类型\' AFTER `platform_category`');
CALL crmeb_v42_add_col('eb_parameter_template', 'required', '`required` int(10) unsigned NOT NULL DEFAULT \'0\' COMMENT \'是否必填\' AFTER `create_time`');
CALL crmeb_v42_add_col('eb_serve_order', 'transaction_id', '`transaction_id` varchar(50) DEFAULT NULL COMMENT \'支付单号\' AFTER `pay_time`');
CALL crmeb_v42_add_col('eb_store_activity_cate', 'is_sys', '`is_sys` tinyint(2) DEFAULT \'0\' COMMENT \'系统自带\' AFTER `create_time`');
CALL crmeb_v42_add_col('eb_store_activity_label', 'start_time', '`start_time` timestamp NULL DEFAULT NULL COMMENT \'标签开始时间\' AFTER `sort`');
CALL crmeb_v42_add_col('eb_store_activity_label', 'end_time', '`end_time` timestamp NULL DEFAULT NULL COMMENT \'标签结束时间\' AFTER `start_time`');
CALL crmeb_v42_add_col('eb_store_order', 'merchant_member_discount_price', '`merchant_member_discount_price` decimal(10,2) unsigned NOT NULL DEFAULT \'0.00\' COMMENT \'店铺会员优惠金额\' AFTER `svip_discount`');
CALL crmeb_v42_add_col('eb_store_order', 'city_delivery_type', '`city_delivery_type` int(10) unsigned DEFAULT \'0\' COMMENT \'同城配送类型: 0 商家 1 达达 2 uu 3 平台\' AFTER `merchant_take_info`');
CALL crmeb_v42_add_col('eb_store_order', 'delivery_grab_status', '`delivery_grab_status` int(10) unsigned DEFAULT \'0\' COMMENT \'0 不支持配送员抢单\\n1 支持配送员抢单\' AFTER `city_delivery_type`');
CALL crmeb_v42_add_col('eb_store_order', 'delivery_settlement_fee', '`delivery_settlement_fee` decimal(10,2) unsigned DEFAULT NULL COMMENT \'实际配送成本\' AFTER `delivery_grab_status`');
CALL crmeb_v42_add_col('eb_store_order', 'delivery_service_fee', '`delivery_service_fee` decimal(10,2) DEFAULT \'0.00\' COMMENT \'配送员可得配送提成\' AFTER `delivery_settlement_fee`');
CALL crmeb_v42_add_col('eb_store_order_product', 'merchant_member_discount_price', '`merchant_member_discount_price` decimal(10,2) unsigned NOT NULL DEFAULT \'0.00\' COMMENT \'店铺会员优惠金额\' AFTER `svip_discount`');
CALL crmeb_v42_add_col('eb_store_product', 'member_price_setting', '`member_price_setting` tinyint(3) unsigned NOT NULL DEFAULT \'0\' COMMENT \'0不设置会员价 1付费会员/SVIP 价格 2店铺会员价格\' AFTER `svip_price_type`');
CALL crmeb_v42_add_col('eb_store_product', 'is_price_negotiable', '`is_price_negotiable` tinyint(1) unsigned NOT NULL DEFAULT \'0\' COMMENT \'是否价格面议：0否，1是\' AFTER `activity_label_ids`');
CALL crmeb_v42_add_col('eb_store_product', 'price_negotiable_text', '`price_negotiable_text` varchar(255) DEFAULT NULL COMMENT \'价格面议其他配置内容\' AFTER `is_price_negotiable`');
CALL crmeb_v42_add_col('eb_system_attachment', 'attachment_cover', '`attachment_cover` varchar(200) DEFAULT NULL COMMENT \'视频封面图\' AFTER `attachment_type`');
CALL crmeb_v42_add_col('eb_user_recharge', 'status', '`status` tinyint(3) DEFAULT NULL COMMENT \'0:默认,1一支付,-1:已退款\' AFTER `refund_price`');
CALL crmeb_v42_add_col('eb_user_recharge', 'transaction_id', '`transaction_id` varchar(50) DEFAULT NULL COMMENT \'支付单号\' AFTER `status`');
ALTER TABLE `eb_delivery_order` MODIFY COLUMN `service_fee` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '服务费';
UPDATE `eb_user_recharge` SET `status` = 1 WHERE `paid` = 1 AND `status` IS NULL;

-- ========== 3. 系统配置分类 / 配置项 ==========
INSERT INTO `eb_system_config_classify` (`config_classify_id`, `pid`, `classify_name`, `classify_key`, `info`, `sort`, `create_time`, `icon`, `status`) SELECT '114', '0', '配送提现', 'mer_withdraw', '', '0', '2026-08-03 17:15:06', '', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config_classify` WHERE `classify_key` = 'mer_withdraw');
UPDATE `eb_system_config_classify` SET `pid` = '0' WHERE `config_classify_id` = 94 AND `pid` = '91';
INSERT INTO `eb_system_config` (`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`, `required`, `info`, `sort`, `user_type`, `status`, `create_time`, `linked_status`, `linked_id`, `linked_value`) SELECT (SELECT `config_classify_id` FROM `eb_system_config_classify` WHERE `classify_key` = 'tab_merchant' LIMIT 1), '店铺商品分类限制', 'category_number_count', 'number', '', 'min=0\ndefaultValue=5', '0', '商户/店铺入驻时可选择的经营商品分类最大数量，填写0代表不限制', '0', '0', '1', '2026-06-23 14:41:15', '0', '0', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'category_number_count');
INSERT INTO `eb_system_config` (`config_classify_id`, `config_name`, `config_key`, `config_type`, `config_rule`, `config_props`, `required`, `info`, `sort`, `user_type`, `status`, `create_time`, `linked_status`, `linked_id`, `linked_value`) SELECT (SELECT `config_classify_id` FROM `eb_system_config_classify` WHERE `classify_key` = 'mer_withdraw' LIMIT 1), '提现方式', 'mer_withdraw_type', 'checkbox', '0:银行卡\n1:微信\n2:支付宝', 'defaultValue = \'1\'', '0', '以上方式均需要手动转账, 全未选择默认使用微信', '0', '1', '1', '2026-08-03 17:16:33', '0', '0', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_config` WHERE `config_key` = 'mer_withdraw_type');
UPDATE `eb_system_config` SET `info` = '填写支付宝官方提供的支付宝公钥，用于接口签名验证，保障支付数据安全与合法性，请从支付宝开放平台准确复制。' WHERE `config_key` = 'alipay_public_key' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '设置用户单次充值时允许的最低金额，低于该金额将无法提交充值。' WHERE `config_key` = 'store_user_min_recharge' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '模糊搜索系统根据输入的关键词进行模糊匹配查询，只要商品名称、标题、备注等字段中包含该关键词片段，即可被检索出来，匹配范围更广，容错性更强；分词搜索系统先将输入的内容按语义拆分为多个独立关键词，再对拆分后的词语进行匹配检索，搜索结果更精准，能有效过滤无关内容，提升检索相关性' WHERE `config_key` = 'vic_word_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '上传小程序授权 LOGO 图片，建议使用正方形高清图，无拉伸、无水印，格式支持 JPG/PNG。' WHERE `config_key` = 'routine_logo' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '设置后台管理系统登录页面的标题名称，将显示在浏览器标签与登录页顶部。' WHERE `config_key` = 'sys_login_title' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '选择允许评论帖子的用户类型' WHERE `config_key` = 'community_reply_auth' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = 'AccessKeyId（访问密钥 ID）是阿里云为用户 / 子账号分配的公开唯一标识符，用于 API/SDK/CLI 调用时标识身份；必须与AccessKey Secret（密钥密文）配对，才能完成签名验证' WHERE `config_key` = 'aliyun_AccessKeyId' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启将对上传图片进行压缩，节省空间并加快加载；关闭则保留原图' WHERE `config_key` = 'jdoss_thumb_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '商家位置定位、商城消费者位置定位需使用腾讯地图，申请并填写腾讯地图KEY' WHERE `config_key` = 'tx_map_key' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '选择图片、视频等文件的存储类型，支持本地存储及多家云存储服务。' WHERE `config_key` = 'upload_type' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启后将启用会员等级、会员折扣、会员权益等相关功能' WHERE `config_key` = 'member_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '设置开屏广告自动关闭时间，开屏广告页面会根据此时间设置展示倒计时。' WHERE `config_key` = 'open_screen_time' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写平台网站名称，用于访问过程网站名称展示' WHERE `config_key` = 'site_name' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启将对上传图片进行压缩，节省空间并加快加载；关闭则保留原图' WHERE `config_key` = 'local_thumb_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启将对上传图片进行压缩，节省空间并加快加载；关闭则保留原图' WHERE `config_key` = 'tengxun_thumb_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启后，保证金不足时自动向商户发送补缴提醒' WHERE `config_key` = 'margin_remind_switch' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '选择新用户注册赠送的优惠券' WHERE `config_key` = 'register_give_coupon' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '短信发送方的身份标识，显示为【XX】，用于识别企业 / 品牌 / 业务。' WHERE `config_key` = 'aliyun_SignName' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写微信支付服务商平台分配的商户 ID，用于服务商支付接口配置' WHERE `config_key` = 'wechat_service_merid' AND `info` <=> '';
UPDATE `eb_system_config` SET `sort` = '100' WHERE `config_key` = 'wechat_service_merid' AND `sort` <=> '0';
UPDATE `eb_system_config` SET `info` = '开启后新用户赠送会赠送积分，关闭后则不赠送' WHERE `config_key` = 'register_integral_status' AND `info` <=> '0:关闭\n1:开启';
UPDATE `eb_system_config` SET `info` = '上传微信服务号官方二维码图片，用于扫码关注与账号绑定；建议使用公众号后台下载的高清二维码，格式为 JPG/PNG。' WHERE `config_key` = 'wechat_qrcode' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启后可以根据订单打印小票，关闭则不可打印' WHERE `config_key` = 'printing_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启之后，店铺创建直播商品无需平台审核，直接由微信进行审核；关闭后，店铺创建直播商品由平台审核通过再交给微信进行审核' WHERE `config_key` = 'broadcast_goods_type' AND `info` <=> '是否开启直播商品免审核';
UPDATE `eb_system_config` SET `info` = '设置注册有礼弹窗，点击后跳转的页面' WHERE `config_key` = 'register_popup_url' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '关闭后，商城不展示热卖排行' WHERE `config_key` = 'hot_ranking_switch' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '用于图片、JS、CSS 等静态资源加速，提升访问速度。' WHERE `config_key` = 'jdoss_cdn' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启后新用户赠送会赠送优惠券，关闭后则不赠送' WHERE `config_key` = 'register_coupon_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '启用注册有礼活动，新用户完成注册自动发放奖励' WHERE `config_key` = 'newcomer_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启之后支持使用电子面单打单发货，关闭则不支持电子面单' WHERE `config_key` = 'mer_dump_switch' AND `info` <=> '电子面单打印功能是否开启';
UPDATE `eb_system_config` SET `info` = '开启之后支持店铺使用电子面单打单发货，关闭则不支持电子面单' WHERE `config_key` = 'crmeb_serve_dump' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '关闭后则本平台不支持同城配送' WHERE `config_key` = 'delivery_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启后支持用户下单后申请发票，关闭则隐藏发票功能' WHERE `config_key` = 'mer_open_receipt' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '店铺资质上传必传开启后，开店必须上传本店铺的营业执照相关资质信息，店铺上传的资质信息会展示在商城店铺详情信息中' WHERE `config_key` = 'sys_bases_status' AND `info` <=> '不选时，默认必填';
UPDATE `eb_system_config` SET `info` = '填写支付宝开放平台为应用分配的唯一标识（app_id），用于支付接口调用、权限校验与数据隔离。需在开放平台创建应用并审核通过后获取，格式为 16–18 位数字。' WHERE `config_key` = 'alipay_app_id' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '打开移动端商城，系统会先展示开屏广告再进入商城，频繁打开不会反复展示。' WHERE `config_key` = 'open_screen_switch' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写小程序唯一标识 AppId，用于接口鉴权与身份验证。' WHERE `config_key` = 'routine_appId' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '设置自提点每日营业时间段，用户仅可在此时段内到店自提，请按实际营业时间填写。' WHERE `config_key` = 'mer_take_time' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '输入新用户注册赠送的积分数量，支持正整数' WHERE `config_key` = 'register_give_integral' AND `info` <=> '';
UPDATE `eb_system_config` SET `config_rule` = '' WHERE `config_key` = 'platform_intervention' AND `config_rule` <=> NULL;
UPDATE `eb_system_config` SET `info` = '开启后，用户退款申请被商家拒绝之后，用户可以向平台申请售后维权，平台支持退款/拒绝操作；关闭后，则用户退款申请被商家拒绝之后，用户不可向平台进行维权申请' WHERE `config_key` = 'platform_intervention' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '配置数据大屏商品排行类型，可按销量/销售额切换' WHERE `config_key` = 'sys_pay_product_rank_type' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '免审核则用户发布图文帖子内容自动免人工审核，选择审核则需审核通过后展示' WHERE `config_key` = 'community_audit' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写当前版本号' WHERE `config_key` = 'appVersion' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '上传新用户注册有礼弹窗，在新人注册后会进行弹窗展示' WHERE `config_key` = 'register_popup_pic' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '小程序官方密钥，用于接口权限验证，请妥善保管，切勿泄露。' WHERE `config_key` = 'routine_appsecret' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启之后，店铺创建直播间无需平台审核，直接由微信进行审核；关闭后，店铺创建直播间由平台审核通过再交给微信进行审核' WHERE `config_key` = 'broadcast_room_type' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '请填写用户充值前需知晓的规则、限制及温馨提示，内容将在充值页面展示。' WHERE `config_key` = 'recharge_attention' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启积分功能，用户下单、签到可获得积分并用于抵扣 / 兑换' WHERE `config_key` = 'integral_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写微信服务号原始 ID（gh_开头），在公众号后台【设置与开发→基本设置→注册信息】中复制，不可修改。' WHERE `config_key` = 'wechat_sourceid' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '选择同城配送支持的第三方平台' WHERE `config_key` = 'delivery_type' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '用于商城分享链接简介说明设置' WHERE `config_key` = 'share_info' AND `info` <=> '';
UPDATE `eb_system_config` SET `config_name` = '店铺排行数据时间' WHERE `config_key` = 'sys_pay_merchant_rank' AND `config_name` <=> '商户排行数据时间';
UPDATE `eb_system_config` SET `info` = '配置数据大屏店铺排行展示周期，可按今日 / 本周 / 本月切换' WHERE `config_key` = 'sys_pay_merchant_rank' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启将对上传图片进行压缩，节省空间并加快加载；关闭则保留原图' WHERE `config_key` = 'uc_thumb_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启将对上传图片进行压缩，节省空间并加快加载；关闭则保留原图' WHERE `config_key` = 'obs_thumb_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '设置常用退款理由，用户提交退款申请时，方便快速选择' WHERE `config_key` = 'refund_message' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '用于微信接口验证的令牌，需与微信公众平台后台填写一致；支持 3-32 位字母 / 数字组合，请勿包含空格与特殊字符微信开放社区' WHERE `config_key` = 'wechat_token' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '选择审核则用户评论需经审核通过后展示，免审核则直接发布' WHERE `config_key` = 'community_reply_audit' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '选择允许发布帖子的用户类型' WHERE `config_key` = 'community_auth' AND `info` <=> '';
UPDATE `eb_system_config` SET `config_name` = '店铺排行数据类型' WHERE `config_key` = 'sys_pay_merchant_rank_type' AND `config_name` <=> '商户排行显示数据';
UPDATE `eb_system_config` SET `info` = '配置数据大屏店铺排行类型，可按销量/销售额切换' WHERE `config_key` = 'sys_pay_merchant_rank_type' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启将对上传图片进行压缩，节省空间并加快加载；关闭则保留原图' WHERE `config_key` = 'ctoss_thumb_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启后新用户赠送会赠送余额，关闭后则不赠送' WHERE `config_key` = 'register_money_status' AND `info` <=> '0:关闭\n1:开启';
UPDATE `eb_system_config` SET `info` = '用于图片、JS、CSS 等静态资源加速，提升访问速度。' WHERE `config_key` = 'ctoss_cdn' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '设置自提点营业日期，用户下单时将根据营业时间判断是否可选择门店自提，请准确填写营业时段。' WHERE `config_key` = 'mer_take_day' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写公众号名称，请真实填写，方便H5与公众号关联' WHERE `config_key` = 'wechat_name' AND `info` <=> '';
UPDATE `eb_system_config` SET `linked_id` = '561' WHERE `config_key` = 'wechat_service_name' AND `linked_id` <=> '562';
UPDATE `eb_system_config` SET `info` = '微信服务号唯一标识 AppID，用于接口授权验证。' WHERE `config_key` = 'wechat_appid' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '关闭之后除了平台管理端之外，店铺以及移动端不允许访问' WHERE `config_key` = 'site_open' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '配置数据大屏商品排行展示周期，可按今日 / 本周 / 本月切换' WHERE `config_key` = 'sys_pay_product_rank' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '用户申请退货时，店铺售后咨询电话' WHERE `config_key` = 'set_phone' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写支付接口密钥，用于支付数据签名与安全校验，从支付平台后台获取，请妥善保管，严禁泄露。' WHERE `config_key` = 'alipay_private_key' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '上传微信服务号官方 LOGO 图片，建议尺寸正方形、清晰无拉伸，支持 JPG/PNG 格式。' WHERE `config_key` = 'wechat_avatar' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写自提点名称，将在用户下单选择门店自提时展示，请填写清晰易识别的名称。' WHERE `config_key` = 'mer_take_name' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '用于商城分享链接标题设置' WHERE `config_key` = 'share_title' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '用于网站流量、访客行为统计的第三方代码（如百度统计、CNZZ 等），粘贴至此处生效' WHERE `config_key` = 'static_script' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写平台管理端的网站域名，例如：https://mer.crmeb.net' WHERE `config_key` = 'site_url' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启后，用户下单时可选择门店自提配送方式；需提前配置自提点地址、营业时间、联系电话等信息。' WHERE `config_key` = 'mer_take_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启将对上传图片进行压缩，节省空间并加快加载；关闭则保留原图' WHERE `config_key` = 'qiniu_thumb_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '输入新用户注册赠送的余额，支持两位小数' WHERE `config_key` = 'register_give_money' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写微信服务号开发者后台的 AppSecret，用于接口权限验证，请妥善保管，切勿泄露。' WHERE `config_key` = 'wechat_appsecret' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写分销规则、佣金说明、结算方式及注意事项，将展示给分销商查看。' WHERE `config_key` = 'promoter_explain' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '支持输入压缩比例（1%～100%）' WHERE `config_key` = 'thumb_rate' AND `info` <=> '（1%～100%）';
UPDATE `eb_system_config` SET `info` = '开启将对上传图片进行压缩，节省空间并加快加载；关闭则保留原图' WHERE `config_key` = 'thumb_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写店铺经度、纬度坐标，用于地图定位、门店自提导航与配送范围计算。可通过地图工具获取，确保坐标精准。' WHERE `config_key` = 'mer_take_location' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '微信支付服务商 APIv3 密钥，用于接口签名加密，请妥善保管，严禁泄露' WHERE `config_key` = 'wechat_service_v3key' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启之后，消费者在商城店铺首页可以看到店铺保证金已缴纳标识' WHERE `config_key` = 'margin_ico_switch' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '请填写阿里云 AccessKeySecret，请注意保密，切勿泄露' WHERE `config_key` = 'aliyun_AccessKeySecret' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启后用户可自行修改头像和昵称，关闭则禁止修改' WHERE `config_key` = 'open_update_info' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写自提点联系电话，用于用户自提咨询及订单通知，请确保号码准确有效。' WHERE `config_key` = 'mer_take_phone' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '开启后，所有用户（含微信 / 第三方登录）必须完成手机号绑定与短信验证，方可登录商城。' WHERE `config_key` = 'is_phone_login' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写微信服务号设置的微信号，用于账号识别与绑定，请确保准确无误。' WHERE `config_key` = 'wechat_id' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '设置数据大屏标题' WHERE `config_key` = 'data_screen_title' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '用于商品复制功能的密钥，用于接口校验与权限验证，请妥善保管，切勿泄露。' WHERE `config_key` = 'copy_product_apikey' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写已认证的小程序官方名称，需与微信后台一致。' WHERE `config_key` = 'routine_name' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '服务商支付公钥证书（从支付服务商平台下载）' WHERE `config_key` = 'wechat_service_public_key' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写自提点详细地址，需精确到门牌号，方便用户导航到店自提。' WHERE `config_key` = 'mer_take_address' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '设置是否强制更新消费者APP程序' WHERE `config_key` = 'openUpgrade' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '控制社区评论功能开关，开启允许评论，关闭则禁止评论' WHERE `config_key` = 'community_reply_status' AND `info` <=> '';
UPDATE `eb_system_config` SET `info` = '填写支付路由唯一标识密钥（pay_routine_key），用于支付渠道识别、接口鉴权与交易路由，请从支付服务商后台获取，妥善保管、严禁泄露。' WHERE `config_key` = 'pay_routine_key' AND `info` <=> '';

-- ========== 4. 其他内置数据 ==========
INSERT INTO `eb_system_notice_config` (`notice_title`, `const_key`, `notice_info`, `notice_sys`, `notice_sms`, `notice_wechat`, `wechat_tempkey`, `wechat_content`, `wechat_tempid`, `notice_routine`, `routine_tempkey`, `routine_content`, `routine_tempid`, `type`, `sms_tempid`, `sms_ali_tempid`, `sms_content`, `create_time`, `update_time`, `kid`) SELECT '配送订单通知', 'DELIVERY_ORDER_NOTICE', '配送订单给配送员提醒', '-1', '-1', '1', '55459', '客户地址{{thing5.DATA}}\n商品名称{{thing8.DATA}}\n结算金额{{amount27.DATA}}\n配送日期{{time18.DATA}}\n门店名称{{thing16.DATA}}', NULL, '-1', NULL, '', NULL, '0', NULL, NULL, NULL, '2026-09-17 15:20:01', '2026-09-17 15:20:01', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_notice_config` WHERE `const_key` = 'DELIVERY_ORDER_NOTICE');
INSERT IGNORE INTO `eb_merchant_member_benefits` (`id`, `mer_id`, `name`, `selected_icon`, `unselected_icon`, `link`, `sort`, `benefit_type`, `can_del`, `is_del`, `create_time`, `update_time`) VALUES ('1', '0', '会员专享价', '', '', '', '1', NULL, '0', '0', '2026-08-12 10:28:30', '2026-08-12 10:28:30');
INSERT IGNORE INTO `eb_merchant_member_benefits` (`id`, `mer_id`, `name`, `selected_icon`, `unselected_icon`, `link`, `sort`, `benefit_type`, `can_del`, `is_del`, `create_time`, `update_time`) VALUES ('2', '0', '会员专享券', '', '', '', '2', NULL, '0', '0', '2026-08-12 10:28:30', '2026-08-12 10:28:30');
INSERT IGNORE INTO `eb_merchant_member_benefits` (`id`, `mer_id`, `name`, `selected_icon`, `unselected_icon`, `link`, `sort`, `benefit_type`, `can_del`, `is_del`, `create_time`, `update_time`) VALUES ('3', '0', '购物金', '', '', '', '3', NULL, '0', '0', '2026-08-12 10:28:30', '2026-08-12 10:28:30');
INSERT IGNORE INTO `eb_merchant_member_benefits` (`id`, `mer_id`, `name`, `selected_icon`, `unselected_icon`, `link`, `sort`, `benefit_type`, `can_del`, `is_del`, `create_time`, `update_time`) VALUES ('4', '0', '入会/升级有礼', '', '', '', '4', NULL, '0', '0', '2026-08-12 10:28:30', '2026-08-12 10:28:30');
INSERT INTO `eb_page_link` (`cate_id`, `type`, `name`, `url`, `param`, `example`, `status`, `sort`, `add_time`, `is_mer`) SELECT '8', '1', '代理入驻', '/pages/agent/form', '', '', '1', '0', '2026-07-17 10:44:23', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_page_link` WHERE `url` = '/pages/agent/form');
INSERT INTO `eb_store_activity_cate` (`name`, `pid`, `pic`, `sort`, `status`, `mer_id`, `type`, `path`, `lv`, `create_time`, `is_sys`) SELECT '系统默认', '0', '', '0', '1', '0', '0', '/', '0', '2026-07-15 11:16:20', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_store_activity_cate` WHERE `name` = '系统默认' AND `mer_id` = '0');

-- ========== 5. 菜单 ==========
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10186', '9675', '/9675/', '', '区域代理', '/business-zones/manage', '[]', '0', '1', '0', '1', '2026-07-11 11:44:54', '2026-07-11 11:45:44', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10186);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10187', '10186', '/9675/10186/', '', '区域列表', '/business-zones/index', '[]', '0', '1', '0', '1', '2026-07-11 11:46:34', '2026-07-11 11:46:33', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10187);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10188', '7656', '/515/537/517/7656/', '', '退款', 'systemUserRechargeRefund', '', '1', '1', '0', '0', '2026-07-21 10:07:44', '2026-07-21 10:07:44', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10188);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10189', '9924', '/9492/9493/9920/9924/', '', '商圈入驻二维码', 'systemCircleQrcode', '', '1', '1', '0', '0', '2026-07-21 10:07:44', '2026-07-21 10:07:44', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10189);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10190', '9925', '/9492/42/9917/9925/', '', '商圈入驻二维码', 'systemCircleQrcode', '', '1', '1', '0', '0', '2026-07-21 10:07:44', '2026-07-21 10:07:44', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10190);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10191', '9365', '/42/6370/9367/9365/', '', '列表', 'systemStoreGroupLst', '', '1', '1', '0', '0', '2026-07-21 10:07:44', '2026-07-21 10:07:44', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10191);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10192', '9365', '/42/6370/9367/9365/', '', '详情', 'systemStoreGroupDetail', '', '1', '1', '0', '0', '2026-07-21 10:07:44', '2026-07-21 10:07:44', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10192);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10193', '9365', '/42/6370/9367/9365/', '', '添加', 'systemStoreGroupCreate', '', '1', '1', '0', '0', '2026-07-21 10:07:44', '2026-07-21 10:07:44', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10193);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10194', '9365', '/42/6370/9367/9365/', '', '编辑', 'systemStoreGroupUpdate', '', '1', '1', '0', '0', '2026-07-21 10:07:44', '2026-07-21 10:07:44', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10194);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10195', '9365', '/42/6370/9367/9365/', '', '删除', 'systemStoreGroupDelete', '', '1', '1', '0', '0', '2026-07-21 10:07:44', '2026-07-21 10:07:44', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10195);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10196', '9365', '/42/6370/9367/9365/', '', '状态切换', 'systemStoreGroupSwitchStatus', '', '1', '1', '0', '0', '2026-07-21 10:07:44', '2026-07-21 10:07:44', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10196);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10197', '9365', '/42/6370/9367/9365/', '', '设置店铺分组模板', 'systemStoreGroupSetTemplate', '', '1', '1', '0', '0', '2026-07-21 10:07:44', '2026-07-21 10:07:44', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10197);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10198', '9365', '/42/6370/9367/9365/', '', '关联店铺列表', 'systemStoreGroupStores', '', '1', '1', '0', '0', '2026-07-21 10:07:44', '2026-07-21 10:07:44', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10198);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10199', '8592', '/512/513/8592/', '', '次卡核销', 'merchantStoreOrderCardVerify', '', '1', '1', '1', '0', '2026-07-21 10:08:39', '2026-07-21 10:08:39', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10199);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10200', '8592', '/512/513/8592/', '', '核销记录', 'merchantStoreOrderCardLog', '', '1', '1', '1', '0', '2026-07-21 10:08:39', '2026-07-21 10:08:39', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10200);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10201', '10187', '/9675/10186/10187/', '', '权限', '/business-zones/index', '', '1', '1', '0', '0', '2026-07-21 10:08:43', '2026-07-21 10:08:42', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10201);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10202', '10201', '/9675/10186/10187/10201/', '', '商圈列表', 'systemCircleList', '', '1', '1', '0', '0', '2026-07-21 10:09:32', '2026-07-21 10:09:32', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10202);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10203', '10201', '/9675/10186/10187/10201/', '', '商圈详情', 'systemCircleDetail', '', '1', '1', '0', '0', '2026-07-21 10:09:32', '2026-07-21 10:09:32', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10203);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10204', '10201', '/9675/10186/10187/10201/', '', '商圈添加', 'systemCircleCreate', '', '1', '1', '0', '0', '2026-07-21 10:09:32', '2026-07-21 10:09:32', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10204);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10205', '10201', '/9675/10186/10187/10201/', '', '商圈编辑', 'systemCircleUpdate', '', '1', '1', '0', '0', '2026-07-21 10:09:32', '2026-07-21 10:09:32', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10205);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10206', '10201', '/9675/10186/10187/10201/', '', '商圈删除', 'systemCircleDelete', '', '1', '1', '0', '0', '2026-07-21 10:09:32', '2026-07-21 10:09:32', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10206);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10207', '10201', '/9675/10186/10187/10201/', '', '商圈状态切换', 'systemCircleSwitch', '', '1', '1', '0', '0', '2026-07-21 10:09:32', '2026-07-21 10:09:32', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10207);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10208', '10201', '/9675/10186/10187/10201/', '', '关联商户列表', 'systemCircleMerchantList', '', '1', '1', '0', '0', '2026-07-21 10:09:32', '2026-07-21 10:09:32', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10208);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10209', '10201', '/9675/10186/10187/10201/', '', '商圈入驻二维码', 'systemCircleQrcode', '', '1', '1', '0', '0', '2026-07-21 10:09:32', '2026-07-21 10:09:32', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10209);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10211', '10328', '/5054/10328/', '', '客服管理', '/service/customer/list', '[]', '2', '0', '0', '1', '2026-07-25 15:05:51', '2026-09-01 10:39:38', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10211);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10212', '10211', '/5054/10328/10211/', '', '客服自动回复', '/systemForm/customer_keyword', '[]', '0', '0', '0', '1', '2026-07-25 15:06:12', '2026-09-01 10:39:38', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10212);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10215', '10211', '/5054/10328/10211/', '', '客服设置', '/systemForm/Basics/service', '[]', '0', '0', '0', '1', '2026-07-25 15:08:43', '2026-09-01 10:39:38', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10215);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10216', '10211', '/5054/10328/10211/', '', '客服列表', '/service/customer/list', '[]', '0', '0', '0', '1', '2026-07-25 15:09:03', '2026-09-01 10:39:38', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10216);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10217', '10328', '/5054/10328/', '', '客服设置', '/systemForm/Basics/service', '[]', '0', '1', '0', '1', '2026-07-25 15:11:34', '2026-09-01 10:40:14', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10217);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10218', '5054', '/5054/', '', '配送管理', '/delivery', '[]', '0', '1', '0', '1', '2026-07-25 15:24:41', '2026-07-25 15:25:36', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10218);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10219', '10218', '/5054/10218/', '', '配送员管理', '/delivery/personnel_manage', '[]', '3', '1', '0', '1', '2026-07-25 15:25:28', '2026-07-27 15:53:21', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10219);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10220', '10218', '/5054/10218/', '', '配送统计', '/delivery/delivice_statistic', '[]', '0', '1', '0', '1', '2026-07-25 15:26:11', '2026-07-25 15:26:11', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10220);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10221', '10218', '/5054/10218/', '', '配送设置', '/delivery/delivery_setting', '[]', '0', '1', '0', '1', '2026-07-25 15:54:17', '2026-07-25 16:55:00', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10221);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10222', '10021', '/10021/', '', '核销记录', '/order/verifyRecord', '[]', '0', '1', '0', '1', '2026-07-27 09:49:04', '2026-07-27 09:49:03', '2' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10222);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10223', '9688', '/9688/', '', '核销记录', '/order/verifyRecord', '[]', '0', '1', '0', '1', '2026-07-27 09:53:31', '2026-07-27 09:53:31', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10223);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10224', '10330', '/512/10330/', '', '次卡核销记录', '/order/verifyRecord', '[]', '0', '1', '1', '1', '2026-07-27 09:54:43', '2026-09-02 11:30:43', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10224);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10225', '10329', '/540/10329/', '', '次卡核销记录', '/order/verifyRecord', '[]', '0', '1', '0', '1', '2026-07-27 09:58:09', '2026-09-02 11:29:29', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10225);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10226', '10218', '/5054/10218/', '', '配送员审核', '/delivery/personnel_review', '[]', '2', '1', '0', '1', '2026-07-27 15:51:36', '2026-07-27 15:53:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10226);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10227', '515', '/515/', '', '配送员结算', '/deliverymanExtract', '[]', '0', '1', '0', '1', '2026-07-28 09:59:42', '2026-07-28 09:59:42', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10227);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10228', '10227', '/515/10227/', '', '提成审核', '/accounts/deliverymanExtract', '[]', '0', '1', '0', '1', '2026-07-28 10:00:11', '2026-08-08 11:07:21', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10228);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10229', '537', '/515/537/', '', '提现配置', '/systemForm/Basics/withdraw', '[]', '0', '1', '0', '1', '2026-07-28 10:28:17', '2026-07-28 10:29:49', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10229);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10230', '525', '/525/', '', '配送提现管理', '/systemForm/Basics/mer_withdraw', '[]', '0', '1', '1', '1', '2026-08-03 17:18:38', '2026-08-03 17:18:37', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10230);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10231', '0', '/', 'user-solid', '会员', '/member', '[]', '3', '1', '1', '1', '2026-08-04 10:17:51', '2026-08-04 10:25:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10231);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10232', '10231', '/10231/', '', '会员概览', '/member/overview', '[]', '0', '1', '1', '1', '2026-08-04 10:19:00', '2026-08-04 10:19:00', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10232);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10233', '10231', '/10231/', '', '会员列表', '/member/customer', '[]', '0', '1', '1', '1', '2026-08-04 14:16:29', '2026-08-12 09:55:03', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10233);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10234', '10231', '/10231/', '', '会员设置', '/member/benefits', '[]', '0', '1', '1', '1', '2026-08-05 10:11:34', '2026-08-05 10:11:34', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10234);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10235', '10234', '/10231/10234/', '', '会员权益', '/member/benefits', '[]', '0', '1', '1', '1', '2026-08-05 10:11:50', '2026-08-05 10:11:49', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10235);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10236', '10234', '/10231/10234/', '', '会员等级', '/member/level', '[]', '0', '1', '1', '1', '2026-08-05 11:53:47', '2026-08-05 11:53:46', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10236);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10237', '10231', '/10231/', '', '购物金', '/member/shopping-credits', '[]', '0', '1', '1', '1', '2026-08-05 17:59:28', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10237);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10238', '10237', '/10231/10237/', '', '充值套餐', '/member/recharge', '[]', '30', '1', '1', '1', '2026-08-05 17:59:46', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10238);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10239', '10227', '/515/10227/', '', '提成流水', '/accounts/deliverySettlement', '[]', '0', '1', '0', '1', '2026-08-06 18:16:37', '2026-08-06 18:16:36', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10239);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10240', '525', '/525/', '', '配送员结算', '/accounts/deliverySettlement', '[]', '0', '1', '1', '1', '2026-08-07 09:56:00', '2026-08-07 09:56:00', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10240);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10241', '10240', '/525/10240/', '', '提现管理', '/accounts/deliverymanExtract', '[]', '0', '1', '1', '1', '2026-08-07 09:56:37', '2026-09-09 15:53:04', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10241);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10242', '10240', '/525/10240/', '', '配送费记录', '/accounts/deliverySettlement', '[]', '0', '1', '1', '1', '2026-08-07 09:57:27', '2026-09-09 15:53:17', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10242);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10243', '540', '/540/', '', '配送订单', '/order/deliveryOrder', '[]', '0', '1', '0', '1', '2026-08-07 11:44:07', '2026-08-07 11:44:06', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10243);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10244', '10234', '/10231/10234/', '', '会员协议', '/member/agreement', '[]', '0', '1', '1', '1', '2026-08-07 15:40:51', '2026-08-07 15:40:51', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10244);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10245', '10237', '/10231/10237/', '', '充值订单', '/member/recharge/order', '[]', '20', '1', '1', '1', '2026-08-07 16:03:36', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10245);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10246', '10237', '/10231/10237/', '', '充值退款', '/member/recharge/refund', '[]', '10', '1', '1', '1', '2026-08-08 14:51:38', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10246);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10248', '10232', '/10231/10232/', '', '权限', '/member/overview', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10248);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10249', '10233', '/10231/10233/', '', '权限', '/member/customer', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10249);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10250', '10234', '/10231/10234/', '', '权限', '/member/benefits', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10250);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10251', '10235', '/10231/10234/10235/', '', '权限', '/member/benefits', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10251);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10252', '10236', '/10231/10234/10236/', '', '权限', '/member/level', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10252);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10254', '10238', '/10231/10237/10238/', '', '权限', '/member/recharge', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10254);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10255', '10244', '/10231/10234/10244/', '', '权限', '/member/agreement', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10255);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10263', '10248', '/10231/10232/10248/', '', '统计', 'merchantMemberStatistics', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10263);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10264', '10249', '/10231/10233/10249/', '', '客户列表', 'merchantMemberCustomerList', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10264);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10265', '10249', '/10231/10233/10249/', '', '客户详情', 'merchantMemberCustomerDetail', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10265);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10266', '10249', '/10231/10233/10249/', '', '等级记录', 'merchantMemberCustomerLevelRecords', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10266);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10267', '10249', '/10231/10233/10249/', '', '购物金记录', 'merchantMemberCustomerCreditsRecords', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10267);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10268', '10249', '/10231/10233/10249/', '', '调整等级', 'merchantMemberCustomerLevelUpdate', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10268);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10269', '10249', '/10231/10233/10249/', '', '购物金冻结', 'merchantMemberCustomerFinancialStatus', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10269);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10270', '10249', '/10231/10233/10249/', '', '调整购物金', 'merchantMemberCustomerCreditsAdjust', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10270);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10271', '10250', '/10231/10234/10250/', '', '权益列表', 'merchantMemberBenefitsList', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10271);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10272', '10251', '/10231/10234/10235/10251/', '', '权益列表', 'merchantMemberBenefitsList', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10272);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10273', '10250', '/10231/10234/10250/', '', '保存权益', 'merchantMemberBenefitsSave', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10273);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10274', '10251', '/10231/10234/10235/10251/', '', '保存权益', 'merchantMemberBenefitsSave', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10274);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10275', '10255', '/10231/10234/10244/10255/', '', '协议详情', 'merchantMemberAgreementDetail', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10275);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10276', '10255', '/10231/10234/10244/10255/', '', '保存协议', 'merchantMemberAgreementSave', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10276);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10277', '10255', '/10231/10234/10244/10255/', '', '会员页配置', 'merchantMemberDiyPageDetail', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10277);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10278', '10255', '/10231/10234/10244/10255/', '', '保存会员页', 'merchantMemberDiyPageSave', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10278);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10279', '10252', '/10231/10234/10236/10252/', '', '等级列表', 'merchantMemberLevelList', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10279);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10280', '10252', '/10231/10234/10236/10252/', '', '等级详情', 'merchantMemberLevelDetail', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10280);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10281', '10252', '/10231/10234/10236/10252/', '', '添加等级', 'merchantMemberLevelCreate', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10281);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10282', '10252', '/10231/10234/10236/10252/', '', '编辑等级', 'merchantMemberLevelUpdate', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10282);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10283', '10252', '/10231/10234/10236/10252/', '', '删除等级', 'merchantMemberLevelDelete', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 10:28:31', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10283);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10284', '10254', '/10231/10237/10238/10254/', '', '套餐列表', 'merchantShoppingCreditsPackageList', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10284);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10286', '10254', '/10231/10237/10238/10254/', '', '套餐详情', 'merchantShoppingCreditsPackageDetail', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10286);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10288', '10254', '/10231/10237/10238/10254/', '', '添加套餐', 'merchantShoppingCreditsPackageCreate', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10288);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10290', '10254', '/10231/10237/10238/10254/', '', '编辑套餐', 'merchantShoppingCreditsPackageUpdate', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10290);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10292', '10254', '/10231/10237/10238/10254/', '', '删除套餐', 'merchantShoppingCreditsPackageDelete', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10292);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10294', '10254', '/10231/10237/10238/10254/', '', '套餐状态', 'merchantShoppingCreditsPackageStatus', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10294);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10296', '10326', '/10231/10237/10245/10326/', '', '充值订单', 'merchantShoppingCreditsOrderList', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10296);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10298', '10327', '/10231/10237/10246/10327/', '', '退款订单', 'merchantShoppingCreditsRefundList', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10298);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10300', '10327', '/10231/10237/10246/10327/', '', '退款详情', 'merchantShoppingCreditsRefundDetail', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10300);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10302', '10327', '/10231/10237/10246/10327/', '', '退款审核', 'merchantShoppingCreditsRefundAudit', '', '1', '1', '1', '0', '2026-08-12 10:28:31', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10302);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10326', '10245', '/10231/10237/10245/', '', '权限', '/member/recharge/order', '', '1', '1', '1', '0', '2026-08-12 12:58:27', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10326);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10327', '10246', '/10231/10237/10246/', '', '权限', '/member/recharge/refund', '', '1', '1', '1', '0', '2026-08-12 12:58:27', '2026-08-12 12:58:27', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10327);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10328', '5054', '/5054/', '', '平台客服', '/service/customer', '[]', '2', '1', '0', '1', '2026-09-01 10:39:22', '2026-09-01 10:39:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10328);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10329', '540', '/540/', '', '核销订单', '/order', '[]', '0', '1', '0', '1', '2026-09-02 11:28:12', '2026-09-02 11:28:11', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10329);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10330', '512', '/512/', '', '核销订单', '/order/cancellation', '[]', '0', '1', '1', '1', '2026-09-02 11:30:07', '2026-09-02 11:32:37', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10330);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10331', '512', '/512/', '', '预约管理', '/order/reservation', '[]', '0', '1', '1', '1', '2026-09-02 11:31:51', '2026-09-02 11:32:46', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10331);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10332', '0', '/', 'delete', '测试', '11', '[]', '0', '1', '0', '1', '2026-09-10 10:06:19', '2026-09-10 10:06:18', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10332);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10333', '10229', '/515/537/10229/', '', '权限', '/systemForm/Basics/withdraw', '', '1', '1', '0', '0', '2026-09-20 10:18:00', '2026-09-20 10:18:00', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10333);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10334', '10217', '/5054/10328/10217/', '', '权限', '/systemForm/Basics/service', '', '1', '1', '0', '0', '2026-09-20 10:18:01', '2026-09-20 10:18:01', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10334);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10335', '10221', '/5054/10218/10221/', '', '权限', '/delivery/delivery_setting', '', '1', '1', '0', '0', '2026-09-20 10:18:08', '2026-09-20 10:18:08', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10335);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10336', '10226', '/5054/10218/10226/', '', '权限', '/delivery/personnel_review', '', '1', '1', '0', '0', '2026-09-20 10:18:10', '2026-09-20 10:18:10', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10336);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10337', '10219', '/5054/10218/10219/', '', '权限', '/delivery/personnel_manage', '', '1', '1', '0', '0', '2026-09-20 10:18:11', '2026-09-20 10:18:10', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10337);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10338', '7649', '/9492/6370/1303/7649/', '', '分账商户申请统计', 'systemMerchantApplymentsTitle', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10338);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10339', '10333', '/515/537/10229/10333/', '', '编辑配置信息', 'configSave', 'withdraw', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10339);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10340', '10334', '/5054/10328/10217/10334/', '', '编辑配置信息', 'configSave', 'service', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10340);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10341', '10335', '/5054/10218/10221/10335/', '', '平台配送设置信息', 'systemPlatformDeliveryConfigSettings', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10341);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10342', '10335', '/5054/10218/10221/10335/', '', '更新平台配送设置', 'systemPlatformDeliveryConfigUpdate', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10342);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10343', '7691', '/110/9360/5124/1653/1655/7691/', '', '平台配送订单列表', 'systemDeliveryOrderManageLst', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10343);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10344', '7691', '/110/9360/5124/1653/1655/7691/', '', '平台配送订单统计', 'systemDeliveryOrderManageTitle', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10344);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10345', '7691', '/110/9360/5124/1653/1655/7691/', '', '平台配送订单批量派单', 'systemDeliveryOrderBatchDispatch', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10345);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10346', '7691', '/110/9360/5124/1653/1655/7691/', '', '平台配送订单批量确认送达', 'systemDeliveryOrderBatchConfirm', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10346);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10347', '7691', '/110/9360/5124/1653/1655/7691/', '', '平台配送派单', 'systemDeliveryOrderDispatch', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10347);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10348', '7691', '/110/9360/5124/1653/1655/7691/', '', '平台配送改派', 'systemDeliveryOrderUpdateDispatch', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10348);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10349', '7691', '/110/9360/5124/1653/1655/7691/', '', '平台配送确认送达', 'systemDeliveryOrderConfirm', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10349);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10350', '10336', '/5054/10218/10226/10336/', '', '配送员申请列表', 'systemDeliveryServiceApplyLst', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10350);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10351', '10336', '/5054/10218/10226/10336/', '', '配送员申请统计', 'systemDeliveryServiceApplyTitle', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10351);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10352', '10336', '/5054/10218/10226/10336/', '', '配送员申请详情', 'systemDeliveryServiceApplyDetail', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10352);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10353', '10336', '/5054/10218/10226/10336/', '', '审核配送员申请', 'systemDeliveryServiceApplyAudit', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10353);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10354', '10337', '/5054/10218/10219/10337/', '', '配送员列表', 'systemDeliveryServiceLst', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10354);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10355', '10337', '/5054/10218/10219/10337/', '', '配送员详情', 'systemDeliveryServiceDetail', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10355);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10356', '10337', '/5054/10218/10219/10337/', '', '配送员配送订单', 'systemDeliveryServiceOrderList', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10356);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10357', '10337', '/5054/10218/10219/10337/', '', '配送员提现成功记录', 'systemDeliveryServiceWithdrawSuccessList', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10357);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10358', '10337', '/5054/10218/10219/10337/', '', '配送员统计列表', 'systemDeliveryServiceStatisticsList', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10358);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10359', '10337', '/5054/10218/10219/10337/', '', '添加配送员', 'systemDeliveryServiceCreate', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10359);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10360', '10337', '/5054/10218/10219/10337/', '', '编辑配送员', 'systemDeliveryServiceUpdate', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10360);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10361', '10337', '/5054/10218/10219/10337/', '', '修改配送员接单状态', 'systemDeliveryServiceStatus', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10361);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10362', '10337', '/5054/10218/10219/10337/', '', '修改配送员状态', 'systemDeliveryServiceOpen', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10362);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10363', '10337', '/5054/10218/10219/10337/', '', '删除配送员', 'systemDeliveryServiceDelete', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10363);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10364', '7772', '/540/542/7772/', '', '退款单状态统计', 'systemRefundOrderTitle', '', '1', '1', '0', '0', '2026-09-20 10:19:32', '2026-09-20 10:19:32', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10364);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10365', '8554', '/525/1102/8554/', '', '统计', 'merchantOrderReceiptTitle', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10365);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10366', '9892', '9330/9558/9892/', '', '配送员详情', 'merchantDeliveryServiceDetail', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10366);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10367', '9892', '9330/9558/9892/', '', '配送员配送订单', 'merchantDeliveryServiceOrderList', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10367);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10368', '9892', '9330/9558/9892/', '', '配送员提现成功记录', 'merchantDeliveryServiceWithdrawSuccessList', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10368);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10369', '9892', '9330/9558/9892/', '', '修改状态', 'merchantDeliveryServiceOpen', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10369);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10370', '8567', '9332/1651/8567/', '', '商户配送订单列表', 'merchantDeliveryOrderManageLst', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10370);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10371', '8567', '9332/1651/8567/', '', '商户配送订单统计', 'merchantDeliveryOrderManageTitle', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10371);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10372', '8567', '9332/1651/8567/', '', '商户配送订单批量派单', 'merchantDeliveryOrderBatchDispatch', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10372);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10373', '8567', '9332/1651/8567/', '', '商户配送订单批量确认送达', 'merchantDeliveryOrderBatchConfirm', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10373);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10374', '10248', '/10231/10232/10248/', '', '统计', 'merchantMemberStatisticsBase', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10374);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10375', '10248', '/10231/10232/10248/', '', '等级统计', 'merchantMemberStatisticsLevel', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10375);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10376', '10248', '/10231/10232/10248/', '', '渠道统计', 'merchantMemberStatisticsChannel', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10376);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10377', '10248', '/10231/10232/10248/', '', '会员趋势', 'merchantMemberStatisticsMemberTrend', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10377);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10378', '10248', '/10231/10232/10248/', '', '消费趋势', 'merchantMemberStatisticsConsumeTrend', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10378);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10379', '10249', '/10231/10233/10249/', '', '会员导出', 'merchantMemberCustomerExport', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10379);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10380', '10327', '/10231/10237/10246/10327/', '', '退款统计', 'merchantShoppingCreditsRefundStatistics', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10380);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10381', '8595', '/512/528/8595/', '', '状态统计', 'merchantStoreRefundOrderTitle', '', '1', '1', '1', '0', '2026-09-20 10:24:30', '2026-09-20 10:24:30', '0' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10381);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10382', '9694', '/9688/9690/9694/', '', '退款单状态统计', 'systemRefundOrderTitle', '', '1', '1', '0', '0', '2026-09-20 10:25:32', '2026-09-20 10:25:32', '1' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10382);
INSERT INTO `eb_system_menu` (`menu_id`, `pid`, `path`, `icon`, `menu_name`, `route`, `params`, `sort`, `is_show`, `is_mer`, `is_menu`, `create_time`, `update_time`, `is_agent`) SELECT '10383', '10064', '/10021/10023/10064/', '', '退款单状态统计', 'systemRefundOrderTitle', '', '1', '1', '0', '0', '2026-09-20 10:26:35', '2026-09-20 10:26:35', '2' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `eb_system_menu` WHERE `menu_id` = 10383);
UPDATE `eb_system_menu` SET `pid` = '95', `path` = '/95/', `sort` = '0' WHERE `menu_id` = 544 AND `pid` <=> '512' AND `path` <=> '512/' AND `sort` <=> '100';
UPDATE `eb_system_menu` SET `pid` = '10329', `path` = '/540/10329/', `menu_name` = '到店自提订单' WHERE `menu_id` = 784 AND `pid` <=> '540' AND `path` <=> '/540/' AND `menu_name` <=> '核销记录';
UPDATE `eb_system_menu` SET `pid` = '10330', `path` = '/512/10330/', `menu_name` = '到店自提订单' WHERE `menu_id` = 789 AND `pid` <=> '512' AND `path` <=> '/512/' AND `menu_name` <=> '核销记录';
UPDATE `eb_system_menu` SET `is_show` = '0' WHERE `menu_id` = 1653 AND `is_show` <=> '1';
UPDATE `eb_system_menu` SET `pid` = '10328', `path` = '/5054/10328/' WHERE `menu_id` = 1668 AND `pid` <=> '5054' AND `path` <=> '/5054/';
UPDATE `eb_system_menu` SET `menu_name` = '员工', `sort` = '94' WHERE `menu_id` = 5054 AND `menu_name` <=> '客服' AND `sort` <=> '92';
UPDATE `eb_system_menu` SET `pid` = '10328', `path` = '/5054/10328/' WHERE `menu_id` = 5088 AND `pid` <=> '5054' AND `path` <=> '/5054/';
UPDATE `eb_system_menu` SET `path` = '/540/10329/784/' WHERE `menu_id` = 7771 AND `path` <=> '/540/784/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/5088/' WHERE `menu_id` = 7791 AND `path` <=> '/5054/5088/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/1668/' WHERE `menu_id` = 7792 AND `path` <=> '/5054/1668/';
UPDATE `eb_system_menu` SET `path` = '/540/10329/784/7771/' WHERE `menu_id` = 8318 AND `path` <=> '/540/784/7771/';
UPDATE `eb_system_menu` SET `path` = '/540/10329/784/7771/' WHERE `menu_id` = 8319 AND `path` <=> '/540/784/7771/';
UPDATE `eb_system_menu` SET `path` = '/540/10329/784/7771/' WHERE `menu_id` = 8320 AND `path` <=> '/540/784/7771/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/5088/7791/' WHERE `menu_id` = 8396 AND `path` <=> '/5054/5088/7791/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/5088/7791/' WHERE `menu_id` = 8397 AND `path` <=> '/5054/5088/7791/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/5088/7791/' WHERE `menu_id` = 8398 AND `path` <=> '/5054/5088/7791/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/5088/7791/' WHERE `menu_id` = 8399 AND `path` <=> '/5054/5088/7791/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/5088/7791/' WHERE `menu_id` = 8400 AND `path` <=> '/5054/5088/7791/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/5088/7791/' WHERE `menu_id` = 8401 AND `path` <=> '/5054/5088/7791/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/5088/7791/' WHERE `menu_id` = 8402 AND `path` <=> '/5054/5088/7791/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/5088/7791/' WHERE `menu_id` = 8403 AND `path` <=> '/5054/5088/7791/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/5088/7791/' WHERE `menu_id` = 8404 AND `path` <=> '/5054/5088/7791/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/5088/7791/' WHERE `menu_id` = 8405 AND `path` <=> '/5054/5088/7791/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/1668/7792/' WHERE `menu_id` = 8406 AND `path` <=> '/5054/1668/7792/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/1668/7792/' WHERE `menu_id` = 8407 AND `path` <=> '/5054/1668/7792/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/1668/7792/' WHERE `menu_id` = 8408 AND `path` <=> '/5054/1668/7792/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/1668/7792/' WHERE `menu_id` = 8409 AND `path` <=> '/5054/1668/7792/';
UPDATE `eb_system_menu` SET `path` = '/5054/10328/1668/7792/' WHERE `menu_id` = 8410 AND `path` <=> '/5054/1668/7792/';
UPDATE `eb_system_menu` SET `path` = '/512/10330/789/' WHERE `menu_id` = 8594 AND `path` <=> '/512/789/';
UPDATE `eb_system_menu` SET `path` = '/95/544/' WHERE `menu_id` = 8602 AND `path` <=> '512/544/';
UPDATE `eb_system_menu` SET `path` = '/95/544/' WHERE `menu_id` = 8603 AND `path` <=> '512/544/';
UPDATE `eb_system_menu` SET `path` = '/512/10330/789/8594/' WHERE `menu_id` = 8842 AND `path` <=> '/512/789/8594/';
UPDATE `eb_system_menu` SET `path` = '/512/10330/789/8594/' WHERE `menu_id` = 8843 AND `path` <=> '/512/789/8594/';
UPDATE `eb_system_menu` SET `path` = '/95/544/8602/' WHERE `menu_id` = 8887 AND `path` <=> '512/544/8602/';
UPDATE `eb_system_menu` SET `path` = '/95/544/8602/' WHERE `menu_id` = 8888 AND `path` <=> '512/544/8602/';
UPDATE `eb_system_menu` SET `path` = '/95/544/8602/' WHERE `menu_id` = 8889 AND `path` <=> '512/544/8602/';
UPDATE `eb_system_menu` SET `path` = '/95/544/8602/' WHERE `menu_id` = 8890 AND `path` <=> '512/544/8602/';
UPDATE `eb_system_menu` SET `path` = '/95/544/8603/' WHERE `menu_id` = 8891 AND `path` <=> '512/544/8603/';
UPDATE `eb_system_menu` SET `path` = '/95/544/8603/' WHERE `menu_id` = 8892 AND `path` <=> '512/544/8603/';
UPDATE `eb_system_menu` SET `pid` = '10331', `path` = '/512/10331/' WHERE `menu_id` = 9405 AND `pid` <=> '512' AND `path` <=> '512/';
UPDATE `eb_system_menu` SET `pid` = '10331', `path` = '/512/10331/', `menu_name` = '预约日历' WHERE `menu_id` = 9406 AND `pid` <=> '512' AND `path` <=> '512/' AND `menu_name` <=> '预约服务';
UPDATE `eb_system_menu` SET `path` = '/512/10331/9406/' WHERE `menu_id` = 9418 AND `path` <=> '512/9406/';
UPDATE `eb_system_menu` SET `path` = '/512/10331/9406/9418/' WHERE `menu_id` = 9439 AND `path` <=> '512/9406/9418/';
UPDATE `eb_system_menu` SET `menu_name` = '核销订单' WHERE `menu_id` = 9691 AND `menu_name` <=> '核销记录';
UPDATE `eb_system_menu` SET `menu_name` = '核销订单' WHERE `menu_id` = 10024 AND `menu_name` <=> '核销记录';
DELETE FROM `eb_system_menu` WHERE `menu_id` = 8981 AND `route` = '/systemForm/Basics/service' AND `pid` = 5127;
DELETE FROM `eb_system_menu` WHERE `menu_id` = 8992 AND `route` = 'configSave' AND `pid` = 8981;

-- ========== 6. 新菜单授权（按上级菜单继承） ==========
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10186') WHERE FIND_IN_SET('9675', `rules`) AND NOT FIND_IN_SET('10186', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10187') WHERE FIND_IN_SET('10186', `rules`) AND NOT FIND_IN_SET('10187', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10188') WHERE FIND_IN_SET('7656', `rules`) AND NOT FIND_IN_SET('10188', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10189') WHERE FIND_IN_SET('9924', `rules`) AND NOT FIND_IN_SET('10189', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10190') WHERE FIND_IN_SET('9925', `rules`) AND NOT FIND_IN_SET('10190', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10191') WHERE FIND_IN_SET('9365', `rules`) AND NOT FIND_IN_SET('10191', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10192') WHERE FIND_IN_SET('9365', `rules`) AND NOT FIND_IN_SET('10192', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10193') WHERE FIND_IN_SET('9365', `rules`) AND NOT FIND_IN_SET('10193', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10194') WHERE FIND_IN_SET('9365', `rules`) AND NOT FIND_IN_SET('10194', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10195') WHERE FIND_IN_SET('9365', `rules`) AND NOT FIND_IN_SET('10195', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10196') WHERE FIND_IN_SET('9365', `rules`) AND NOT FIND_IN_SET('10196', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10197') WHERE FIND_IN_SET('9365', `rules`) AND NOT FIND_IN_SET('10197', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10198') WHERE FIND_IN_SET('9365', `rules`) AND NOT FIND_IN_SET('10198', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10199, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 8592 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10199);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10199') WHERE FIND_IN_SET('8592', `rules`) AND NOT FIND_IN_SET('10199', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10200, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 8592 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10200);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10200') WHERE FIND_IN_SET('8592', `rules`) AND NOT FIND_IN_SET('10200', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10201') WHERE FIND_IN_SET('10187', `rules`) AND NOT FIND_IN_SET('10201', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10202') WHERE FIND_IN_SET('10201', `rules`) AND NOT FIND_IN_SET('10202', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10203') WHERE FIND_IN_SET('10201', `rules`) AND NOT FIND_IN_SET('10203', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10204') WHERE FIND_IN_SET('10201', `rules`) AND NOT FIND_IN_SET('10204', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10205') WHERE FIND_IN_SET('10201', `rules`) AND NOT FIND_IN_SET('10205', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10206') WHERE FIND_IN_SET('10201', `rules`) AND NOT FIND_IN_SET('10206', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10207') WHERE FIND_IN_SET('10201', `rules`) AND NOT FIND_IN_SET('10207', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10208') WHERE FIND_IN_SET('10201', `rules`) AND NOT FIND_IN_SET('10208', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10209') WHERE FIND_IN_SET('10201', `rules`) AND NOT FIND_IN_SET('10209', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10328') WHERE FIND_IN_SET('5054', `rules`) AND NOT FIND_IN_SET('10328', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10211') WHERE FIND_IN_SET('10328', `rules`) AND NOT FIND_IN_SET('10211', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10212') WHERE FIND_IN_SET('10211', `rules`) AND NOT FIND_IN_SET('10212', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10215') WHERE FIND_IN_SET('10211', `rules`) AND NOT FIND_IN_SET('10215', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10216') WHERE FIND_IN_SET('10211', `rules`) AND NOT FIND_IN_SET('10216', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10217') WHERE FIND_IN_SET('10328', `rules`) AND NOT FIND_IN_SET('10217', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10218') WHERE FIND_IN_SET('5054', `rules`) AND NOT FIND_IN_SET('10218', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10219') WHERE FIND_IN_SET('10218', `rules`) AND NOT FIND_IN_SET('10219', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10220') WHERE FIND_IN_SET('10218', `rules`) AND NOT FIND_IN_SET('10220', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10221') WHERE FIND_IN_SET('10218', `rules`) AND NOT FIND_IN_SET('10221', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10222') WHERE FIND_IN_SET('10021', `rules`) AND NOT FIND_IN_SET('10222', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10223') WHERE FIND_IN_SET('9688', `rules`) AND NOT FIND_IN_SET('10223', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10330, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 512 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10330);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10330') WHERE FIND_IN_SET('512', `rules`) AND NOT FIND_IN_SET('10330', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10224, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10330 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10224);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10224') WHERE FIND_IN_SET('10330', `rules`) AND NOT FIND_IN_SET('10224', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10329') WHERE FIND_IN_SET('540', `rules`) AND NOT FIND_IN_SET('10329', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10225') WHERE FIND_IN_SET('10329', `rules`) AND NOT FIND_IN_SET('10225', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10226') WHERE FIND_IN_SET('10218', `rules`) AND NOT FIND_IN_SET('10226', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10227') WHERE FIND_IN_SET('515', `rules`) AND NOT FIND_IN_SET('10227', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10228') WHERE FIND_IN_SET('10227', `rules`) AND NOT FIND_IN_SET('10228', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10229') WHERE FIND_IN_SET('537', `rules`) AND NOT FIND_IN_SET('10229', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10230, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 525 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10230);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10230') WHERE FIND_IN_SET('525', `rules`) AND NOT FIND_IN_SET('10230', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT t.`mer_type_id`, 10231, 'mer_auth' FROM `eb_merchant_type` t WHERE NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = t.`mer_type_id` AND r.`right_id` = 10231);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10232, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10231 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10232);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10232') WHERE FIND_IN_SET('10231', `rules`) AND NOT FIND_IN_SET('10232', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10233, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10231 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10233);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10233') WHERE FIND_IN_SET('10231', `rules`) AND NOT FIND_IN_SET('10233', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10234, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10231 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10234);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10234') WHERE FIND_IN_SET('10231', `rules`) AND NOT FIND_IN_SET('10234', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10235, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10234 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10235);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10235') WHERE FIND_IN_SET('10234', `rules`) AND NOT FIND_IN_SET('10235', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10236, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10234 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10236);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10236') WHERE FIND_IN_SET('10234', `rules`) AND NOT FIND_IN_SET('10236', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10237, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10231 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10237);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10237') WHERE FIND_IN_SET('10231', `rules`) AND NOT FIND_IN_SET('10237', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10238, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10237 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10238);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10238') WHERE FIND_IN_SET('10237', `rules`) AND NOT FIND_IN_SET('10238', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10239') WHERE FIND_IN_SET('10227', `rules`) AND NOT FIND_IN_SET('10239', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10240, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 525 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10240);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10240') WHERE FIND_IN_SET('525', `rules`) AND NOT FIND_IN_SET('10240', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10241, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10240 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10241);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10241') WHERE FIND_IN_SET('10240', `rules`) AND NOT FIND_IN_SET('10241', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10242, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10240 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10242);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10242') WHERE FIND_IN_SET('10240', `rules`) AND NOT FIND_IN_SET('10242', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10243') WHERE FIND_IN_SET('540', `rules`) AND NOT FIND_IN_SET('10243', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10244, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10234 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10244);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10244') WHERE FIND_IN_SET('10234', `rules`) AND NOT FIND_IN_SET('10244', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10245, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10237 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10245);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10245') WHERE FIND_IN_SET('10237', `rules`) AND NOT FIND_IN_SET('10245', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10246, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10237 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10246);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10246') WHERE FIND_IN_SET('10237', `rules`) AND NOT FIND_IN_SET('10246', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10248, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10232 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10248);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10248') WHERE FIND_IN_SET('10232', `rules`) AND NOT FIND_IN_SET('10248', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10249, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10233 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10249);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10249') WHERE FIND_IN_SET('10233', `rules`) AND NOT FIND_IN_SET('10249', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10250, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10234 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10250);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10250') WHERE FIND_IN_SET('10234', `rules`) AND NOT FIND_IN_SET('10250', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10251, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10235 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10251);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10251') WHERE FIND_IN_SET('10235', `rules`) AND NOT FIND_IN_SET('10251', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10252, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10236 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10252);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10252') WHERE FIND_IN_SET('10236', `rules`) AND NOT FIND_IN_SET('10252', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10254, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10238 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10254);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10254') WHERE FIND_IN_SET('10238', `rules`) AND NOT FIND_IN_SET('10254', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10255, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10244 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10255);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10255') WHERE FIND_IN_SET('10244', `rules`) AND NOT FIND_IN_SET('10255', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10263, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10248 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10263);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10263') WHERE FIND_IN_SET('10248', `rules`) AND NOT FIND_IN_SET('10263', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10264, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10249 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10264);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10264') WHERE FIND_IN_SET('10249', `rules`) AND NOT FIND_IN_SET('10264', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10265, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10249 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10265);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10265') WHERE FIND_IN_SET('10249', `rules`) AND NOT FIND_IN_SET('10265', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10266, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10249 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10266);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10266') WHERE FIND_IN_SET('10249', `rules`) AND NOT FIND_IN_SET('10266', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10267, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10249 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10267);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10267') WHERE FIND_IN_SET('10249', `rules`) AND NOT FIND_IN_SET('10267', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10268, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10249 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10268);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10268') WHERE FIND_IN_SET('10249', `rules`) AND NOT FIND_IN_SET('10268', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10269, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10249 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10269);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10269') WHERE FIND_IN_SET('10249', `rules`) AND NOT FIND_IN_SET('10269', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10270, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10249 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10270);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10270') WHERE FIND_IN_SET('10249', `rules`) AND NOT FIND_IN_SET('10270', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10271, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10250 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10271);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10271') WHERE FIND_IN_SET('10250', `rules`) AND NOT FIND_IN_SET('10271', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10272, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10251 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10272);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10272') WHERE FIND_IN_SET('10251', `rules`) AND NOT FIND_IN_SET('10272', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10273, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10250 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10273);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10273') WHERE FIND_IN_SET('10250', `rules`) AND NOT FIND_IN_SET('10273', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10274, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10251 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10274);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10274') WHERE FIND_IN_SET('10251', `rules`) AND NOT FIND_IN_SET('10274', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10275, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10255 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10275);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10275') WHERE FIND_IN_SET('10255', `rules`) AND NOT FIND_IN_SET('10275', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10276, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10255 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10276);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10276') WHERE FIND_IN_SET('10255', `rules`) AND NOT FIND_IN_SET('10276', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10277, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10255 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10277);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10277') WHERE FIND_IN_SET('10255', `rules`) AND NOT FIND_IN_SET('10277', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10278, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10255 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10278);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10278') WHERE FIND_IN_SET('10255', `rules`) AND NOT FIND_IN_SET('10278', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10279, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10252 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10279);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10279') WHERE FIND_IN_SET('10252', `rules`) AND NOT FIND_IN_SET('10279', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10280, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10252 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10280);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10280') WHERE FIND_IN_SET('10252', `rules`) AND NOT FIND_IN_SET('10280', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10281, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10252 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10281);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10281') WHERE FIND_IN_SET('10252', `rules`) AND NOT FIND_IN_SET('10281', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10282, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10252 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10282);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10282') WHERE FIND_IN_SET('10252', `rules`) AND NOT FIND_IN_SET('10282', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10283, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10252 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10283);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10283') WHERE FIND_IN_SET('10252', `rules`) AND NOT FIND_IN_SET('10283', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10284, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10254 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10284);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10284') WHERE FIND_IN_SET('10254', `rules`) AND NOT FIND_IN_SET('10284', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10286, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10254 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10286);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10286') WHERE FIND_IN_SET('10254', `rules`) AND NOT FIND_IN_SET('10286', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10288, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10254 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10288);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10288') WHERE FIND_IN_SET('10254', `rules`) AND NOT FIND_IN_SET('10288', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10290, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10254 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10290);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10290') WHERE FIND_IN_SET('10254', `rules`) AND NOT FIND_IN_SET('10290', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10292, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10254 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10292);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10292') WHERE FIND_IN_SET('10254', `rules`) AND NOT FIND_IN_SET('10292', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10294, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10254 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10294);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10294') WHERE FIND_IN_SET('10254', `rules`) AND NOT FIND_IN_SET('10294', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10326, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10245 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10326);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10326') WHERE FIND_IN_SET('10245', `rules`) AND NOT FIND_IN_SET('10326', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10296, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10326 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10296);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10296') WHERE FIND_IN_SET('10326', `rules`) AND NOT FIND_IN_SET('10296', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10327, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10246 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10327);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10327') WHERE FIND_IN_SET('10246', `rules`) AND NOT FIND_IN_SET('10327', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10298, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10327 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10298);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10298') WHERE FIND_IN_SET('10327', `rules`) AND NOT FIND_IN_SET('10298', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10300, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10327 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10300);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10300') WHERE FIND_IN_SET('10327', `rules`) AND NOT FIND_IN_SET('10300', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10302, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10327 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10302);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10302') WHERE FIND_IN_SET('10327', `rules`) AND NOT FIND_IN_SET('10302', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10331, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 512 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10331);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10331') WHERE FIND_IN_SET('512', `rules`) AND NOT FIND_IN_SET('10331', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10333') WHERE FIND_IN_SET('10229', `rules`) AND NOT FIND_IN_SET('10333', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10334') WHERE FIND_IN_SET('10217', `rules`) AND NOT FIND_IN_SET('10334', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10335') WHERE FIND_IN_SET('10221', `rules`) AND NOT FIND_IN_SET('10335', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10336') WHERE FIND_IN_SET('10226', `rules`) AND NOT FIND_IN_SET('10336', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10337') WHERE FIND_IN_SET('10219', `rules`) AND NOT FIND_IN_SET('10337', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10338') WHERE FIND_IN_SET('7649', `rules`) AND NOT FIND_IN_SET('10338', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10339') WHERE FIND_IN_SET('10333', `rules`) AND NOT FIND_IN_SET('10339', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10340') WHERE FIND_IN_SET('10334', `rules`) AND NOT FIND_IN_SET('10340', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10341') WHERE FIND_IN_SET('10335', `rules`) AND NOT FIND_IN_SET('10341', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10342') WHERE FIND_IN_SET('10335', `rules`) AND NOT FIND_IN_SET('10342', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10343') WHERE FIND_IN_SET('7691', `rules`) AND NOT FIND_IN_SET('10343', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10344') WHERE FIND_IN_SET('7691', `rules`) AND NOT FIND_IN_SET('10344', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10345') WHERE FIND_IN_SET('7691', `rules`) AND NOT FIND_IN_SET('10345', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10346') WHERE FIND_IN_SET('7691', `rules`) AND NOT FIND_IN_SET('10346', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10347') WHERE FIND_IN_SET('7691', `rules`) AND NOT FIND_IN_SET('10347', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10348') WHERE FIND_IN_SET('7691', `rules`) AND NOT FIND_IN_SET('10348', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10349') WHERE FIND_IN_SET('7691', `rules`) AND NOT FIND_IN_SET('10349', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10350') WHERE FIND_IN_SET('10336', `rules`) AND NOT FIND_IN_SET('10350', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10351') WHERE FIND_IN_SET('10336', `rules`) AND NOT FIND_IN_SET('10351', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10352') WHERE FIND_IN_SET('10336', `rules`) AND NOT FIND_IN_SET('10352', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10353') WHERE FIND_IN_SET('10336', `rules`) AND NOT FIND_IN_SET('10353', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10354') WHERE FIND_IN_SET('10337', `rules`) AND NOT FIND_IN_SET('10354', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10355') WHERE FIND_IN_SET('10337', `rules`) AND NOT FIND_IN_SET('10355', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10356') WHERE FIND_IN_SET('10337', `rules`) AND NOT FIND_IN_SET('10356', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10357') WHERE FIND_IN_SET('10337', `rules`) AND NOT FIND_IN_SET('10357', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10358') WHERE FIND_IN_SET('10337', `rules`) AND NOT FIND_IN_SET('10358', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10359') WHERE FIND_IN_SET('10337', `rules`) AND NOT FIND_IN_SET('10359', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10360') WHERE FIND_IN_SET('10337', `rules`) AND NOT FIND_IN_SET('10360', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10361') WHERE FIND_IN_SET('10337', `rules`) AND NOT FIND_IN_SET('10361', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10362') WHERE FIND_IN_SET('10337', `rules`) AND NOT FIND_IN_SET('10362', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10363') WHERE FIND_IN_SET('10337', `rules`) AND NOT FIND_IN_SET('10363', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10364') WHERE FIND_IN_SET('7772', `rules`) AND NOT FIND_IN_SET('10364', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10365, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 8554 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10365);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10365') WHERE FIND_IN_SET('8554', `rules`) AND NOT FIND_IN_SET('10365', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10366, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 9892 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10366);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10366') WHERE FIND_IN_SET('9892', `rules`) AND NOT FIND_IN_SET('10366', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10367, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 9892 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10367);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10367') WHERE FIND_IN_SET('9892', `rules`) AND NOT FIND_IN_SET('10367', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10368, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 9892 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10368);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10368') WHERE FIND_IN_SET('9892', `rules`) AND NOT FIND_IN_SET('10368', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10369, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 9892 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10369);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10369') WHERE FIND_IN_SET('9892', `rules`) AND NOT FIND_IN_SET('10369', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10370, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 8567 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10370);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10370') WHERE FIND_IN_SET('8567', `rules`) AND NOT FIND_IN_SET('10370', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10371, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 8567 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10371);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10371') WHERE FIND_IN_SET('8567', `rules`) AND NOT FIND_IN_SET('10371', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10372, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 8567 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10372);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10372') WHERE FIND_IN_SET('8567', `rules`) AND NOT FIND_IN_SET('10372', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10373, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 8567 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10373);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10373') WHERE FIND_IN_SET('8567', `rules`) AND NOT FIND_IN_SET('10373', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10374, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10248 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10374);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10374') WHERE FIND_IN_SET('10248', `rules`) AND NOT FIND_IN_SET('10374', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10375, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10248 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10375);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10375') WHERE FIND_IN_SET('10248', `rules`) AND NOT FIND_IN_SET('10375', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10376, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10248 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10376);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10376') WHERE FIND_IN_SET('10248', `rules`) AND NOT FIND_IN_SET('10376', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10377, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10248 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10377);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10377') WHERE FIND_IN_SET('10248', `rules`) AND NOT FIND_IN_SET('10377', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10378, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10248 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10378);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10378') WHERE FIND_IN_SET('10248', `rules`) AND NOT FIND_IN_SET('10378', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10379, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10249 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10379);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10379') WHERE FIND_IN_SET('10249', `rules`) AND NOT FIND_IN_SET('10379', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10380, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 10327 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10380);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10380') WHERE FIND_IN_SET('10327', `rules`) AND NOT FIND_IN_SET('10380', `rules`);
INSERT INTO `eb_relevance` (`left_id`, `right_id`, `type`) SELECT DISTINCT p.`left_id`, 10381, 'mer_auth' FROM `eb_relevance` p WHERE p.`type` = 'mer_auth' AND p.`right_id` = 8595 AND NOT EXISTS (SELECT 1 FROM `eb_relevance` r WHERE r.`type` = 'mer_auth' AND r.`left_id` = p.`left_id` AND r.`right_id` = 10381);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10381') WHERE FIND_IN_SET('8595', `rules`) AND NOT FIND_IN_SET('10381', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10382') WHERE FIND_IN_SET('9694', `rules`) AND NOT FIND_IN_SET('10382', `rules`);
UPDATE `eb_system_role` SET `rules` = CONCAT(TRIM(TRAILING ',' FROM `rules`), ',', '10383') WHERE FIND_IN_SET('10064', `rules`) AND NOT FIND_IN_SET('10383', `rules`);

DROP PROCEDURE IF EXISTS crmeb_v42_add_col;
SET FOREIGN_KEY_CHECKS = 1;
