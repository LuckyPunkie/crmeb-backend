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

    Route::group('blindbox', function () {
        Route::get('recycle/lst', '/recycleLst')->name('adminBlindBoxRecycleLst')->option([
            '_alias' => '盲盒回收记录列表',
        ]);
        Route::get('recycle/stats', '/recycleStats')->name('adminBlindBoxRecycleStats')->option([
            '_alias' => '盲盒回收统计',
        ]);
        Route::get('shop_options', '/shopOptions')->name('adminBlindBoxShopOptions')->option([
            '_alias' => '盲盒店铺选项',
            '_auth' => false,
        ]);
        Route::get('product_options', '/productOptions')->name('adminBlindBoxProductOptions')->option([
            '_alias' => '盲盒商品选项',
            '_auth' => false,
        ]);

        Route::get('merchants', '/blindboxMerchants')->name('adminBlindBoxMerchants')->option([
            '_alias' => '盲盒商户列表',
        ]);
        Route::post('enable/:id', '/enableBlindbox')->name('adminBlindBoxEnable')->option([
            '_alias' => '开启盲盒权限',
        ]);
        Route::post('disable/:id', '/disableBlindbox')->name('adminBlindBoxDisable')->option([
            '_alias' => '关闭盲盒权限',
        ]);
        Route::post('batch', '/batchBlindbox')->name('adminBlindBoxBatch')->option([
            '_alias' => '批量设置盲盒权限',
        ]);
    })->prefix('admin.store.BlindBox')->option([
        '_path' => '/marketing/blindbox',
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
