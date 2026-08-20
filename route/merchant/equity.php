<?php

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

Route::group(function () {
    Route::get('equity/config', '/config')->name('merchantEquityConfig')->option(['_alias' => '消费送股配置']);
    Route::post('equity/config', '/saveConfig')->name('merchantEquityConfigSave')->option(['_alias' => '保存消费送股配置']);
    Route::get('equity/progress', '/progress')->name('merchantEquityProgress')->option(['_alias' => '消费送股进度']);
    Route::get('equity/transactions', '/transactions')->name('merchantEquityTransactions')->option(['_alias' => '最近入股记录']);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class)
    ->prefix('merchant.store.equity.EquityConfig')
    ->option(['_path' => '/marketing/equity', '_auth' => true]);
