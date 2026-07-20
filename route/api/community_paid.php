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

use think\facade\Route;
use app\common\middleware\UserTokenMiddleware;

// 需要登录认证的路由
Route::group(function () {
    Route::post('community/paid/create', 'api.community.CommunityPaidContent/create');
    Route::post('community/paid/unlock/:id', 'api.community.CommunityPaidContent/unlock');
    Route::get('community/paid/check/:id', 'api.community.CommunityPaidContent/check');
    Route::get('community/paid/income', 'api.community.CommunityPaidContent/income');
    Route::get('community/paid/orders', 'api.community.CommunityPaidContent/orders');
})->middleware(UserTokenMiddleware::class, true);

// 无需登录的路由
Route::group(function () {
    Route::get('community/paid/detail/:id', 'api.community.CommunityPaidContent/detail');
})->middleware(UserTokenMiddleware::class, false);
