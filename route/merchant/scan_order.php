<?php
// +----------------------------------------------------------------------
// | 扫码下单 商户后台 API
// +----------------------------------------------------------------------

use think\facade\Route;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

Route::group('scan_order', function () {
    // 下单设置
    Route::get('config', 'merchant.store.scanOrder.ScanOrderConfig/get')
        ->name('merchantScanOrderConfig')
        ->option(['_alias' => '扫码下单设置']);
    Route::post('config', 'merchant.store.scanOrder.ScanOrderConfig/save')
        ->name('merchantScanOrderConfigSave')
        ->option(['_alias' => '保存扫码下单设置']);
    Route::get('printer_status', 'merchant.store.scanOrder.ScanOrderConfig/printerStatus')
        ->name('merchantScanOrderPrinterStatus')
        ->option(['_alias' => '打印机绑定状态', '_auth' => false]);

    // 台号管理
    Route::group('table', function () {
        Route::get('lst', 'merchant.store.scanOrder.ScanOrderTable/lst')
            ->name('merchantScanOrderTableLst')
            ->option(['_alias' => '台号列表']);
        Route::post('create', 'merchant.store.scanOrder.ScanOrderTable/create')
            ->name('merchantScanOrderTableCreate')
            ->option(['_alias' => '创建台号']);
        Route::delete(':id', 'merchant.store.scanOrder.ScanOrderTable/delete')
            ->name('merchantScanOrderTableDelete')
            ->option(['_alias' => '删除台号']);
        Route::get('qrcode/:id', 'merchant.store.scanOrder.ScanOrderTable/qrcode')
            ->name('merchantScanOrderTableQrcode')
            ->option(['_alias' => '台号二维码', '_auth' => false]);
    });
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class)
    ->option(['_path' => '/scan_order', '_auth' => true]);
