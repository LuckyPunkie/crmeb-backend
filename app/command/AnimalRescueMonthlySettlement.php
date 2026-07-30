<?php

declare(strict_types=1);

namespace app\command;

use app\common\repositories\animal_rescue\FundAuditRepository;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Log;

/**
 * 救助站月捐结算
 * 每月1号 00:00 执行
 * php think animal_rescue:monthly_settlement
 * php think animal_rescue:monthly_settlement --month=2026-06
 */
class AnimalRescueMonthlySettlement extends Command
{
    protected function configure()
    {
        $this->setName('animal_rescue:monthly_settlement')
            ->addOption('month', 'm', Option::VALUE_OPTIONAL, '结算月份 YYYY-MM，默认上个月')
            ->setDescription('救助站月捐结算：将上月捐款转入商家钱包并重置进度');
    }

    protected function execute(Input $input, Output $output)
    {
        $month = $input->getOption('month') ?: null;
        $output->writeln('========================================');
        $output->writeln('开始执行：救助站月捐结算');
        $output->writeln('结算月份：' . ($month ?: date('Y-m', strtotime('first day of last month'))));
        $output->writeln('执行时间：' . date('Y-m-d H:i:s'));
        $output->writeln('========================================');

        try {
            /** @var FundAuditRepository $repo */
            $repo = app()->make(FundAuditRepository::class);
            $count = $repo->settleMonthly($month);
            $output->writeln("执行完成：共结算 {$count} 个救助站月捐帖子");
            Log::info('animal_rescue monthly_settlement done: count=' . $count);
            return 0;
        } catch (\Exception $e) {
            $output->writeln('执行异常：' . $e->getMessage());
            Log::error('animal_rescue monthly_settlement error: ' . $e->getMessage());
            return 1;
        }
    }
}
