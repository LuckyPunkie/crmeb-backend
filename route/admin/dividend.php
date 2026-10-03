<?php

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {
    Route::group('dividend', function () {
        Route::post('preview', '/preview')->name('adminDividendPreview')->option(['_alias' => '商家分红预览']);
        Route::post('issue', '/issue')->name('adminDividendIssue')->option(['_alias' => '商家分红确认发放']);
        Route::get('lst', '/lst')->name('adminDividendLst')->option(['_alias' => '商家分红批次列表']);
        Route::get(':id', '/detail')->name('adminDividendDetail')->option(['_alias' => '商家分红批次详情']);
    })->prefix('admin.store.dividend.DividendAdmin')->option([
        '_path' => '/dividend',
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
