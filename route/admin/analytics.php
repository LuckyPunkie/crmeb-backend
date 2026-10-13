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
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {

    //订单统计
    Route::group('analytics/order', function () {
        //顶部统计
        Route::get('top', '/top')->name('systemAnalyticsOrderTop')->option([
            '_alias' => '顶部统计',
        ]);
        Route::get('line_chart', '/lineChart')->name('systemAnalyticsOrderLineChart')->option([
            '_alias' => '折线图统计',
        ]);
        Route::get('pie_chart/:type', '/typePieCahrt')->name('systemAnalyticsOrderTypePieChart')->option([
            '_alias' => '折线图统计',
        ]);
    })->prefix('admin.analytics.StoreOrder')->option([
        '_path' => '/statistic/order',
        '_auth' => true,
    ]);

    //商品统计
    Route::group('analytics/product', function () {
        //顶部统计
        Route::get('top', '/top')->name('systemAnalyticsProductTop')->option([
            '_alias' => '顶部统计',
        ]);
        Route::get('line_chart', '/lineChart')->name('systemAnalyticsProductLineChart')->option([
            '_alias' => '折线图统计',
        ]);
        Route::get('pie_chart/:type', '/typePieCahrt')->name('systemAnalyticsProductTypePieChart')->option([
            '_alias' => '折线图统计',
        ]);
    })->prefix('admin.analytics.StoreProduct')->option([
        '_path' => '/statistic/product',
        '_auth' => true,
    ]);

    //用户统计
    Route::group('analytics/user', function () {
        //顶部统计
        Route::get('top', '/top')->name('systemAnalyticsUserTop')->option([
            '_alias' => '顶部统计',
        ]);
        Route::get('line_chart', '/lineChart')->name('systemAnalyticsUserLineChart')->option([
            '_alias' => '折线图统计',
        ]);
        Route::get('pie_chart', '/typePieCahrt')->name('systemAnalyticsUserTypePieChart')->option([
            '_alias' => '折线图统计',
        ]);
    })->prefix('admin.analytics.User')->option([
        '_path' => '/statistic/member',
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
