<?php

use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use think\facade\Route;

Route::group('member', function () {
    Route::group(function () {
        Route::get('statistics/base', [app\controller\merchant\member\Member::class, 'statisticsBase'])->name('merchantMemberStatisticsBase')->option([
            '_alias' => '统计',
        ]);
        Route::get('statistics/level', [app\controller\merchant\member\Member::class, 'statisticsLevel'])->name('merchantMemberStatisticsLevel')->option([
            '_alias' => '等级统计',
        ]);
        Route::get('statistics/channel', [app\controller\merchant\member\Member::class, 'statisticsChannel'])->name('merchantMemberStatisticsChannel')->option([
            '_alias' => '渠道统计',
        ]);
        Route::get('statistics/member-trend', [app\controller\merchant\member\Member::class, 'statisticsMemberTrend'])->name('merchantMemberStatisticsMemberTrend')->option([
            '_alias' => '会员趋势',
        ]);
        Route::get('statistics/consume-trend', [app\controller\merchant\member\Member::class, 'statisticsConsumeTrend'])->name('merchantMemberStatisticsConsumeTrend')->option([
            '_alias' => '消费趋势',
        ]);
    })->option([
        '_path' => '/member/overview',
    ]);

    Route::group(function () {
        Route::get('customer/list', [app\controller\merchant\member\Member::class, 'customers'])->name('merchantMemberCustomerList')->option([
            '_alias' => '会员列表',
        ]);
        Route::get('customer/export', [app\controller\merchant\member\Member::class, 'customerExport'])->name('merchantMemberCustomerExport')->option([
            '_alias' => '会员导出',
        ]);
        Route::get('customer/:uid', [app\controller\merchant\member\Member::class, 'customerDetail'])->name('merchantMemberCustomerDetail')->option([
            '_alias' => '客户详情',
        ]);
        Route::get('customer/:uid/level/records', [app\controller\merchant\member\Member::class, 'customerLevels'])->name('merchantMemberCustomerLevelRecords')->option([
            '_alias' => '等级记录',
        ]);
        Route::get('customer/:uid/credits/records', [app\controller\merchant\member\Member::class, 'customerCredits'])->name('merchantMemberCustomerCreditsRecords')->option([
            '_alias' => '购物金记录',
        ]);
        Route::post('customer/:uid/level', [app\controller\merchant\member\Member::class, 'changeLevel'])->name('merchantMemberCustomerLevelUpdate')->option([
            '_alias' => '调整等级',
        ]);
        Route::post('customer/:uid/financial-status', [app\controller\merchant\member\Member::class, 'financialStatus'])->name('merchantMemberCustomerFinancialStatus')->option([
            '_alias' => '购物金冻结',
        ]);
        Route::post('customer/:uid/credits', [app\controller\merchant\member\Member::class, 'adjustCredits'])->name('merchantMemberCustomerCreditsAdjust')->option([
            '_alias' => '调整购物金',
        ]);
    })->option([
        '_path' => '/member/customer',
    ]);

    Route::group(function () {
        Route::get('benefits', [app\controller\merchant\member\Member::class, 'benefits'])->name('merchantMemberBenefitsList')->option([
            '_alias' => '权益列表',
        ]);
        Route::post('benefits', [app\controller\merchant\member\Member::class, 'saveBenefits'])->name('merchantMemberBenefitsSave')->option([
            '_alias' => '保存权益',
        ]);
    })->option([
        '_path' => '/member/benefits',
    ]);

    Route::group(function () {
        Route::get('agreement', [app\controller\merchant\member\Member::class, 'agreement'])->name('merchantMemberAgreementDetail')->option([
            '_alias' => '开通协议',
        ]);
        Route::post('agreement', [app\controller\merchant\member\Member::class, 'saveAgreement'])->name('merchantMemberAgreementSave')->option([
            '_alias' => '保存开通协议',
        ]);
    })->option([
        '_path' => '/member/agreement',
    ]);

    Route::group(function () {
        Route::get('level/list', [app\controller\merchant\member\Member::class, 'levels'])->name('merchantMemberLevelList')->option([
            '_alias' => '等级列表',
        ]);
        Route::get('level/:id', [app\controller\merchant\member\Member::class, 'levelDetail'])->name('merchantMemberLevelDetail')->option([
            '_alias' => '等级详情',
        ]);
        Route::post('level', [app\controller\merchant\member\Member::class, 'createLevel'])->name('merchantMemberLevelCreate')->option([
            '_alias' => '添加等级',
        ]);
        Route::post('level/:id', [app\controller\merchant\member\Member::class, 'updateLevel'])->name('merchantMemberLevelUpdate')->option([
            '_alias' => '编辑等级',
        ]);
        Route::delete('level/:id', [app\controller\merchant\member\Member::class, 'deleteLevel'])->name('merchantMemberLevelDelete')->option([
            '_alias' => '删除等级',
        ]);
    })->option([
        '_path' => '/member/level',
    ]);

    Route::group(function () {
        Route::get('shopping-credits/package/list', [app\controller\merchant\member\ShoppingCredits::class, 'packages'])->name('merchantShoppingCreditsPackageList')->option([
            '_alias' => '套餐列表',
        ]);
        Route::get('shopping-credits/package/:id', [app\controller\merchant\member\ShoppingCredits::class, 'packageDetail'])->name('merchantShoppingCreditsPackageDetail')->option([
            '_alias' => '套餐详情',
        ]);
        Route::post('shopping-credits/package', [app\controller\merchant\member\ShoppingCredits::class, 'createPackage'])->name('merchantShoppingCreditsPackageCreate')->option([
            '_alias' => '添加套餐',
        ]);
        Route::post('shopping-credits/package/:id', [app\controller\merchant\member\ShoppingCredits::class, 'updatePackage'])->name('merchantShoppingCreditsPackageUpdate')->option([
            '_alias' => '编辑套餐',
        ]);
        Route::delete('shopping-credits/package/:id', [app\controller\merchant\member\ShoppingCredits::class, 'deletePackage'])->name('merchantShoppingCreditsPackageDelete')->option([
            '_alias' => '删除套餐',
        ]);
        Route::post('shopping-credits/package/:id/status', [app\controller\merchant\member\ShoppingCredits::class, 'packageStatus'])->name('merchantShoppingCreditsPackageStatus')->option([
            '_alias' => '套餐状态',
        ]);
    })->option([
        '_path' => '/member/recharge',
    ]);

    Route::get('shopping-credits/order/list', [app\controller\merchant\member\ShoppingCredits::class, 'orders'])->name('merchantShoppingCreditsOrderList')->option([
        '_alias' => '充值订单',
        '_path' => '/member/recharge/order',
    ]);

    Route::group(function () {
        Route::get('shopping-credits/refund/statistics', [app\controller\merchant\member\ShoppingCredits::class, 'refundStatistics'])->name('merchantShoppingCreditsRefundStatistics')->option([
            '_alias' => '退款统计',
        ]);
        Route::get('shopping-credits/refund/list', [app\controller\merchant\member\ShoppingCredits::class, 'refunds'])->name('merchantShoppingCreditsRefundList')->option([
            '_alias' => '退款订单',
        ]);
        Route::get('shopping-credits/refund/:refundNo', [app\controller\merchant\member\ShoppingCredits::class, 'refundDetail'])->name('merchantShoppingCreditsRefundDetail')->option([
            '_alias' => '退款详情',
        ]);
        Route::get('shopping-credits/refund/audit/:id/form', [app\controller\merchant\member\ShoppingCredits::class, 'auditRefundForm'])
            ->name('merchantShoppingCreditsRefundAudit')
            ->option([
                '_auth' => false,
                '_form' => 'merchantShoppingCreditsRefundAudit',
                '_alias' => '退款审核表单',
            ]);
        Route::post('shopping-credits/refund/audit/:id', [app\controller\merchant\member\ShoppingCredits::class, 'auditRefund'])->name('merchantShoppingCreditsRefundAudit')->option([
            '_alias' => '退款审核',
        ]);
    })->option([
        '_path' => '/member/recharge/refund',
    ]);
})
    ->option([
    '_auth' => true,
])->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
