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
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

Route::group(function () {

    //订单统计
    Route::group('analytics/order', function () {
        //顶部统计
        Route::get('top', '/top')->name('merchantAnalyticsOrderTop')->option([
            '_alias' => '顶部统计',
        ]);
        Route::get('line_chart', '/lineChart')->name('merchantAnalyticsOrderLineChart')->option([
            '_alias' => '折线图统计',
        ]);
        Route::get('pie_chart/:type', '/typePieCahrt')->name('merchantAnalyticsOrderTypePieChart')->option([
            '_alias' => '折线图统计',
        ]);
    })->prefix('admin.analytics.StoreOrder')->option([
        '_path' => '/statistic/order',
        '_auth' => true,
    ]);

    //商品统计
    Route::group('analytics/product', function () {
        //顶部统计
        Route::get('top', '/top')->name('merchantAnalyticsProductTop')->option([
            '_alias' => '顶部统计',
        ]);
        Route::get('line_chart', '/lineChart')->name('merchantAnalyticsProductLineChart')->option([
            '_alias' => '折线图统计',
        ]);
        Route::get('pie_chart/:type', '/typePieCahrt')->name('merchantAnalyticsProductTypePieChart')->option([
            '_alias' => '折线图统计',
        ]);
    })->prefix('admin.analytics.StoreProduct')->option([
        '_path' => '/statistic/product',
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
