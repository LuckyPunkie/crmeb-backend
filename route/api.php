<?php
//
//// +----------------------------------------------------------------------
//// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
//// +----------------------------------------------------------------------
//// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
//// +----------------------------------------------------------------------
//// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
//// +----------------------------------------------------------------------
//// | Author: CRMEB Team <admin@crmeb.com>
//// +----------------------------------------------------------------------

use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\CheckSiteOpenMiddleware;
use app\common\middleware\InstallMiddleware;
use app\common\middleware\RequestLockMiddleware;
use think\facade\Route;

Route::group('api/', function () {
    $path = $this->app->getRootPath() . 'route' . DIRECTORY_SEPARATOR.'api';
    $files = scandir($path);
    foreach ($files as $file) {
        if($file != '.' && $file != '..'){
            include $path . DIRECTORY_SEPARATOR . $file;
        }
    }
})
    ->middleware(AllowOriginMiddleware::class)
    ->middleware(InstallMiddleware::class)
    ->middleware(CheckSiteOpenMiddleware::class)
    ->middleware(RequestLockMiddleware::class);

