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
        Route::get('/lst','/lst')->name('merchantServeExportLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('/options','/options')->option([
            '_alias' => '列表',
            '_auth' => false,
        ]);

        Route::get('/partner/:id/form','/partnerForm')->name('merchantExpressPratnerUpdateForm')->option([
            '_alias' => '月结账号编辑表单',
            '_auth' => false,
            '_form' => 'merchantExpressPratnerUpdate',
        ]);
        Route::post('/partner/:id','/partner')->name('merchantExpressPratnerUpdate')->option([
            '_alias' => '月结账号编辑',
        ]);
        Route::post('/changeMerStatus/:id','/merStatus')->name('merchantExpressChangeMerStatus')->option([
            '_alias' => '修改状态',
        ]);
    })->prefix('admin.store.Express')->option([
        '_path' => '/config/freight/express',
        '_auth' => true,
    ]);

    //同城配送
    Route::group('delivery/station', function () {
        //获取分类
        Route::get('business','/getBusiness')->name('merchantStoreDeliveryBusiness')->option([
                '_alias' => '获取分类',
            ]);
        //添加
        Route::post('create','/create')->name('merchantStoreDeliveryCreate')->option([
                '_alias' => '添加',
            ]);
        //编辑
        Route::post('update/:id','/update')->name('merchantStoreDeliveryUpdate')->option([
                '_alias' => '编辑',
            ]);
        //编辑状态
        Route::post('status/:id','/switchWithStatus')->name('merchantStoreDeliveryStatus')->option([
                '_alias' => '编辑状态',
            ]);
        //列表
        Route::get('lst','/lst')->name('merchantStoreDeliveryLst')->option([
                '_alias' => '列表',
            ]);
        //详情
        Route::get('detail/:id','/detail')->name('merchantStoreDeliveryDetail')->option([
                '_alias' => '详情',
            ]);
        //删除
        Route::delete('delete/:id','/delete')->name('merchantStoreDeliveryDelete')->option([
                '_alias' => '删除',
            ]);
        //备注
        Route::get('mark/:id/form','/markForm')->name('merchantStoreDeliveryMarkForm')->option([
            '_alias' => '备注表单',
            '_auth' => false,
            '_form' => 'merchantStoreDeliveryMark',
        ]);
        Route::post('mark/:id','/mark')->name('merchantStoreDeliveryMark')->option([
            '_alias' => '备注',
        ]);

        Route::get('options','/options')->option([
            '_alias' => '列表',
            '_auth' => false,
        ]);

        Route::get('select','/select')->option([
            '_alias' => '列表',
            '_auth' => false,
        ]);
        //城市列表
        Route::get('getCity','/getCityLst')->name('merchantStoreDeliveryCityList')->option([
                '_alias' => '城市列表',
            ]);

        //充值记录
        Route::get('payLst','/payLst')->name('merchantStoreDeliveryPayLst')->option([
                '_alias' => '充值记录',
                '_path' => '/delivery/recharge_record',
            ]);
        Route::get('code','/getQrcode')->name('merchantStoreDeliveryGetQrcode')->option([
            '_alias' => '充值二维码',
            '_path' => '/delivery/recharge_record',
            '_auth' => false,
        ]);

    })->prefix('merchant.store.delivery.DeliveryStation')->option([
        '_path' => '/delivery/store_manage',
        '_auth' => true,
    ]);

    Route::group('delivery/service', function () {
        Route::get('lst','/lst')->name('merchantDeliveryServiceLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('status/:id','/switchWithStatus')->name('merchantDeliveryServiceStatus')->option([
            '_alias' => '修改状态',
        ]);
        Route::get('create/form','/createForm')->name('merchantDeliveryServiceCreateForm')->option([
            '_alias' => '添加表单',
            '_auth' => false,
            '_form' => 'merchantServiceCreate',
        ]);
        //添加
        Route::post('create','/create')->name('merchantDeliveryServiceCreate')->option([
            '_alias' => '添加',
        ]);
        //编辑
        Route::get('update/:id/form','/updateForm')->name('merchantDeliveryServiceUpdateForm')->option([
            '_alias' => '编辑',
            '_auth' => false,
            '_form' => 'merchantServiceUpdate',
        ]);
        //编辑
        Route::post('update/:id','/update')->name('merchantDeliveryServiceUpdate')->option([
            '_alias' => '编辑',
        ]);
        //删除
        Route::delete('delete/:id','/delete')->name('merchantDeliveryServiceDelete')->option([
            '_alias' => '删除',
        ]);
        Route::get('statisticsList','/statisticsList')->name('merchantDeliveryServiceStatisticsList')->option([
            '_alias' => '统计列表',
        ]);
        Route::get('statisticsDetail/:id','/statisticsDetail')->name('merchantDeliveryServiceStatisticsDetail')->option([
            '_alias' => '统计详情',
        ]);
        Route::get('options','/options');
    })->prefix('merchant.store.delivery.DeliveryService')->option([
        '_path' => '/delivery/personnel_manage',
        '_auth' => true,
    ]);


    //同城配送
    Route::group('delivery/order', function () {
        //
        Route::get('lst','/lst')
            ->name('merchantStoreDeliveryOrderLst')->option([
                '_alias' => '列表',
            ]);
        //取消
        Route::get('cancel/:id/form','/cancelForm')
            ->name('merchantStoreDeliveryOrderCancelForm')->option([
                '_alias' => '取消表单',
                '_auth' => false,
                '_form' => 'merchantStoreDeliveryOrderCancel',
            ]);

        Route::post('cancel/:id','/cancel')
            ->name('merchantStoreDeliveryOrderCancel')->option([
                '_alias' => '取消',
            ]);

        //详情
        Route::get('detail/:id','/detail')
            ->name('merchantStoreDeliveryOrderDetail')->option([
                '_alias' => '详情',
            ]);

    })->prefix('merchant.store.delivery.DeliveryOrder')->option([
        '_path' => '/delivery/usage_record',
        '_auth' => true,
    ]);

    //运费模板
    Route::group('store/shipping', function () {
        Route::get('lst', '/lst')->name('merchantStoreShippingTemplateLst')->option([
            '_alias' => '列表',
            '_auth' => false,
        ]);
        Route::get('list', '/getList')->option([
            '_alias' => '列表 ',
        ]);
        Route::post('create', '/create')->name('merchantStoreShippingTemplateCreate')->option([
            '_alias' => '添加 ',
        ]);
        Route::post('update/:id', '/update')->name('merchantStoreShippingTemplateUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('detail/:id', '/detail')->name('merchantStoreShippingTemplateDetail')->option([
            '_alias' => '详情',
        ]);
        Route::delete('delete/:id', '/delete')->name('merchantStoreShippingTemplateDelete')->option([
            '_alias' => '删除',
        ]);
        Route::get('setDefault/:id', '/setDefault')->name('merchantStoreShippingTemplateSetDefault')->option([
            '_alias' => '设置默认模板',
        ]);
    })->prefix('merchant.store.shipping.ShippingTemplate')->option([
        '_path' => '/config/freight/shippingTemplates',
        '_auth' => true,
    ]);

    //地址信息
    Route::get('system/city/lst', 'merchant.store.shipping.City/lst')->option([
        '_alias' => '列表',
        '_auth' => false,
    ]);
    Route::get('v2/system/city/lst/:pid', 'merchant.store.shipping.City/lstV2')->option([
        '_alias' => '列表',
        '_auth' => false,
    ]);
    // 配送设置
    Route::group('delivery/config', function () {
        Route::get('settings','/configuration')->name('merchantDeliveryConfigSettings')->option([
            '_alias' => '配送设置信息'
        ]);
        Route::post('update/:id','/update')->name('merchantDeliveryConfigUpdate')->option([
            '_alias' => '更新配送设置信息'
        ]);
    })->prefix('merchant.store.delivery.DeliveryConfig')->option([
        '_path' => '/delivery/delivery_settings',
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
