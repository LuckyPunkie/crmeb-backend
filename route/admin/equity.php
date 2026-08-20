<?php

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {
    Route::group('equity', function () {
        Route::get('projects', '/projectLst')->name('adminEquityProjects')->option(['_alias' => '消费送股项目列表']);
        Route::get('pending-stores', '/pendingStores')->name('adminEquityPending')->option(['_alias' => '待开业管理']);
        Route::post('pending-stores/:id/bind', '/bindStore')->name('adminEquityBind')->option(['_alias' => '绑定新店']);
        Route::get('refunds', '/refunds')->name('adminEquityRefunds')->option(['_alias' => '充值退款列表']);
        Route::post('refunds/:id/audit', '/auditRefund')->name('adminEquityRefundAudit')->option(['_alias' => '审核充值退款']);
        Route::get('dividend-notices', '/noticeLst')->name('adminEquityNotices')->option(['_alias' => '分红公告列表']);
        Route::post('dividend-notices', '/noticeSave')->name('adminEquityNoticeSave')->option(['_alias' => '保存分红公告']);
        Route::post('dividend-notices/:id/withdraw', '/noticeWithdraw')->name('adminEquityNoticeWithdraw')->option(['_alias' => '撤回分红公告']);
        Route::post('dividends/execute', '/executeDividend')->name('adminEquityDividendExecute')->option(['_alias' => '执行分红']);
        Route::get('financial-reports', '/financialReportLst')->name('adminEquityFinanceLst')->option(['_alias' => '财报列表']);
        Route::post('projects/:id/financial-report', '/saveFinancialReport')->name('adminEquityFinanceSave')->option(['_alias' => '录入财报']);
        Route::delete('financial-reports/:id', '/deleteFinancialReport')->name('adminEquityFinanceDelete')->option(['_alias' => '删除财报']);
        Route::get('projects/:id/staff-pool', '/staffPool')->name('adminEquityStaffPool')->option(['_alias' => '员工激励池']);
        Route::post('projects/:id/staff-pool', '/saveStaffPool')->name('adminEquityStaffPoolSave')->option(['_alias' => '保存员工激励池']);
    })->prefix('admin.store.equity.EquityAdmin')->option([
        '_path' => '/equity',
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
