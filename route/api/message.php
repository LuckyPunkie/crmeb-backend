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

Route::group('message', function () {
    Route::get('dialog/list', 'api.message.Message/dialogList');

    Route::group('user', function () {
        Route::get('history/:uid', 'api.message.Message/messageHistory');
        Route::post('send/:uid', 'api.message.Message/sendMessage');
        Route::post('recall/:message_id', 'api.message.Message/recallMessage');
        Route::put('read/:uid', 'api.message.Message/markAsRead');
    });

    Route::group('notification', function () {
        Route::get('list', 'api.message.Notification/list');
        Route::put('read/:id', 'api.message.Notification/markRead');
        Route::get('unread-count', 'api.message.Notification/unreadCount');
    });

    Route::post('upload-voice', 'api.message.Message/uploadVoice');
    Route::post('upload-image', 'api.message.Message/uploadImage');
})->middleware(UserTokenMiddleware::class, true);
