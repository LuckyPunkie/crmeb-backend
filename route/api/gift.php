<?php

use think\facade\Route;
use app\common\middleware\UserTokenMiddleware;

Route::group(function () {
    Route::get('gift/list', 'api.gift.Gift/list');
    Route::post('gift/order/create', 'api.gift.Gift/createOrder');
    Route::post('gift/order/pay', 'api.gift.Gift/pay');
    Route::get('gift/order/status', 'api.gift.Gift/orderStatus');
    Route::get('gift/received/lst', 'api.gift.Gift/receivedList');
    Route::get('gift/sent/lst', 'api.gift.Gift/sentList');
})->middleware(UserTokenMiddleware::class, true);
