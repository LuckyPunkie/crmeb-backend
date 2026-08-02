<?php
// +----------------------------------------------------------------------
// | 扫码下单 用户端 API
// +----------------------------------------------------------------------

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\UserTokenMiddleware;

// 中转页（无需登录）
Route::group(function () {
    Route::get('scan_order/jump/:mer_id/:table_id', 'api.store.scanOrder.ScanOrderJump/jump')
        ->pattern(['mer_id' => '\d+', 'table_id' => '\d+']);
})->middleware(AllowOriginMiddleware::class);

// 浏览：台号列表 / 台号上下文 / 商品列表（可未登录）
Route::group(function () {
    Route::get('scan_order/tables', 'api.store.scanOrder.ScanOrder/tables');
    Route::get('scan_order/context', 'api.store.scanOrder.ScanOrder/context');
    Route::get('scan_order/categories', 'api.store.scanOrder.ScanOrder/categories');
    Route::get('scan_order/goods', 'api.store.scanOrder.ScanOrder/goods');
})->middleware(AllowOriginMiddleware::class)
    ->middleware(UserTokenMiddleware::class, false);

// 本店购物车：可游客（tourist_unique_key）
Route::group(function () {
    Route::get('scan_order/cart/lst', 'api.store.scanOrder.ScanOrderCart/lst');
    Route::get('scan_order/cart/count', 'api.store.scanOrder.ScanOrderCart/count');
    Route::post('scan_order/cart/create', 'api.store.scanOrder.ScanOrderCart/create');
    Route::post('scan_order/cart/change/:id', 'api.store.scanOrder.ScanOrderCart/change');
    Route::post('scan_order/cart/delete', 'api.store.scanOrder.ScanOrderCart/delete');
})->middleware(AllowOriginMiddleware::class)
    ->middleware(UserTokenMiddleware::class, false);

// 合并游客车 / 提交订单（需登录）
Route::group(function () {
    Route::post('scan_order/cart/merge', 'api.store.scanOrder.ScanOrderCart/merge');
    Route::post('scan_order/order/submit', 'api.store.scanOrder.ScanOrderOrder/submit');
})->middleware(AllowOriginMiddleware::class)
    ->middleware(UserTokenMiddleware::class, true);
