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
use think\facade\Log;

/**
 * 筹款到期检查任务
 * 每日凌晨1:00执行
 * 调用方式: php think animal_rescue:expire_check
 */
class AnimalRescueExpireCheck extends Command
{
    protected function configure()
    {
        $this->setName('animal_rescue:expire_check')
            ->setDescription('筹款到期检查：将到期帖子状态更新为已完成');
    }

    /**
     * 执行到期检查
     * @param Input $input
     * @param Output $output
     * @return int
     */
    protected function execute(Input $input, Output $output)
    {
        $output->writeln('========================================');
        $output->writeln('开始执行：筹款到期检查');
        $output->writeln('执行时间：' . date('Y-m-d H:i:s'));
        $output->writeln('========================================');

        try {
            /** @var AdoptionRepository $adoptionRepo */
            $adoptionRepo = app()->make(AdoptionRepository::class);
            $count = $adoptionRepo->checkExpiredPosts();

            $output->writeln("执行完成：共处理 {$count} 个到期帖子");
            $output->writeln('========================================');
            Log::info('animal_rescue expire_check done: count=' . $count . ' time=' . date('Y-m-d H:i:s'));
            return 0;
        } catch (\Exception $e) {
            $output->writeln('执行异常：' . $e->getMessage());
            $output->writeln($e->getTraceAsString());
            Log::error('animal_rescue expire_check error: ' . $e->getMessage());
            return 1;
        }
    }
}
