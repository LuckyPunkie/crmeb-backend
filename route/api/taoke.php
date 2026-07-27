<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2024 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

use think\facade\Route;
use app\common\middleware\UserTokenMiddleware;

// ==================== 淘宝客相关接口 ====================
Route::group('taoke', function () {

    // 诊断路由：验证路由文件是否被加载
    Route::get('ping', function () {
        return json(['msg' => 'taoke routes loaded', 'time' => time()]);
    });

    // 商品相关接口（无需强制登录）
    Route::group('goods', function () {
        Route::get('taobao', 'api.taoke.Goods/taobao');
        Route::get('taobao_search', 'api.taoke.Goods/taobaoSearch');
        Route::get('taobao_detail', 'api.taoke.Goods/taobaoDetail');
        Route::post('taobao_orders', 'api.taoke.Goods/taobaoOrders');
        
        Route::post('jutuike_order', 'api.taoke.Goods/jutuikeOrder');
        
        Route::get('activity_list', 'api.taoke.Goods/activityList');
        Route::post('activity_union', 'api.taoke.Goods/activityUnion');
        
        Route::post('taobao_goods', 'api.taoke.Goods/taobaoGoods');
        Route::post('create_taobao_link', 'api.taoke.Goods/createTaobaoLink');
        
        Route::post('pdd_goods', 'api.taoke.Goods/pddGoods');
        Route::post('pdd_goods_detail', 'api.taoke.Goods/pddGoodsDetail');
        Route::post('create_pdd_pid', 'api.taoke.Goods/createPddPid');
        Route::post('create_pdd_link', 'api.taoke.Goods/createPddLink');
        Route::post('create_pdd_url', 'api.taoke.Goods/createPddUrl');
        
        Route::post('jd_goods', 'api.taoke.Goods/jdGoods');
        Route::post('jd_goods_detail', 'api.taoke.Goods/jdGoodsDetail');
        Route::post('create_jd_link', 'api.taoke.Goods/createJdLink');
        
        Route::post('wph_goods', 'api.taoke.Goods/wphGoods');
        Route::post('vip_goods_detail', 'api.taoke.Goods/vipGoodsDetail');
        Route::post('create_vip_link', 'api.taoke.Goods/createVipLink');

        Route::post('douyin_goods', 'api.taoke.Goods/douyinGoods');
        Route::get('service_tabs', 'api.taoke.Goods/serviceTabs');
        Route::post('aggregate_recommend', 'api.taoke.Goods/aggregateRecommend');
        Route::post('brand_goods', 'api.taoke.Goods/brandGoods');
        
        Route::post('category', 'api.taoke.Goods/category');
    })->middleware(UserTokenMiddleware::class, false);

    // 订单相关接口（需要登录）
    Route::group('order', function () {
        Route::post('jutuike_order', 'api.taoke.Order/jutuikeOrder');
        Route::post('taobao_order', 'api.taoke.Order/taobaoOrder');
        
        Route::post('pdd_order', 'api.taoke.Order/pddOrder');
        Route::post('pdd_order_detail', 'api.taoke.Order/pddOrderDetail');
         
        Route::post('jd_order', 'api.taoke.Order/jdOrder');
        
        Route::post('vip_order', 'api.taoke.Order/vipOrder');
        Route::post('vip_order_detail', 'api.taoke.Order/vipOrderDetail');
    })->middleware(UserTokenMiddleware::class, true);

    // 佣金相关接口（需要登录）
    Route::group('commission', function () {
        Route::get('list', 'api.taoke.Commission/list');
        Route::get('stats', 'api.taoke.Commission/stats');
        Route::get('balance', 'api.taoke.Commission/balance');
        Route::post('withdraw', 'api.taoke.Commission/withdraw');
        Route::get('withdraw_list', 'api.taoke.Commission/withdrawList');
    })->middleware(UserTokenMiddleware::class, true);

    // 美团相关接口（无需强制登录）
    Route::group('meituan', function () {
        Route::get('waimai_coupon', 'api.taoke.Meituan/waimaiCoupon');
        Route::get('hotel_coupon', 'api.taoke.Meituan/hotelCoupon');
        Route::get('daodian', 'api.taoke.Meituan/daodian');
    })->middleware(UserTokenMiddleware::class, false);

    // 饿了么相关接口（无需强制登录）
    Route::group('eleme', function () {
        Route::get('coupon', 'api.taoke.Eleme/coupon');
        Route::get('cash', 'api.taoke.Eleme/cash');
        Route::get('consume_day', 'api.taoke.Eleme/consumeDay');
    })->middleware(UserTokenMiddleware::class, false);

});
