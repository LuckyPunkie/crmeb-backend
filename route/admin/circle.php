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
    //商圈
    Route::group('circle', function () {
        Route::get('list', '/list')->name('systemCircleList')->option([
            '_alias' => '商圈列表',
        ]);
        Route::get('detail/:id', '/detail')->name('systemCircleDetail')->option([
            '_alias' => '商圈详情',
        ]);
        Route::post('create', '/create')->name('systemCircleCreate')->option([
            '_alias' => '商圈添加',
        ]);
        Route::post('update/:id', '/update')->name('systemCircleUpdate')->option([
            '_alias' => '商圈编辑',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemCircleDelete')->option([
            '_alias' => '商圈删除',
        ]);
        Route::post('switch/:id', '/switch')->name('systemCircleSwitch')->option([
            '_alias' => '商圈状态切换',
        ]);
        Route::get('merchantList/:id', '/associatedMerchantList')->name('systemCircleMerchantList')->option([
            '_alias' => '关联商户列表',
        ]);
        Route::get('options', '/options');
    })->prefix('admin.circle.Circle')->option([
        '_path' => ['/business-zones/index', '/merchant/index'],
        '_auth' => true,
    ]);
    // --------------- 代理相关 ---------------
    // 代理公共接口
    Route::group('circle/agent', function () {
        Route::get('list', '/list')->name('systemCircleAgentList')->option([
            '_alias' => '商圈代理列表',
        ]);
        Route::get('detail/:id', '/detail')->name('systemCircleAgentDetail')->option([
            '_alias' => '商圈代理详情',
        ]);
    })->prefix('admin.circle.CircleAgent')->option([
        '_path' => ['/business-zones/agents', '/merchant/admin-list'],
        '_auth' => false,
    ]);
    // 商圈代理
    Route::group('circle/agent', function () {
        Route::post('create', '/create')->name('systemCircleAgentCreate')->option([
            '_alias' => '商圈代理添加',
        ]);
        Route::post('update/:id', '/update')->name('systemCircleAgentUpdate')->option([
            '_alias' => '商圈代理编辑',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemCircleAgentDelete')->option([
            '_alias' => '商圈代理删除',
        ]);
        Route::get('merchantList/:id', '/associatedMerchantList')->name('systemCircleAgentMerchant')->option([
            '_alias' => '关联商户列表',
        ]);
        Route::get('options', '/options')->name('systemCircleAgentOptions')->option([
            '_alias' => '代理选项',
        ]);
        Route::post('resetPwd/:id', '/resetPassword')->name('systemCircleAgentResetPassword')->option([
            '_alias' => '重置密码',
        ]);
    })->prefix('admin.circle.CircleAgent')->option([
        '_path' => ['/business-zones/agents', '/merchant/admin-list'],
        '_auth' => true,
    ]);
    // 代理审核
    Route::group('circle/agent', function () {
        Route::post('audit/:id', '/audit')->name('systemCircleAgentAudit')->option([
            '_alias' => '商圈代理审核',
        ]);
    })->prefix('admin.circle.CircleAgent')->option([
        '_path' => ['/business-zones/agent-review', '/merchant/review'],
        '_auth' => true,
    ]);
    // 商圈结算方式
    Route::group('circle/agent', function () {
        Route::get('settlementMethod/:id', '/getSettlementMethod')->name('systemCircleAgentGetSettlementMethod')->option([
            '_alias' => '结算方式get',
        ]);
        Route::post('settlementMethod/:id', '/setSettlementMethod')->name('systemCircleAgentSetSettlementMethod')->option([
            '_alias' => '结算方式post',
        ]);
    })->prefix('admin.circle.CircleAgent')->option([
        '_path' => '/accounts/zoneAgent/settlementAccount',
        '_auth' => true,
    ]);
    // --------------- 结算相关 ---------------
    // 结算公共接口
    Route::group('circle/checkout', function () {
        Route::get('list', '/list')->name('systemCircleCheckoutList')->option([
            '_alias' => '结算记录列表',
        ]);
        Route::get('detail/:id', '/detail')->name('systemCircleCheckoutDetail')->option([
            '_alias' => '结算记录详情',
        ]);
    })->prefix('admin.circle.CircleBrokerageCheckout')->option([
        '_auth' => false,
    ]);
    // 平台结算审核
    Route::group('circle/checkout', function () {
        Route::post('audit/:id', '/audit')->name('systemCircleCheckoutAudit')->option([
            '_alias' => '平台结算审核',
        ]);
        Route::post('transfer/:id', '/transfer')->name('systemCircleCheckoutTransfer')->option([
            '_alias' => '平台转账',
        ]);
        Route::post('platformRemark/:id', '/platformRemark')->name('systemCircleCheckoutPlatformRemark')->option([
            '_alias' => '平台备注',
        ]);
    })->prefix('admin.circle.CircleBrokerageCheckout')->option([
        '_path' => '/accounts/zoneAgent/settlementReview',
        '_auth' => true,
    ]);
    // 商圈申请结算
    Route::group('circle/checkout', function () {
        Route::get('create', '/create')->name('systemCircleCheckoutCreate')->option([
            '_alias' => '商圈申请结算获取余额',
        ]);
        Route::post('create', '/save')->name('systemCircleCheckoutSave')->option([
            '_alias' => '商圈申请结算提交',
        ]);
        Route::post('revoke/:id', '/revoke')->name('systemCircleCheckoutRevoke')->option([
            '_alias' => '商圈撤销结算',
        ]);
        Route::post('remark/:id', '/remark')->name('systemCircleCheckoutRemark')->option([
            '_alias' => '商圈备注',
        ]);
    })->prefix('admin.circle.CircleBrokerageCheckout')->option([
        '_path' => '/accounts/zoneAgent/settlementApply',
        '_auth' => true,
    ]);
    //  --------------- 提成流水 ---------------
    // 提成流水
    Route::group('circle/financialRecord', function () {
        Route::get('list', '/list')->name('systemCircleFinancialRecordList')->option([
            '_alias' => '商圈提成流水列表',
        ]);
    })->prefix('admin.circle.CircleFinancialRecord')->option([
        '_path' => '/accounts/zoneAgent/commissionRecord',
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
