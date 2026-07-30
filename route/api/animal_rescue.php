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

// 无需登录（可选登录，挂上 isLogin 宏）
Route::group('animal_rescue', function () {
    Route::get('lst', '/lst');
    Route::get('detail/:id', '/show');
    Route::get('category_count', '/categoryCount');
})->prefix('api.animal_rescue.AnimalRescue')
  ->middleware(UserTokenMiddleware::class, false);

// 需要登录
Route::group('animal_rescue', function () {
    Route::post('create', '/create');
    Route::post('update/:id', '/update');
    Route::delete('delete/:id', '/delete');
    Route::post('donate', '/donate');
    Route::post('donate/pay/:order_id', '/donatePay');
    Route::post('adoption/apply', '/applyAdoption');
    Route::post('adoption/pay_deposit', '/payDeposit');
    Route::post('adoption/audit/:id', '/auditAdoption');
    Route::get('applications/:post_id', '/postApplications');
    Route::get('application/:id', '/applicationDetail');
    Route::get('my_applications', '/myApplications');
    Route::get('my_records', '/myRecords');
    Route::get('my_posts', '/myPosts');
    // v2.1 拨款凭证 / 救助站
    Route::get('shelter/check', '/shelterCheck');
    Route::post('fund_voucher/:id', '/submitFundVoucher');
    Route::get('fund_voucher/:id', '/fundVoucherDetail');
})->prefix('api.animal_rescue.AnimalRescue')
  ->middleware(UserTokenMiddleware::class, true);
