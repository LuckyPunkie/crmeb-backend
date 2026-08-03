<?php
// +----------------------------------------------------------------------
// | AI 点餐 商户后台 API
// +----------------------------------------------------------------------

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

Route::group('ai_order', function () {
    Route::get('config', 'merchant.store.aiOrder.AiOrderConfig/get')
        ->name('merchantAiOrderConfig')
        ->option(['_alias' => 'AI点餐配置']);
    Route::post('config', 'merchant.store.aiOrder.AiOrderConfig/save')
        ->name('merchantAiOrderConfigSave')
        ->option(['_alias' => '保存AI点餐配置']);
    Route::get('balance', 'merchant.store.aiOrder.AiOrderConfig/balance')
        ->name('merchantAiOrderBalance')
        ->option(['_alias' => 'AI余额']);
    Route::get('logs', 'merchant.store.aiOrder.AiOrderConfig/logs')
        ->name('merchantAiOrderLogs')
        ->option(['_alias' => 'AI余额流水']);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class)
    ->option(['_path' => '/ai_order', '_auth' => true]);
