<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

declare (strict_types=1);

namespace app\command;

use app\common\repositories\animal_rescue\AdoptionRepository;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Cache;
use think\facade\Log;

/**
 * 保证金自动解冻任务
 * 每日凌晨2:00执行
 * 调用方式: php think animal_rescue:deposit_thaw
 */
class AnimalRescueDepositThaw extends Command
{
    const LOCK_KEY = 'animal_rescue:deposit_thaw_lock';
    const LOCK_TIMEOUT = 300;

    protected function configure()
    {
        $this->setName('animal_rescue:deposit_thaw')
            ->setDescription('领养保证金自动解冻：检查到期保证金并返还到用户钱包');
    }

    /**
     * 执行保证金解冻
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        // 分布式锁：防止多实例并发重复解冻（原子操作 SET NX EX）
        $redis = Cache::store('redis')->handler();
        if (!$redis->set(self::LOCK_KEY, 1, ['nx', 'ex' => self::LOCK_TIMEOUT])) {
            $output->writeln('已有解冻任务正在执行，本次跳过');
            return 0;
        }

        try {
            $output->writeln('========================================');
            $output->writeln('开始执行：领养保证金自动解冻');
            $output->writeln('执行时间：' . date('Y-m-d H:i:s'));
            $output->writeln('========================================');

            /** @var AdoptionRepository $adoptionRepo */
            $adoptionRepo = app()->make(AdoptionRepository::class);
            $remindCount = $adoptionRepo->remindDepositExpiring(7);
            $count = $adoptionRepo->autoThawDeposits();

            $output->writeln("到期提醒：共通知 {$remindCount} 笔即将解冻保证金");
            $output->writeln("执行完成：共解冻 {$count} 笔保证金");
            $output->writeln('========================================');
            Log::info('animal_rescue deposit_thaw done: remind=' . $remindCount . ' thaw=' . $count . ' time=' . date('Y-m-d H:i:s'));
            return 0;
        } catch (\Exception $e) {
            $output->writeln('执行异常：' . $e->getMessage());
            $output->writeln($e->getTraceAsString());
            Log::error('animal_rescue deposit_thaw error: ' . $e->getMessage());
            return 1;
        } finally {
            Cache::store('redis')->handler()->del(self::LOCK_KEY);
        }
    }
}
