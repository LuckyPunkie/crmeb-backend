<?php

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

// 店铺详情页「线上下单」「买单」按钮 商家开关
Route::group(function () {
    Route::get('nearby/action_switch', '/detail')->name('merchantNearbyActionSwitch')->option(['_alias' => '线上下单/买单开关']);
    Route::post('nearby/action_switch', '/save')->name('merchantNearbyActionSwitchSave')->option(['_alias' => '保存线上下单/买单开关']);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class)
    ->prefix('merchant.store.nearby.NearbyActionSwitch')
    ->option(['_path' => '/scanOrder/config', '_auth' => true]);

// 买单支付成功页盲盒入口 商家开关（营销 > 盲盒入口）
Route::group(function () {
    Route::get('nearby/blindbox_switch', '/blindboxDetail')->name('merchantNearbyBlindboxSwitch')->option(['_alias' => '盲盒入口开关']);
    Route::post('nearby/blindbox_switch', '/blindboxSave')->name('merchantNearbyBlindboxSwitchSave')->option(['_alias' => '保存盲盒入口开关']);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class)
    ->prefix('merchant.store.nearby.NearbyActionSwitch')
    ->option(['_path' => '/marketing/blindboxSwitch', '_auth' => true]);

// 买单页公益商品 / 购物享免单 商家开关（营销 > 公益商品）
Route::group(function () {
    Route::get('nearby/welfare_switch', '/welfareDetail')->name('merchantNearbyWelfareSwitch')->option(['_alias' => '公益商品开关']);
    Route::post('nearby/welfare_switch', '/welfareSave')->name('merchantNearbyWelfareSwitchSave')->option(['_alias' => '保存公益商品开关']);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class)
    ->prefix('merchant.store.nearby.NearbyActionSwitch')
    ->option(['_path' => '/marketing/welfareSwitch', '_auth' => true]);
