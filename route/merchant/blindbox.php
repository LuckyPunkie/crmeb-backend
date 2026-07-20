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

use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use think\facade\Route;

Route::group(function () {

    Route::group('store/blindbox', function () {
        Route::get('settings', '/settings')->name('merchantBlindBoxSettings')->option([
            '_alias' => '盲盒设置',
        ]);
        Route::post('settings', '/saveSettings')->name('merchantBlindBoxSaveSettings')->option([
            '_alias' => '保存盲盒设置',
        ]);
        Route::get('attr/list', '/attrList')->name('merchantBlindBoxAttrList')->option([
            '_alias' => '商品款式权重列表',
        ]);
        Route::post('attr/weight', '/updateAttrWeight')->name('merchantBlindBoxAttrWeight')->option([
            '_alias' => '更新款式概率权重',
        ]);
        Route::get('recycle/lst', '/recycleLst')->name('merchantBlindBoxRecycleLst')->option([
            '_alias' => '盲盒回收记录',
        ]);
        Route::get('recycle/stats', '/recycleStats')->name('merchantBlindBoxRecycleStats')->option([
            '_alias' => '盲盒回收统计',
        ]);
    })->prefix('merchant.store.BlindBox')->option([
        '_path' => '/product/blindbox',
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
