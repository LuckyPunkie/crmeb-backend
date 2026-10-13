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

    //店铺分组

    Route::group('system/merchant/', function () {
        Route::get('group/lst', '/list')->name('systemStoreGroupLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('group/detail/:id', '/detail')->name('systemStoreGroupDetail')->option([
            '_alias' => '详情',
        ]);
        Route::post('group/create', '/create')->name('systemStoreGroupCreate')->option([
            '_alias' => '添加',
        ]);
        Route::post('group/update/:id', '/update')->name('systemStoreGroupUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::delete('group/delete/:id', '/delete')->name('systemStoreGroupDelete')->option([
            '_alias' => '删除',
        ]);
        Route::post('group/status/:id', '/switchStatus')->name('systemStoreGroupSwitchStatus')->option([
            '_alias' => '状态切换',
        ]);
        Route::post('group/setTemplate/:id', '/setTemplate')->name('systemStoreGroupSetTemplate')->option([
            '_alias' => '设置店铺分组模板',
        ]);
        Route::get('group/stores/:id', '/stores')->name('systemStoreGroupStores')->option([
            '_alias' => '关联店铺列表',
        ]);
        Route::get('group/options', '/options')->option([
            '_alias' => '筛选',
        ]);
    })->prefix('admin.system.merchant.StoreGroup')->option([
        '_path' => '/merchant/grouping',
        '_auth' => true,
    ]);


    //店铺分类
    Route::group('system/merchant', function () {
        Route::get('category/lst', '/lst')->name('systemMerchantCategoryLst')->option([
            '_alias' => '店铺分类列表',
            ]);
        Route::get('category_lst', '/lst')->option([
            '_alias' => '店铺分类列表',
            '_auth'  => false,
        ]);
        Route::post('category', '/create')->name('systemMerchantCategoryCreate')->option([
            '_alias' => '店铺分类添加',
            ]);
        Route::get('category/form', '/createForm')->name('systemMerchantCategoryCreateForm')->option([
            '_alias' => '店铺分类添加表单',
            '_auth' => false,
            '_form' => 'systemMerchantCategoryCreate',
        ]);
        Route::delete('category/:id', '/delete')->name('systemMerchantCategoryDelete')->option([
            '_alias' => '店铺分类删除',
            ]);
        Route::post('category/:id', '/update')->name('systemMerchantCategoryUpdate')->option([
            '_alias' => '店铺分类编辑',
            ]);
        Route::get('category/form/:id', '/updateForm')->name('systemMerchantCategoryUpdateForm')->option([
            '_alias' => '店铺分类编辑表单',
            '_auth' => false,
            '_form' => 'systemMerchantCategoryUpdate',
        ]);
        Route::get('category/options', '/getOptions')->option([
            '_alias' => '店铺分类筛选',
            '_auth'  => false,
        ]);
    })->prefix('admin.system.merchant.MerchantCategory')->option([
        '_path' => '/merchant/classify',
        '_auth' => true,
    ]);

    //申请列表
    Route::group('merchant/intention', function () {
        Route::get('lst', '/lst')->name('systemMerchantIntentionLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('status/:id', '/switchStatus')->name('systemMerchantIntentionStatus')->option([
            '_alias' => '审核',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemMerchantIntentionDelete')->option([
            '_alias' => '删除',
        ]);
        Route::get('mark/:id/form', '/form')->name('systemMerchantIntentionMarkForm')->option([
            '_alias' => '备注',
            '_auth' => false,
            '_form' => 'systemMerchantIntentionMark',
        ]);
        Route::get('status/:id/form', '/statusForm')->name('systemMerchantIntentionStatusForm')->option([
            '_alias' => '申请店铺',
            '_auth' => false,
            '_form' => 'systemMerchantIntentionStatus',
        ]);

        Route::post('mark/:id', '/mark')->name('systemMerchantIntentionMark')->option([
            '_alias' => '备注',
        ]);
        Route::get('excel', '/excel');
    })->prefix('admin.system.merchant.MerchantIntention')->option([
        '_path' => '/merchant/application',
        '_auth' => true,
    ]);

    //店铺管理
    Route::group('system/merchant', function () {
        Route::get('care_ficti/form/:id', '.Merchant/careFictiForm')->name('systemMerchantCareFictiForm')->option([
            '_alias' => '虚拟关注量表单',
            '_auth' => false,
            '_form' => 'systemMerchantCareFicti',
        ]);
        Route::post('care_ficti/:id', '.Merchant/careFicti')->name('systemMerchantCareFicti')->option([
            '_alias' => '虚拟关注量',
            '_auth' => true,
        ]);

        Route::get('mer_select', '.Merchant/mer_select')->option([
            '_alias' => '列表',
            '_auth'  => false,
        ]);
        Route::get('create/form', '.Merchant/createForm')->name('systemMerchantCreateForm')->option([
            '_alias' => '店铺列表',
            ]);
        Route::get('count', '.Merchant/count')->name('systemMerchantCount')->option([
            '_alias' => '店铺列表统计',
        ]);
        Route::get('lst', '.Merchant/lst')->name('systemMerchantLst')->option([
            '_alias' => '店铺列表',
            ]);
        Route::post('create', '.Merchant/create')->name('systemMerchantCreate')->option([
            '_alias' => '店铺添加',
            ]);
        Route::get('update/form/:id', '.Merchant/updateForm')->name('systemMerchantUpdateForm')->option([
            '_alias' => '店铺编辑表单',
            '_auth' => false,
            '_form' => 'systemMerchantUpdate',
        ]);
        Route::post('update/:id', '.Merchant/update')->name('systemMerchantUpdate')->option([
            '_alias' => '店铺编辑',
            ]);
        Route::post('status/:id', '.Merchant/switchStatus')->name('systemMerchantStatus')->option([
            '_alias' => '店铺修改推荐',
            ]);
        Route::post('close/:id', '.Merchant/switchClose')->name('systemMerchantClose')->option([
            '_alias' => '店铺开启/关闭',
            ]);
        Route::get('delete/:id/form', '.Merchant/deleteForm')->name('systemMerchantDeleteForm')->option([
            '_alias' => '店铺删除',
            '_auth' => false,
            '_form' => 'systemMerchantDelete',
        ]);
        Route::post('delete/:id', '.Merchant/delete')->name('systemMerchantDelete')->option([
            '_alias' => '店铺删除',
            ]);
        Route::post('password/:id', '.MerchantAdmin/password')->name('systemMerchantAdminPassword')->option([
            '_alias' => '店铺修改密码',
            ]);
        Route::get('password/form/:id', '.MerchantAdmin/passwordForm')->name('systemMerchantAdminPasswordForm')->option([
            '_alias' => '店铺修改密码表单',
            '_auth' => false,
            '_form' => 'systemMerchantAdminPassword',
        ]);
        Route::post('login/:id', '.Merchant/login')->name('systemMerchantLogin')->option([
            '_alias' => '店铺登录',
            ]);
        Route::get('changecopy/:id/form', '.Merchant/changeCopyNumForm')->name('systemMerchantChangeCopyForm')->option([
            '_alias' => '修改采集商品次数表单',
            '_auth' => false,
            '_form' => 'systemMerchantChangeCopy',
        ]);
        Route::post('changecopy/:id', '.Merchant/changeCopyNum')->name('systemMerchantChangeCopy')->option([
            '_alias' => '修改采集商品次数',
            ]);
        Route::get('detail/:id', '.Merchant/detail')->name('systemMerchantDetail')->option([
            '_alias' => '详情',
        ]);
        Route::get('get_operate_list/:merchant_id', '.Merchant/getOperateList')->name('systemMerchantOperateList')->option([
            '_alias' => '操作日志',
        ]);
        Route::post('businessCreate', '.Merchant/businessCreate')->name('systemMerchantBusinessCreate')->option([
            '_alias' => '商户添加店铺',
        ]);
    })->prefix('admin.system.merchant')->option([
        '_path' => '/merchant/list',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'uploadImage',
                '_path'  =>'/merchant/list',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'systemAttachmentLst',
                '_path'  =>'/merchant/list',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],
        ]
    ]);

    Route::group('merchant/type', function () {
        Route::get('lst', '/lst')->name('systemMerchantTypeLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('create', '/create')->name('systemMerchantTypeCreate')->option([
            '_alias' => '添加',
        ]);
        Route::post('update/:id', '/update')->name('systemMerchantTypeUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemMerchantTypeDelete')->option([
            '_alias' => '删除',
        ]);
        Route::get('mark/:id', '/markForm')->name('systemMerchantTypeMarkForm')->option([
            '_alias' => '备注',
            '_auth'  => false,
            '_form' => 'systemMerchantTypeMark',
        ]);
        Route::post('mark/:id', '/mark')->name('systemMerchantTypeMark')->option([
            '_alias' => '备注',
        ]);

        Route::get('detail/:id', '/detail')->name('systemMerchantTypeDetail')->option([
            '_alias' => '备注',
        ]);

        Route::get('options', '/options')->option([
            '_alias' => '筛选',
            '_auth'  => false,
        ]);
        Route::get('mer_auth', '/mer_auth')->option([
            '_alias' => '权限',
            '_auth'  => false,
        ]);
    })->prefix('admin.system.merchant.MerchantType')->option([
        '_path' => '/merchant/type',
        '_auth' => true,
    ]);

    //保证金
    Route::group('margin', function () {
        //缴纳记录
        Route::get('lst', 'merchant.MerchantMargin/lst')->name('systemMerchantMarginLst')->option([
                '_alias' => '缴纳记录',
            ]);
        //扣费记录
        Route::get('list/:id', 'merchant.MerchantMargin/getMarginLst')->name('systemMarginList')->option([
                '_alias' => '扣费记录',
            ]);
        //扣除保证金
        Route::get('set/:id/form', 'merchant.MerchantMargin/setMarginForm')->name('systemMarginSetForm')->option([
            '_alias' => '扣除保证金表单',
            '_auth' => false,
            '_form' => 'systemMarginSet',
            ]);
        Route::post('set', 'merchant.MerchantMargin/setMargin')->name('systemMarginSet')->option([
                '_alias' => '扣除保证金',
            ]);
        //退款申请
        Route::get('refund/lst', 'financial.Financial/getMarginLst')->name('systemMarginRefundList')->option([
                '_alias' => '退款申请列表',
            ]);
        Route::get('refund/show/:id', 'financial.Financial/refundShow')->name('systemMarginRefundShow')->option([
                '_alias' => '退款申请详情',
            ]);
        //审核
        Route::get('refund/status/:id/form', 'financial.Financial/statusForm')->name('systemMarginRefundSwitchStatusForm')->option([
            '_alias' => '审核表单',
            '_auth' => false,
            '_form' => 'systemMarginRefundSwitchStatus',
        ]);
        Route::post('refund/status/:id', 'financial.Financial/switchStatus')->name('systemMarginRefundSwitchStatus')->append(['type' => 1])->option([
                '_alias' => '审核',
            ]);
        //备注
        Route::get('refund/mark/:id/form', 'financial.Financial/markMarginForm')->name('systemMarginRefundMarkForm')->option([
            '_alias' => '备注表单',
            '_auth' => false,
            '_form' => 'systemMarginRefundMark',
        ]);
        Route::post('refund/mark/:id', 'financial.Financial/mark')->name('systemMarginRefundMark')->option([
            '_alias' => '备注',
            ]);
        Route::get('make_up', 'merchant.Merchant/makeUpMarginLst')->name('systemMarginMakeUpMarginLst')->option([
            '_alias' => '待缴列表',
        ]);
        //线下缴纳
        Route::get('local/:id/form', 'merchant.MerchantMargin/localMarginForm')->name('systemMarginLocalForm')->option([
            '_alias' => '扣除保证金表单',
            '_auth' => false,
            '_form' => 'systemMarginSet',
        ]);
        Route::post('local/:id', 'merchant.MerchantMargin/localMarginSet')->name('systemMarginLocalSet')->option([
            '_alias' => '扣除保证金表单',
            '_auth' => false,
            '_form' => 'systemMarginLocalSet',
        ]);
    })->prefix('admin.system.')->option([
        '_path' => '/merchant/deposit_list',
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
