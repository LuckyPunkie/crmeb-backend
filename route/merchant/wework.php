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
// | 企业微信群配置 商户后台API路由
// +----------------------------------------------------------------------

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

Route::group(function () {
    Route::get('wework/group', '/info')
        ->name('merchantWeworkGroupInfo')
        ->option(['_alias' => '企业微信群配置']);
    Route::post('wework/group', '/save')
        ->name('merchantWeworkGroupSave')
        ->option(['_alias' => '保存企业微信群配置']);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class)
    ->prefix('merchant.system.WeworkGroup')
    ->option([
        '_path' => '/systemForm/wework',
        '_auth' => true,
        '_alias' => '企业微信',
    ]);
