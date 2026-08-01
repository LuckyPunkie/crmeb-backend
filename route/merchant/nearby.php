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
// | 附近好店 商户后台API路由
// +----------------------------------------------------------------------

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

Route::group(function () {
    // 店铺设置
    Route::get('nearby/shop/config', '/config')
        ->name('merchantNearbyShopConfig')
        ->option(['_alias' => '附近好店设置']);
    Route::post('nearby/shop/config', '/saveConfig')
        ->name('merchantNearbyShopConfigSave')
        ->option(['_alias' => '保存设置']);
    Route::get('nearby/shop/scan_pay_qrcode', '/scanPayQrcode')
        ->name('merchantNearbyScanPayQrcode')
        ->option(['_alias' => '扫码买单收款码', '_auth' => false]);
    // 收款语音待播队列（网页轮询兜底，WebSocket 漏推时仍能播）
    Route::get('nearby/shop/voice_pending', '/voicePending')
        ->name('merchantNearbyVoicePending')
        ->option(['_alias' => '收款语音待播', '_auth' => false]);

    // 分类树（商户后台表单下拉，走 /mer 同源避免 CORS）
    Route::get('nearby/category/tree', '/categoryTree')
        ->name('merchantNearbyCategoryTree')
        ->option(['_alias' => '附近好店分类树']);

    // 推荐菜管理
    Route::group('nearby/recommend', function () {
        Route::get('lst', '/lst')->name('merchantNearbyRecommendLst');
        Route::post('create', '/create')->name('merchantNearbyRecommendCreate');
        Route::post(':id', '/update')->name('merchantNearbyRecommendUpdate');
        Route::delete(':id', '/delete')->name('merchantNearbyRecommendDelete');
    })->prefix('merchant.store.nearby.NearbyRecommend')
        ->option(['_path' => '/nearby/recommend', '_auth' => true]);

    // 套餐管理
    Route::group('nearby/package', function () {
        Route::get('lst', '/lst')->name('merchantNearbyPackageLst');
        Route::post('create', '/create')->name('merchantNearbyPackageCreate');
        Route::post(':id', '/update')->name('merchantNearbyPackageUpdate');
        Route::delete(':id', '/delete')->name('merchantNearbyPackageDelete');
    })->prefix('merchant.store.nearby.NearbyPackage')
        ->option(['_path' => '/nearby/package', '_auth' => true]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class)
    ->prefix('merchant.store.nearby.NearbyShop');
