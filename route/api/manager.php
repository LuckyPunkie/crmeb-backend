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
        Route::get('history/:id', [app\controller\api\store\service\Service::class,'chatHistory']);
        Route::get('list', [app\controller\api\store\service\Service::class,'getList']);
        Route::get('mer_history/:merId/:id', [app\controller\api\store\service\Service::class,'serviceHistory']);
        Route::get('user_list/:merId', [app\controller\api\store\service\Service::class,'serviceUserList']);
        //客服扫码登录
        Route::post('scan_login/:key', [app\controller\api\store\service\Service::class,'scanLogin']);
        Route::get('user/:merId/:uid', [app\controller\api\store\service\Service::class,'user']);
        Route::post('mark/:merId/:uid', [app\controller\api\store\service\Service::class,'mark']);
    });

    //客服商品管理
    Route::group('server', function () {
        //添加
        Route::post(':merId/product/create', [app\controller\api\server\StoreProduct::class,'create']);
        Route::post(':merId/product/update/:id', [app\controller\api\server\StoreProduct::class,'update']);
        Route::get(':merId/product/detail/:id', [app\controller\api\server\StoreProduct::class,'detail']);
        Route::post(':merId/product/delete/:id', [app\controller\api\server\StoreProduct::class,'delete']);
        Route::post(':merId/product/status/:id', [app\controller\api\server\StoreProduct::class,'switchStatus']);
        Route::get(':merId/product/lst', [app\controller\api\server\StoreProduct::class,'lst']);
        Route::get(':merId/product/statistics', [app\controller\api\server\StoreProduct::class,'statistics']);
        Route::get(':merId/product/title', [app\controller\api\server\StoreProduct::class,'title']);
        Route::post(':merId/product/restore/:id', [app\controller\api\server\StoreProduct::class,'restore']);
        Route::post(':merId/product/destory/:id', [app\controller\api\server\StoreProduct::class,'destory']);
        Route::post(':merId/product/good/:id', [app\controller\api\server\StoreProduct::class,'updateGood']);
        Route::get(':merId/product/config', [app\controller\api\server\StoreProduct::class,'config']);
        //修改分类
        Route::post(':merId/product/edit_cate/:id', [app\controller\api\server\StoreProduct::class,'editCate']);
        Route::post(':merId/product/edit_mer_cate/:id', [app\controller\api\server\StoreProduct::class,'editMerCate']);
        Route::get(':merId/product/value/:id', [app\controller\api\server\StoreProduct::class,'getValue']);
        Route::post(':merId/product/value/:id', [app\controller\api\server\StoreProduct::class,'setValue']);
        //商品分类
        Route::get(':merId/category/lst', [app\controller\api\server\StoreCategory::class,'lst']);
        Route::post(':merId/category/create', [app\controller\api\server\StoreCategory::class,'create']);
        Route::post(':merId/category/update/:id', [app\controller\api\server\StoreCategory::class,'update']);
        Route::get(':merId/category/detail/:id', [app\controller\api\server\StoreCategory::class,'detail']);
        Route::post(':merId/category/status/:id', [app\controller\api\server\StoreCategory::class,'switchStatus']);
        Route::post(':merId/category/delete/:id', [app\controller\api\server\StoreCategory::class,'delete']);
        Route::get(':merId/category/list', [app\controller\api\server\StoreCategory::class,'getList']);
        Route::get(':merId/category/select', [app\controller\api\server\StoreCategory::class,'getTreeList']);
        Route::get(':merId/category/brandlist', [app\controller\api\server\StoreCategory::class,'BrandList']);

        //运费模板
        Route::get(':merId/template/lst', [app\controller\api\server\ShippingTemplate::class,'lst']);
        Route::post(':merId/template/create', [app\controller\api\server\ShippingTemplate::class,'create']);
        Route::post(':merId/template/update/:id', [app\controller\api\server\ShippingTemplate::class,'update']);
        Route::get(':merId/template/select', [app\controller\api\server\ShippingTemplate::class,'getList']);
        Route::get(':merId/template/detail/:id', [app\controller\api\server\ShippingTemplate::class,'detail']);
        Route::post(':merId/template/delete', [app\controller\api\server\ShippingTemplate::class,'batchDelete']);

        //品牌管理
        Route::get(':merId/attr/lst', [app\controller\api\server\StoreProductAttrTemplate::class,'lst']);
        Route::post(':merId/attr/create', [app\controller\api\server\StoreProductAttrTemplate::class,'create']);
        Route::post(':merId/attr/update/:id', [app\controller\api\server\StoreProductAttrTemplate::class,'update']);
        Route::get(':merId/attr/detail/:id', [app\controller\api\server\StoreProductAttrTemplate::class,'detail']);
        Route::post(':merId/attr/delete', [app\controller\api\server\StoreProductAttrTemplate::class,'batchDelete']);
        Route::get(':merId/attr/detail/:id', [app\controller\api\server\StoreProductAttrTemplate::class,'detail']);
        Route::get(':merId/attr/list', [app\controller\api\server\StoreProductAttrTemplate::class,'getlist']);
    })
        ->middleware(
            MerchantServerMiddleware::class,
            ['reqire' => true, 'auth' => 1]
        );

    Route::group(function () {
        //订单
        Route::group('admin', function () {
            Route::get(':merId/statistics', [app\controller\api\server\StoreOrder::class,'orderStatistics']);
            Route::get(':merId/order_price', [app\controller\api\server\StoreOrder::class,'orderDetail']);
            Route::get(':merId/order_list', [app\controller\api\server\StoreOrder::class,'orderList']);
            Route::get(':merId/order/:id', [app\controller\api\server\StoreOrder::class,'order']);
            Route::post(':merId/mark/:id', [app\controller\api\server\StoreOrder::class,'mark']);
            Route::post(':merId/price/:id', [app\controller\api\server\StoreOrder::class,'price']);
            Route::post(':merId/delivery/:id', [app\controller\api\server\StoreOrder::class,'delivery']);
            Route::post(':merId/verify/:id', [app\controller\api\server\StoreOrder::class,'verify']);
            Route::get(':merId/pay_price', [app\controller\api\server\StoreOrder::class,'payPrice']);
            Route::get(':merId/pay_number', [app\controller\api\server\StoreOrder::class,'payNumber']);
            Route::get(':merId/mer_form', [app\controller\api\server\StoreOrder::class,'getFormData']);
            Route::get(':merId/dump_temp', [app\controller\api\server\StoreOrder::class,'getFormData']);
            Route::get(':merId/delivery_config', [app\controller\api\server\StoreOrder::class,'getDeliveryConfig']);
            Route::get(':merId/delivery_options', [app\controller\api\server\StoreOrder::class,'getDeliveryOptions']);
            Route::get(':merId/delivery/options', [app\controller\api\server\StoreOrder::class,'options']);
            Route::post(':merId/offline/:id', [app\controller\api\server\StoreOrder::class,'offline']);
            // 预约
            Route::get(':merId/reservation/staffs', [app\controller\api\server\StoreOrder::class,'staffList']);
            Route::post(':merId/reservation/dispatch/:id', [app\controller\api\server\StoreOrder::class,'reservationDispatch']);
            Route::post(':merId/reservation/updateDispatch/:id', [app\controller\api\server\StoreOrder::class,'reservationUpdateDispatch']);
            Route::post(':merId/reservation/reschedule/:id', [app\controller\api\server\StoreOrder::class,'reservationReschedule']);
            Route::get(':merId/reservation/config', [app\controller\api\server\StoreOrder::class,'reservationConfig']);
            Route::post(':merId/reservation/verify/:id', [app\controller\api\server\StoreOrder::class,'reservationVerify']);
            // 同城配送
            Route::get(':merId/delivery/person', [app\controller\api\server\StoreOrder::class,'deliveryPersonList']);
            Route::post(':merId/delivery/dispatch/:id', [app\controller\api\server\StoreOrder::class,'deliveryDispatch']);
            Route::post(':merId/delivery/updateDispatch/:id', [app\controller\api\server\StoreOrder::class,'deliveryUpdateDispatch']);
            Route::post(':merId/delivery/confirm/:id', [app\controller\api\server\StoreOrder::class,'deliveryConfirm']);
        });
        //管理员订单相关
        Route::group('server', function () {
            //退款单
            Route::get(':merId/refund/check/:id', [app\controller\api\server\StoreRefundOrder::class,'check']);
            Route::post(':merId/refund/create', [app\controller\api\server\StoreRefundOrder::class,'create']);
            Route::post(':merId/refund/compute', [app\controller\api\server\StoreRefundOrder::class,'compute']);
            Route::get(':merId/refund/lst', [app\controller\api\server\StoreRefundOrder::class,'lst']);
            Route::get(':merId/refund/detail/:id', [app\controller\api\server\StoreRefundOrder::class,'detail']);
            Route::get(':merId/refund/get/:id', [app\controller\api\server\StoreRefundOrder::class,'getRefundPrice']);
            Route::post(':merId/refund/confirm/:id', [app\controller\api\server\StoreRefundOrder::class,'refundPrice']);
            Route::get(':merId/refund/express/:id', [app\controller\api\server\StoreRefundOrder::class,'express']);
            Route::post(':merId/refund/status/:id', [app\controller\api\server\StoreRefundOrder::class,'switchStatus']);
            Route::post(':merId/refund/mark/:id', [app\controller\api\server\StoreRefundOrder::class,'mark']);
        });
    })
        ->middleware(
            MerchantServerMiddleware::class,
            ['reqire' => true,'auth' => 0]
        );

    //核销订单
    Route::group('verifier', function () {
        Route::get('/:merId/order/:id', [app\controller\api\store\order\StoreOrderVerify::class,'detail']);
        Route::post('/:merId/:id', [app\controller\api\store\order\StoreOrderVerify::class,'verify']);
        Route::get('card_log/:merId/:id', [app\controller\api\store\order\StoreOrderVerify::class,'verifyCardLog']);
        Route::post('card/:merId/:id', [app\controller\api\store\order\StoreOrderVerify::class,'verifyCard']);
    })
    ->middleware(
        MerchantServerMiddleware::class,
        ['reqire' => false,'auth' => 0],
        ['reqire' => false]
    );


    Route::group('staffs/', function () {
        Route::get('order_lst', [app\controller\api\store\service\Staffs::class,'order_lst']);
        Route::get('order/:id', [app\controller\api\store\service\Staffs::class,'orderDetail']);
        Route::post('order/:id/dispatch', [app\controller\api\store\service\Staffs::class,'reservationDispatch']);
        Route::post('order/:id/verifier', [app\controller\api\store\service\Staffs::class,'verify']);
        Route::post('order/:id/check', [app\controller\api\store\service\Staffs::class,'checkIn']);
        Route::post('order/:id/trace', [app\controller\api\store\service\Staffs::class,'addTrace']);
        Route::post('order/:id/mark', [app\controller\api\store\service\Staffs::class,'mark']);
        Route::get('reservation/config', [app\controller\api\store\service\Staffs::class,'reservationConfig']);
    })->middleware(
        MerchantServerMiddleware::class,
        [],
        ['reqire' => true]
    );

    Route::group('delivery', function () {
        Route::get('order/grab_lst', [app\controller\api\store\service\Delivery::class,'grabList']);
        Route::get('order/my_lst', [app\controller\api\store\service\Delivery::class,'myList']);
        Route::get('order/accept_lst', [app\controller\api\store\service\Delivery::class,'acceptList']);
        Route::get('order/:id', [app\controller\api\store\service\Delivery::class,'orderDetail']);
        Route::post('order/:id/receive', [app\controller\api\store\service\Delivery::class,'receive']);
        Route::post('order/:id/accept', [app\controller\api\store\service\Delivery::class,'accept']);
        Route::post('order/:id/confirm', [app\controller\api\store\service\Delivery::class,'confirm']);
        Route::post('order/:id/mark', [app\controller\api\store\service\Delivery::class,'mark']);
        Route::post('config', [app\controller\api\store\service\Delivery::class,'config']);
        Route::get('service/info', [app\controller\api\store\service\Delivery::class,'info']);
        Route::get('service/bill/day', [app\controller\api\store\service\Delivery::class,'dayBill']);
        Route::get('service/bill/month', [app\controller\api\store\service\Delivery::class,'monthBill']);
        Route::get('service/bill/day/chart', [app\controller\api\store\service\Delivery::class,'dayBillChart']);
        Route::get('service/bill/month/chart', [app\controller\api\store\service\Delivery::class,'monthBillChart']);
        Route::get('service/withdraw/info', [app\controller\api\store\service\Delivery::class,'withdrawInfo']);
        Route::get('service/withdraw/lst', [app\controller\api\store\service\Delivery::class,'withdrawList']);
        Route::get('service/withdraw/detail/:id', [app\controller\api\store\service\Delivery::class,'withdrawDetail']);
        Route::post('service/withdraw/cancel/:id', [app\controller\api\store\service\Delivery::class,'withdrawCancel']);
        Route::post('service/withdraw', [app\controller\api\store\service\Delivery::class,'withdraw']);
        Route::post('service/status', [app\controller\api\store\service\Delivery::class,'status']);
    })->middleware(
        MerchantServerMiddleware::class,
        [],
        [],
        ['reqire' => true]
    );
})
    ->middleware(AllowOriginMiddleware::class)
    ->middleware(UserTokenMiddleware::class,true);
