<?php
// +----------------------------------------------------------------------
// | AI 点餐 平台后台 API
// +----------------------------------------------------------------------

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group('ai_order', function () {
    Route::get('overview', 'admin.system.aiOrder.AiOrderAdmin/overview')
        ->name('systemAiOrderOverview')
        ->option(['_alias' => 'AI点餐概览']);
    Route::post('adjust', 'admin.system.aiOrder.AiOrderAdmin/adjust')
        ->name('systemAiOrderAdjust')
        ->option(['_alias' => 'AI余额调账']);
    Route::get('logs', 'admin.system.aiOrder.AiOrderAdmin/logs')
        ->name('systemAiOrderLogs')
        ->option(['_alias' => 'AI余额流水']);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class)
    ->option(['_path' => '/ai_order', '_auth' => true]);
