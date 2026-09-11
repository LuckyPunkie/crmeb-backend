<?php

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {

    Route::get('service_brand_tab/config', 'admin.taoke.ServiceBrandTab/index')
        ->name('serviceBrandTabIndex')
        ->option(['_alias' => '服务页品牌类别配置', '_auth' => false, '_path' => '/serviceBrandTab/index']);

    Route::post('service_brand_tab/config/save', 'admin.taoke.ServiceBrandTab/save')
        ->name('serviceBrandTabSave')
        ->option(['_alias' => '保存服务页品牌类别', '_auth' => true, '_path' => '/serviceBrandTab/index']);

    Route::get('service_tab_config', 'admin.taoke.ServiceTabConfig/index')
        ->name('serviceTabConfigIndex')
        ->option(['_alias' => '服务页Tab配置列表', '_auth' => false, '_path' => '/serviceBrandTab/index']);

    Route::post('service_tab_config/save', 'admin.taoke.ServiceTabConfig/save')
        ->name('serviceTabConfigSave')
        ->option(['_alias' => '保存服务页Tab配置', '_auth' => true, '_path' => '/serviceBrandTab/index']);

    Route::post('service_tab_config/delete', 'admin.taoke.ServiceTabConfig/delete')
        ->name('serviceTabConfigDelete')
        ->option(['_alias' => '删除服务页Tab配置', '_auth' => true, '_path' => '/serviceBrandTab/index']);

    Route::get('official_service_tab_config', 'admin.taoke.OfficialServiceTabConfig/index')
        ->name('officialServiceTabConfigIndex')
        ->option(['_alias' => '平台直连Tab配置列表', '_auth' => false, '_path' => '/serviceOfficialTab/index']);

    Route::post('official_service_tab_config/save', 'admin.taoke.OfficialServiceTabConfig/save')
        ->name('officialServiceTabConfigSave')
        ->option(['_alias' => '保存平台直连Tab配置', '_auth' => true, '_path' => '/serviceOfficialTab/index']);

    Route::post('official_service_tab_config/delete', 'admin.taoke.OfficialServiceTabConfig/delete')
        ->name('officialServiceTabConfigDelete')
        ->option(['_alias' => '删除平台直连Tab配置', '_auth' => true, '_path' => '/serviceOfficialTab/index']);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
