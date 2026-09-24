<?php
// +----------------------------------------------------------------------
// | uni-ad 激励视频服务器回调路由
// +----------------------------------------------------------------------

use app\common\middleware\UserTokenMiddleware;
use think\facade\Route;

Route::group('uni_ad', function () {
    // uni-ad 服务器回调（GET，无需登录；uni-ad 服务器直接请求）
    Route::get('reward_callback', 'api.UniAdCallback/rewardCallback');

    // 前端轮询接口（需登录）
    Route::get('is_rewarded', 'api.UniAdCallback/isRewarded')
        ->middleware(UserTokenMiddleware::class, true);
});
