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

use app\common\middleware\UserTokenMiddleware;
use think\facade\Route;

// 公开接口：可选登录（挂上 isLogin 宏）
Route::group('store/blindbox', function () {
    Route::get('shop_list', '/shopList');
    Route::get('product_list', '/productList');
    Route::get('detail/:id', '/detail');
    // 普通店铺主页盲盒入口
    Route::get('entry', '/entry');
})->prefix('api.store.BlindBox')->middleware(UserTokenMiddleware::class, false);

// 需登录
Route::group('store/blindbox', function () {
    Route::post('bind_share', '/bindShare');
    Route::get('result/:order_id', '/result');
    Route::get('cabinet', '/cabinet');
    Route::get('cabinet_stats', '/cabinetStats');
    Route::post('recycle', '/recycle');
    Route::get('recycle_records', '/recycleRecords');
    Route::post('apply_ship', '/applyShip');
})->prefix('api.store.BlindBox')->middleware(UserTokenMiddleware::class, true);
