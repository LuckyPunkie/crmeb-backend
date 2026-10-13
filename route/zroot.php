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

use app\common\middleware\CheckSiteOpenMiddleware;
use app\common\middleware\InstallMiddleware;
use think\facade\Route;

Route::group(config('admin.service_prefix'), function () {
    Route::miss(function () {
        $DB = DIRECTORY_SEPARATOR;
        return __view(app()->getRootPath() . 'public' . $DB . 'ser.html');
    });
})->middleware(InstallMiddleware::class)
    ->middleware(CheckSiteOpenMiddleware::class);

Route::group(config('admin.merchant_prefix'), function () {
    Route::miss(function () {
        $DB = DIRECTORY_SEPARATOR;
        return __view(app()->getRootPath() . 'public' . $DB . 'mer.html');
    });
})->middleware(InstallMiddleware::class)
    ->middleware(CheckSiteOpenMiddleware::class);

Route::group(config('admin.admin_prefix'), function () {
    Route::miss(function () {
        $DB = DIRECTORY_SEPARATOR;
        return __view(app()->getRootPath() . 'public' . $DB . 'system.html');
    });
})->middleware(InstallMiddleware::class);

Route::group(function(){
    $DS = DIRECTORY_SEPARATOR;
    if (is_dir(app()->getRootPath() . 'public' . $DS . 'pc')) {
        Route::any('/', 'pc.View/pc');
    } else {
        Route::any('/', 'View/h5');
    }

    Route::group('pages', function () {
        Route::miss('View/h5');
    });

    Route::group('open-location', function () {
        Route::miss('View/h5');
    });

    Route::miss(function(){
        $DS = DIRECTORY_SEPARATOR;
        if (is_dir(app()->getRootPath() . 'public' . $DS . 'pc')) {
            if (file_exists(app()->getRootPath() . 'public' . $DS . 'pc'. $DS . 'index.html')) {
                return app()->make(\app\controller\pc\View::class)->pc();
            }
        }
        return  app()->make(\app\controller\View::class)->h5();
    });
})
    ->middleware(InstallMiddleware::class)
    ->middleware(CheckSiteOpenMiddleware::class);

