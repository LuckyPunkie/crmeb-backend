<?php

namespace app\command;

use app\common\repositories\merchant\ShoppingCreditsRefundRepository;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 查询并重试退款中的购物金退款单：命令行执行后按 limit 批量确认或重试处理中的退款单
 */
class QueryShoppingCreditsRefund extends Command
{
    /**
     * 配置命令名称、limit 选项及命令描述（查询并重试退款中的购物金退款单）
     */
    protected function configure()
    {
        $this->setName('merchant:shopping-credits-refund-query')
            ->addOption('limit', 'l', Option::VALUE_OPTIONAL, '每次最多查询的退款单数量', 100)
            ->setDescription('查询并重试退款中的购物金退款单');
    }

    /**
     * 执行命令：按 limit 批量查询并重试处理退款中的购物金退款单，输出处理结果
     * @param Input $input 命令输入对象，用于获取 limit 选项值
     * @param Output $output 命令输出对象，用于向终端输出结果信息
     * @return int 命令执行成功返回退出码 0
     */
    protected function execute(Input $input, Output $output)
    {
        $limit = max(1, (int)$input->getOption('limit'));
        $count = app()->make(ShoppingCreditsRefundRepository::class)->retryProcessingRefunds($limit);
        $output->writeln('已确认支付宝或提交重试的购物金退款：' . $count . '笔');
        return 0;
    }
}
