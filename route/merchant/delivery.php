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

    //快递公司
    Route::group('expr',function(){
        Route::get('/lst', [app\controller\admin\store\Express::class, 'lst'])->name('merchantServeExportLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('/options', [app\controller\admin\store\Express::class, 'options'])->option([
            '_alias' => '列表',
            '_auth' => false,
        ]);

        Route::get('/partner/:id/form', [app\controller\admin\store\Express::class, 'partnerForm'])->name('merchantExpressPratnerUpdateForm')->option([
            '_alias' => '月结账号编辑表单',
            '_auth' => false,
            '_form' => 'merchantExpressPratnerUpdate',
        ]);
        Route::post('/partner/:id', [app\controller\admin\store\Express::class, 'partner'])->name('merchantExpressPratnerUpdate')->option([
            '_alias' => '月结账号编辑',
        ]);
        Route::post('/changeMerStatus/:id', [app\controller\admin\store\Express::class, 'merStatus'])->name('merchantExpressChangeMerStatus')->option([
            '_alias' => '修改状态',
        ]);
    })->option([
        '_path' => '/config/freight/express',
        '_auth' => true,
    ]);

    //同城配送
    Route::group('delivery/station', function () {
        //获取分类
        Route::get('business', [app\controller\merchant\store\delivery\DeliveryStation::class, 'getBusiness'])->name('merchantStoreDeliveryBusiness')->option([
                '_alias' => '获取分类',
            ]);
        //添加
        Route::post('create', [app\controller\merchant\store\delivery\DeliveryStation::class, 'create'])->name('merchantStoreDeliveryCreate')->option([
                '_alias' => '添加',
            ]);
        //编辑
        Route::post('update/:id', [app\controller\merchant\store\delivery\DeliveryStation::class, 'update'])->name('merchantStoreDeliveryUpdate')->option([
                '_alias' => '编辑',
            ]);
        //编辑状态
        Route::post('status/:id', [app\controller\merchant\store\delivery\DeliveryStation::class, 'switchWithStatus'])->name('merchantStoreDeliveryStatus')->option([
                '_alias' => '编辑状态',
            ]);
        //列表
        Route::get('lst', [app\controller\merchant\store\delivery\DeliveryStation::class, 'lst'])->name('merchantStoreDeliveryLst')->option([
                '_alias' => '列表',
            ]);
        //详情
        Route::get('detail/:id', [app\controller\merchant\store\delivery\DeliveryStation::class, 'detail'])->name('merchantStoreDeliveryDetail')->option([
                '_alias' => '详情',
            ]);
        //删除
        Route::delete('delete/:id', [app\controller\merchant\store\delivery\DeliveryStation::class, 'delete'])->name('merchantStoreDeliveryDelete')->option([
                '_alias' => '删除',
            ]);
        //备注
        Route::get('mark/:id/form', [app\controller\merchant\store\delivery\DeliveryStation::class, 'markForm'])->name('merchantStoreDeliveryMarkForm')->option([
            '_alias' => '备注表单',
            '_auth' => false,
            '_form' => 'merchantStoreDeliveryMark',
        ]);
        Route::post('mark/:id', [app\controller\merchant\store\delivery\DeliveryStation::class, 'mark'])->name('merchantStoreDeliveryMark')->option([
            '_alias' => '备注',
        ]);

        Route::get('options', [app\controller\merchant\store\delivery\DeliveryStation::class, 'options'])->option([
            '_alias' => '列表',
            '_auth' => false,
        ]);

        Route::get('select', [app\controller\merchant\store\delivery\DeliveryStation::class, 'select'])->option([
            '_alias' => '列表',
            '_auth' => false,
        ]);
        //城市列表
        Route::get('getCity', [app\controller\merchant\store\delivery\DeliveryStation::class, 'getCityLst'])->name('merchantStoreDeliveryCityList')->option([
                '_alias' => '城市列表',
            ]);

        //充值记录
        Route::get('payLst', [app\controller\merchant\store\delivery\DeliveryStation::class, 'payLst'])->name('merchantStoreDeliveryPayLst')->option([
                '_alias' => '充值记录',
                '_path' => '/delivery/recharge_record',
            ]);
        Route::get('code', [app\controller\merchant\store\delivery\DeliveryStation::class, 'getQrcode'])->name('merchantStoreDeliveryGetQrcode')->option([
            '_alias' => '充值二维码',
            '_path' => '/delivery/recharge_record',
            '_auth' => false,
        ]);

    })->option([
        '_path' => '/delivery/store_manage',
        '_auth' => true,
    ]);

    Route::group('delivery/service', function () {
        Route::get('lst', [app\controller\merchant\store\delivery\DeliveryService::class, 'lst'])->name('merchantDeliveryServiceLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('detail/:id', [app\controller\merchant\store\delivery\DeliveryService::class, 'detail'])->name('merchantDeliveryServiceDetail')->option([
            '_alias' => '配送员详情',
        ]);
        Route::get('order/lst/:id', [app\controller\merchant\store\delivery\DeliveryService::class, 'orderList'])->name('merchantDeliveryServiceOrderList')->option([
            '_alias' => '配送员配送订单',
        ]);
        Route::get('withdraw/success/:id', [app\controller\merchant\store\delivery\DeliveryService::class, 'withdrawSuccessList'])->name('merchantDeliveryServiceWithdrawSuccessList')->option([
            '_alias' => '配送员提现成功记录',
        ]);
        Route::post('status/:id', [app\controller\merchant\store\delivery\DeliveryService::class, 'switchWithStatus'])->name('merchantDeliveryServiceStatus')->option([
            '_alias' => '修改接单状态',
        ]);
        Route::post('change/:id', [app\controller\merchant\store\delivery\DeliveryService::class, 'changeStatus'])
            ->name('merchantDeliveryServiceOpen')->option([
            '_alias' => '修改状态',
        ]);
        Route::get('create/form', [app\controller\merchant\store\delivery\DeliveryService::class, 'createForm'])->name('merchantDeliveryServiceCreateForm')->option([
            '_alias' => '添加表单',
            '_auth' => false,
            '_form' => 'merchantServiceCreate',
        ]);
        //添加
        Route::post('create', [app\controller\merchant\store\delivery\DeliveryService::class, 'create'])->name('merchantDeliveryServiceCreate')->option([
            '_alias' => '添加',
        ]);
        //编辑
        Route::get('update/:id/form', [app\controller\merchant\store\delivery\DeliveryService::class, 'updateForm'])->name('merchantDeliveryServiceUpdateForm')->option([
            '_alias' => '编辑',
            '_auth' => false,
            '_form' => 'merchantServiceUpdate',
        ]);
        //编辑
        Route::post('update/:id', [app\controller\merchant\store\delivery\DeliveryService::class, 'update'])->name('merchantDeliveryServiceUpdate')->option([
            '_alias' => '编辑',
        ]);
        //删除
        Route::delete('delete/:id', [app\controller\merchant\store\delivery\DeliveryService::class, 'delete'])->name('merchantDeliveryServiceDelete')->option([
            '_alias' => '删除',
        ]);
        Route::get('statisticsList', [app\controller\merchant\store\delivery\DeliveryService::class, 'statisticsList'])->name('merchantDeliveryServiceStatisticsList')->option([
            '_alias' => '统计列表',
        ]);
        Route::get('statisticsDetail/:id', [app\controller\merchant\store\delivery\DeliveryService::class, 'statisticsDetail'])->name('merchantDeliveryServiceStatisticsDetail')->option([
            '_alias' => '统计详情',
        ]);
        Route::get('options', [app\controller\merchant\store\delivery\DeliveryService::class, 'options']);
    })->option([
        '_path' => '/delivery/personnel_manage',
        '_auth' => true,
    ]);

    Route::group('delivery/service/bill', function () {
        Route::get('lst', [app\controller\merchant\store\delivery\DeliveryServiceBill::class, 'lst'])->name('merchantDeliveryServiceBillLst')->option([
            '_alias' => '配送员流水列表',
        ]);
        Route::get('detail/:id', [app\controller\merchant\store\delivery\DeliveryServiceBill::class, 'detail'])->name('merchantDeliveryServiceBillDetail')->option([
            '_alias' => '配送员流水详情',
        ]);
    })->option([
        '_path' => '/delivery/service_bill',
        '_auth' => true,
    ]);

    Route::group('delivery/service/withdraw', function () {
        Route::get('lst', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'lst'])->name('merchantDeliveryServiceWithdrawLst')->option([
            '_alias' => '配送员提现列表',
        ]);
        Route::get('title', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'title'])->name('merchantDeliveryServiceWithdrawTitle')->option([
            '_alias' => '配送员提现统计',
        ]);
        Route::get('detail/:id', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'detail'])->name('merchantDeliveryServiceWithdrawDetail')->option([
            '_alias' => '配送员提现详情',
        ]);
        Route::get('create/form', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'createForm'])->name('merchantDeliveryServiceWithdrawCreateForm')->option([
            '_alias' => '配送员提现添加表单',
            '_auth' => false,
            '_form' => 'merchantDeliveryServiceWithdrawCreate',
        ]);
        Route::post('create', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'create'])->name('merchantDeliveryServiceWithdrawCreate')->option([
            '_alias' => '添加配送员提现',
        ]);
        Route::get('update/:id/form', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'updateForm'])->name('merchantDeliveryServiceWithdrawUpdateForm')->option([
            '_alias' => '配送员提现编辑表单',
            '_auth' => false,
            '_form' => 'merchantDeliveryServiceWithdrawUpdate',
        ]);
        Route::post('update/:id', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'update'])->name('merchantDeliveryServiceWithdrawUpdate')->option([
            '_alias' => '编辑配送员提现',
        ]);
        Route::delete('delete/:id', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'delete'])->name('merchantDeliveryServiceWithdrawDelete')->option([
            '_alias' => '删除配送员提现',
        ]);
        Route::get('audit/:id/form', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'auditForm'])->name('merchantDeliveryServiceWithdrawAuditForm')->option([
            '_alias' => '配送员提现审核表单',
            '_auth' => false,
            '_form' => 'merchantDeliveryServiceWithdrawAudit',
        ]);
        Route::post('audit/:id', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'audit'])->name('merchantDeliveryServiceWithdrawAudit')->option([
            '_alias' => '审核配送员提现',
        ]);
        Route::get('remark/:id/form', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'remarkForm'])->name('merchantDeliveryServiceWithdrawRemarkForm')->option([
            '_alias' => '配送员提现备注表单',
            '_auth' => false,
            '_form' => 'merchantDeliveryServiceWithdrawRemark',
        ]);
        Route::post('remark/:id', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'remark'])->name('merchantDeliveryServiceWithdrawRemark')->option([
            '_alias' => '修改配送员提现备注',
        ]);
        Route::get('transfer/:id/form', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'transferForm'])->name('merchantDeliveryServiceWithdrawTransferForm')->option([
            '_alias' => '配送员提现转账表单',
            '_auth' => false,
            '_form' => 'merchantDeliveryServiceWithdrawTransfer',
        ]);
        Route::post('transfer/:id', [app\controller\merchant\store\delivery\DeliveryServiceWithdraw::class, 'transfer'])->name('merchantDeliveryServiceWithdrawTransfer')->option([
            '_alias' => '记录配送员提现转账',
        ]);
    })->option([
        '_path' => '/delivery/withdraw_manage',
        '_auth' => true,
    ]);


    //同城配送
    Route::group('delivery/order', function () {
        //
        Route::get('lst', [app\controller\merchant\store\delivery\DeliveryOrder::class, 'lst'])
            ->name('merchantStoreDeliveryOrderLst')->option([
                '_alias' => '列表',
            ]);
        Route::get('manage/lst', [app\controller\merchant\store\delivery\DeliveryOrder::class, 'manageList'])
            ->name('merchantDeliveryOrderManageLst')->option([
                '_alias' => '商户配送订单列表',
            ]);
        Route::get('manage/title', [app\controller\merchant\store\delivery\DeliveryOrder::class, 'manageTitle'])
            ->name('merchantDeliveryOrderManageTitle')->option([
                '_alias' => '商户配送订单统计',
            ]);
        Route::post('manage/batch/dispatch', [app\controller\merchant\store\delivery\DeliveryOrder::class, 'batchDispatch'])
            ->name('merchantDeliveryOrderBatchDispatch')->option([
                '_alias' => '商户配送订单批量派单',
            ]);
        Route::post('manage/batch/confirm', [app\controller\merchant\store\delivery\DeliveryOrder::class, 'batchConfirm'])
            ->name('merchantDeliveryOrderBatchConfirm')->option([
                '_alias' => '商户配送订单批量确认送达',
            ]);
        //取消
        Route::get('cancel/:id/form', [app\controller\merchant\store\delivery\DeliveryOrder::class, 'cancelForm'])
            ->name('merchantStoreDeliveryOrderCancelForm')->option([
                '_alias' => '取消表单',
                '_auth' => false,
                '_form' => 'merchantStoreDeliveryOrderCancel',
            ]);

        Route::post('cancel/:id', [app\controller\merchant\store\delivery\DeliveryOrder::class, 'cancel'])
            ->name('merchantStoreDeliveryOrderCancel')->option([
                '_alias' => '取消',
            ]);

        //详情
        Route::get('detail/:id', [app\controller\merchant\store\delivery\DeliveryOrder::class, 'detail'])
            ->name('merchantStoreDeliveryOrderDetail')->option([
                '_alias' => '详情',
            ]);

    })->option([
        '_path' => '/delivery/usage_record',
        '_auth' => true,
    ]);

    //运费模板
    Route::group('store/shipping', function () {
        Route::get('lst', [app\controller\merchant\store\shipping\ShippingTemplate::class, 'lst'])->name('merchantStoreShippingTemplateLst')->option([
            '_alias' => '列表',
            '_auth' => false,
        ]);
        Route::get('list', [app\controller\merchant\store\shipping\ShippingTemplate::class, 'getList'])->option([
            '_alias' => '列表 ',
        ]);
        Route::post('create', [app\controller\merchant\store\shipping\ShippingTemplate::class, 'create'])->name('merchantStoreShippingTemplateCreate')->option([
            '_alias' => '添加 ',
        ]);
        Route::post('update/:id', [app\controller\merchant\store\shipping\ShippingTemplate::class, 'update'])->name('merchantStoreShippingTemplateUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('detail/:id', [app\controller\merchant\store\shipping\ShippingTemplate::class, 'detail'])->name('merchantStoreShippingTemplateDetail')->option([
            '_alias' => '详情',
        ]);
        Route::delete('delete/:id', [app\controller\merchant\store\shipping\ShippingTemplate::class, 'delete'])->name('merchantStoreShippingTemplateDelete')->option([
            '_alias' => '删除',
        ]);
        Route::get('setDefault/:id', [app\controller\merchant\store\shipping\ShippingTemplate::class, 'setDefault'])->name('merchantStoreShippingTemplateSetDefault')->option([
            '_alias' => '设置默认模板',
        ]);
    })->option([
        '_path' => '/config/freight/shippingTemplates',
        '_auth' => true,
    ]);

    //地址信息
    Route::get('system/city/lst', [app\controller\merchant\store\shipping\City::class, 'lst'])->option([
        '_alias' => '列表',
        '_auth' => false,
    ]);
    Route::get('v2/system/city/lst/:pid', [app\controller\merchant\store\shipping\City::class, 'lstV2'])->option([
        '_alias' => '列表',
        '_auth' => false,
    ]);
    // 配送设置
    Route::group('delivery/config', function () {
        Route::get('settings', [app\controller\merchant\store\delivery\DeliveryConfig::class, 'configuration'])->name('merchantDeliveryConfigSettings')->option([
            '_alias' => '配送设置信息'
        ]);
        Route::post('update/:id', [app\controller\merchant\store\delivery\DeliveryConfig::class, 'update'])->name('merchantDeliveryConfigUpdate')->option([
            '_alias' => '更新配送设置信息'
        ]);
    })->option([
        '_path' => '/delivery/delivery_settings',
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
