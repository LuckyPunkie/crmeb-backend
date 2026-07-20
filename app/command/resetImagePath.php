<?php

declare(strict_types=1);

namespace app\command;

use app\common\model\article\Article;
use app\common\model\article\ArticleCategory;
use app\common\model\community\Community;
use app\common\model\community\CommunityTopic;
use app\common\model\store\broadcast\BroadcastGoods;
use app\common\model\store\broadcast\BroadcastRoom;
use app\common\model\store\Guarantee;
use app\common\model\store\product\Product;
use app\common\model\store\product\ProductAssistUser;
use app\common\model\store\product\ProductAttrValue;
use app\common\model\store\product\ProductGroupUser;
use app\common\model\store\product\ProductReply;
use app\common\model\store\product\Spu;
use app\common\model\store\service\StoreService;
use app\common\model\store\StoreCategory;
use app\common\model\store\StoreSeckillTime;
use app\common\model\system\attachment\Attachment;
use app\common\model\system\financial\Financial;
use app\common\model\system\merchant\Merchant;
use app\common\model\system\merchant\MerchantIntention;
use app\common\model\user\MemberInterests;
use app\common\model\user\User;
use app\common\model\user\UserBrokerage;
use crmeb\services\ImageHostService;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Log;

class resetImagePath extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('reset:imagePath')
            ->addArgument('origin', Argument::OPTIONAL, 'path:http:/crmeb.com')
            ->addArgument('replace', Argument::OPTIONAL, 'path:http:/crmeb.com')
            ->setDescription('php think reset:imagePath http://old.com  http://new.com');
    }

    /**
     * 重置图片路径
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/12
     */
    protected function execute(Input $input, Output $output)
    {
        $origin = $input->getArgument('origin');
        $replace = $input->getArgument('replace');
        $output->writeln('开始执行');
        $service = ImageHostService::getInstance();
        $res = $service->execute($origin,$replace);
        if ($res) {
            $output->info('执行完成');
        } else {
            $output->warning('执行过程中存在错误,请在runtime/log中查看具体错误信息');
        }
    }
}

