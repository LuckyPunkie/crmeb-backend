<?php

use app\common\middleware\UserTokenMiddleware;
use think\facade\Route;

Route::group(function () {
    Route::get('major_customer/config', 'api.major_customer.MajorCustomer/config');
    Route::post('major_customer/switch', 'api.major_customer.MajorCustomer/switch');
    Route::post('major_customer/referrer', 'api.major_customer.MajorCustomer/referrer');
    Route::post('major_customer/plans', 'api.major_customer.MajorCustomer/plans');
    Route::post('major_customer/share_switch', 'api.major_customer.MajorCustomer/shareSwitch');
    Route::get('major_customer/prestore', 'api.major_customer.MajorCustomer/prestore');

    Route::get('major_customer/commission/summary', 'api.major_customer.MajorCustomer/commissionSummary');
    Route::get('major_customer/commission/list', 'api.major_customer.MajorCustomer/commissionList');
    Route::get('major_customer/rebate/list', 'api.major_customer.MajorCustomer/rebateList');
})->middleware(UserTokenMiddleware::class, true);
