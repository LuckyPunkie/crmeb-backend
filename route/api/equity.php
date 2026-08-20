<?php

use think\facade\Route;
use app\common\middleware\UserTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;

Route::group(function () {
    Route::get('equity/shop/:merId', 'api.store.equity.EquityApi/shopModule');
})->middleware(AllowOriginMiddleware::class)
    ->middleware(UserTokenMiddleware::class, false);

Route::group(function () {
    Route::get('user/equity/projects', 'api.store.equity.EquityApi/myProjects');
    Route::get('user/equity/dividends', 'api.store.equity.EquityApi/myDividends');
    Route::get('equity/projects/:id', 'api.store.equity.EquityApi/detail');
    Route::get('equity/projects/:id/transactions', 'api.store.equity.EquityApi/myTransactions');
    Route::post('equity/projects/:id/invest', 'api.store.equity.EquityApi/invest');
    Route::post('equity/projects/:id/invest/refund', 'api.store.equity.EquityApi/investRefund');
    Route::get('equity/projects/:id/dividend-notices', 'api.store.equity.EquityApi/dividendNotices');
    Route::get('equity/projects/:id/financial-report', 'api.store.equity.EquityApi/financialReport');
})->middleware(AllowOriginMiddleware::class)
    ->middleware(UserTokenMiddleware::class, true);
