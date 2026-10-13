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

    //参数模板
    Route::group('store/params', function () {
        Route::get('temp/lst', '/lst')->name('merchantStoreParameterTemplateLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('temp/detail/:id', '/detail')->name('merchantStoreParameterTemplateDetail')->option([
            '_alias' => '详情',
        ]);
        Route::delete('temp/delete/:id', '/delete')->name('merchantStoreParameterTemplateDelete')->option([
            '_alias' => '删除',
        ]);
        Route::delete('temp/delete/value/:id', '/deleteValue')->name('merchantStoreParameterTemplateDeleteValue')
        ->option([
            '_alias' => '删除属性',
        ]);
        Route::post('temp/create', '/create')->name('merchantStoreParameterTemplateCreate')->option([
            '_alias' => '添加',
        ]);
        Route::post('temp/update/:id', '/update')->name('merchantStoreParameterTemplateUpdate')->option([
            '_alias' => '编辑',
        ]);

        Route::get('temp/select', '/select')->option([
            '_alias' => '筛选列表',
            '_auth' => false,
        ]);
        Route::get('temp/show', '/show')->option([
            '_alias' => '参数',
            '_auth' => false,
        ]);
    })->prefix('admin.parameter.ParameterTemplate')->option([
        '_path' => '/product/specs',
        '_auth' => true,
    ]);

    //产品规则模板
    Route::group('store/attr/template', function () {
        Route::get('lst', '/lst')->name('merchantStoreAttrTemplateLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('list', '/getlist')->option([
            '_alias' => '筛选',
            '_auth'  => false,
        ]);
        Route::post('create', '/create')->name('merchantStoreAttrTemplateCreate')->option([
            '_alias' => '添加 ',
        ]);
        Route::delete(':id', '/delete')->name('merchantStoreAttrTemplateDelete')->option([
            '_alias' => '删除',
        ]);
        Route::post(':id', '/update')->name('merchantStoreAttrTemplateUpdate')->option([
            '_alias' => '文件类型',
        ]);
    })->prefix('merchant.store.StoreAttrTemplate')->option([
        '_path' => '/product/attr',
        '_auth' => true,
    ]);

    //商品分类
    Route::group('store/category', function () {
        Route::get('create/form', '/createForm')->name('merchantStoreCategoryCreateForm')->option([
            '_alias' => '添加表单',
            '_auth' => false,
            '_form' => 'merchantStoreCategoryCreate',
        ]);
        Route::get('update/form/:id', '/updateForm')->name('merchantStoreCategoryUpdateForm')->option([
            '_alias' => '编辑表单',
            '_auth' => false,
            '_form' => 'merchantStoreCategoryUpdate',
        ]);
        Route::post('update/:id', '/update')->name('merchantStoreCategoryUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('lst', '/lst')->name('merchantStoreCategoryLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('detail/:id', '/detail')->name('merchantStoreCategoryDtailt')->option([
            '_alias' => '详情',
        ]);
        Route::post('create', '/create')->name('merchantStoreCategoryCreate')->option([
            '_alias' => '添加',
        ]);
        Route::delete('delete/:id', '/delete')->name('merchantStoreCategoryDelete')->option([
            '_alias' => '删除',
        ]);
        Route::post('status/:id', '/switchStatus')->name('merchantStoreCategorySwitchStatus')->option([
            '_alias' => '修改状态',
        ]);
        Route::get('list', '/getList')->option([
            '_alias' => '筛选',
            '_auth'  => false,
        ])->append(['type' => 1]);
        Route::get('select', '/getTreeList')->option([
            '_alias' => '树形',
            '_auth'  => false,
        ]);
        Route::get('brandlist', '/BrandList')->option([
            '_alias' => '品牌列表',
            '_auth'  => false,
        ]);
    })->prefix('admin.store.StoreCategory')->option([
        '_path' => '/product/classify',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'merchantUploadImage',
                '_path'  =>'/product/classify',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'merchantAttachmentLst',
                '_path'  =>'/product/classify',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],
        ]
    ]);

    //商品
    Route::group('store/product', function () {
        Route::get('config', '/config')->option([
            '_alias' => '配置',
            '_auth'  => false,
        ]);
        Route::get('get_edit/:id', '/getEdit')->name('merchantStoreProductGetEdit')->option([
            '_alias' => '编辑商品获取信息',
        ]);
        Route::get('lst_filter', '/getStatusFilter')->name('merchantStoreProductLstFilter')->option([
            '_alias' => '头部统计',
        ]);
        Route::get('lst', '/lst')->name('merchantStoreProductLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('list', '/lst')->option([
            '_alias' => '列表',
            '_auth'  => false,
        ]);
        Route::post('create', '/create')->name('merchantStoreProductCreate')->option([
            '_alias' => '添加',
        ]);
        Route::get('detail/:id', '/detail')->name('merchantStoreProductDetail')->option([
            '_alias' => '详情',
        ]);
        Route::get('temp_key', '/temp_key')->name('merchantStoreProductTempKey')->option([
            '_alias' => '上传视频配置',
        ]);
        Route::post('update/:id', '/update')->name('merchantStoreProductUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::post('free_trial/:id', '/freeTrial')->name('merchantStoreProductFreeTrial')->option([
            '_alias' => '免审编辑',
        ]);
        Route::delete('delete/:id', '/delete')->name('merchantStoreProductDelete')->option([
            '_alias' => '加入回收站',
        ]);
        Route::delete('batch_delete', '/batchDelete')->name('merchantStoreProductBatchDelete')->option([
            '_alias' => '批量加入回收站',
        ]);
        Route::delete('destory/:id', '/destory')->name('merchantStoreProductDestory')->option([
            '_alias' => '彻底删除',
        ]);
        Route::post('restore/:id', '/restore')->name('merchantStoreProductRestore')->option([
            '_alias' => '恢复',
        ]);
        Route::post('batch_restore', '/batchRestore')->name('merchantStoreProductBatchRestore')->option([
            '_alias' => '批量恢复',
        ]);
        Route::post('status/:id', '/switchStatus')->name('merchantStoreProductSwitchStatus')->option([
            '_alias' => '上下架',
        ]);
        Route::post('batch_status', '/batchShow')->name('merchantStoreProductSwitchBatchStatus')->option([
            '_alias' => '批量上下架',
        ]);
        Route::post('batch_temp', '/batchTemplate')->name('merchantStoreProductSwitchBatchTemplate')->option([
            '_alias' => '批量设置运费模板',
        ]);
        Route::post('batch_guarantee', '/batchGuarantee')->name('merchantStoreProductBatchGuarantee')->option([
            '_alias' => '批量设置服务保障',
        ]);
        Route::post('batch_labels', '/batchLabels')->name('merchantStoreProductSwitchBatchLabels')->option([
            '_alias' => '批量设置标签',
        ]);
        Route::post('batch_hot', '/batchHot')->name('merchantStoreProductSwitchBatchHot')->option([
            '_alias' => '批量设置推荐',
        ]);
        Route::post('batch_ext', '/batchExtension')->name('merchantStoreProductSwitchBatchExtension')->option([
            '_alias' => '批量设置推荐',
        ]);
        Route::post('batch_svip', '/batchSvipType')->name('merchantStoreProductSwitchBatchSvipType')->option([
            '_alias' => '批量设置会员价',
        ]);
        Route::post('sort/:id', '/updateSort')->name('merchantStoreProductUpdateSort')->option([
            '_alias' => '排序',
        ]);
        Route::post('preview', '/preview')->name('merchantStoreProductPreview')->option([
            '_alias' => '预览',
        ]);
        Route::post('labels/:id', '/setLabels')->name('merchantStoreProductLabels')->option([
            '_alias' => '标签',
        ]);
        Route::get('attr_value/:id', '/getAttrValue')->name('merchantStoreProductAttrValue')->option([
            '_alias' => '获取规格',
        ]);
        Route::post('get_attr_value/:id', '/isFormatAttr')->name('merchantStoreProductFormatAttr')->option([
            '_alias' => '获取规格',
        ]);
        Route::get('get_batch_list', '/getBatchList')->name('merchantStoreProductGetBatchList')->option([
            '_alias' => '获取批量修改列表',
        ]);
        Route::post('batch_process', '/batchProcess')->name('merchantStoreProductSwitchBatchProcess')->option([
            '_alias' => '批量修改商品属性',
        ]);
        Route::get('get_operate_list/:product_id', '/getOperateList')->name('merchantStoreProductGetOperateList')->option([
            '_alias' => '操作记录',
        ]);
        Route::post('unbind', '/unbind')->name('merchantStoreProductUnbind')->option([
            '_alias' => '操作记录',
        ]);
    })->prefix('merchant.store.product.Product')->option([
        '_path' => '/product/list',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'merchantUploadImage',
                '_path'  =>'/product/list',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'merchantAttachmentLst',
                '_path'  =>'/product/list',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],

        ]
    ]);


    //复制商品
    Route::group('store/productcopy', function () {
        Route::get('lst', '/lst')->name('merchantStoreProductCopyLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('get', '/get')->name('merchantStoreProductCopyGet')->option([
            '_alias' => '获取信息',
        ]);
        Route::get('count', '/count')->name('merchantStoreProductCopyCount')->option([
            '_alias' => '统计',
        ]);
        Route::post('save', '/save')->name('merchantStoreProductCopySave')->option([
            '_alias' => '保存',
        ]);
    })->prefix('merchant.store.product.ProductCopy')->option([
        '_path' => '/product/list',
        '_auth' => true,
    ]);


    //商品评价管理
    Route::group('store/reply', function () {
        Route::get('lst', '/lst')->name('merchantProductReplyLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('form/:id', '/replyForm')->name('merchantProductReplyForm')->option([
            '_alias' => '回复表单',
        ]);
        Route::post('reply/:id', '/reply')->name('merchantProductReplyReply')->option([
            '_alias' => '回复',
        ]);
    })->prefix('admin.store.StoreProductReply')->option([
        '_path' => '/product/reviews',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'merchantUploadImage',
                '_path'  =>'/product/reviews',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'merchantAttachmentLst',
                '_path'  =>'/product/reviews',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],
        ]
    ]);

    Route::group('store/reply', function () {
        Route::post('sort/:id', '/changeSort')->name('merchantProductReplySort')->option([
            '_alias' => '排序',
        ]);
    })->prefix('merchant.store.StoreProductReply')->option([
        '_path' => '/product/reviews',
        '_auth' => true,
    ]);;


    //商品标签
    Route::group('product/label', function () {
        Route::get('lst', '/lst')->name('merchantStoreProductLabelLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('create/form', '/createForm')->name('merchantStoreProductLabelCreateForm')->option([
            '_alias' => '添加表单',
            '_auth' => false,
            '_form' => 'merchantStoreProductLabelCreate',
        ]);
        Route::post('create', '/create')->name('merchantStoreProductLabelCreate')->option([
            '_alias' => '添加',
        ]);
        Route::get('update/:id/form', '/updateForm')->name('merchantStoreProductLabelUpdateForm')->option([
            '_alias' => '编辑表单',
            '_auth' => false,
            '_form' => 'merchantStoreProductLabelUpdate',
        ]);
        Route::post('update/:id', '/update')->name('merchantStoreProductLabelUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('detail/:id', '/detail')->name('merchantStoreProductLabelDetail')->option([
            '_alias' => '详情',
        ]);
        Route::delete('delete/:id', '/delete')->name('merchantStoreProductLabelDelete')->option([
            '_alias' => '删除',
        ]);
        Route::post('status/:id', '/switchWithStatus')->name('merchantStoreProductLabelStatus')->option([
            '_alias' => '修改状态',
        ]);
        Route::get('option', '/getOptions')->option([
            '_alias' => '筛选',
            '_auth' => false,
        ]);

    })->prefix('merchant.store.product.ProductLabel')->option([
        '_path' => '/product/label',
        '_auth' => true,
    ]);


    Route::group('discounts/', function () {
        Route::post('create','/create')->name('merchantStoreDiscountsCreate')->option([
            '_alias' => '添加',
        ]);
        Route::post('update/:id','/update')->name('merchantStoreDiscountsUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('lst','/lst')->name('merchantStoreDiscountsLst')->option([
            '_alias' => '列表',
        ]);
        Route::get('detail/:id','/detail')->name('merchantStoreDiscountsDetail')->option([
            '_alias' => '详情',
        ]);
        Route::delete('delete/:id','/delete')->name('merchantStoreDiscountsDelete')->option([
            '_alias' => '删除',
        ]);
        Route::post('status/:id','/switchStatus')->name('merchantStoreDiscountsStatus')->option([
            '_alias' => '修改状态',
        ]);
    })->prefix('merchant.store.product.Discounts')->option([
        '_path' => '/marketing/discounts/list',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'merchantUploadImage',
                '_path'  =>'/marketing/discounts/list',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'merchantAttachmentLst',
                '_path'  =>'/marketing/discounts/list',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],
        ]
    ]);

    //保障服务
    Route::group('guarantee',function(){
        Route::get('list','/list')->option([
            '_alias' => '列表',
            '_auth' => false,
        ]);
        Route::get('select','/select')->option([
            '_alias' => '筛选',
            '_auth' => false,
        ]);
        Route::get('lst','/lst')->name('merchantGuaranteeLst')->option([
            '_alias' => '列表',
        ]);
        Route::post('create','/create')->name('smerchantGuaranteeCreate')->option([
            '_alias' => '添加',
        ]);
        Route::post('update/:id','/update')->name('merchantGuaranteeUpdate')->option([
            '_alias' => '编辑',
        ]);
        Route::get('detail/:id','/detail')->name('merchantGuaranteeDetail')->option([
            '_alias' => '详情',
        ]);
        Route::delete('delete/:id','/delete')->name('merchantGuaranteeDelete')->option([
            '_alias' => '删除',
        ]);
        Route::post('sort/:id','/sort')->name('merchantGuaranteeSort')->option([
            '_alias' => '排序',
        ]);
        Route::post('status/:id','/switchStatus')->name('merchantGuaranteeStatus')->option([
            '_alias' => '修改状态',
        ]);
    })->prefix('merchant.store.guarantee.GuaranteeTemplate')->option([
        '_path' => '/config/guarantee',
        '_auth' => true,
    ]);


    //商品单位
    Route::group('product/unit', function () {
        Route::get('list', '/list')->name('merchantStoreProductUnitLst')->option([
            '_alias' => '商品单位列表',
        ]);
        Route::get('create/form', '/createForm')->name('merchantStoreProductUnitCreateForm')->option([
            '_alias' => '商品单位添加表单',
            '_auth' => false,
            '_form' => 'merchantStoreProductUnitCreate',
        ]);
        Route::post('create', '/create')->name('merchantStoreProductUnitCreate')->option([
            '_alias' => '商品单位添加',
        ]);
        Route::get('update/:id/form', '/updateForm')->name('merchantStoreProductUnitUpdateForm')->option([
            '_alias' => '商品单位编辑表单',
            '_auth' => false,
            '_form' => 'merchantStoreProductUnitUpdate',
        ]);
        Route::post('update/:id', '/update')->name('merchantStoreProductUnitUpdate')->option([
            '_alias' => '商品单位编辑',
        ]);
        Route::delete('delete/:id', '/delete')->name('merchantStoreProductUnitDelete')->option([
            '_alias' => '商品单位删除',
        ]);
        Route::get('option', '/getSelectList')->option([
            '_alias' => '筛选',
            '_auth' => false,
        ]);

    })->prefix('merchant.store.product.ProductUnit')->option([
        '_path' => '/product/unit',
        '_auth' => true,
    ]);

    Route::group('product/cdkey', function () {
        Route::get('library/lst', 'CdkeyLibrary/lst')->name('merchantStoreProductCdkeyLibraryLst')->option([
            '_alias' => '列表',
            '_auth' => true,
        ]);
        Route::get('library/detail/:id', 'CdkeyLibrary/detail')->name('merchantStoreProductCdkeyLibraryDetail')
            ->option([
            '_alias' => '列表',
            '_auth' => true,
        ]);
        Route::get('library/create/form', 'CdkeyLibrary/createForm')->name('merchantStoreProductCdkeyLibraryCreateForm')
            ->option([
            '_alias' => '添加表单',
            '_auth' => false,
            '_form' => 'merchantStoreProductCdkeyLibraryCreate',
        ]);
        Route::post('library/create', 'CdkeyLibrary/create')->name('merchantStoreProductCdkeyLibraryCreate')->option([
            '_alias' => '添加',
            '_auth' => true,
        ]);
        Route::get('library/update/:id/form', 'CdkeyLibrary/updateForm')->name('merchantStoreProductCdkeyLibraryUpdateForm')->option([
            '_alias' => '编辑表单',
            '_auth' => true,
            '_form' => 'merchantStoreProductCdkeyLibraryUpdate',
        ]);
        Route::post('library/update/:id', 'CdkeyLibrary/update')->name('merchantStoreProductCdkeyLibraryUpdate')->option([
            '_alias' => '编辑',
            '_auth' => true,
        ]);
        Route::delete('library/delete/:id', 'CdkeyLibrary/delete')->name('merchantStoreProductCdkeyLibraryDelete')
            ->option([
                '_alias' => '删除',
                '_auth' => true,
            ]);

        Route::get('library/excel', 'CdkeyLibrary/excel')->name('merchantStoreProductCdkeyLibraryExcel')
            ->option([
                '_alias' => '导出',
                '_auth' => true,
            ]);
        Route::get('library/options', 'CdkeyLibrary/options');
    })->prefix('merchant.store.product.')->option([
        '_path' => '/product/cdkey',
        '_auth' => true,
    ]);

    // 批量导入
    Route::post('product/cdkey/library/import/:type', 'merchant.store.StoreImport/import')->name('merchantStoreProductCdkeyLibraryImport')
        ->option([
            '_alias' => '批量导入',
            '_path' => '/product/cdkey',
            '_auth' => true,
        ]);


    // 卡密信息
    Route::group('product/cdkey', function () {
        Route::get('lst', 'ProductCdkey/lst')->name('merchantStoreProductCdkeyLst')->option([
            '_alias' => '卡密列表',
            '_auth' => true,
        ]);
        Route::post('create', 'ProductCdkey/create')->name('merchantStoreProductCdkeyCreate')->option([
            '_alias' => '添加卡密',
            '_auth' => true,
        ]);
        Route::post('update/:id', 'ProductCdkey/update')->name('merchantStoreProductCdkeyUpdate')->option([
            '_alias' => '编辑卡密',
            '_auth' => true,
        ]);
        Route::delete('delete/:id', 'ProductCdkey/delete')->name('merchantStoreProductCdkeyDelete')->option([
            '_alias' => '删除卡密',
            '_auth' => true,
        ]);
        Route::delete('batch_delete', 'ProductCdkey/batchDelete')->name('merchantStoreProductCdkeyLibraryBatchDelete')
            ->option([
                '_alias' => '批量删除',
                '_auth' => true,
            ]);
    })->prefix('merchant.store.product.')->option([
        '_path' => '/product/cdkey',
        '_auth' => true,
    ]);

    //预约商品
    Route::group('store/reservation/product', function () {
        Route::post('create', '/create')->name('merchantStoreReservationProductCreate')->option([
            '_alias' => '添加预约商品',
            '_auth' => true,
        ]);
        Route::get('edit/:id', '/editInfo')->name('merchantStoreReservationProductEditInfo')->option([
            '_alias' => '获取预约商品',
            '_auth' => true,
        ]);
        Route::post('edit/:id', '/edit')->name('merchantStoreReservationProductEdit')->option([
            '_alias' => '编辑预约商品',
            '_auth' => true,
        ]);
        Route::get('detail/:id', '/detail')->name('merchantStoreReservationProductDetail')->option([
            '_alias' => '预约商品详情',
            '_auth' => true,
        ]);
        Route::post('setStock/:id', '/batchSetReservationProductStock')->name('merchantStoreReservationProductEditStock')->option([
            '_alias' => '批量修改预约商品库存',
            '_auth' => true,
        ]);
        Route::get('showMonth/:id', '/showMonth')->name('merchantStoreReservationProductShowMonth')->option([
            '_alias' => '商品日历month',
            '_auth' => false,
        ]);
        Route::get('showDay/:id', '/showDay')->name('merchantStoreReservationProductShowDay')->option([
            '_alias' => '商品日历day',
            '_auth' => false,
        ]);
    })->prefix('merchant.store.product.ReservationProduct')->option([
        '_path' => '/product/list',
        '_auth' => true,
        '_append'=> [
            [
                '_name'  =>'merchantUploadImage',
                '_path'  =>'/product/list',
                '_alias' => '上传图片',
                '_auth'  => true,
            ],
            [
                '_name'  =>'merchantAttachmentLst',
                '_path'  =>'/product/list',
                '_alias' => '图片列表',
                '_auth'  => true,
            ],

        ]
    ]);

})->middleware(AllowOriginMiddleware::class)
    ->middleware(MerchantTokenMiddleware::class, true)
    ->middleware(MerchantAuthMiddleware::class)
    ->middleware(MerchantCheckBaseInfoMiddleware::class)
    ->middleware(LogMiddleware::class);
