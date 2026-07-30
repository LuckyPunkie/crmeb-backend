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

    // 流浪动物救助
    Route::group('animal_rescue', function () {
        // 帖子列表
        Route::get('lst', '/lst')->name('systemAnimalRescueLst')->option([
            '_alias' => '流浪动物救助列表',
        ]);
        // 删除帖子
        Route::delete('delete/:id', '/delete')->name('systemAnimalRescueDelete')->option([
            '_alias' => '删除救助帖子',
        ]);
        // 审核帖子
        Route::post('audit/:id', '/audit')->name('systemAnimalRescueAudit')->option([
            '_alias' => '审核救助帖子',
        ]);
        // 领养申请列表
        Route::get('adoption_list', '/adoptionLst')->name('systemAnimalRescueAdoptionLst')->option([
            '_alias' => '领养申请列表',
        ]);
        // 审核领养申请
        Route::post('adoption_audit/:id', '/adoptionAudit')->name('systemAnimalRescueAdoptionAudit')->option([
            '_alias' => '审核领养申请',
        ]);
        // 数据统计
        Route::get('statistics', '/statistics')->name('systemAnimalRescueStatistics')->option([
            '_alias' => '流浪救助数据统计',
        ]);
        // v2.1 拨款审核
        Route::get('fund_audit/lst', '/fundAuditLst')->name('systemAnimalRescueFundAuditLst')->option([
            '_alias' => '拨款审核列表',
        ]);
        Route::get('fund_audit/:id', '/fundAuditDetail')->name('systemAnimalRescueFundAuditDetail')->option([
            '_alias' => '拨款审核详情',
        ]);
        Route::post('fund_audit/approve/:id', '/fundAuditApprove')->name('systemAnimalRescueFundAuditApprove')->option([
            '_alias' => '拨款审核通过',
        ]);
        Route::post('fund_audit/reject/:id', '/fundAuditReject')->name('systemAnimalRescueFundAuditReject')->option([
            '_alias' => '拨款审核拒绝',
        ]);
        Route::post('fund_audit/rollback/:id', '/fundAuditRollback')->name('systemAnimalRescueFundAuditRollback')->option([
            '_alias' => '拨款退回待提交',
        ]);
        // 领养保证金托管
        Route::get('deposit_list', '/depositLst')->name('systemAnimalRescueDepositLst')->option([
            '_alias' => '领养保证金托管列表',
        ]);
        Route::get('deposit_statistics', '/depositStatistics')->name('systemAnimalRescueDepositStatistics')->option([
            '_alias' => '领养保证金汇总',
        ]);
        // 月捐结算记录
        Route::get('settlement_list', '/settlementLst')->name('systemAnimalRescueSettlementLst')->option([
            '_alias' => '月捐结算记录',
        ]);
    })->prefix('admin.animal_rescue.AnimalRescue')->option([
        '_path' => '/animal_rescue/list',
        '_auth' => true,
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
