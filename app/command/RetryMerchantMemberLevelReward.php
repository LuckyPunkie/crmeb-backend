<?php

namespace app\command;

use app\common\repositories\merchant\MerchantMemberRepository;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 补发店铺会员入会和升级优惠券：命令行执行后按 limit 批量重试处理待发放的等级奖励
 */
class RetryMerchantMemberLevelReward extends Command
{
    /**
     * 配置命令名称、limit 选项及命令描述（补发店铺会员入会和升级优惠券）
     */
    protected function configure()
    {
        $this->setName('merchant:member-level-reward-retry')
            ->addOption('limit', 'l', Option::VALUE_OPTIONAL, '每次最多处理的等级奖励记录数', 100)
            ->setDescription('补发店铺会员入会和升级优惠券');
    }

    /**
     * 执行命令：按 limit 批量重试补发店铺会员入会和升级优惠券，并输出处理统计结果
     * @param Input $input 命令输入对象，用于获取 limit 选项值
     * @param Output $output 命令输出对象，用于向终端输出结果信息
     * @return int 命令执行成功返回退出码 0
     */
    protected function execute(Input $input, Output $output)
    {
        $limit = max(1, (int)$input->getOption('limit'));
        $result = app()->make(MerchantMemberRepository::class)->retryPendingLevelRewards($limit);
        $output->writeln(sprintf(
            '已处理%d条，完成%d条，本次新发%d张优惠券',
            $result['processed'],
            $result['completed'],
            $result['received']
        ));
        return 0;
    }
}
