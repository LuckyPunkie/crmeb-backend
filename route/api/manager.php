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
use app\common\middleware\UserTokenMiddleware;
use app\common\middleware\MerchantServerMiddleware;
use app\common\middleware\AllowOriginMiddleware;

Route::group(function () {

    //客服聊天
    Route::group('service', function () {
        Route::get('history/:id', 'api.store.service.Service/chatHistory');
        Route::get('list', 'api.store.service.Service/getList');
        Route::get('mer_history/:merId/:id', 'api.store.service.Service/serviceHistory');
        Route::get('user_list/:merId', 'api.store.service.Service/serviceUserList');
        //客服扫码登录
        Route::post('scan_login/:key', 'api.store.service.Service/scanLogin');
        Route::get('user/:merId/:uid', 'api.store.service.Service/user');
        Route::post('mark/:merId/:uid', 'api.store.service.Service/mark');
    });

    //客服商品管理
    Route::group('server/', function () {
        //添加
        Route::post(':merId/product/create', 'StoreProduct/create');
        //编辑
        Route::post(':merId/product/update/:id', 'StoreProduct/update');
        Route::get(':merId/product/detail/:id', 'StoreProduct/detail');
        Route::post(':merId/product/delete/:id', 'StoreProduct/delete');
        Route::post(':merId/product/status/:id', 'StoreProduct/switchStatus');
        Route::get(':merId/product/lst', 'StoreProduct/lst');
        Route::get(':merId/product/title', 'StoreProduct/title');
        Route::post(':merId/product/restore/:id', 'StoreProduct/restore');
        Route::post(':merId/product/destory/:id', 'StoreProduct/destory');
        Route::post(':merId/product/good/:id', 'StoreProduct/updateGood');
        Route::get(':merId/product/config', 'StoreProduct/config');
        //修改分类
        Route::post(':merId/product/edit_cate/:id', 'StoreProduct/editCate');
        Route::post(':merId/product/edit_mer_cate/:id', 'StoreProduct/editMerCate');
        Route::get(':merId/product/value/:id', 'StoreProduct/getValue');
        Route::post(':merId/product/value/:id', 'StoreProduct/setValue');

        //商品分类
        Route::get(':merId/category/lst', 'StoreCategory/lst');
        Route::post(':merId/category/create', 'StoreCategory/create');
        Route::post(':merId/category/update/:id', 'StoreCategory/update');
        Route::get(':merId/category/detail/:id', 'StoreCategory/detail');
        Route::post(':merId/category/status/:id', 'StoreCategory/switchStatus');
        Route::post(':merId/category/delete/:id', 'StoreCategory/delete');
        Route::get(':merId/category/list', 'StoreCategory/getList');
        Route::get(':merId/category/select', 'StoreCategory/getTreeList');
        Route::get(':merId/category/brandlist', 'StoreCategory/BrandList');

        //运费模板
        Route::get(':merId/template/lst', 'ShippingTemplate/lst');
        Route::post(':merId/template/create', 'ShippingTemplate/create');
        Route::post(':merId/template/update/:id', 'ShippingTemplate/update');
        Route::get(':merId/template/select', 'ShippingTemplate/getList');
        Route::get(':merId/template/detail/:id', 'ShippingTemplate/detail');
        Route::post(':merId/template/delete', 'ShippingTemplate/batchDelete');

        //品牌管理
        Route::get(':merId/attr/lst', 'StoreProductAttrTemplate/lst');
        Route::post(':merId/attr/create', 'StoreProductAttrTemplate/create');
        Route::post(':merId/attr/update/:id', 'StoreProductAttrTemplate/update');
        Route::get(':merId/attr/detail/:id', 'StoreProductAttrTemplate/detail');
        Route::post(':merId/attr/delete', 'StoreProductAttrTemplate/batchDelete');
        Route::get(':merId/attr/detail/:id', 'StoreProductAttrTemplate/detail');
        Route::get(':merId/attr/list', 'StoreProductAttrTemplate/getlist');
    })
        ->prefix('api.server.')
        ->middleware(
            MerchantServerMiddleware::class,
            ['reqire' => true, 'auth' => 1]
        );

    Route::group(function () {
        //订单
        Route::group('admin', function () {
            Route::get(':merId/statistics', '/orderStatistics');
            Route::get(':merId/order_price', '/orderDetail');
            Route::get(':merId/order_list', '/orderList');
            Route::get(':merId/order/:id', '/order');
            Route::post(':merId/mark/:id', '/mark');
            Route::post(':merId/price/:id', '/price');
            Route::post(':merId/delivery/:id', '/delivery');
            Route::post(':merId/verify/:id', '/verify');
            Route::get(':merId/pay_price', '/payPrice');
            Route::get(':merId/pay_number', '/payNumber');
            Route::get(':merId/mer_form', '/getFormData');
            Route::get(':merId/dump_temp', '/getFormData');
            Route::get(':merId/delivery_config', '/getDeliveryConfig');
            Route::get(':merId/delivery_options', '/getDeliveryOptions');
            Route::get(':merId/delivery/options', '/options');
            Route::post(':merId/offline/:id', '/offline');
            // 预约
            Route::get(':merId/reservation/staffs', '/staffList');
            Route::post(':merId/reservationdispatch/:id', '/reservationDispatch');
            Route::post(':merId/reservationupdateDispatch/:id', '/reservationUpdateDispatch');
            Route::post(':merId/reservationreschedule/:id', '/reservationReschedule');
            Route::post(':merId/reservationverify/:id', '/reservationVerify');
            Route::get(':merId/reservationconfig', '/reservationConfig');

            // 同城配送
            Route::get(':merId/delivery/person', '/deliveryPersonList');
            Route::post(':merId/delivery/dispatch/:id', '/deliveryDispatch');
            Route::post(':merId/delivery/updateDispatch/:id', '/deliveryUpdateDispatch');
            Route::post(':merId/delivery/confirm/:id', '/deliveryConfirm');
        })
            ->prefix('api.server.StoreOrder');
        //管理员订单相关
        Route::group('server', function () {
            //退款单
            Route::get(':merId/refund/check/:id', '/check');
            Route::post(':merId/refund/create', '/create');
            Route::post(':merId/refund/compute', '/compute');
            Route::get(':merId/refund/lst', '/lst');
            Route::get(':merId/refund/detail/:id', '/detail');
            Route::get(':merId/refund/get/:id', '/getRefundPrice');
            Route::post(':merId/refund/confirm/:id', '/refundPrice');
            Route::get(':merId/refund/express/:id', '/express');
            Route::post(':merId/refund/status/:id', '/switchStatus');
            Route::post(':merId/refund/mark/:id', '/mark');
        })
            ->prefix('api.server.StoreRefundOrder');
    })
        ->middleware(
            MerchantServerMiddleware::class,
            ['reqire' => true,'auth' => 0]
        );
    //核销订单
    Route::group(function () {
        Route::group('verifier', function () {
            Route::get('/:merId/order/:id', '/detail');
            Route::post('/:merId/:id', '/verify');
        })->prefix('api.store.order.StoreOrderVerify');
    })
        ->middleware(
            MerchantServerMiddleware::class,
            ['reqire' => false,'auth' => 0],
            ['reqire' => false]
        );

    Route::group(function () {
        Route::group('staffs/', function () {
            Route::get('order_lst', '/order_lst');
            Route::get('order/:id', '/orderDetail');
            Route::post('order/:id/dispatch', '/reservationDispatch');
            Route::post('order/:id/verifier', '/verify');
            Route::post('order/:id/check', '/checkIn');
            Route::post('order/:id/trace', '/addTrace');
            Route::post('order/:id/mark', '/mark');
            Route::get('reservation/config', '/reservationConfig');
        })
            ->prefix('api.store.service.Staffs');
    })
        ->middleware(
            MerchantServerMiddleware::class,
            [],
            ['reqire' => true]
        );

    Route::group(function () {
        Route::group('delivery/', function () {
            Route::get('order_lst', '/order_lst')->name('deliveryOrderLst');
            Route::get('order/:id', '/orderDetail')->name('deliveryOrderDetail');
            Route::post('order/:id/receive', '/receive');
            Route::post('order/:id/confirm', '/confirm');
            Route::post('order/:id/mark', '/mark');
        })
            ->prefix('api.store.service.Delivery');
    })
        ->middleware(
            MerchantServerMiddleware::class,
            [],
            [],
            ['reqire' => true]
        );
})
    ->middleware(AllowOriginMiddleware::class)
    ->middleware(UserTokenMiddleware::class,true);
