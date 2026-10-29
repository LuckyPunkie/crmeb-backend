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

    Route::group('v2', function () {
        Route::post('order/check', [app\controller\api\store\order\StoreOrder::class,'v2CheckOrder']);
        Route::post('order/create', [app\controller\api\store\order\StoreOrder::class,'v2CreateOrder']);
    });
    Route::get('pay/config', [app\controller\api\store\order\StoreOrder::class,'payConfig']);

    //退出登录
    Route::post('logout', [app\controller\api\Auth::class,'logout']);
    //用户信息
    Route::get('user', [app\controller\api\Auth::class,'userInfo']);
    //平台配送员入驻申请
    Route::post('delivery/service/apply', [app\controller\api\delivery\DeliveryServiceApply::class,'apply']);
    Route::get('delivery/service/apply/detail', [app\controller\api\delivery\DeliveryServiceApply::class,'detail']);
    //绑定推荐人
    Route::post('user/spread', [app\controller\api\Auth::class,'spread']);
    //优惠券
    Route::post('coupon/receive/:id', [app\controller\api\store\product\StoreCoupon::class,'receiveCoupon']);

    //订单
    Route::group('order', function () {
        Route::get('group_order_list', [app\controller\api\store\order\StoreOrder::class,'groupOrderList']);
        Route::get('group_order_detail/:id', [app\controller\api\store\order\StoreOrder::class,'groupOrderDetail']);
        Route::post('cancel/:id', [app\controller\api\store\order\StoreOrder::class,'cancelGroupOrder']);
        Route::get('list', [app\controller\api\store\order\StoreOrder::class,'lst']);
        Route::get('detail/:id', [app\controller\api\store\order\StoreOrder::class,'detail']);
        Route::get('take_order_list/:id', [app\controller\api\store\order\StoreOrder::class,'takeOrderList']);
        Route::get('number', [app\controller\api\store\order\StoreOrder::class,'number']);
        Route::post('pay/:id', [app\controller\api\store\order\StoreOrder::class,'groupOrderPay']);
        Route::post('points/pay/:id', [app\controller\api\store\order\StoreOrder::class,'groupOrderPay'])->append(['is_points' => 1]);
        Route::post('take/:id', [app\controller\api\store\order\StoreOrder::class,'take']);
        Route::post('express/:id', [app\controller\api\store\order\StoreOrder::class,'express']);
        Route::post('del/:id', [app\controller\api\store\order\StoreOrder::class,'del']);
        Route::get('status/:id', [app\controller\api\store\order\StoreOrder::class,'groupOrderStatus']);
        Route::get('verify_code/:id', [app\controller\api\store\order\StoreOrder::class,'verifyCode']);
        Route::post('receipt/:id', [app\controller\api\store\order\StoreOrder::class,'createReceipt']);
        Route::get('delivery/:id', [app\controller\api\store\order\StoreOrder::class,'getOrderDelivery']);
        Route::get('cashier_order/:id', [app\controller\api\store\order\StoreOrder::class,'getCashierOrder']);
        Route::post('self/cancel/:id', [app\controller\api\store\order\StoreOrder::class,'cancelOrder']);
        Route::get('deliveryStation/list', [app\controller\api\store\order\StoreOrder::class,'deliveryStationList']);
        Route::get('deliverySetings', [app\controller\api\store\order\StoreOrder::class,'deliveryConfig']);
        Route::get('deliveryTrack/:id', [app\controller\api\store\order\StoreOrder::class,'deliveryTrack']);
        Route::get('card_log/:id', [app\controller\api\store\order\StoreOrder::class,'verifyCardLog']);
    });

    // 预售
    Route::group('presell', function () {
        Route::post('pay/:id', [app\controller\api\store\order\PresellOrder::class,'pay']);
    });

    //退款单
    Route::group('refund', function () {
        Route::get('batch_product/:id', [app\controller\api\store\order\StoreRefundOrder::class,'batchProduct']);
        Route::get('express/:id', [app\controller\api\store\order\StoreRefundOrder::class,'express']);
        Route::get('product/:id', [app\controller\api\store\order\StoreRefundOrder::class,'product']);
        Route::post('compute', [app\controller\api\store\order\StoreRefundOrder::class,'compute']);
        Route::post('apply/:id', [app\controller\api\store\order\StoreRefundOrder::class,'refund']);
        Route::get('list', [app\controller\api\store\order\StoreRefundOrder::class,'lst']);
        Route::get('detail/:id', [app\controller\api\store\order\StoreRefundOrder::class,'detail']);
        Route::post('del/:id', [app\controller\api\store\order\StoreRefundOrder::class,'del']);
        Route::post('back_goods/:id', [app\controller\api\store\order\StoreRefundOrder::class,'back_goods']);
        Route::post('cancel/:id', [app\controller\api\store\order\StoreRefundOrder::class,'cancel']);
        Route::post('platform/:id', [app\controller\api\store\order\StoreRefundOrder::class,'platformIntervene']);
    });

    //评价
    Route::group('reply', function () {
        Route::get('product/:id', [app\controller\api\store\product\StoreReply::class,'product']);
        Route::post(':id', [app\controller\api\store\product\StoreReply::class,'reply']);
    });

    //注销用户
    Route::post('user/cancel', [app\controller\api\Auth::class,'cancel']);
    //用户
    Route::group('user', function () {
        //切换账号
        Route::get('account', [app\controller\api\user\User::class,'account']);
        Route::post('switch', [app\controller\api\user\User::class,'switchUser']);
        //修改信息
        Route::post('change/phone', [app\controller\api\user\User::class,'changePhone']);
        Route::post('change/info', [app\controller\api\user\User::class,'updateBaseInfo']);
        Route::post('change/password', [app\controller\api\user\User::class,'changePassword']);
        //收藏
        Route::get('/relation/product/lst', [app\controller\api\user\UserRelation::class,'productList']);
        Route::get('/relation/merchant/lst', [app\controller\api\user\UserRelation::class,'merchantList']);
        Route::post('/relation/create', [app\controller\api\user\UserRelation::class,'create']);
        Route::post('/relation/batch/create', [app\controller\api\user\UserRelation::class,'batchCreate']);
        Route::post('/relation/delete', [app\controller\api\user\UserRelation::class,'delete']);
        Route::post('/relation/batch/delete', [app\controller\api\user\UserRelation::class,'batchDelete']);

        //反馈
        Route::post('/feedback', [app\controller\api\user\Feedback::class,'feedback']);
        Route::get('/feedback/list', [app\controller\api\user\Feedback::class,'feedbackList']);
        Route::get('/feedback/detail/:id', [app\controller\api\user\Feedback::class,'detail']);
        //充值
        Route::post('/recharge', [app\controller\api\user\UserRecharge::class,'recharge']);
        Route::post('/recharge/brokerage', [app\controller\api\user\UserRecharge::class,'brokerage']);
        //地址
        Route::get('/address/lst', [app\controller\api\user\UserAddress::class,'lst']);
        Route::post('/address/create', [app\controller\api\user\UserAddress::class,'create']);
        Route::get('/address/detail/:id', [app\controller\api\user\UserAddress::class,'detail']);
        Route::post('/address/update/:id', [app\controller\api\user\UserAddress::class,'editDefault']);
        Route::post('/address/delete/:id', [app\controller\api\user\UserAddress::class,'delete']);

        //分销海报
        Route::get('/spread_image', [app\controller\api\user\User::class,'spread_image']);
        Route::get('/v2/spread_image', [app\controller\api\user\User::class,'spread_image_v2']);
        //推广人列表
        Route::get('/spread_list', [app\controller\api\user\User::class,'spread_list']);

        //提现
        Route::get('/extract/lst', [app\controller\api\user\UserExtract::class,'lst']);
        Route::get('/extract/detail/:id', [app\controller\api\user\UserExtract::class,'detail']);
        Route::get('/extract/banklst', [app\controller\api\user\UserExtract::class,'bankLst']);
        Route::post('/extract/create', [app\controller\api\user\UserExtract::class,'create']);
        Route::get('/extract/history_bank', [app\controller\api\user\UserExtract::class,'historyBank']);

        //绑定手机号
        Route::post('binding', [app\controller\api\user\User::class,'binding']);
        //小程序获取手机号
        Route::post('mp/binding', [app\controller\api\user\User::class,'mpPhone']);

        //余额记录
        Route::get('bill', [app\controller\api\user\User::class,'bill']);
        //佣金记录
        Route::get('brokerage_list', [app\controller\api\user\User::class,'brokerage_list']);
        //推广人订单
        Route::get('spread_order', [app\controller\api\user\User::class,'spread_order']);
        //推广人排行榜
        Route::get('spread_top', [app\controller\api\user\User::class,'spread_top']);
        //佣金排行榜
        Route::get('brokerage_top', [app\controller\api\user\User::class,'brokerage_top']);
        Route::get('spread_info', [app\controller\api\user\User::class,'spread_info']);
        Route::get('spread_level', [app\controller\api\user\User::class,'spread_info']);

        Route::get('brokerage/info', [app\controller\api\user\User::class,'brokerage_info']);
        Route::get('brokerage/all', [app\controller\api\user\User::class,'brokerage_all']);
        Route::get('brokerage/notice', [app\controller\api\user\User::class,'notice']);

        //浏览记录
        Route::get('history', [app\controller\api\user\UserHistory::class,'lst']);
        Route::post('history/delete/:id', [app\controller\api\user\UserHistory::class,'deleteHistory']);
        Route::post('history/batch/delete', [app\controller\api\user\UserHistory::class,'deleteHistoryBatch']);

        //发票
        Route::post('receipt/create', [app\controller\api\user\UserReceipt::class,'create']);
        Route::get('receipt/lst', [app\controller\api\user\UserReceipt::class,'lst']);
        Route::get('receipt/order', [app\controller\api\user\UserReceipt::class,'order']);
        Route::get('receipt/order/:id', [app\controller\api\user\UserReceipt::class,'orderDetail']);
        Route::post('receipt/delete/:id', [app\controller\api\user\UserReceipt::class,'delete']);
        Route::post('receipt/update/:id', [app\controller\api\user\UserReceipt::class,'update']);
        Route::post('receipt/is_default/:id', [app\controller\api\user\UserReceipt::class,'isDefault']);
        Route::get('receipt/detail/:id', [app\controller\api\user\UserReceipt::class,'detail']);

        //签到
        Route::get('sign/lst', [app\controller\api\user\UserSign::class,'lst']);
        Route::post('sign/create', [app\controller\api\user\UserSign::class,'create']);
        Route::get('sign/month', [app\controller\api\user\UserSign::class,'month']);

        //积分
        Route::get('integral/info', [app\controller\api\user\User::class,'integralInfo']);
        Route::get('integral/lst', [app\controller\api\user\User::class,'integralList']);

        //客服列表
        Route::get('services', [app\controller\api\user\User::class,'services']);

        // APP 推送设备绑定
        Route::post('push_client', 'User/bindPushClient');

        Route::get('member/info', 'User/memberInfo');
        Route::get('member/log', 'Member/getMemberValue');

        // 用户表单数据操作
        Route::get('fields/info', [app\controller\api\user\UserFields::class,'info']);
        Route::post('fields/save', [app\controller\api\user\UserFields::class,'save']);
        Route::delete('fields/delete', [app\controller\api\user\UserFields::class,'delete']);

        // 社交档案
        Route::get('profile', 'UserProfile/detail');
        Route::post('profile/save', 'UserProfile/save');
        // 找到我的方式 / 找人
        Route::get('find_me', 'UserProfile/findMeState');
        Route::post('find_me/toggle', 'UserProfile/findMeToggle');
        Route::get('find_me/search', 'UserProfile/findMeSearch');
        // find_me/pay_info 在 notLogin.php（未登录也要拿 banner / 套餐）
        Route::post('find_me/order', 'UserProfile/findMeOrder');
        Route::get('find_me/order/:order_no', 'UserProfile/findMeOrderStatus');
        // 付费解锁微信号
        Route::post('wechat_unlock/:uid', 'UserProfile/wechatUnlock');
        Route::get('wechat_unlock_check/:uid', 'UserProfile/wechatUnlockCheck');
        // 付费解锁主页

        // 资质认证
        Route::get('certification', 'UserCertification/list');
        Route::post('certification/save', 'UserCertification/save');
        Route::post('certification/identity_verify', 'UserCertification/identityVerify');
        Route::post('certification/chsi_verify', 'UserCertification/chsiVerify');
        Route::get('review_status', 'UserCertification/reviewStatus');
        Route::post('review_urgent/:uid', 'UserCertification/applyUrgent');

    })->prefix('api.user.');

    //购物车
    Route::group('user/cart', function () {
        Route::post('/check/:id', [app\controller\api\store\order\StoreCart::class,'checkCerate']);
        Route::get('/lst', [app\controller\api\store\order\StoreCart::class,'lst']);
        Route::post('/create', [app\controller\api\store\order\StoreCart::class,'create']);
        Route::post('/again', [app\controller\api\store\order\StoreCart::class,'again']);
        Route::post('/change/:id', [app\controller\api\store\order\StoreCart::class,'change']);
        Route::post('/delete', [app\controller\api\store\order\StoreCart::class,'batchDelete']);
        Route::get('/count', [app\controller\api\store\order\StoreCart::class,'cartCount']);
        Route::post('/batchCreate', [app\controller\api\store\order\StoreCart::class,'batchCreate']);
        Route::post('/clear', [app\controller\api\store\order\StoreCart::class,'clear']);
    });

    Route::group('store/product', function () {
        Route::post('/assist/create/:id', [app\controller\api\store\product\StoreProductAssistSet::class,'create']);
        Route::get('/assist/detail/:id', [app\controller\api\store\product\StoreProductAssistSet::class,'detail']);
        Route::post('/assist/set/:id', [app\controller\api\store\product\StoreProductAssistSet::class,'set']);
        Route::get('/assist/user/:id', [app\controller\api\store\product\StoreProductAssistSet::class,'userList']);
        Route::get('/assist/share/:id', [app\controller\api\store\product\StoreProductAssistSet::class,'shareNum']);
        Route::get('/assist/set/lst', [app\controller\api\store\product\StoreProductAssistSet::class,'lst']);
        Route::post('/assist/set/delete/:id', [app\controller\api\store\product\StoreProductAssistSet::class,'delete']);
        Route::post('/increase_take', [app\controller\api\store\product\StoreProduct::class,'setIncreaseTake']);
        Route::get('/get_attr_value/:id', [app\controller\api\store\product\StoreProduct::class,'getAttrValue']);
    });

    //申请商户
    Route::get('intention/lst', 'api.store.merchant.MerchantIntention/lst');
    Route::get('intention/detail/:id', 'api.store.merchant.MerchantIntention/detail');
    Route::post('intention/update/:id', 'api.store.merchant.MerchantIntention/update');

    // 商家标签（需要是商户）
    Route::group('mer/label', function () {
        Route::get('lst', 'api.store.merchant.MerchantLabelApi/lst');
        Route::post('join/:id', 'api.store.merchant.MerchantLabelApi/join');
        Route::post('pay/:id', 'api.store.merchant.MerchantLabelApi/pay');
        Route::post('confirm/:order_sn', 'api.store.merchant.MerchantLabelApi/confirm');
    });
    Route::post('store/product/group/cancel', 'api.store.product.StoreProductGroup/cancel');

    //社区
    Route::group('community', function () {

        Route::post('/create', 'Community/create');
        Route::post('/update/:id', 'Community/update');
        Route::post('/delete/:id', 'Community/delete');
        Route::get('pay_product/lst', 'Community/payList');
        Route::get('rela_product/lst', 'Community/relationList');
        Route::get('hist_product/lst', 'Community/historyList');
        // 漫步：清除自己的位置留痕
        Route::post('roam/clear', 'Community/clearRoamTrace');

        Route::post('fans/:id', [app\controller\api\community\Community::class,'setFocus']);
        Route::get('fans/lst', [app\controller\api\community\Community::class,'getUserFans']);
        Route::get('focus/lst', [app\controller\api\community\Community::class,'getUserFocus']);

        Route::post('like/:id', 'Community/toggleLike');
        Route::get('like_me/lst', 'Community/likeMeList');
        Route::get('i_like/lst', 'Community/iLikeList');

        Route::post('start/:id', 'Community/startCommunity');
        Route::get('start/lst', 'Community/getUserStartCommunity');
        Route::post('collect/:id', 'Community/collectCommunity');
        Route::get('collect/lst', 'Community/getUserCollectCommunity');
        Route::post('dislike/:id', 'Community/dislikeCommunity');

        Route::post('reply/create/:id', 'CommunityReply/create');
        Route::post('reply/start/:id', 'CommunityReply/start');
        Route::delete('reply/delete/:id', 'CommunityReply/delete');

        Route::get('order/:id', [app\controller\api\community\Community::class,'getSpuByOrder']);


    })->prefix('api.community.');

    //请求频率
    Route::group(function () {

        //付费会员购买
        Route::post('svip/pay/:id', [app\controller\api\user\Svip::class,'createOrder']);

        //订单检查
        Route::group('order/v3', function () {
            Route::post('check', [app\controller\api\store\order\PointsOrder::class,'beforCheck']);
            Route::post('create', [app\controller\api\store\order\PointsOrder::class,'createOrder']);
            Route::post('pay/:id', [app\controller\api\store\order\PointsOrder::class,'orderPay']);
        });

    })->middleware(\app\common\middleware\BlockerMiddleware::class);

    Route::group('points/order', function () {
        Route::get('lst', [app\controller\api\store\order\PointsOrder::class,'lst']);
        Route::get('detail/:id', [app\controller\api\store\order\PointsOrder::class,'detail']);
        Route::post('take/:id', [app\controller\api\store\order\PointsOrder::class,'take']);
        Route::post('deleate/:id', [app\controller\api\store\order\PointsOrder::class,'del']);
    });

    Route::group('user/form', function () {
        Route::post('/create/:id', [app\controller\api\store\form\FormRelated::class,'create']);
        Route::get('/lst', [app\controller\api\store\form\FormRelated::class,'lst']);
        Route::get('/detail/:id', [app\controller\api\store\form\FormRelated::class,'detail']);
        Route::get('/show/:id', [app\controller\api\store\form\FormRelated::class,'show']);
    });
    // 商圈入驻
    Route::group('circleAgent', function () {
        Route::get('list', [app\controller\api\circle\CircleAgent::class,'list']);
        Route::get('detail/:id', [app\controller\api\circle\CircleAgent::class,'detail']);
        Route::post('create', [app\controller\api\circle\CircleAgent::class,'create']);
        Route::post('update/:id', [app\controller\api\circle\CircleAgent::class,'update']);
        Route::post('revoke/:id', [app\controller\api\circle\CircleAgent::class,'revoke']);
    });

    // 获取微信小程序 WebSocket 临时连接 Token
    Route::post('temp-ws-token', 'api.store.service.Service/tempWsToken');

})->middleware(UserTokenMiddleware::class, true);

