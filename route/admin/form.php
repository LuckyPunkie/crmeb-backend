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

    Route::group('system/form',function () {
        Route::post('create', '/create')->name('systemFormCreate')->option([
            '_alias' => '添加',
            '_auth' => true,
        ]);
        Route::post('update/:id', '/update')->name('systemFormUpdate')->option([
            '_alias' => '编辑',
            '_auth' => true,
        ]);
        Route::post('status/:id', '/statusSwitch')->name('systemFormStatusSwitch')->option([
            '_alias' => '编辑状态',
            '_auth' => true,
        ]);
        Route::delete('delete/:id', '/delete')->name('systemFormDelete')->option([
            '_alias' => '删除',
            '_auth' => true,
        ]);
        Route::get('detail/:id', '/detail')->name('systemFormDetail')->option([
            '_alias' => '详情',
            '_auth' => true,
        ]);
        Route::get('lst', '/lst')->name('systemFormLst')->option([
            '_alias' => '列表',
            '_auth' => true,
        ]);

        Route::get('excel', '/excel')->name('systemFormExcel')->option([
            '_alias' => '导出',
            '_auth' => true,
        ]);
        Route::get('user_lst/:id', '/formUserList')->name('systemFormUserLst')->option([
            '_alias' => '表单提交记录',
            '_auth' => true,
        ]);
        Route::get('select', '/select');
        Route::get('info/:id', '/info');
    })->prefix('admin.system.form.Form')->option([
        '_path' => '/systemForm/form_list',
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
