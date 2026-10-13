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
use app\common\middleware\MerchantTokenMiddleware;
use think\facade\Route;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

Route::group(function () {

    //商户员工对应的用户列表
    Route::get('manager/user/lst', [app\controller\merchant\user\UserMerchant::class,'managerUserLst']);

    //搜索记录
    Route::get('user/search_log', [app\controller\admin\user\User::class, 'searchLog'])
        ->name('merchantUserSearchLog')->option([
        '_alias' => '搜索记录',
        '_path' => '/user/searchRecord',
        '_auth' => true,
    ]);

    Route::get('user/search_log/export', [app\controller\admin\user\User::class, 'exportSearchLog'])->name('merchantUserExportSearchLog')
        ->option([
        '_alias' => '用户搜索记录导出',
        '_path' => '/user/searchRecord',
    ]);
    //清除搜索记录
    Route::get('user/clear_search_log', [app\controller\admin\user\User::class, 'clearSearchLog'])->name('merchantUserClearSearchLog')->option([
        '_alias' => '清除用户搜索记录',
    ]);

    //商户用户列表
    Route::group('user', function () {
        Route::get('lst', [app\controller\merchant\user\UserMerchant::class, 'getList'])->name('merchantUserLst')->option([
            '_alias' => '列表',
        ]);
        //修改用户标签
        Route::get('change_label/form/:id', [app\controller\merchant\user\UserMerchant::class, 'changeLabelForm'])->name('merchantUserChangeLabelForm')->option([
            '_alias' => '修改标签表单',
            '_auth' => false,
            '_form' => 'merchantUserChangeLabel',
        ]);
        Route::post('change_label/:id', [app\controller\merchant\user\UserMerchant::class, 'changeLabel'])->name('merchantUserChangeLabel')->option([
            '_alias' => '修改标签',
        ]);
        Route::get('order/:uid', [app\controller\merchant\user\UserMerchant::class, 'order'])->name('merchantUserOrder')->option([
            '_alias' => '订单列表',
        ]);
        Route::get('coupon/:uid', [app\controller\merchant\user\UserMerchant::class, 'coupon'])->name('merchantUserCoupon')->option([
            '_alias' => '优惠券',
        ]);
    })->option([
        '_path' => '/user/list',
        '_auth' => true,
    ]);

    //用户自动标签
    Route::group('auto_label', function () {
        Route::get('lst', [app\controller\merchant\user\LabelRule::class, 'getList'])->name('merchantLabelRuleLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('create', [app\controller\merchant\user\LabelRule::class, 'create'])->name('merchantLabelRuleCreate')->option([
            '_alias' => '添加',
        ]);
        Route::post('update/:id', [app\controller\merchant\user\LabelRule::class, 'update'])->name('merchantLabelRuleUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::delete('delete/:id', [app\controller\merchant\user\LabelRule::class, 'delete'])->name('merchantLabelRuleDelete')->option([
            '_alias' => '删除',
        ]);
        Route::post('sync/:id', [app\controller\merchant\user\LabelRule::class, 'sync'])->name('merchantLabelRuleSync')->option([
            '_alias' => '自动同步',
        ]);
    })->option([
        '_path' => '/user/maticlabel',
        '_auth' => true,
    ]);

    //手动标签
    Route::group('user/label', function () {
        Route::get('lst', [app\controller\admin\user\UserLabel::class, 'lst'])->name('merchantUserLabelLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('user/label', [app\controller\admin\user\UserLabel::class, 'create'])->name('merchantUserLabelCreate')->option([
            '_alias' => '添加',
        ]);
        Route::get('form', [app\controller\admin\user\UserLabel::class, 'createForm'])->name('merchantUserLabelCreateForm')->option([
            '_alias' => '添加表单',
            '_auth' => false,
            '_form' => 'merchantUserLabelCreate',
        ]);
        Route::delete(':id', [app\controller\admin\user\UserLabel::class, 'delete'])->name('merchantUserLabelDelete')->option([
            '_alias' => '删除',
        ]);
        Route::post(':id', [app\controller\admin\user\UserLabel::class, 'update'])->name('merchantUserLabelUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('form/:id', [app\controller\admin\user\UserLabel::class, 'updateForm'])->name('merchantUserLabelUpdateForm')->option([
            '_alias' => '编辑表单',
            '_auth' => false,
            '_form' => 'merchantUserLabelUpdate',
        ]);
    })->option([
        '_path' => '/user/label',
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
