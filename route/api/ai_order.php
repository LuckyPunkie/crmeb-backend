<?php
// +----------------------------------------------------------------------
// | AI 点餐 用户端 API
// +----------------------------------------------------------------------

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\UserTokenMiddleware;

// 是否开通（可未登录，用于展示头像入口）
Route::group(function () {
    Route::get('ai_order/status', 'api.store.aiOrder.AiOrderCall/status');
})->middleware(AllowOriginMiddleware::class)
    ->middleware(UserTokenMiddleware::class, false);

// 发起 / 结束通话（需登录）
Route::group(function () {
    Route::post('ai_order/session/start', 'api.store.aiOrder.AiOrderCall/start');
    Route::post('ai_order/dialog/hello', 'api.store.aiOrder.AiOrderCall/hello');
    Route::post('ai_order/dialog/speak', 'api.store.aiOrder.AiOrderCall/speak');
    Route::post('ai_order/dialog/speak_text', 'api.store.aiOrder.AiOrderCall/speakText');
    Route::post('ai_order/session/end', 'api.store.aiOrder.AiOrderCall/end');
    Route::post('ai_order/session/add_cart', 'api.store.aiOrder.AiOrderCall/addCart');
})->middleware(AllowOriginMiddleware::class)
    ->middleware(UserTokenMiddleware::class, true);
