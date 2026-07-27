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
use app\common\middleware\UserTokenMiddleware;

// 需要登录认证的路由
Route::group(function () {
    Route::post('community/redpacket/create', 'api.community.CommunityRedpacket/create');
    Route::post('community/redpacket/update/:id', 'api.community.CommunityRedpacket/update');
    Route::post('community/redpacket/pay', 'api.community.CommunityRedpacket/pay');
    Route::post('community/redpacket/take/:id', 'api.community.CommunityRedpacket/take');
    Route::post('community/redpacket/submit/:taskId', 'api.community.CommunityRedpacket/submit');
    Route::post('community/redpacket/confirm/:taskId', 'api.community.CommunityRedpacket/confirm');
    Route::get('community/redpacket/my-list', 'api.community.CommunityRedpacket/myList');
})->middleware(UserTokenMiddleware::class, true);

// 无需登录的路由
Route::group(function () {
    Route::get('community/redpacket/detail/:id', 'api.community.CommunityRedpacket/detail');
    Route::get('community/redpacket/task-list/:id', 'api.community.CommunityRedpacket/taskList');
    Route::get('commission/rates', 'api.CommissionConfig/rates');
})->middleware(UserTokenMiddleware::class, false);
