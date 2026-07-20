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

use app\common\repositories\store\CityAreaRepository;
use app\common\repositories\store\product\SpuRepository;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\console\input\Option;

class updateCityArea extends Command
{
    protected function configure()
    {
        // 指令配置
        $this->setName('update:city')
            ->setDescription('更新数据地址信息，城市数据');
    }

    /**
     * 执行城市数据更新
     * @Author:Qinii
     * @Date: 2020/5/15
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    protected function execute(Input $input, Output $output)
    {
        $output->writeln('开始执行');
        $this->updateData();
        $this->changSnum();
        $output->writeln('执行完成');
    }

    /**
     * 如果需要重新导入数据，将需要导入的文件 addres.txt 文件放到项目目录下 和 public平级
     * @return bool
     * @author Qinii
     * @day 2024/1/19
     */
    public function updateData()
    {
        $fiel = root_path().'addres.txt';
        if (file_exists($fiel)) {
            app()->make(CityAreaRepository::class)->updateCityForTxt($fiel);
        }
        return true;
    }

    /**
     *  统计每个地址的子集数量
     * @author Qinii
     * @day 2024/1/19
     */
    public function changSnum()
    {
        app()->make(CityAreaRepository::class)->sumChildren();
    }

}
