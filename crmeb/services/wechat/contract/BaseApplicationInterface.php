<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services\wechat\contract;

use EasyWeChat\Pay\Contracts\Application;
use EasyWeChat\MiniApp\Contracts\Application as MiniAppApplication;
use EasyWeChat\OfficialAccount\Contracts\Application as ApplicationInterface;
use EasyWeChat\Work\Contracts\Application as WorkApplication;

/**
 * Interface BaseApplicationInterface
 * @package crmeb\services\wechat\contract
 */
interface BaseApplicationInterface
{

    /**
     * @return static
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public static function instance(): self;

    /**
     * @return ApplicationInterface|Application|MiniAppApplication|WorkApplication
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/14
     */
    public function application(): ApplicationInterface|Application|MiniAppApplication|WorkApplication;
}
