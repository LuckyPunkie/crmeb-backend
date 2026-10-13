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

    //同城配送
    Route::group('delivery', function () {

        //配置表单
        Route::get('form', [app\controller\admin\delivery\DeliveryStation::class, 'deliveryForm'])->name('systemDeliveryConfigForm')->option([
            '_alias' => '配置表单',
            '_auth' => false,
            '_form' => 'saveDeliveryConfig',
        ]);
        //保存
        Route::post('/config/save', [app\controller\admin\delivery\DeliveryStation::class, 'saveDeliveryConfig'])->name('systemDeliveryConfigSave')->option([
            '_alias' => '编辑配置',
            '_path' => '/systemForm/delivery',
            ]);

        // 平台配送员配置
        Route::get('config/settings', [app\controller\admin\delivery\DeliveryConfig::class, 'configuration'])->name('systemPlatformDeliveryConfigSettings')->option([
            '_alias' => '平台配送设置信息',
            '_path' => '/delivery/delivery_setting',
        ]);
        Route::post('config/update/:id', [app\controller\admin\delivery\DeliveryConfig::class, 'update'])->name('systemPlatformDeliveryConfigUpdate')->option([
            '_alias' => '更新平台配送设置',
            '_path' => '/delivery/delivery_setting',
        ]);

        //门店列表
        Route::get('station/lst', [app\controller\admin\delivery\DeliveryStation::class, 'lst'])->name('systemDeliveryStationlst')->option([
            '_alias' => '门店列表',
            '_path' => '/delivery/station',
        ]);

        //详情
        Route::get('station/detail/:id', [app\controller\admin\delivery\DeliveryStation::class, 'detail'])->name('systemDeliveryStationDetail')->option([
            '_alias' => '门店详情',
            '_path' => '/delivery/station',
        ]);

        //
        Route::get('station/options', [app\controller\admin\delivery\DeliveryStation::class, 'options']);

        //详情
        Route::get('recharge', [app\controller\admin\delivery\DeliveryStation::class, 'getRecharge']);

        Route::get('statistics/trend', [app\controller\admin\delivery\DeliveryOrder::class, 'trend'])
            ->name('systemDeliveryStatisticsTrend')->option([
                '_alias' => '平台配送趋势统计',
                '_path' => '/delivery/statistics',
            ]);

        Route::group('order', function(){
            Route::get('lst', [app\controller\admin\delivery\DeliveryOrder::class, 'lst'])->name('systemDeliveryOrderLst')->option([
                '_alias' => '配送记录',
            ]);
            Route::get('manage/lst', [app\controller\admin\delivery\DeliveryOrder::class, 'manageList'])->name('systemDeliveryOrderManageLst')->option([
                '_alias' => '平台配送订单列表',
            ]);
            Route::get('manage/title', [app\controller\admin\delivery\DeliveryOrder::class, 'manageTitle'])->name('systemDeliveryOrderManageTitle')->option([
                '_alias' => '平台配送订单统计',
            ]);
            Route::post('manage/batch/dispatch', [app\controller\admin\delivery\DeliveryOrder::class, 'batchDispatch'])->name('systemDeliveryOrderBatchDispatch')->option([
                '_alias' => '平台配送订单批量派单',
            ]);
            Route::post('manage/batch/confirm', [app\controller\admin\delivery\DeliveryOrder::class, 'batchConfirm'])->name('systemDeliveryOrderBatchConfirm')->option([
                '_alias' => '平台配送订单批量确认送达',
            ]);
            Route::get('detail/:id', [app\controller\admin\delivery\DeliveryOrder::class, 'detail'])->name('systemDeliveryOrderDetail')->option([
                '_alias' => '配送详情',
            ]);
            Route::post('dispatch/:id', [app\controller\admin\delivery\DeliveryOrder::class, 'dispatch'])->name('systemDeliveryOrderDispatch')->option([
                '_alias' => '平台配送派单',
            ]);
            Route::post('updateDispatch/:id', [app\controller\admin\delivery\DeliveryOrder::class, 'updateDispatch'])->name('systemDeliveryOrderUpdateDispatch')->option([
                '_alias' => '平台配送改派',
            ]);
            Route::post('confirm/:id', [app\controller\admin\delivery\DeliveryOrder::class, 'confirm'])->name('systemDeliveryOrderConfirm')->option([
                '_alias' => '平台配送确认送达',
            ]);
        })->option([
            '_path' => '/delivery/usage_record',
        ]);


        Route::group(function(){
            //充值记录
            Route::get('station/payLst', [app\controller\admin\delivery\DeliveryStation::class, 'payLst'])->name('systemDeliveryStationPaayyLst')->option([
                '_alias' => '充值记录',
            ]);
            //lst
            Route::get('title', [app\controller\admin\delivery\DeliveryOrder::class, 'title'])->name('systemDeliveryOrderTitle')->option([
                '_alias' => '统计',
            ]);
            //详情
            Route::get('belence', [app\controller\admin\delivery\DeliveryStation::class, 'getBalance'])->name('systemDeliveryStationGetBalance')->option([
                '_alias' => '余额',
            ]);
        })->option([
            '_path' => '/delivery/recharge_record',
        ]);


        Route::group('service', function(){
            Route::get('apply/lst', [app\controller\admin\delivery\DeliveryServiceApply::class, 'lst'])->name('systemDeliveryServiceApplyLst')->option([
                '_alias' => '配送员申请列表',
            ]);
            Route::get('apply/title', [app\controller\admin\delivery\DeliveryServiceApply::class, 'title'])->name('systemDeliveryServiceApplyTitle')->option([
                '_alias' => '配送员申请统计',
            ]);
            Route::get('apply/detail/:id', [app\controller\admin\delivery\DeliveryServiceApply::class, 'detail'])->name('systemDeliveryServiceApplyDetail')->option([
                '_alias' => '配送员申请详情',
            ]);
            Route::get('apply/audit/:id/form', [app\controller\admin\delivery\DeliveryServiceApply::class, 'auditFrom'])->option([
                '_alias' => '审核配送员申请',
                '_auth' => false,
                '_form' => 'systemDeliveryServiceApplyAudit',
            ]);
            Route::post('apply/audit/:id', [app\controller\admin\delivery\DeliveryServiceApply::class, 'audit'])
                ->name('systemDeliveryServiceApplyAudit')
                ->option([
                    '_alias' => '审核配送员申请',
                ]);
        })->option([
            '_path' => '/delivery/personnel_review',
        ]);

        Route::group('service', function(){
            Route::get('lst', [app\controller\admin\delivery\DeliveryService::class, 'lst'])->name('systemDeliveryServiceLst')->option([
                '_alias' => '配送员列表',
            ]);
            Route::get('detail/:id', [app\controller\admin\delivery\DeliveryService::class, 'detail'])->name('systemDeliveryServiceDetail')->option([
                '_alias' => '配送员详情',
            ]);
            Route::get('order/lst/:id', [app\controller\admin\delivery\DeliveryService::class, 'orderList'])->name('systemDeliveryServiceOrderList')->option([
                '_alias' => '配送员配送订单',
            ]);
            Route::get('withdraw/success/:id', [app\controller\admin\delivery\DeliveryService::class, 'withdrawSuccessList'])->name('systemDeliveryServiceWithdrawSuccessList')->option([
                '_alias' => '配送员提现成功记录',
            ]);
            Route::get('statisticsList', [app\controller\admin\delivery\DeliveryService::class, 'statisticsList'])->name('systemDeliveryServiceStatisticsList')->option([
                '_alias' => '配送员统计列表',
            ]);
            Route::get('create/form', [app\controller\admin\delivery\DeliveryService::class, 'createForm'])->option([
                '_alias' => '配送员添加表单',
                '_auth' => false,
                '_form' => 'systemDeliveryServiceCreate',
            ]);
            Route::post('create', [app\controller\admin\delivery\DeliveryService::class, 'create'])->name('systemDeliveryServiceCreate')->option([
                '_alias' => '添加配送员',
            ]);
            Route::get('update/:id/form', [app\controller\admin\delivery\DeliveryService::class, 'updateForm'])->option([
                '_alias' => '配送员编辑表单',
                '_auth' => false,
                '_form' => 'systemDeliveryServiceUpdate',
            ]);
            Route::post('update/:id', [app\controller\admin\delivery\DeliveryService::class, 'update'])->name('systemDeliveryServiceUpdate')->option([
                '_alias' => '编辑配送员',
            ]);
            Route::post('status/:id', [app\controller\admin\delivery\DeliveryService::class, 'switchWithStatus'])->name('systemDeliveryServiceStatus')
                ->option([
                '_alias' => '修改配送员接单状态',
            ]);
            Route::post('change/:id', [app\controller\admin\delivery\DeliveryService::class, 'changeStatus'])->name
            ('systemDeliveryServiceOpen')
                ->option([
                    '_alias' => '修改配送员状态',
                ]);
            Route::delete('delete/:id', [app\controller\admin\delivery\DeliveryService::class, 'delete'])->name('systemDeliveryServiceDelete')->option([
                '_alias' => '删除配送员',
            ]);
        })->option([
            '_path' => '/delivery/personnel_manage',
        ]);

        Route::group('service/bill', function () {
            Route::get('lst', [app\controller\admin\delivery\DeliveryServiceBill::class, 'lst'])->name('systemDeliveryServiceBillLst')->option([
                '_alias' => '配送员流水列表',
            ]);
            Route::get('export', [app\controller\admin\delivery\DeliveryServiceBill::class, 'export'])->name('systemDeliveryServiceBillExport')->option([
                '_alias' => '配送员流水导出',
            ]);
            Route::get('detail/:id', [app\controller\admin\delivery\DeliveryServiceBill::class, 'detail'])->name('systemDeliveryServiceBillDetail')->option([
                '_alias' => '配送员流水详情',
            ]);
        })->option([
            '_path' => '/delivery/service_bill',
        ]);

        Route::group('service/withdraw', function () {
            Route::get('lst', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'lst'])->name('systemDeliveryServiceWithdrawLst')->option([
                '_alias' => '配送员提现列表',
            ]);
            Route::get('title', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'title'])->name('systemDeliveryServiceWithdrawTitle')->option([
                '_alias' => '配送员提现统计',
            ]);
            Route::get('export', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'export'])->name('systemDeliveryServiceWithdrawExport')->option([
                '_alias' => '配送员提现导出',
            ]);
            Route::get('detail/:id', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'detail'])->name('systemDeliveryServiceWithdrawDetail')->option([
                '_alias' => '配送员提现详情',
            ]);
            Route::get('create/form', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'createForm'])->name('systemDeliveryServiceWithdrawCreateForm')->option([
                '_alias' => '配送员提现添加表单',
                '_auth' => false,
                '_form' => 'systemDeliveryServiceWithdrawCreate',
            ]);
            Route::post('create', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'create'])
                ->name('systemDeliveryServiceWithdrawCreate')
                ->option([
                    '_alias' => '添加配送员提现',
                ]);
            Route::get('update/:id/form', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'updateForm'])
                ->name('systemDeliveryServiceWithdrawUpdateForm')
                ->option([
                '_alias' => '配送员提现编辑表单',
                '_auth' => false,
                '_form' => 'systemDeliveryServiceWithdrawUpdate',
            ]);
            Route::post('update/:id', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'update'])
                ->name('systemDeliveryServiceWithdrawUpdate')
                ->option([
                '_alias' => '编辑配送员提现',
            ]);
            Route::delete('delete/:id', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'delete'])
                ->name('systemDeliveryServiceWithdrawDelete')
                ->option([
                '_alias' => '删除配送员提现',
            ]);
            Route::get('audit/:id/form', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'auditForm'])
                ->name('systemDeliveryServiceWithdrawAuditForm')
                ->option([
                '_alias' => '配送员提现审核表单',
                '_auth' => false,
                '_form' => 'systemDeliveryServiceWithdrawAudit',
            ]);
            Route::post('audit/:id', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'audit'])
                ->name('systemDeliveryServiceWithdrawAudit')
                ->option([
                    '_alias' => '审核配送员提现',
                ]);
            Route::get('remark/:id/form', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'remarkForm'])
                ->name('systemDeliveryServiceWithdrawRemarkForm')
                ->option([
                    '_alias' => '配送员提现备注表单',
                    '_auth' => false,
                    '_form' => 'systemDeliveryServiceWithdrawRemark',
                ]);
            Route::post('remark/:id', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'remark'])
                ->name('systemDeliveryServiceWithdrawRemark')
                ->option([
                    '_alias' => '修改配送员提现备注',
                ]);
            Route::get('transfer/:id/form', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'transferForm'])
                ->name('systemDeliveryServiceWithdrawTransferForm')
                ->option([
                    '_alias' => '配送员提现转账表单',
                    '_auth' => false,
                    '_form' => 'systemDeliveryServiceWithdrawTransfer',
                ]);
            Route::post('transfer/:id', [app\controller\admin\delivery\DeliveryServiceWithdraw::class, 'transfer'])
                ->name('systemDeliveryServiceWithdrawTransfer')
                ->option([
                    '_alias' => '记录配送员提现转账',
                ]);
        })->option([
            '_path' => '/delivery/withdraw_manage',
        ]);

    })->option([
        '_auth' => true,
    ]);

    //地址快快递公司
    Route::group('store/express', function () {
        Route::get('lst', [app\controller\admin\store\Express::class, 'lst'])->name('systemExpressLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('status/:id', [app\controller\admin\store\Express::class, 'switchStatus'])->name('systemExpressSwitchStatus')->option([
            '_alias' => '修改状态',
        ]);
        Route::get('update/form/:id', [app\controller\admin\store\Express::class, 'updateForm'])->name('systemExpressUpdateForm')->option([
            '_alias' => '编辑表单',
            '_auth' => false,
            '_form' => 'systemExpressUpdate',
        ]);
        Route::post('update/:id', [app\controller\admin\store\Express::class, 'update'])->name('systemExpressUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::delete('delete/:id', [app\controller\admin\store\Express::class, 'delete'])->name('systemExpressDelete')->option([
            '_alias' => '删除',
        ]);
        Route::get('sync', [app\controller\admin\store\Express::class, 'syncAll'])->name('systemExpressSync')->option([
            '_alias' => '同步',
        ]);
        Route::get('/options', [app\controller\admin\store\Express::class, 'options'])->option([
            '_alias' => '列表',
            '_auth' => false,
        ]);
    })->option([
        '_path' => '/freight/express',
        '_auth' => true,
    ]);

    //地址快快递公司
    Route::group('store/city', function () {
        Route::get('lst/:id', [app\controller\admin\store\CityArea::class, 'lst'])->name('systemCityAreaLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('create/form/:id', [app\controller\admin\store\CityArea::class, 'createForm'])->name('systemCityAreaCreateForm')->option([
            '_alias' => '编辑表单',
            '_auth' => false,
            '_form' => 'systemCityAreaCreate',
        ]);
        Route::post('create', [app\controller\admin\store\CityArea::class, 'create'])->name('systemCityAreaCreate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('update/:id/form', [app\controller\admin\store\CityArea::class, 'updateForm'])->name('systemCityAreaUpdateForm')->option([
            '_alias' => '编辑表单',
            '_auth' => false,
            '_form' => 'systemExpressUpdate',
        ]);
        Route::post('update/:id', [app\controller\admin\store\CityArea::class, 'update'])->name('systemCityAreaUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::delete('delete/:id', [app\controller\admin\store\CityArea::class, 'delete'])->name('systemCityAreaDelete')->option([
            '_alias' => '删除',
        ]);
    })->option([
        '_path' => '/freight/express',
        '_auth' => true,
    ]);


})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
