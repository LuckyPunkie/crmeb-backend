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

// 需要登录认证的路由（简历所有接口都需要登录）
Route::group(function () {
    Route::post('community/resume/parse', 'api.community.CommunityResume/parse');
    Route::post('community/resume/create', 'api.community.CommunityResume/create');
    Route::put('community/resume/update/:id', 'api.community.CommunityResume/update');
    Route::get('community/resume/detail/:id', 'api.community.CommunityResume/detail');
    Route::get('community/resume/my-list', 'api.community.CommunityResume/myList');
    Route::delete('community/resume/delete/:id', 'api.community.CommunityResume/delete');
    Route::post('community/resume/upload', 'api.community.CommunityResume/upload');
    Route::put('community/resume/default/:id', 'api.community.CommunityResume/setDefault');
})->middleware(UserTokenMiddleware::class, true);
