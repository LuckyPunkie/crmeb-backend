<?php

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {

    Route::get('commission/config', 'admin.commission.CommissionConfig/index')
        ->name('commissionConfigIndex')
        ->option(['_alias' => '抽成配置详情', '_auth' => false, '_path' => '/commissionConfig/index']);

    Route::post('commission/config/save', 'admin.commission.CommissionConfig/save')
        ->name('commissionConfigSave')
        ->option(['_alias' => '保存抽成配置', '_auth' => true, '_path' => '/commissionConfig/index']);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
