<?php

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {
    Route::get('major_customer/config', 'admin.system.MajorCustomer/config')
        ->option(['_alias' => '大客户卡平台配置读取', '_auth' => true, '_path' => '/setting/major_customer']);

    Route::post('major_customer/config', 'admin.system.MajorCustomer/saveConfig')
        ->option(['_alias' => '大客户卡平台配置保存', '_auth' => true, '_path' => '/setting/major_customer']);

    Route::get('major_customer/virtual_recharge', 'admin.system.MajorCustomer/virtualRechargeList')
        ->option(['_alias' => '大客户卡虚拟充值记录', '_auth' => true, '_path' => '/setting/major_customer']);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