//非强制登录
Route::group(function () {
    // 付费会员
    Route::group('svip', function () {
        //价格列表
        Route::get('pay_lst', [app\controller\api\user\Svip::class,'getTypeLst']);
        Route::get('user_info', [app\controller\api\user\Svip::class,'svipUserInfo']);
        Route::get('coupon_lst', [app\controller\api\user\Svip::class,'svipCoupon']);
        Route::get('product_lst', [app\controller\api\user\Svip::class,'svipProductList']);
        Route::post('coupon_receive/:id', [app\controller\api\user\Svip::class,'receiveCoupon']);
    });
    //社区
    Route::group('community', function () {
        //社区文章列表
        Route::get('/lst', [app\controller\api\community\Community::class,'lst']);
        Route::get('/user_lst', [app\controller\api\community\Community::class,'userList']);
        Route::get('/video_lst', [app\controller\api\community\Community::class,'videoShow']);
        //详情
        Route::get('/show/:id', [app\controller\api\community\Community::class,'show']);
        //用户页
        Route::get('/user/info/:id', [app\controller\api\community\Community::class,'userInfo']);
        //用户的文章
        Route::get('/user/community/:id', [app\controller\api\community\Community::class,'userCommunitylst']);
        //用户的视频
        Route::get('/user/community_video/:id', [app\controller\api\community\Community::class,'userCommunityVideolst']);
        //分类&话题
        Route::get('category/lst', [app\controller\api\community\CommunityCategory::class,'lst']);
        Route::get('/:id/reply', [app\controller\api\community\CommunityReply::class,'lst']);

        Route::get('/focuslst', 'Community/focuslst');
        Route::get('qrcode/:id', 'Community/qrcode');
        // 机器人帖图片加载失败上报（首页可未登录浏览）
        Route::post('/image_fail', 'Community/imageFail');
    })->prefix('api.community.');
    // 申请加急复审 / 查询审核状态（无需登录；勿挂在 community 前缀下，否则控制器路径会被拼错）
    Route::post('community/user/review_urgent/:uid', 'api.user.UserCertification/applyUrgent');
    Route::get('community/user/review_status/:uid', 'api.user.UserCertification/reviewStatusPublic');
    //专题
    Route::group('activity', function () {
        Route::get('lst/:id', [app\controller\api\Common::class,'activityLst']);
        Route::get('info/:id', [app\controller\api\Common::class,'activityInfo']);
    });
    //商品
    Route::group('store/product', function () {
        Route::get('seckill/select', [app\controller\api\store\product\StoreProductSeckill::class,'select']);
        Route::get('seckill/lst', [app\controller\api\store\product\StoreProductSeckill::class,'lst']);
        Route::get('seckill/detail/:id', [app\controller\api\store\product\StoreProductSeckill::class,'detail'])->middleware(VisitProductMiddleware::class, 1);
        Route::get('category/lst', [app\controller\api\store\product\StoreCategory::class,'lst']);
        Route::get('category', [app\controller\api\store\product\StoreCategory::class,'children']);
        Route::get('brand/lst', [app\controller\api\store\product\StoreBrand::class,'lst']);
        Route::get('detail/:id', [app\controller\api\store\product\StoreProduct::class,'detail'])->middleware(VisitProductMiddleware::class, 0);
        Route::get('show/:id', [app\controller\api\store\product\StoreProduct::class,'show']);
        Route::get('good_list/:id', [app\controller\api\store\product\StoreProduct::class,'getGoodList']);
        Route::get('/qrcode/:id', [app\controller\api\store\product\StoreProduct::class,'qrcode']);
        Route::get('category/hotranking', [app\controller\api\store\product\StoreCategory::class,'cateHotRanking']);

        Route::get('bag/explain', [app\controller\api\store\product\StoreProduct::class,'getBagExplain']);
        Route::get('/reply/lst/:id', [app\controller\api\store\product\StoreReply::class,'lst']);
        //预售
        Route::get('/presell/lst', [app\controller\api\store\product\StoreProductPresell::class,'lst']);
        Route::get('/presell/detail/:id', [app\controller\api\store\product\StoreProductPresell::class,'detail'])->middleware(VisitProductMiddleware::class, 2);
        //预售协议
        Route::get('presell/agree', [app\controller\api\store\product\StoreProductPresell::class,'getAgree']);
        //助力
        Route::get('/assist/lst', [app\controller\api\store\product\StoreProductAssist::class,'lst']);
        //拼团
        Route::get('group/lst', [app\controller\api\store\product\StoreProductGroup::class,'lst']);
        Route::get('group/detail/:id', [app\controller\api\store\product\StoreProductGroup::class,'detail'])->middleware(VisitProductMiddleware::class, 4);
        Route::get('group/count', [app\controller\api\store\product\StoreProductGroup::class,'userCount']);
        Route::get('group/category', [app\controller\api\store\product\StoreProductGroup::class,'category']);
        Route::get('group/get/:id', [app\controller\api\store\product\StoreProductGroup::class,'groupBuying']);

        Route::get('/guarantee/:id', [app\controller\api\store\product\StoreProduct::class,'guaranteeTemplate']);
        Route::get('/preview', [app\controller\api\store\product\StoreProduct::class,'preview']);
        Route::get('/price_rule/:id', [app\controller\api\store\product\StoreProduct::class,'priceRule']);
        Route::get('/cate_hot', [app\controller\api\store\product\StoreProduct::class,'cateHotList']);
        //商品列表获取商品规格
        Route::get('/get_spec/:id', [app\controller\api\store\product\StoreProduct::class,'getSpec']);
        // 商品列表获取推荐商品
        Route::get('/recommendProduct', [app\controller\api\store\product\StoreProduct::class,'recommendProduct']);
        /**
         * 预约商品相关
         */
        Route::get('/reservation/getMonth/:id', [app\controller\api\store\product\StorePrdouctReservation::class,'showMonth']);
        Route::get('/reservation/getDay/:id', [app\controller\api\store\product\StorePrdouctReservation::class,'showDay']);
        Route::post('/reservation/checkRange', [app\controller\api\store\product\StorePrdouctReservation::class,'checkRange']);
    });
    //各种商品列表
    Route::group('product/spu', function () {
        //礼包 product/spu/bag
        Route::get('/bag', [app\controller\api\store\product\StoreSpu::class,'bag']);
        //商品 product/spu/lst
        Route::get('/lst', [app\controller\api\store\product\StoreSpu::class,'lst']);
        //热门 product/spu/hot/:type
        Route::get('/hot/:type', [app\controller\api\store\product\StoreSpu::class,'hot']);
        //推荐 product/spu/recommend
        Route::get('/recommend', [app\controller\api\store\product\StoreSpu::class,'recommend']);
        //商户商品  product/spu/merchant/:id
        Route::get('/merchant/:id', [app\controller\api\store\product\StoreSpu::class,'merProductLst']);
        //礼包推荐  product/spu/bag/recommend
        Route::get('/bag/recommend', [app\controller\api\store\product\StoreSpu::class,'bagRecommend']);
        //活动分类  product/spu/active/category/:type
        Route::get('/active/category/:type', [app\controller\api\store\product\StoreSpu::class,'activeCategory']);
        //标签获取数据
        Route::get('/labels', [app\controller\api\store\product\StoreSpu::class,'labelsLst']);
        //本地生活商品
        Route::get('/local/:id', [app\controller\api\store\product\StoreSpu::class,'local']);
        //复制口令
        Route::get('/copy', [app\controller\api\store\product\StoreSpu::class,'copy']);
        Route::get('/get/:id', [app\controller\api\store\product\StoreSpu::class,'get']);
        //优惠券商品列表
        Route::get('/coupon_product', [app\controller\api\store\product\StoreSpu::class,'getProductByCoupon']);
        //热卖排行
        Route::get('/get_hot_ranking', [app\controller\api\store\product\StoreSpu::class,'getHotRanking']);
        //商品参数
        Route::get('/params', [app\controller\api\store\product\StoreParams::class,'select']);
        //商品参数值
        Route::get('/params_value/:id', [app\controller\api\store\product\StoreParams::class,'getValue']);
        //商品热搜
        Route::get('/hot_lst', [app\controller\api\store\product\StoreProduct::class,'getHotList']);
        Route::get('/hot_top', [app\controller\api\store\product\StoreProduct::class,'getHotTop']);
    });
    //直播
    Route::group('broadcast', function () {
        Route::get('/lst', [app\controller\api\store\broadcast\BroadcastRoom::class,'lst']);
        Route::get('/hot', [app\controller\api\store\broadcast\BroadcastRoom::class,'hot']);
    });
    //优惠券
    Route::group('coupon', function () {
        Route::get('product', [app\controller\api\store\product\StoreCoupon::class,'coupon']);
        Route::get('store/:id', [app\controller\api\store\product\StoreCoupon::class,'merCoupon']);
        Route::get('list', [app\controller\api\store\product\StoreCoupon::class,'lst']);
        Route::get('getlst', [app\controller\api\store\product\StoreCoupon::class,'getList']);
        Route::get('new_people', [app\controller\api\store\product\StoreCoupon::class,'newPeople']);
    });
    //商户
    Route::group('store/merchant/', function () {
        Route::get('/lst', 'Merchant/lst');
        Route::get('/online_product/lst', 'Merchant/onlineProductLst');
        Route::get('/product/lst/:id', 'Merchant/productList');
        Route::get('/category/lst/:id', 'Merchant/categoryList');
        Route::get('/detail/0', 'Merchant/systemDetail');
        Route::get('/detail/:id', 'Merchant/detail');
        Route::get('/qrcode/:id', 'Merchant/qrcode');
        Route::get('/local', 'Merchant/localLst');
        Route::get('/localDetail/:id', 'Merchant/localDetail');
    })->prefix('api.store.merchant.');
    Route::post('store/certificate/:merId', 'api.Auth/getMerCertificate');
    //文章
    Route::group('article', function () {
        Route::get('/lst/:cid', [app\controller\api\article\Article::class,'lst']);
        Route::get('/list', [app\controller\api\article\Article::class,'list']);
        Route::get('detail/:id', [app\controller\api\article\Article::class,'detail']);
        Route::get('/category/lst', [app\controller\api\article\ArticleCategory::class,'lst']);
    });

    //申请商户
    Route::post('intention/create', [app\controller\api\store\merchant\MerchantIntention::class,'create']);
    Route::get('intention/cate', [app\controller\api\store\merchant\MerchantIntention::class,'cateLst']);
    Route::get('intention/type', [app\controller\api\store\merchant\MerchantIntention::class,'typeLst']);
    Route::get('intention/circles', [app\controller\api\store\merchant\MerchantIntention::class,'circles']);
    Route::get('intention/business', [app\controller\api\store\merchant\MerchantIntention::class,'business']);
    //浏览
    Route::post('common/visit', [app\controller\api\Common::class,'visit']);
    Route::get('store/product/assist/count', [app\controller\api\store\product\StoreProductAssist::class,'userCount']);
    Route::get('store/expr/temps', [app\controller\admin\system\serve\Export::class,'getExportTemp']);
    //复制口令
    Route::get('command/copy', [app\controller\api\Common::class,'getCommand']);
    Route::group('discounts', function () {
        Route::get('lst', '/lst');
        Route::get('detail/:id', '/detail');
    })->prefix('api.store.product.Discounts');
    Route::get('subscribe', 'api.Common/subscribe');
    Route::group('points', function () {
        Route::get('home', [app\controller\api\store\product\PointsProduct::class,'home']);
        Route::get('scope', [app\controller\api\store\product\PointsProduct::class,'points_mall_scope']);
        Route::get('lst', [app\controller\api\store\product\PointsProduct::class,'lst']);
        Route::get('detail/:id', [app\controller\api\store\product\PointsProduct::class,'detail']);
    });
    Route::group('diy', function () {
        //普通商品详情
        Route::get('/product_detail', [app\controller\api\Diy::class,'productDetail']);
        //秒杀
        Route::get('/seckill', [app\controller\api\Diy::class,'seckill']);
        //助力
        Route::get('/assist', [app\controller\api\Diy::class,'assist']);
        //预售
        Route::get('/presell', [app\controller\api\Diy::class,'presell']);
        //拼团
        Route::get('/group', [app\controller\api\Diy::class,'group']);
        //商品列表
        Route::get('/spu', [app\controller\api\Diy::class,'spu']);
        //社区
        Route::get('/community', [app\controller\api\Diy::class,'community']);
        //优惠券
        Route::get('/coupon', [app\controller\api\Diy::class,'coupon']);
        //品牌好店
        Route::get('/store', [app\controller\api\Diy::class,'store']);
        //二级分类
        Route::get('/category', [app\controller\api\Diy::class,'category']);
        //直播
        Route::get('/broadcast', [app\controller\api\Diy::class,'broadcast']);
        //热卖排行
        Route::get('/hot_top', [app\controller\api\Diy::class,'hot_top']);
        // 悬浮按钮
        Route::get('/fab', [app\controller\api\Diy::class,'fab']);
        // 平台商品分类
        Route::get('/productCategory', '/productCategory');
        // 盲盒店铺
        Route::get('/blindbox', '/blindbox');
    })->prefix('api.Diy');
    Route::group('system/form', function () {
        Route::get('/lst', [app\controller\api\store\form\Form::class,'lst']);
        Route::get('/detail/:id', [app\controller\api\store\form\Form::class,'detail']);
        Route::get('/info/:form_id', [app\controller\api\store\form\Form::class,'getFormInfo']);
        Route::get('/share_posters/:id', [app\controller\api\store\form\Form::class,'getSharePosters']);
    });
    // 分组推荐
    Route::group('store/group', function () {
        Route::get('/recommend', [app\controller\api\store\StoreGroup::class,'recommendList']);
        Route::get('/options', [app\controller\api\store\StoreGroup::class,'options']);
    });
    // 用户
    Route::group('user', function () {
        // 签到数据信息获取
        Route::get('sign/info', 'UserSign/info');

        // 印象墙
        Route::group('impression', function () {
            Route::get('summary/:owner_uid', 'Impression/summary');
            Route::get('lst/:owner_uid', 'Impression/lst');
            Route::post('create/:owner_uid', 'Impression/create');
            Route::post('delete/:impression_id', 'Impression/delete');

            Route::get('comment/:impression_id', 'Impression/comments');
            Route::get('comment/replies/:parent_id', 'Impression/commentReplies');
            Route::post('comment/create/:impression_id', 'Impression/commentCreate');
            Route::post('comment/delete/:comment_id', 'Impression/commentDelete');

            Route::post('like', 'Impression/like');
            Route::post('wall/toggle', 'Impression/wallToggle');
        });
    })->prefix('api.user.');

    //获取商户基本信息
    Route::get('service/info/:id', [app\controller\api\store\service\Service::class,'merchantInfo']);
    Route::get('has_service/:id', [app\controller\api\store\service\Service::class,'hasService']);
    Route::group('merchant/member', function () {
        Route::get('level/list/:merId', [app\controller\api\merchant\Member::class,'levels']);
        Route::get('agreement/open/:merId', [app\controller\api\merchant\Member::class,'openAgreement']);
        Route::get('diy-page/:merId', [app\controller\api\merchant\Member::class,'diyPage']);
    });
    Route::group('merchant/shopping/credits', function () {
        Route::get('package/list/ids', [app\controller\api\merchant\ShoppingCredits::class,'packagesByIds']);
        Route::get('package/list/:merId', [app\controller\api\merchant\ShoppingCredits::class,'packages']);
    });
    Route::get('coupon/merchant/member/list/ids', [app\controller\api\store\product\StoreCoupon::class,'merchantMemberListByIds']);
    //上传图片
    Route::post('upload/image/:field', 'api.Common/uploadImage');
    Route::post('scan_upload/image/:field/:token', 'api.Common/scanUploadImage');
    //附件上传（pdf/doc/image，最大20MB）
    Route::post('upload/attachment/:field', 'api.Common/uploadAttachment');
    //入住商户上传证件接口
    Route::post('upload/certificate/:field', [app\controller\api\Common::class,'uploadCertificate']);
    //直传 OSS：开关读取、签名（未登录时签名接口返回失败，前端回退原上传接口）
    Route::get('oss/config', 'api.OssUpload/config');
    Route::post('oss/sign', 'api.OssUpload/sign');
    //公共配置
    Route::get('config', [app\controller\api\Common::class,'config']);
    Route::get('navigation', [app\controller\api\Common::class,'getNavigation']);
})->middleware(UserTokenMiddleware::class, false);
