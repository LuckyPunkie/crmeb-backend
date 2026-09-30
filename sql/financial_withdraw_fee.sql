-- 2026-09-24：手续费从订单支付时收取改为商户提现时收取
-- eb_financial（提现申请）新增：提现时的费率快照 / 手续费金额 / 实际到账金额
ALTER TABLE `eb_financial`
    ADD COLUMN `withdraw_fee_rate` decimal(7,4) unsigned NOT NULL DEFAULT 0 COMMENT '提现时使用的手续费率快照(%)，来自商户自身设置或所属店铺分类' AFTER `extract_money`,
    ADD COLUMN `withdraw_fee` decimal(12,2) unsigned NOT NULL DEFAULT 0 COMMENT '本次提现扣除的手续费金额=extract_money*withdraw_fee_rate/100' AFTER `withdraw_fee_rate`,
    ADD COLUMN `actual_money` decimal(12,2) unsigned NOT NULL DEFAULT 0 COMMENT '实际转账到账金额=extract_money-withdraw_fee' AFTER `withdraw_fee`;
