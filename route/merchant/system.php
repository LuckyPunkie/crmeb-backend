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
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;
use app\common\middleware\MerchantAuthMiddleware;
use app\common\middleware\MerchantTokenMiddleware;
use think\facade\Route;
use app\common\middleware\MerchantCheckBaseInfoMiddleware;

Route::group(function () {

    Route::get('excel/download/:type', 'merchant.store.Excel/downloadExpress');
    //打印机
    Route::group('store/printer', function () {
        //lst
        Route::get('lst','/lst')->name('merchantStorePrinterLst')->option([
                '_alias' => '列表',
            ]);
        //添加
        Route::get('create/form','/createForm')
            ->name('merchantStorePrinterCreateForm')->option([
                '_alias' => '添加表单',
                '_auth' => false,
                '_form' => 'merchantStorePrinterCreate',
            ]);
        Route::post('create','/create')
            ->name('merchantStorePrinterCreate')->option([
                '_alias' => '添加',
            ]);
        //编辑
        Route::get('update/:id/form','/updateForm')
            ->name('merchantStorePrinterCreate')->option([
                '_alias' => '编辑表单',
                '_auth' => false,
                '_form' => 'merchantStorePrinterUpdate',
            ]);
        Route::post('update/:id','/update')
            ->name('merchantStorePrinterUpdate')->option([
                '_alias' => '编辑',
            ]);

        //取消
        Route::post('status/:id','/switchWithStatus')
            ->name('merchantStorePrinterStatus')->option([
                '_alias' => '取消',
            ]);

        Route::delete('delete/:id','/delete')
            ->name('merchantStorePrinterDelete')->option([
                '_alias' => '删除',
            ]);

        Route::get('get_content/:id','/getContent')
            ->name('merchantStorePrinterGetContent')->option([
                '_alias' => '获取配置内容',
            ]);
        Route::post('set_content/:id','/setContent')
            ->name('merchantStorePrinterSetContent')->option([
                '_alias' => '保存打印机内容',
            ]);

    })->prefix('merchant.store.StorePrinter')->option([
        '_path' => '/setting/printer/list',
        '_auth'  => true,
    ]);

    Route::group('store/printer', function () {
        Route::get('get_content/:id','/getContent')
            ->name('merchantStorePrinterGetContent')->option([
                '_alias' => '获取配置内容',
            ]);
        Route::post('set_content/:id','/setContent')
            ->name('merchantStorePrinterSetContent')->option([
                '_alias' => '保存打印机内容',
            ]);
    })->prefix('merchant.store.StorePrinter')->option([
        '_path' => '/setting/printer/content',
        '_auth'  => true,
    ]);

    Route::group('statistics', function () {
        Route::get('main', '/main')->name('merchantStatisticsMain')->option([
            '_alias' => '所有数据',
        ]);
        Route::get('order', '/order')->name('merchantStatisticsOrder')->option([
            '_alias' => '支付订单',
        ]);
        Route::get('user', '/user')->name('merchantStatisticsUser')->option([
            '_alias' => '成交客户',
        ]);
        Route::get('user_rate', '/userRate')->name('merchantStatisticsUserRate')->option([
            '_alias' => '成交客户比',
        ]);
        Route::get('product', '/product')->name('merchantStatisticsProduct')->option([
            '_alias' => '商品支付排行',
        ]);
        Route::get('product_visit', '/productVisit')->name('merchantStatisticsProductVisit')->option([
            '_alias' => '商品访问排行',
        ]);
        Route::get('product_cart', '/productCart')->name('merchantStatisticsProductCart')->option([
            '_alias' => '商品加购排行',
        ]);
        Route::get('get_merchant_count', '/getMerchantCount')->name('merchantStatisticsMerchantCount')->option([
            '_alias' => '首页未处理业务统计',
        ]);
        Route::get('get_merchant_todo', '/getMerchantTodo')->name('merchantStatisticsMerchantTodo')->option([
            '_alias' => '待办事项',
        ]);
        Route::get('get_product_sales_price_top', '/getProductSalesPriceTop')->name('merchantStatisticsProductSalesPriceTop')->option([
            '_alias' => '获取商户代办统计',
        ]);
    })->prefix('merchant.Common')->option([
        '_path' => '/dashboard',
        '_auth'  => true,
    ]);

    //系统公告
    Route::group('notice', function () {
        Route::get('lst', '/lst')->name('systemNoticeLogList')->option([
            '_alias' => '列表',
        ]);
        Route::post('read/:id', '/read')->name('systemNoticeLogRead')->option([
            '_alias' => '已读',
        ]);
        Route::delete('del/:id', '/del')->name('systemNoticeLogDel')->option([
            '_alias' => '删除',
        ]);
        Route::get('detail/:id', '/detail')->name('systemNoticeLogDetail')->option([
            '_alias' => '详情',
        ]);
        Route::get('unread_count', '/unreadCount')->name('systemNoticeLogUnreadCount')->option([
            '_alias' => '未读统计',
        ]);
    })->prefix('merchant.system.notice.SystemNoticeLog')->option([
        '_path' => '/station/notice',
        '_auth'  => true,
    ]);

    //配置
    Route::group( function () {
        Route::get('config', 'merchant.Common/config');
        Route::post('config/:type', 'merchant.Common/saveConfig');
        Route::get('menus', 'admin.system.auth.Menu/merchantMenus')->append(['merchant' => 1]);
        Route::post('getMerchantMenusList', 'admin.system.auth.Menu/getMenusList')->append(['is_mer' => 1]);;
        Route::post('menus', 'admin.system.auth.Menu/merchantMenus')->append(['merchant' => 1]);
        Route::get('logout', 'merchant.system.admin.Login/logout');
        //获取版本号
        Route::get('version', 'admin.Common/version');
        Route::get('info', 'merchant.system.Merchant/info');
        Route::get('margin/code', 'merchant.system.Merchant/getMarginQrCode')->append(['type' => 10]);
        Route::get('margin/make_code', 'merchant.system.Merchant/getMarginQrCode')->append(['type' => 11]);
        Route::get('margin/lst', 'merchant.system.Merchant/getMarginLst');
        Route::post('upload/certificate', 'merchant.Common/uploadCertificate');

    })->option([
        '_path' => '',
        '_auth'  => false,
    ]);

    Route::group( function () {
        Route::get('update/form', 'merchant.system.Merchant/updateForm')->name('merchantUpdateForm')->option([
            '_alias' => '编辑',
            '_auth'  => false,
        ]);

        Route::post('info/update', 'merchant.system.Merchant/update')->name('merchantUpdate')->option([
            '_alias' => '资料更新',
        ]);
    })->option([
        '_path' => '/systemForm/Basics/mer_base',
        '_auth'  => true,
    ]);

    // 商家标签
    Route::group('merchant_label', function () {
        Route::get('lst', 'merchant.system.MerchantLabel/labels');
        Route::post('join/:id', 'merchant.system.MerchantLabel/join');
        Route::get('margin_code/:id', 'merchant.system.MerchantLabel/marginCode');
    })->option([
        '_path' => '/merchant_label/lst',
        '_auth' => false,
    ]);

    Route::group( function () {
        Route::get('take/info', 'merchant.system.Merchant/takeInfo')->name('merchantTakeInfo')->option([
            '_alias' => '到店自提信息',
            '_auth'  => false,
        ]);
        Route::post('take/update', 'merchant.system.Merchant/take')->name('merchantTakeUpdate')->option([
            '_alias' => '保存到店自提信息',
        ]);
    })->option([
        '_path' => '/systemForm/modifyStoreInfo',
        '_auth'  => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
