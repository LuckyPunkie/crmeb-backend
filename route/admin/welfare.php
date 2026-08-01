<?php

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {

    Route::group('welfare', function () {
        Route::get('merchants', '/merchants')->name('adminWelfareMerchants')->option([
            '_alias' => '公益商户列表',
        ]);
        Route::post('enable/:id', '/enable')->name('adminWelfareEnable')->option([
            '_alias' => '开启公益店铺',
        ]);
        Route::post('disable/:id', '/disable')->name('adminWelfareDisable')->option([
            '_alias' => '关闭公益店铺',
        ]);
        Route::post('batch', '/batch')->name('adminWelfareBatch')->option([
            '_alias' => '批量设置公益店铺',
        ]);
    })->prefix('admin.store.WelfareShop')->option([
        '_path' => '/marketing/welfare',
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
