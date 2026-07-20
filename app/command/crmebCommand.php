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


namespace app\command;


use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\Exception;
use think\facade\Db;

class crmebCommand extends Command
{
    protected function configure()
    {
        $this->setName('cm_cli')
            ->setDescription('crmeb_merchant命令集');
    }

    /**
     *  获取所有可使用的命令
     * @param Input $input
     * @param Output $output
     * @return void
     * @author Qinii
     */
    protected function execute(Input $input, Output $output)
    {
        $context = '-------------------------------------------------------------------------------------------------------------- ' . PHP_EOL;
        $context .= $this->get_msage('php think menu','自动同步路由权限');
        $context .= $this->get_msage('php think spu','将所有商品加入到spu表');
        $context .= $this->get_msage('php think clear:attachment','清除缓存素材,（图片信息）');
        $context .= $this->get_msage('php think version:update','版本更新（代码升级/pc代码安装升级）');
        $context .= $this->get_msage('php think clear:merchant','清除所有[除配置相关之外]的数据');
        $context .= $this->get_msage('php think clear:redundancy','清除所有已删除的商户的商品相关数据');
        $context .= $this->get_msage('php think reset:password','重置平台管理员的密码:php think reset:password admin --pwd 123456');
        $context .= $this->get_msage('php think reset:imagePath','修改图片地址前缀: php think reset:imagePath http://old.com  http://new.com');
        $context .= $this->get_msage('php think clear:cache','清除登录限制');
        $context .= $this->get_msage('php think change:hotTop','更新热卖榜单');
        $context .= $this->get_msage('php think update:city','更新城市数据:将需要导入的文件 addres.txt 文件放到项目目录下');
        $context .= $this->get_msage('php think createTool','根据数据表创建新的模块');
        $output->info('多商户可使用的命令：'.PHP_EOL.$context);
    }

    public function get_msage($name,$value)
    {
        $context = "\e[34m" . str_pad($name, 30, ' ', STR_PAD_RIGHT) .'|    '. "\e[32m" . $value . "\e[0m \n";
        $context .= '-------------------------------------------------------------------------------------------------------------- '. PHP_EOL;
        return $context;
    }

}
