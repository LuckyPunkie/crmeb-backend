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

use app\common\middleware\CheckSiteOpenMiddleware;
use app\common\middleware\InstallMiddleware;
use think\facade\Route;

Route::get('install','Install/begin');
Route::group('install',function(){
   route::get('environment','/environment') ;
   route::get('databases','/databases') ;
   route::post('databases/create','/create') ;
   route::post('databases/check','/databasesCheck') ;
   route::post('perform/:n','/perform') ;
   route::get('end','/end') ;
   route::get('loader','/swooleCompiler') ;
})->prefix('Install');

// 收钱吧同款：统一收款中转短链（微信「普通链接二维码」规则建议配此前缀）
// 微信域名校验文件（须先于商户 ID 路由注册）
Route::get('payjump/:verify_file', function (string $verify_file) {
    if (!preg_match('/^[A-Za-z0-9_-]+\.txt$/', $verify_file)) {
        abort(404);
    }
    $path = public_path() . 'payjump' . DIRECTORY_SEPARATOR . $verify_file;
    if (!is_file($path)) {
        abort(404);
    }
    return response(file_get_contents($path), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
})->pattern(['verify_file' => '[A-Za-z0-9_-]+\.txt']);

Route::get('payjump/:mer_id', 'api.store.nearby.ScanPay/jump')->pattern(['mer_id' => '\d+']);

// 扫码下单中转短链（微信「普通链接二维码」规则建议配此前缀）
// 微信域名校验文件（须先于商户/台号路由注册）
Route::get('scanjump/:verify_file', function (string $verify_file) {
    if (!preg_match('/^[A-Za-z0-9_-]+\.txt$/', $verify_file)) {
        abort(404);
    }
    $path = public_path() . 'scanjump' . DIRECTORY_SEPARATOR . $verify_file;
    if (!is_file($path)) {
        abort(404);
    }
    return response(file_get_contents($path), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
})->pattern(['verify_file' => '[A-Za-z0-9_-]+\.txt']);

Route::get('scanjump/:mer_id/:table_id', 'api.store.scanOrder.ScanOrderJump/jump')
    ->pattern(['mer_id' => '\d+', 'table_id' => '\d+']);


