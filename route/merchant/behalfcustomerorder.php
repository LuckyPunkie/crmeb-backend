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
    // 代客下单
    Route::group('store/behalf', function () {
        Route::get('productCategory', 'Product/category')->name('behalfProductCategory')->option([
            '_alias' => '商品分类',
        ]);
        Route::get('productList', 'Product/list')->name('behalfProductList')->option([
            '_alias' => '商品列表',
        ]);
        Route::get('productDetail/:id', 'Product/detail')->name('behalfProductDetail')->option([
            '_alias' => '商品规格详情',
        ]);
        Route::get('userQuery', 'User/query')->name('behalfUserQuery')->option([
            '_alias' => '会员查询',
        ]);
        Route::get('userInfo', 'User/info')->name('behalfUserInfo')->option([
            '_alias' => '会员详情',
        ]);
        Route::post('userCreate', 'User/create')->name('behalfUserCreate')->option([
            '_alias' => '会员添加',
        ]);
        Route::get('userAddressList', 'User/addressList')->name('behalfUserAddressList')->option([
            '_alias' => '地址列表',
        ]);
        Route::post('userAddressCreate', 'User/addressCreate')->name('behalfUserAddressCreate')->option([
            '_alias' => '地址添加',
        ]);
        Route::get('cartList', 'Cart/list')->name('behalfCartList')->option([
            '_alias' => '购物车列表',
        ]);
        Route::post('cartCreate', 'Cart/create')->name('behalfCartCreate')->option([
            '_alias' => '添加购物车',
        ]);
        Route::post('cartChange/:id', 'Cart/change')->name('behalfCartChange')->option([
            '_alias' => '修改购物车数据',
        ]);
        Route::delete('cartDelete/:id', 'Cart/delete')->name('behalfCartDelete')->option([
            '_alias' => '删除购物数据',
        ]);
        Route::post('cartClear', 'Cart/clear')->name('behalfCartClear')->option([
            '_alias' => '清空购物车',
        ]);
        Route::get('cartCount', 'Cart/count')->name('behalfCartCount')->option([
            '_alias' => '购物车总数量',
        ]);
        Route::post('cartUpdatePrice/:id', 'Cart/updatePrice')->name('behalfCartUpdatePrice')->option([
            '_alias' => '修改价格',
        ]);
        Route::post('cartBatchUpdatePrice', 'Cart/batchUpdatePrice')->name('behalfCartBatchUpdatePrice')->option([
            '_alias' => '批量修改价格',
        ]);
        Route::post('orderCheck', 'Order/check')->name('behalfCheck')->option([
            '_alias' => '校验订单',
        ]);
        Route::post('payConfig', 'Order/payConfig')->name('behalfPayConfig')->option([
            '_alias' => '支付配置',
        ]);
        Route::post('orderCreate', 'Order/create')->name('behalfCreate')->option([
            '_alias' => '创建订单',
        ]);
        Route::post('orderPay/:id', 'Order/pay')->name('behalfPay')->option([
            '_alias' => '支付',
        ]);
        Route::post('payStatus/:id', 'Order/payStatus')->name('behalfPayStatus')->option([
            '_alias' => '获取结果',
        ]);
        Route::post('orderVerify', 'Order/verify')->name('behalfVerify')->option([
            '_alias' => '余额支付获取验证码',
        ]);
    })->prefix('merchant.store.behalfcustomerorder.')->option([
        '_path'=>'/order/customer',
        '_auth' => true
    ]);

})->middleware(AllowOriginMiddleware::class)
->middleware(MerchantTokenMiddleware::class, true)
->middleware(MerchantAuthMiddleware::class)
->middleware(MerchantCheckBaseInfoMiddleware::class)
->middleware(LogMiddleware::class);
