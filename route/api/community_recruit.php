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
    Route::post('community/recruit/create', 'api.community.CommunityRecruit/create');
    Route::post('community/recruit/update/:id', 'api.community.CommunityRecruit/update');
    Route::post('community/recruit/apply/:id', 'api.community.CommunityRecruit/apply');
    Route::get('community/recruit/my-list', 'api.community.CommunityRecruit/myList');
    Route::get('community/recruit/applications/:id', 'api.community.CommunityRecruit/applications');
    Route::get('community/recruit/merchant-applications', 'api.community.CommunityRecruit/merchantApplications');
    Route::get('community/recruit/merchant-stats', 'api.community.CommunityRecruit/merchantStats');
    Route::post('community/recruit/mark/:applyId', 'api.community.CommunityRecruit/mark');
    Route::put('community/recruit/close/:id', 'api.community.CommunityRecruit/close');
    Route::get('community/recruit/my-applications', 'api.community.CommunityRecruit/myApplications');
    Route::get('community/recruit/application/:id', 'api.community.CommunityRecruit/applicationDetail');
    Route::get('community/recruit/apply-resume/:applyId', 'api.community.CommunityRecruit/applyResume');
})->middleware(UserTokenMiddleware::class, true);

// 无需登录的路由
Route::group(function () {
    Route::get('community/recruit/detail/:id', 'api.community.CommunityRecruit/detail');
})->middleware(UserTokenMiddleware::class, false);
