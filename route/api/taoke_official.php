<?php

use think\facade\Route;
use app\common\middleware\UserTokenMiddleware;

// 平台官方直连（与 /api/taoke/goods 订单侠链路分离）
Route::group('taoke/official', function () {

    Route::get('ping', function () {
        return json(['msg' => 'taoke official routes loaded', 'channel' => 'official', 'time' => time()]);
    });

    Route::group('goods', function () {
        Route::post('taobao_goods', 'api.taoke.OfficialGoods/taobaoGoods');
        Route::post('taobao_goods_detail', 'api.taoke.OfficialGoods/taobaoGoodsDetail');
        Route::post('create_taobao_link', 'api.taoke.OfficialGoods/createTaobaoLink');

        Route::post('jd_goods', 'api.taoke.OfficialGoods/jdGoods');
        Route::post('jd_goods_detail', 'api.taoke.OfficialGoods/jdGoodsDetail');
        Route::post('create_jd_link', 'api.taoke.OfficialGoods/createJdLink');

        Route::post('pdd_goods', 'api.taoke.OfficialGoods/pddGoods');
        Route::post('pdd_goods_detail', 'api.taoke.OfficialGoods/pddGoodsDetail');
        Route::post('create_pdd_link', 'api.taoke.OfficialGoods/createPddLink');
        Route::post('create_pdd_pid', 'api.taoke.OfficialGoods/createPddPid');
        Route::post('create_pdd_url', 'api.taoke.OfficialGoods/createPddUrl');
        Route::post('pdd_authority_status', 'api.taoke.OfficialGoods/pddAuthorityStatus');
        Route::post('create_pdd_authority_url', 'api.taoke.OfficialGoods/createPddAuthorityUrl');

        Route::post('kuaishou_goods', 'api.taoke.OfficialGoods/kuaishouGoods');
        Route::post('kuaishou_goods_detail', 'api.taoke.OfficialGoods/kuaishouGoodsDetail');
        Route::post('create_kuaishou_link', 'api.taoke.OfficialGoods/createKuaishouLink');

        Route::post('category', 'api.taoke.OfficialGoods/category');
        Route::get('service_tabs', 'api.taoke.OfficialGoods/serviceTabs');
    })->middleware(UserTokenMiddleware::class, false);
});
