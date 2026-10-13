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

use think\facade\Route;
use app\common\middleware\AdminAuthMiddleware;
use app\common\middleware\AdminTokenMiddleware;
use app\common\middleware\AllowOriginMiddleware;
use app\common\middleware\LogMiddleware;

Route::group(function () {

    //积分
    Route::group('user/integral', function () {
        Route::get('config', '.UserIntegral/getConfig')->name('systemUserIntegralConfig')->option([
            '_alias' => '积分配置获取',
            '_auth' => false,
            '_form' => 'systemUserIntegralConfigSave',
        ]);
        Route::post('config', '.UserIntegral/saveConfig')->name('systemUserIntegralConfigSave')->option([
            '_alias' => '积分配置保存',
        ]);
    })->prefix('admin.user')->option([
        '_path' => '/marketing/integral/config',
        '_auth' => true,
    ]);

    Route::group('user/integral', function () {
        Route::get('title', '.UserIntegral/getTitle')->name('systemUserIntegralTitle')->option([
            '_alias' => '积分统计',
        ]);
        Route::get('lst', '.UserIntegral/getList')->name('systemUserIntegralLst')->option([
            '_alias' => '积分日志',
        ]);
        Route::get('excel', '.UserIntegral/excel')->name('systemUserIntegralExcel')->option([
            '_alias' => '积分导出',
        ]);
    })->prefix('admin.user')->option([
        '_path' => '/marketing/integral/log',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'systemStoreExcelLst',
                '_path'  =>'/marketing/integral/log',
                '_alias' => '导出列表',
                '_auth'  => true,
            ],
            [
                '_name'  =>'systemStoreExcelDownload',
                '_path'  =>'/marketing/integral/log',
                '_alias' => '导出下载',
                '_auth'  => true,
            ],
        ]
    ]);

    //预售商品
    Route::group('store/product/presell', function () {
        Route::get('lst', 'StoreProductPresell/lst')->name('systemStoreProductPresellLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('is_show/:id', 'StoreProductPresell/switchStatus')->name('systemStoreProductPresellShow')->option([
            '_alias' => '显示/隐藏',
        ]);
        Route::get('detail/:id', 'StoreProductPresell/detail')->name('systemStoreProductPresellDetail')->option([
            '_alias' => '详情',
        ]);
        Route::get('get/:id', 'StoreProductPresell/get')->name('systemStoreProductPresellGet')->option([
            '_alias' => '编辑数据',
        ]);
        Route::post('update/:id', 'StoreProductPresell/update')->name('systemStoreProductPresellUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::post('status', 'StoreProductPresell/switchAudit')->name('systemStoreProductPresellSwitchStatus')->option([
            '_alias' => '审核',
        ]);
        Route::post('labels/:id', 'StoreProductPresell/setLabels')->name('systemStoreProductPresellLabels')->option([
            '_alias' => '设置标签',
        ]);
    })->prefix('admin.store.')->option([
        '_path' => '/marketing/presell/list',
        '_auth' => true,
    ]);


    //助力商品
    Route::group('store/product/assist', function () {
        Route::get('lst', 'StoreProductAssist/lst')->name('systemStoreProductAssistLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('is_show/:id', 'StoreProductAssist/switchStatus')->name('systemStoreProductAssistShow')->option([
            '_alias' => '显示/隐藏',
        ]);
        Route::get('detail/:id', 'StoreProductAssist/detail')->name('systemStoreProductAssistDetail')->option([
            '_alias' => '详情',
        ]);
        Route::post('update/:id', 'StoreProductAssist/update')->name('systemStoreProductAssistProductUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::post('status', 'StoreProductAssist/switchAudit')->name('systemStoreProductAssistStatus')->option([
            '_alias' => '审核',
        ]);
        Route::get('get/:id', 'StoreProductAssist/get')->name('systemStoreProductAssistGet')->option([
            '_alias' => '编辑数据',
        ]);
        Route::post('labels/:id', 'StoreProductAssist/setLabels')->name('systemStoreProductAssistLabels')->option([
            '_alias' => '设置标签',
        ]);
    })->prefix('admin.store.')->option([
        '_path' => '/marketing/assist/goods_list',
        '_auth' => true,
    ]);

    //助力活动
    Route::group('store/product/assist', function () {

        Route::get('set/lst', 'StoreProductAssistSet/lst')->name('systemStoreProductAssistSetLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('set/detail/:id', 'StoreProductAssistSet/detail')->name('systemStoreProductAssistSetDetail')->option([
            '_alias' => '详情',
        ]);
    })->prefix('admin.store.')->option([
        '_path' => '/marketing/assist/list',
        '_auth' => true,
    ]);


    //拼团商品
    Route::group('store/product/group', function () {
        Route::get('lst', 'StoreProductGroup/lst')->name('systemStoreProductGroupLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('is_show/:id', 'StoreProductGroup/switchStatus')->name('systemStoreProductGroupShow')->option([
            '_alias' => '显示/隐藏',
        ]);
        Route::get('detail/:id', 'StoreProductGroup/detail')->name('systemStoreProductGroupDetail')->option([
            '_alias' => '详情',
        ]);
        Route::post('update/:id', 'StoreProductGroup/update')->name('systemStoreProductGroupProductUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::post('status', 'StoreProductGroup/switchAudit')->name('systemStoreProductGroupStatus')->option([
            '_alias' => '审核',
        ]);
        Route::get('get/:id', 'StoreProductGroup/get')->name('systemStoreProductGroupGet')->option([
            '_alias' => '编辑数据',
        ]);
        Route::post('sort/:id', 'StoreProductGroup/updateSort')->name('systemStoreProductGroupSort')->option([
            '_alias' => '排序',
        ]);
        Route::post('labels/:id', 'StoreProductGroup/setLabels')->name('systemStoreProductGroupLabels')->option([
            '_alias' => '设置标签',
        ]);
    })->prefix('admin.store.')->option([
        '_path' => '/marketing/combination/combination_goods',
        '_auth' => true,
    ]);
    //拼团活动
    Route::group('store/product/group', function () {
        Route::get('buying/lst', '/lst')->name('systemStoreProductGroupBuyingLst')->option([
            '_alias' => '列表',
            ]);
        Route::get('buying/detail/:id', '/detail')->name('systemStoreProductGroupBuyingDetail')->option([
            '_alias' => '详情',
            ]);
    })->prefix('admin.store.StoreProductGroupBuying')->option([
        '_path' => '/marketing/combination/combination_list',
        '_auth' => true,
    ]);

    Route::group('config/others', function () {
        Route::get('group_buying', '/getGroupBuying')->name('configOthersGroupBuyingDetail')->option([
            '_alias' => '配置信息',
            '_auth' => false,
            '_form' => 'configOthersGroupBuyingUpdate',
        ]);
        Route::post('group_buying', '/setGroupBuying')->name('configOthersGroupBuyingUpdate')->option([
            '_alias' => '配置保存',
        ]);
    })->prefix('admin.system.config.ConfigOthers')->option([
        '_path' => '/marketing/combination/combination_set',
        '_auth' => true,
    ]);

    //直播间
    Route::group('broadcast/room', function () {
        Route::get('lst', '/lst')->name('systemBroadcastRoomLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('detail/:id', '/detail')->name('systemBroadcastRoomDetail')->option([
            '_alias' => '详情',
        ]);
        Route::get('apply/form/:id', '/applyForm')->name('systemBroadcastRoomApplyForm')->option([
            '_alias' => '申请审核表单',
            '_auth' => false,
            '_form' => 'systemBroadcastRoomApply',
        ]);
        Route::post('apply/:id', '/apply')->name('systemBroadcastRoomApply')->option([
            '_alias' => '申请',
            ]);
        Route::post('status/:id', '/changeStatus')->name('systemBroadcastRoomChangeStatus')->option([
            '_alias' => '修改状态',
            ]);
        Route::post('sort/:id', '/sort')->name('systemBroadcastRoomSort')->option([
            '_alias' => '排序',
            ]);
        Route::post('live_status/:id', '/changeLiveStatus')->name('systemBroadcastRoomChangeLiveStatus')->option([
            '_alias' => '修改状态',
            ]);
        Route::delete('delete/:id', '/delete')->name('systemBroadcastRoomDelete')->option([
            '_alias' => '删除',
            ]);
        Route::get('goods/:id', '/goodsList')->name('systemBroadcastRoomGoods')->option([
            '_alias' => '商品列表',
            ]);
        Route::post('closeKf/:id', '/closeKf')->name('systemBroadcastRoomCloseKf')->option([
            '_alias' => '客服开关',
            ]);
        Route::post('comment/:id', '/banComment')->name('systemBroadcastRoomCloseComment')->option([
            '_alias' => '禁言开关',
            ]);
        Route::post('feedsPublic/:id', '/isFeedsPublic')->name('systemBroadcastRoomClosesFeeds')->option([
            '_alias' => '收录开关',
            ]);
    })->prefix('admin.store.BroadcastRoom')->option([
        '_path' => '/marketing/studio/list',
        '_auth' => true,
    ]);

    //直播间商品
    Route::group('broadcast/goods', function () {
        Route::get('lst', '/lst')->name('systemBroadcastGoodsLst')->option([
            '_alias' => '列表',
            ]);
        Route::get('detail/:id', '/detail')->name('systemBroadcastGoodsDetail')->option([
            '_alias' => '详情',
            ]);
        Route::get('apply/form/:id', '/applyForm')->name('systemBroadcastGoodsApplyForm')->option([
            '_alias' => '审核表单',
            '_auth' => false,
            '_form' => 'systemBroadcastRoomApply',
        ]);
        Route::post('apply/:id', '/apply')->name('systemBroadcastGoodsApply')->option([
            '_alias' => '审核',
            ]);
        Route::post('status/:id', '/changeStatus')->name('systemBroadcastGoodsChangeStatus')->option([
            '_alias' => '修改状态',
            ]);
        Route::post('sort/:id', '/sort')->name('systemBroadcastGoodsSort')
            ->option([
                '_alias' => '排序',

            ]);
        Route::delete('delete/:id', '/delete')->name('systemBroadcastGoodsDelete')->option([
            '_alias' => '删除',
            ]);
    })->prefix('admin.store.BroadcastGoods')->option([
        '_path' => '/marketing/broadcast/list',
        '_auth' => true,
    ]);

    //秒杀配置管理
    Route::group('seckill/config', function () {
        Route::get('lst', '/lst')->name('systemSeckillConfigLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('select', '/select')->option([
            '_alias' => '筛选',
            '_auth'  => false,
        ]);
        Route::get('create/form', '/createForm')->name('systemSeckillConfigCreateForm')->option([
            '_alias' => '添加表单',
            '_auth' => false,
            '_form' => 'systemSeckillConfigCreate',
        ]);
        Route::post('create', '/create')->name('systemSeckillConfigCreate')->option([
            '_alias' => '添加',
        ]);
        Route::get('update/:id/form', '/updateForm')->name('systemSeckillConfigUpdateForm')->option([
            '_alias' => '编辑表单',
            '_auth' => false,
            '_form' => 'systemSeckillConfigUpdate',
        ]);
        Route::post('update/:id', '/update')->name('systemSeckillConfigUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::post('status/:id', '/switchStatus')->name('systemSeckillConfigStatus')->option([
            '_alias' => '排序',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemSeckillConfigDelete')->option([
            '_alias' => '删除',
        ]);
    })->prefix('admin.store.StoreSeckill')->option([
        '_path' => '/marketing/seckill/seckillConfig',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'uploadImage',
                '_path'  =>'/marketing/seckill/seckillConfig',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'systemAttachmentLst',
                '_path'  =>'/marketing/seckill/seckillConfig',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],
        ]
    ]);

    //秒杀活动管理
    Route::group('seckill/active', function () {
        Route::get('lst', '/list')->name('systemSeckillActiveGetActiveList')->option([
            '_alias' => '列表',
        ]);
        Route::get('detail/:id', '/detail')->name('systemSeckillActiveGetActiveInfo')->option([
            '_alias' => '详情',
        ]);
        Route::get('select', '/select')->name('systemSeckillActiveGetActiveAll')->option([
            '_alias' => '列表',
            '_auth' => false,
        ]);
        Route::post('create', '/create')->name('systemSeckillActiveCreateActive')->option([
            '_alias' => '创建',
        ]);
        Route::post('update/:id', '/update')->name('systemSeckillActiveUpdateActive')->option([
            '_alias' => '编辑',
        ]);
        Route::post('update_status/:id', '/update_status')->name('systemSeckillActiveUpdateActiveStatus')->option([
            '_alias' => '编辑状态',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemSeckillActiveDeleteActive')->option([
            '_alias' => '删除',
        ]);
        Route::get('chart_panel/:id', '/chart_panel')->name('systemSeckillActiveChartPanel')->option([
            '_alias' => '活动统计数据面板',
        ]);
        Route::get('chart_people/:id', '/chart_people')->name('systemSeckillActiveChartPeople')->option([
            '_alias' => '活动参与人统计列表',
        ]);
        Route::get('chart_order/:id', '/chart_order')->name('systemSeckillActiveChartOrder')->option([
            '_alias' => '活动订单统计列表',
        ]);
        Route::get('chart_product/:id', '/chart_product')->name('systemSeckillActiveChartProduct')->option([
            '_alias' => '活动商品统计列表',
        ]);
    })->prefix('admin.store.StoreSeckillActive')->option([
        "_path" => '/marketing/seckill/store_seckill/list',
        '_auth' => true,
    ]);


    //秒杀商品管理
    Route::group('seckill/product', function () {
        Route::get('mer_select', '/lists')->option([
            '_alias' => '列表 ',
            '_auth'  => false,
        ]);
        Route::get('lst_filter', '/getStatusFilter')->name('systemStoreSeckillProductLstFilter')->option([
            '_alias' => '统计',
        ]);
        Route::get('lst', '/get_page_list')->name('systemStoreSeckillProductPageLst')->option([
            '_alias' => '列表',
        ]);

        Route::get('list', '/get_list')->name('systemStoreSeckillProductLst')->option([
            '_alias' => '列表',
        ]);

        Route::get('detail/:id', '/detail')->name('systemStoreSeckillProductDetail')->option([
            '_alias' => '详情',
        ]);
        Route::post('update/:id', '/update')->name('systemStoreSeckillProductUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::post('status', '/switchStatus')->name('systemStoreSeckillProductSwitchStatus')->option([
            '_alias' => '审核',
        ]);
        Route::get('switchStatus/:id/form', '/get_switch_status_form')->name('systemStoreSeckillProductSwitchStatusForm')->option([
            '_alias' => '审核表单',
            '_auth' => false,
            '_form' => 'switchStatusForm',
        ]);
        Route::post('change/:id', '/changeUsed')->name('systemStoreSeckillProductChangeUsed')->option([
            '_alias' => '显示/隐藏',
        ]);
        Route::post('labels/:id', '/setLabels')->name('systemStoreSeckillProductLabels')->option([
            '_alias' => '设置标签',
        ]);
        Route::post('delete/:id', '/delete')->name('systemStoreSeckillProductDelete')->option([
            '_alias' => '加入回收站',
        ]);
        Route::post('destory/:id', '/destory')->name('systemStoreSeckillProductDestory')->option([
            '_alias' => '删除',
        ]);
        Route::get('status/:id/form', '/down_product_status_form')->name('downProductStatusForm')->option([
            '_alias' => '强制下架表单',
            '_auth' => false,
            '_form' => 'downProductStatus',
        ]);
    })->prefix('admin.store.StoreProductSeckill')->option([
        '_path' => '/marketing/seckill/list',
        '_auth' => true,
    ]);

    //商品列表
    Route::get('marketing/spu/lst', 'admin.store.marketing.StoreAtmosphere/markLst');


    //活动氛围图 - 详情下边框图
    Route::group('activity/atmosphere/', function () {
        Route::post('create', '/create')->name('systemActivityAtmosphereCreate')->option([
            '_alias' => '添加',
        ]);
        Route::get('lst', '/lst')->name('systemActivityAtmosphereLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('update/:id', '/update')->name('systemActivityAtmosphereUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('detail/:id', '/detail')->name('systemActivityAtmosphereDetail')->option([
            '_alias' => '详情',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemActivityAtmosphereDelete')->option([
            '_alias' => '删除',
        ]);
        Route::post('status/:id', '/statusSwitch')->name('systemActivityAtmosphereStatus')->option([
            '_alias' => '修改状态',
        ]);
    })->prefix('admin.store.marketing.StoreAtmosphere')->option([
        '_path' => '/marketing/atmosphere/list',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'uploadImage',
                '_path'  =>'/marketing/atmosphere/list',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'systemAttachmentLst',
                '_path'  =>'/marketing/atmosphere/list',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],
        ]
    ]);

    //活动氛围图-列表边框
    Route::group('activity/border/', function () {
        Route::post('create', '/create')->name('systemActivityBorderCreate')->option([
            '_alias' => '添加',
        ]);
        Route::get('lst', '/lst')->name('systemActivityBorderLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('update/:id', '/update')->name('systemActivityBorderUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('detail/:id', '/detail')->name('systemActivityBorderDetail')->option([
            '_alias' => '详情',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemActivityBorderDelete')->option([
            '_alias' => '删除',
        ]);
        Route::post('status/:id', '/statusSwitch')->name('systemActivityBorderStatus')->option([
            '_alias' => '修改状态',
        ]);
    })->prefix('admin.store.marketing.StoreBorder')->option([
        '_path' => '/marketing/border/list',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'uploadImage',
                '_path'  =>'/marketing/border/list',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'systemAttachmentLst',
                '_path'  =>'/marketing/border/list',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],
        ]
    ]);

    //报名活动
    Route::group('activity/form/', function () {
        Route::post('create', '/create')->name('systemActivityFormCreate')->option([
            '_alias' => '添加',
        ]);
        Route::get('lst', '/lst')->name('systemActivityFormLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('update/:id', '/update')->name('systemActivityFormUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('detail/:id', '/detail')->name('systemActivityFormDetail')->option([
            '_alias' => '详情',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemActivityFormDelete')->option([
            '_alias' => '删除',
        ]);
        Route::post('status/:id', '/statusSwitch')->name('systemActivityFormStatus')->option([
            '_alias' => '修改状态',
        ]);
        Route::get('/user/lst/:id', '/activUserLst')->name('systemFormActivUserLst')->option([
            '_alias' => '活动记录',
            '_auth' => true,
        ]);
        Route::get('/excel/:id', '/activUserExcel')->name('systemFormActivUserExcel')->option([
            '_alias' => '活动记录导出',
            '_auth' => true,
        ]);
    })->prefix('admin.store.marketing.StoreForm')->option([
        '_path' => '/marketing/application/list',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'uploadImage',
                '_path'  =>'/marketing/application/list',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'systemAttachmentLst',
                '_path'  =>'/marketing/application/list',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],
        ]
    ]);
    Route::group('activity/cate/', function () {
        Route::get('form/create', '/createForm')->name('systemActivityCateFormCreate')->option([
            '_alias' => '添加表单',
        ]);
        Route::post('create', '/create')->name('systemActivityCateCreate')->option([
            '_alias' => '添加',
        ]);
        Route::get('lst', '/lst')->name('systemActivityCateLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('update/:id/form', '/updateForm')->name('systemActivityCateFormUpdate')->option([
            '_alias' => '编辑表单',
        ]);
        Route::post('update/:id', '/update')->name('systemActivityCateUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemActivityCateDelete')->option([
            '_alias' => '删除',
        ]);
        Route::get('select', '/select')->name('systemActivityCateSelect')->option([
            '_auth' => false,
        ]);
    })->prefix('admin.store.StoreActivityCate')->option([
        '_path' => 'product/activityLabel',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'uploadImage',
                '_path'  =>'product/activityLabel',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'systemAttachmentLst',
                '_path'  =>'product/activityLabel',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],
        ]
    ]);

    Route::group('activity/label/', function () {
        Route::post('create', '/create')->name('systemActivityLabelCreate')->option([
            '_alias' => '添加',
        ]);
        Route::get('lst', '/lst')->name('systemActivityLabelLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('update/:id', '/update')->name('systemActivityLabelUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::delete('delete/:id', '/delete')->name('systemActivityLabelDelete')->option([
            '_alias' => '删除',
        ]);
        Route::get('detail/:id', '/detail')->name('systemActivityLabelDetail')->option([
            '_alias' => '详情',
        ]);
        Route::post('status/:id', '/status')->name('systemActivityLabelUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('options', '/options')->name('systemActivityLabelOptions')->option([
            '_alias' => '下拉列表',
        ]);
    })->prefix('admin.store.StoreActivityLabel')->option([
        '_path' => 'product/activityLabel',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'uploadImage',
                '_path'  =>'product/activityLabel',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'systemAttachmentLst',
                '_path'  =>'product/activityLabel',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],
        ]
    ]);
})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
