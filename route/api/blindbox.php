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

Route::group('store/blindbox', function () {
    Route::get('shop_list', '/shopList');
    Route::get('product_list', '/productList');
    Route::get('detail/:id', '/detail');
})->prefix('api.store.BlindBox');

Route::group('store/blindbox', function () {
    Route::get('result/:order_id', '/result');
    Route::get('cabinet', '/cabinet');
    Route::get('cabinet_stats', '/cabinetStats');
    Route::post('recycle', '/recycle');
    Route::get('recycle_records', '/recycleRecords');
})->prefix('api.store.BlindBox')->middleware(\app\common\middleware\UserTokenMiddleware::class);
