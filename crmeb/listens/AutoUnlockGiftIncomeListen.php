<?php

namespace crmeb\listens;

use app\common\dao\user\UserBillDao;
use app\common\repositories\gift\GiftRepository;
use crmeb\interfaces\ListenerInterface;
use crmeb\services\TimerService;
use think\facade\Db;

/**
 * 礼物收益、微信号解锁收益冻结期满后自动解冻进佣金（可提现）
 * 冻结天数统一用 GiftRepository::FREEZE_DAYS
 */
class AutoUnlockGiftIncomeListen extends TimerService implements ListenerInterface
{
    public function handle($event): void
    {
        $this->tick(1000 * 60 * 20, function () {
            $billDao = app()->make(UserBillDao::class);
            request()->clearCache();
            $time = date('Y-m-d H:i:s', strtotime('-' . GiftRepository::FREEZE_DAYS . ' days'));
            $bills = $billDao->getTimeoutGiftIncomeBill($time);
            Db::transaction(function () use ($bills) {
                foreach ($bills as $bill) {
                    if ($bill->number > 0 && $bill->user) {
                        $amount = $bill->number;
                        $bill->user->brokerage_price = bcadd($bill->user->brokerage_price, $amount, 2);
                        $bill->user->save();
                    }
                    $bill->status = 1;
                    $bill->balance = $bill->user ? $bill->user->brokerage_price : 0;
                    $bill->save();
                }
            });
        });
    }
}
