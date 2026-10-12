<?php

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\LogMiddleware;

// 店铺功能开关 平台总开关
Route::group(function () {
    // 店铺详情页「线上下单」「买单」按钮
    Route::group('nearby/action_switch', function () {
        Route::get('', '/getModes')->name('systemNearbyActionSwitch')->option(['_alias' => '买单下单设置']);
        Route::post('', '/saveModes')->name('systemNearbyActionSwitchSave')->option(['_alias' => '保存买单下单设置']);
    })->prefix('admin.system.merchant.MerchantActionSwitch')->option([
        '_path' => '/merchant/action-switch',
        '_auth' => true,
    ]);
    // 买单支付成功页盲盒入口
    Route::group('nearby/blindbox_switch', function () {
        Route::get('', '/getBlindboxModes')->name('systemNearbyBlindboxSwitch')->option(['_alias' => '盲盒入口设置']);
        Route::post('', '/saveBlindboxModes')->name('systemNearbyBlindboxSwitchSave')->option(['_alias' => '保存盲盒入口设置']);
    })->prefix('admin.system.merchant.MerchantActionSwitch')->option([
        '_path' => '/merchant/blindbox-switch',
        '_auth' => true,
    ]);
    // 买单页公益商品 / 购物享免单
    Route::group('nearby/welfare_switch', function () {
        Route::get('', '/getWelfareModes')->name('systemNearbyWelfareSwitch')->option(['_alias' => '公益商品设置']);
        Route::post('', '/saveWelfareModes')->name('systemNearbyWelfareSwitchSave')->option(['_alias' => '保存公益商品设置']);
    })->prefix('admin.system.merchant.MerchantActionSwitch')->option([
        '_path' => '/merchant/welfare-switch',
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
