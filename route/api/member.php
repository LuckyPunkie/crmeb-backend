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
use app\common\middleware\VisitProductMiddleware;

//强制登录
Route::group(function () {
    Route::group('merchant', function () {
        Route::group('member/', function () {
            Route::get('coupon_list/ids', [app\controller\api\store\product\StoreCoupon::class,'merchantMemberListByIds']);
            Route::post('become', [app\controller\api\merchant\Member::class,'become']);
            Route::get('store/list', [app\controller\api\merchant\Member::class,'storeList']);
            Route::get('user/info/:merId', [app\controller\api\merchant\Member::class,'info']);
            Route::post('logoff/:merId', [app\controller\api\merchant\Member::class,'logoff']);
            Route::get('coupon/list/:merId', [app\controller\api\merchant\Member::class,'coupons']);
            Route::post('coupon/receive/:id', [app\controller\api\merchant\Member::class,'receiveCoupon']);
            Route::get('level/list/:merId', [app\controller\api\merchant\Member::class,'levels']);
            Route::get('agreement/open/:merId', [app\controller\api\merchant\Member::class,'openAgreement']);
        });
        Route::group('shopping/credits', function () {
            Route::get('asset/:merId', [app\controller\api\merchant\ShoppingCredits::class,'asset']);
            Route::post('pay/package', [app\controller\api\merchant\ShoppingCredits::class,'payPackage']);
            Route::get('pay/package/record', [app\controller\api\merchant\ShoppingCredits::class,'rechargeOrders']);
            Route::get('user/record/page', [app\controller\api\merchant\ShoppingCredits::class,'records']);
            Route::post('order/refund/apply', [app\controller\api\merchant\ShoppingCredits::class,'refundApply']);
            Route::get('refund/order/page', [app\controller\api\merchant\ShoppingCredits::class,'refundOrders']);
            Route::get('package/list/ids', [app\controller\api\merchant\ShoppingCredits::class,'packagesByIds']);
            Route::get('package/list/:merId', [app\controller\api\merchant\ShoppingCredits::class,'packages']);
        });
    });
})
    ->middleware(UserTokenMiddleware::class, true);
