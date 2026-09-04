<?php

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {

    Route::get('gift/lst', 'admin.gift.Gift/lst')
        ->name('adminGiftLst')
        ->option(['_alias' => '礼物列表', '_auth' => false, '_path' => '/gift/index']);

    Route::post('gift/create', 'admin.gift.Gift/create')
        ->name('adminGiftCreate')
        ->option(['_alias' => '新增礼物', '_auth' => true, '_path' => '/gift/index']);

    Route::post('gift/update/:id', 'admin.gift.Gift/update')
        ->name('adminGiftUpdate')
        ->option(['_alias' => '编辑礼物', '_auth' => true, '_path' => '/gift/index']);

    Route::post('gift/status/:id', 'admin.gift.Gift/status')
        ->name('adminGiftStatus')
        ->option(['_alias' => '上下架礼物', '_auth' => true, '_path' => '/gift/index']);

    Route::delete('gift/delete/:id', 'admin.gift.Gift/delete')
        ->name('adminGiftDelete')
        ->option(['_alias' => '删除礼物', '_auth' => true, '_path' => '/gift/index']);

    Route::get('gift/commission', 'admin.gift.GiftCommission/index')
        ->name('adminGiftCommissionIndex')
        ->option(['_alias' => '礼物抽佣配置', '_auth' => false, '_path' => '/gift/commission']);

    Route::post('gift/commission/save', 'admin.gift.GiftCommission/save')
        ->name('adminGiftCommissionSave')
        ->option(['_alias' => '保存礼物抽佣', '_auth' => true, '_path' => '/gift/commission']);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
