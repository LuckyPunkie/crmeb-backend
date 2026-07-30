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
// | 附近好店 C端API路由
// +----------------------------------------------------------------------

use think\facade\Route;
use app\common\middleware\UserTokenMiddleware;

// 统一收款码落地页（无需登录）
Route::get('scan_pay/:mer_id', 'api.store.nearby.ScanPay/jump');

// 非强制登录（浏览类接口）
Route::group(function () {
    // 商家列表
    Route::get('nearby/shop/lst', 'api.store.nearby.NearbyShop/lst');
    // 商家详情
    Route::get('nearby/shop/detail/:mer_id', 'api.store.nearby.NearbyShop/detail');
    // 分类树
    Route::get('nearby/category/tree', 'api.store.nearby.NearbyCategory/tree');
    // 推荐菜列表
    Route::get('nearby/recommend/lst/:mer_id', 'api.store.nearby.NearbyRecommend/lst');
    // 套餐列表
    Route::get('nearby/package/lst/:mer_id', 'api.store.nearby.NearbyPackage/lst');
    // 套餐详情
    Route::get('nearby/package/detail/:id', 'api.store.nearby.NearbyPackage/detail');
    // 热门搜索
    Route::get('nearby/search/hot', 'api.store.nearby.NearbySearch/hot');
    // 搜索商家
    Route::get('nearby/search/lst', 'api.store.nearby.NearbySearch/lst');
})->middleware(UserTokenMiddleware::class, false);

// 扫码买单：允许游客（uid=0）；余额支付在控制器内校验登录
Route::group(function () {
    Route::post('nearby/bill/create', 'api.store.nearby.NearbyBill/create');
    Route::post('nearby/bill/pay/:order_sn', 'api.store.nearby.NearbyBill/pay');
})->middleware(UserTokenMiddleware::class, false);
