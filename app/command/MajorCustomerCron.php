<?php

declare(strict_types=1);

namespace app\command;

use app\common\repositories\major_customer\MajorCustomerRepository;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\Output;
use think\facade\Cache;
use think\facade\Log;

/**
 * 大客户卡定时任务
 * php think major_customer:cron recharge|rebate|weekly|all
 */
class MajorCustomerCron extends Command
{
    protected function configure()
    {
        $this->setName('major_customer:cron')
            ->addArgument('action', Argument::OPTIONAL, 'recharge|rebate|weekly|all', 'all')
            ->setDescription('大客户卡：虚拟充值、折扣返还、预存周更');
    }

    protected function execute(Input $input, Output $output)
    {
        $action = (string)$input->getArgument('action');
        $lockKey = 'major_customer:cron:' . $action;
        $redis = Cache::store('redis')->handler();
        if (!$redis->set($lockKey, 1, ['nx', 'ex' => 600])) {
            $output->writeln('任务进行中，跳过');
            return 0;
        }

        try {
            /** @var MajorCustomerRepository $repo */
            $repo = app()->make(MajorCustomerRepository::class);
            if ($action === 'recharge' || $action === 'all') {
                $n = $repo->runPendingVirtualRecharges();
                $output->writeln('虚拟充值执行：' . $n);
            }
            if ($action === 'rebate' || $action === 'all') {
                $n = $repo->runDueRebates();
                $output->writeln('折扣返还：' . $n);
            }
            if ($action === 'weekly' || $action === 'all') {
                $n = $repo->runWeeklyPrestoreSnapshot();
                $output->writeln('预存周更：' . $n);
            }
            Log::info('major_customer:cron done action=' . $action);
            return 0;
        } catch (\Throwable $e) {
            $output->writeln('异常：' . $e->getMessage());
            Log::error('major_customer:cron ' . $e->getMessage());
            return 1;
        } finally {
            $redis->del($lockKey);
        }
    }
}
