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

    //身份规则
    Route::group('system/storage', function () {

        //获取存储类型
        Route::get('type_list', '/typeList');

        //获取存储配置
        Route::get('config', '/getConfig')->name('systemStorageGetConfig')->option([
            '_alias' => '配置信息',
        ]);
        //提交配置
        Route::post('config', '/setConfig')->name('systemStorageSetConfig')->option([
            '_alias' => '提交配置',
        ]);

        Route::get(':type/form', '/form')->name('systemStorageConfigForm')->option([
            '_alias' => '获取云存储配置表单',
            '_form'  => 'systemStorageUpdate',
            '_auth' => false,
        ]);

        Route::post('set_key', '/setForm')->name('systemStorageUpdate')->option([
            '_alias' => '保存云存储配置',
        ]);

         Route::get('sync/:type', '/sync')->name('systemStorageSync')->option([
             '_alias' => '同步存储空间',
         ]);

        Route::get('region/lst/:type', '/lstRegion')->name('systemStorageLstRegion')->option([
            '_alias' => '存储空间列表',
        ]);

        Route::get('region/create/:type/form', '/createRegionForm')->name('systemStorageCreateRegionForm')->option([
            '_alias' => '添加存储空间表单',
            '_form'  => 'systemStorageCreateRegion',
            '_auth' => false,
        ]);

        Route::post('region/create/:type', '/createRegion')->name('systemStorageCreateRegion')->option([
            '_alias' => '添加存储空间',
        ]);

        Route::delete('region/delete/:id', '/deleteRegion')->name('systemStorageDeleteRegion')->option([
            '_alias' => '删除存储空间',
        ]);

        Route::post('region/status/:id', '/swtichStatus')->name('systemStorageRegionSwtichStatus')->option([
            '_alias' => '使用存储空间',
        ]);

        Route::get('domain/update/:id/form', '/editDomainForm')->name('systemStorageUpdateDomainForm')->option([
            '_alias' => '修改存储空间名称表单',
            '_form'  => 'systemStorageUpdateDomain',
            '_auth' => false,
        ]);

        Route::post('domain/update/:id', '/editDomain')->name('systemStorageUpdateDomain')->option([
            '_alias' => '修改存储空间名称',
        ]);

    })->prefix('admin.system.SystemStorage')->option([
        '_path' => '/setting/storage',
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

})->middleware(AllowOriginMiddleware::class)
    ->middleware(AdminTokenMiddleware::class, true)
    ->middleware(AdminAuthMiddleware::class)
    ->middleware(LogMiddleware::class);
