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

    //客服
    Route::group('staffs', function () {
        Route::get('list', 'Staffs/lst')->name('merchantStaffsLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('create', 'Staffs/create')->name('merchantStaffsCreate')->option([
            '_alias' => '添加',
        ]);
        Route::get('create/form', 'Staffs/createForm')->name('merchantStaffsCreateForm')->option([
            '_alias' => '添加表单',
            '_auth' => false,
            '_form' => 'merchantStaffsCreate',
        ]);
        Route::post('update/:id', 'Staffs/update')->name('merchantStaffsUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('update/form/:id', 'Staffs/updateForm')->name('merchantStaffsUpdateForm')->option([
            '_alias' => '编辑表单',
            '_auth' => false,
            '_form' => 'merchantStaffsUpdate',
        ]);
        Route::post('status/:id', 'Staffs/changeStatus')->name('merchantStaffsSwitchStatus')->option([
            '_alias' => '修改状态',
        ]);
        Route::delete('delete/:id', 'Staffs/delete')->name('merchantStaffsDelete')->option([
            '_alias' => '删除',
        ]);
        Route::get('statisticsList', 'Staffs/statisticsList')->name('merchantStaffsStatisticsList')->option([
            '_alias' => '列表',
        ]);
        Route::get('statisticsDetail/:id', 'Staffs/statisticsDetail')->name('merchantStaffsStatisticsDetail')->option([
            '_alias' => '详情',
        ]);
    })->prefix('merchant.store.')->option([
        '_path' => '/config/service_staff',
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
