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
    //申请分账商户
    Route::group('community',function(){
        Route::get('cate/lst','/cateLst')->name('merchantCommunityCateLst')->option([
            '_alias' => '分类列表',
        ]);
        Route::get('lst','/lst')->name('merchantCommunityLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('create','/create')->name('merchantCommunityCreate')->option([
            '_alias' => '添加',
        ]);
        Route::get('detail/:id','/detail')->name('merchantCommunityDetail')->option([
            '_alias' => '详情',
        ]);
        Route::post('update/:id','/update')->name('merchantCommunityUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::delete('delete/:id','/delete')->name('merchantCommunityDelete')->option([
            '_alias' => '删除',
        ]);
        Route::get('reply/:id','/reply')->name('merchantCommunityReply')->option([
            '_alias' => '评论',
        ]);
    })->prefix('merchant.store.content.Community')->option([
        '_path' => '/community/list',
        '_auth' => true,
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
