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

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {

    Route::group('store/form',function () {
        Route::post('create', '/create')->name('merFormCreate')->option([
            '_alias' => '添加',
            '_auth' => true,
        ]);
        Route::post('update/:id', '/update')->name('merFormUpdate')->option([
            '_alias' => '编辑',
            '_auth' => true,
        ]);
        Route::delete('delete/:id', '/delete')->name('merFormDelete')->option([
            '_alias' => '删除',
            '_auth' => true,
        ]);
        Route::get('detail/:id', '/detail')->name('merFormDetail')->option([
            '_alias' => '详情',
            '_auth' => true,
        ]);
        Route::get('lst', '/lst')->name('merFormLst')->option([
            '_alias' => '列表',
            '_auth' => true,
        ]);

        Route::get('select', '/select');
        Route::get('info/:id', '/info');

    })->prefix('admin.system.form.Form')->option([
        '_path' => '/systemForm/form_list',
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(\app\common\middleware\MerchantTokenMiddleware::class, true)
    ->middleware(\app\common\middleware\MerchantAuthMiddleware::class)
    ->middleware(\app\common\middleware\MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
