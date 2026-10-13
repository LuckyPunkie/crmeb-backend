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

declare (strict_types = 1);

namespace app;

use think\Service;
use crmeb\services\wechat\config\HttpCommonConfig;
use app\common\repositories\system\config\ConfigValueRepository;

/**
 * 应用服务类
 */
class AppService extends Service
{

    public function register()
    {
        defined('TOP_PRECISION') || define('TOP_PRECISION', 2);
        // 服务注册
        
        //http配置服务
        $this->app->bind(HttpCommonConfig::class, function () {
            return (new HttpCommonConfig())->setServe(ConfigValueRepository::class);
        });
    }

    public function boot()
    {
        // 服务启动
    }
}
