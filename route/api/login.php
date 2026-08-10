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
    Route::get('pay/config', 'api.store.order.StoreOrder/payConfig');
    Route::group('v3', function () {
        //新的下单接口,支持分账
        Route::group('order', function () {
            Route::post('check', '/v3CheckOrder');
            Route::post('create', '/v3CreateOrder');
        })->prefix('api.store.order.StoreOrder');
    });
    Route::group('v2', function () {
        //新的下单接口,支持分账
        Route::group('order', function () {
            Route::post('check', '/v2CheckOrder');
            Route::post('create', '/v2CreateOrder');
        })->prefix('api.store.order.StoreOrder');
    });

    //退出登录
    Route::post('logout', 'api.Auth/logout');
    //用户信息
    Route::get('user', 'api.Auth/userInfo');

    //绑定推荐人
    Route::post('user/spread', 'api.Auth/spread');

    //优惠券
    Route::group('coupon', function () {
        Route::post('receive/:id', 'api.store.product.StoreCoupon/receiveCoupon');
    });

    //订单
    Route::group('order', function () {
        Route::post('check', '/checkOrder');
        Route::post('create', '/createOrder');
        Route::get('group_order_list', '/groupOrderList');
        Route::get('group_order_detail/:id', '/groupOrderDetail');
        Route::post('cancel/:id', '/cancelGroupOrder');
        Route::get('list', '/lst');
        Route::get('detail/:id', '/detail');
        Route::get('number', '/number');
        Route::post('pay/:id', '/groupOrderPay');
        Route::post('points/pay/:id', '/groupOrderPay')->append(['is_points' => 1]);
        Route::post('take/:id', '/take');
        Route::post('express/:id', '/express');
        Route::post('del/:id', '/del');
        Route::get('status/:id', '/groupOrderStatus');
        Route::get('verify_code/:id', '/verifyCode');
        Route::post('receipt/:id', '/createReceipt');
        Route::get('delivery/:id', '/getOrderDelivery');
        Route::get('cashier_order/:id', '/getCashierOrder');
        Route::post('self/cancel/:id', '/cancelOrder');
        Route::get('deliveryStation/list', '/deliveryStationList');
        Route::get('deliverySetings', '/deliveryConfig');
        Route::get('deliveryTrack/:id', '/deliveryTrack');
    })->prefix('api.store.order.StoreOrder');

    // 预售
    Route::group('presell', function () {
        Route::post('pay/:id', '/pay');
    })->prefix('api.store.order.PresellOrder');

    //退款单
    Route::group('refund', function () {
        Route::get('batch_product/:id', '/batchProduct');
        Route::get('express/:id', '/express');
        Route::get('product/:id', '/product');
        Route::post('compute', '/compute');
        Route::post('apply/:id', '/refund');
        Route::get('list', '/lst');
        Route::get('detail/:id', '/detail');
        Route::post('del/:id', '/del');
        Route::post('back_goods/:id', '/back_goods');
        Route::post('cancel/:id', '/cancel');
        Route::post('platform/:id', '/platformIntervene');
    })->prefix('api.store.order.StoreRefundOrder');

    //评价
    Route::group('reply', function () {
        Route::get('product/:id', '/product');
        Route::post(':id', '/reply');
    })->prefix('api.store.product.StoreReply');

    //注销用户
    Route::post('user/cancel', 'api.Auth/cancel');
    //用户
    Route::group('user', function () {
        //切换账号
        Route::get('account', 'User/account');
        Route::post('switch', 'User/switchUser');
        //修改信息
        Route::post('change/phone', 'User/changePhone');
        Route::post('change/info', 'User/updateBaseInfo');
        Route::post('change/password', 'User/changePassword');
        //收藏
        Route::get('/relation/product/lst', 'UserRelation/productList');
        Route::get('/relation/merchant/lst', 'UserRelation/merchantList');
        Route::post('/relation/create', 'UserRelation/create');
        Route::post('/relation/batch/create', 'UserRelation/batchCreate');
        Route::post('/relation/delete', 'UserRelation/delete');
        Route::post('/relation/batch/delete', 'UserRelation/batchDelete');

        //反馈
        Route::post('/feedback', 'Feedback/feedback');
        Route::get('/feedback/list', 'Feedback/feedbackList');
        Route::get('/feedback/detail/:id', 'Feedback/detail');
        //充值
        Route::post('/recharge', 'UserRecharge/recharge');
        Route::post('/recharge/brokerage', 'UserRecharge/brokerage');
        //地址
        Route::get('/address/lst', 'UserAddress/lst');
        Route::post('/address/create', 'UserAddress/create');
        Route::get('/address/detail/:id', 'UserAddress/detail');
        Route::post('/address/update/:id', 'UserAddress/editDefault');
        Route::post('/address/delete/:id', 'UserAddress/delete');

        //分销海报
        Route::get('/spread_image', 'User/spread_image');
        Route::get('/v2/spread_image', 'User/spread_image_v2');
        //推广人列表
        Route::get('/spread_list', 'User/spread_list');

        //提现
        Route::get('/extract/lst', 'UserExtract/lst');
        Route::get('/extract/detail/:id', 'UserExtract/detail');
        Route::get('/extract/banklst', 'UserExtract/bankLst');
        Route::post('/extract/create', 'UserExtract/create');
        Route::get('/extract/history_bank', 'UserExtract/historyBank');

        //绑定手机号
        Route::post('binding', 'User/binding');
        //小程序获取手机号
        Route::post('mp/binding', 'User/mpPhone');

        //余额记录
        Route::get('bill', 'User/bill');
        //佣金记录
        Route::get('brokerage_list', 'User/brokerage_list');
        //推广人订单
        Route::get('spread_order', 'User/spread_order');
        //推广人排行榜
        Route::get('spread_top', 'User/spread_top');
        //佣金排行榜
        Route::get('brokerage_top', 'User/brokerage_top');
        Route::get('spread_info', 'User/spread_info');
        Route::get('spread_level', 'User/spread_info');

        Route::get('brokerage/info', 'User/brokerage_info');
        Route::get('brokerage/all', 'User/brokerage_all');
        Route::get('brokerage/notice', 'User/notice');

        //浏览记录
        Route::get('history', 'UserHistory/lst');
        Route::post('history/delete/:id', 'UserHistory/deleteHistory');
        Route::post('history/batch/delete', 'UserHistory/deleteHistoryBatch');

        //发票
        Route::post('receipt/create', 'UserReceipt/create');
        Route::get('receipt/lst', 'UserReceipt/lst');
        Route::get('receipt/order', 'UserReceipt/order');
        Route::get('receipt/order/:id', 'UserReceipt/orderDetail');
        Route::post('receipt/delete/:id', 'UserReceipt/delete');
        Route::post('receipt/update/:id', 'UserReceipt/update');
        Route::post('receipt/is_default/:id', 'UserReceipt/isDefault');
        Route::get('receipt/detail/:id', 'UserReceipt/detail');

        //签到
        Route::get('sign/lst', 'UserSign/lst');
        Route::post('sign/create', 'UserSign/create');
        Route::get('sign/month', 'UserSign/month');

        //积分
        Route::get('integral/info', 'User/integralInfo');
        Route::get('integral/lst', 'User/integralList');

        //客服列表
        Route::get('services', 'User/services');

        // APP 推送设备绑定
        Route::post('push_client', 'User/bindPushClient');

        Route::get('member/info', 'User/memberInfo');
        Route::get('member/log', 'Member/getMemberValue');

        // 用户表单数据操作
        Route::get('fields/info', 'UserFields/info');
        Route::post('fields/save', 'UserFields/save');
        Route::delete('fields/delete', 'UserFields/delete');

        // 社交档案
        Route::get('profile', 'UserProfile/detail');
        Route::post('profile/save', 'UserProfile/save');

        // 资质认证
        Route::get('certification', 'UserCertification/list');
        Route::post('certification/save', 'UserCertification/save');
        Route::post('certification/chsi_verify', 'UserCertification/chsiVerify');
        Route::get('review_status', 'UserCertification/reviewStatus');
        Route::post('review_urgent/:uid', 'UserCertification/applyUrgent');

    })->prefix('api.user.');

    //购物车
    Route::group('user/cart', function () {
        Route::post('/check/:id', 'StoreCart/checkCerate');
        Route::get('/lst', 'StoreCart/lst');
        Route::post('/create', 'StoreCart/create');
        Route::post('/again', 'StoreCart/again');
        Route::post('/change/:id', 'StoreCart/change');
        Route::post('/delete', 'StoreCart/batchDelete');
        Route::get('/count', 'StoreCart/cartCount');
        Route::post('/batchCreate', 'StoreCart/batchCreate');
        Route::post('/clear', 'StoreCart/clear');
    })->prefix('api.store.order.');

    Route::group('store/product', function () {
        Route::post('/assist/create/:id', 'StoreProductAssistSet/create');
        Route::get('/assist/detail/:id', 'StoreProductAssistSet/detail');
        Route::post('/assist/set/:id', 'StoreProductAssistSet/set');
        Route::get('/assist/user/:id', 'StoreProductAssistSet/userList');
        Route::get('/assist/share/:id', 'StoreProductAssistSet/shareNum');
        Route::get('/assist/set/lst', 'StoreProductAssistSet/lst');
        Route::post('/assist/set/delete/:id', 'StoreProductAssistSet/delete');
        Route::post('/increase_take', 'StoreProduct/setIncreaseTake');
        Route::get('/get_attr_value/:id', 'StoreProduct/getAttrValue');
    })->prefix('api.store.product.');

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

        Route::post('fans/:id', 'Community/setFocus');
        Route::get('fans/lst', 'Community/getUserFans');
        Route::get('focus/lst', 'Community/getUserFocus');

        Route::post('like/:id', 'Community/toggleLike');
        Route::get('like_me/lst', 'Community/likeMeList');
        Route::get('i_like/lst', 'Community/iLikeList');

        Route::post('start/:id', 'Community/startCommunity');
        Route::get('start/lst', 'Community/getUserStartCommunity');
        Route::post('collect/:id', 'Community/collectCommunity');

        Route::post('reply/create/:id', 'CommunityReply/create');
        Route::post('reply/start/:id', 'CommunityReply/start');

        Route::get('order/:id', 'Community/getSpuByOrder');


    })->prefix('api.community.');

    //请求频率
    Route::group(function () {

        //付费会员购买
        Route::post('svip/pay/:id', 'api.user.Svip/createOrder');

        //订单检查
        Route::group('order/v3', function () {
            Route::post('check', 'PointsOrder/beforCheck');
            Route::post('create', 'PointsOrder/createOrder');
            Route::post('pay/:id', 'PointsOrder/orderPay');
        })->prefix('api.store.order.');

    })->middleware(\app\common\middleware\BlockerMiddleware::class);

    Route::group('points/order', function () {
        Route::get('lst', 'PointsOrder/lst');
        Route::get('detail/:id', 'PointsOrder/detail');
        Route::post('take/:id', 'PointsOrder/take');
        Route::post('deleate/:id', 'PointsOrder/del');
    })->prefix('api.store.order.');

    Route::group('user/form', function () {
        Route::post('/create/:id', '/create');
        Route::get('/lst', '/lst');
        Route::get('/detail/:id', '/detail');
        Route::get('/show/:id', '/show');
    })->prefix('api.store.form.FormRelated');
    // 商圈入驻
    Route::group('circleAgent', function () {
        Route::get('list', '/list');
        Route::get('detail/:id', '/detail');
        Route::post('create', '/create');
        Route::post('update/:id', '/update');
        Route::post('revoke/:id', '/revoke');
    })->prefix('api.circle.CircleAgent');

    // 获取微信小程序 WebSocket 临时连接 Token
    Route::post('temp-ws-token', 'api.store.service.Service/tempWsToken');

})->middleware(UserTokenMiddleware::class, true);

//非强制登录
Route::group(function () {
    // 付费会员
    Route::group('svip', function () {
        //价格列表
        Route::get('pay_lst', '/getTypeLst');
        Route::get('user_info', '/svipUserInfo');
        Route::get('coupon_lst', '/svipCoupon');
        Route::get('product_lst', '/svipProductList');
        Route::post('coupon_receive/:id', '/receiveCoupon');
    })->prefix('api.user.Svip');
    //社区
    Route::group('community', function () {
        //社区文章列表
        Route::get('/lst', 'Community/lst');
        Route::get('/user_lst', 'Community/userList');
        Route::get('/video_lst', 'Community/videoShow');
        //详情
        Route::get('/show/:id', 'Community/show');
        //用户页
        Route::get('/user/info/:id', 'Community/userInfo');
        //用户的文章
        Route::get('/user/community/:id', 'Community/userCommunitylst');
        //用户的视频
        Route::get('/user/community_video/:id', 'Community/userCommunityVideolst');
        //分类&话题
        Route::get('category/lst', 'CommunityCategory/lst');
        Route::get('/:id/reply', 'CommunityReply/lst');

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
        Route::get('lst/:id', 'api.Common/activityLst');
        Route::get('info/:id', 'api.Common/activityInfo');
    });
    //商品
    Route::group('store/product', function () {
        Route::get('seckill/select', 'StoreProductSeckill/select');
        Route::get('seckill/lst', 'StoreProductSeckill/lst');
        Route::get('seckill/detail/:id', 'StoreProductSeckill/detail')->middleware(VisitProductMiddleware::class, 1);
        Route::get('category/lst', 'StoreCategory/lst');
        Route::get('category', 'StoreCategory/children');
        Route::get('brand/lst', 'StoreBrand/lst');
        Route::get('detail/:id', 'StoreProduct/detail')->middleware(VisitProductMiddleware::class, 0);
        Route::get('show/:id', 'StoreProduct/show');
        Route::get('good_list/:id', 'StoreProduct/getGoodList');
        Route::get('/qrcode/:id', 'StoreProduct/qrcode');
        Route::get('category/hotranking', 'StoreCategory/cateHotRanking');

        Route::get('bag/explain', 'StoreProduct/getBagExplain');
        Route::get('/reply/lst/:id', 'StoreReply/lst');
        //预售
        Route::get('/presell/lst', 'StoreProductPresell/lst');
        Route::get('/presell/detail/:id', 'StoreProductPresell/detail')->middleware(VisitProductMiddleware::class, 2);
        //预售协议
        Route::get('presell/agree', 'StoreProductPresell/getAgree');
        //助力
        Route::get('/assist/lst', 'StoreProductAssist/lst');
        //拼团
        Route::get('group/lst', 'StoreProductGroup/lst');
        Route::get('group/detail/:id', 'StoreProductGroup/detail')->middleware(VisitProductMiddleware::class, 4);
        Route::get('group/count', 'StoreProductGroup/userCount');
        Route::get('group/category', 'StoreProductGroup/category');
        Route::get('group/get/:id', 'StoreProductGroup/groupBuying');

        Route::get('/guarantee/:id', 'StoreProduct/guaranteeTemplate');
        Route::get('/preview', 'StoreProduct/preview');
        Route::get('/price_rule/:id', 'StoreProduct/priceRule');
        Route::get('/cate_hot', 'StoreProduct/cateHotList');
        //商品列表获取商品规格
        Route::get('/get_spec/:id', 'StoreProduct/getSpec');
        // 商品列表获取推荐商品
        Route::get('/recommendProduct', 'StoreProduct/recommendProduct');
        /**
         * 预约商品相关
         */
        Route::get('/reservation/getMonth/:id', 'StorePrdouctReservation/showMonth');
        Route::get('/reservation/getDay/:id', 'StorePrdouctReservation/showDay');
        Route::post('/reservation/checkRange', 'StorePrdouctReservation/checkRange');
    })->prefix('api.store.product.');
    //各种商品列表
    Route::group('product/spu', function () {
        //礼包 product/spu/bag
        Route::get('/bag', 'StoreSpu/bag');
        //商品 product/spu/lst
        Route::get('/lst', 'StoreSpu/lst');
        //热门 product/spu/hot/:type
        Route::get('/hot/:type', 'StoreSpu/hot');
        //推荐 product/spu/recommend
        Route::get('/recommend', 'StoreSpu/recommend');
        //商户商品  product/spu/merchant/:id
        Route::get('/merchant/:id', 'StoreSpu/merProductLst');
        //礼包推荐  product/spu/bag/recommend
        Route::get('/bag/recommend', 'StoreSpu/bagRecommend');
        //活动分类  product/spu/active/category/:type
        Route::get('/active/category/:type', 'StoreSpu/activeCategory');
        //标签获取数据
        Route::get('/labels', 'StoreSpu/labelsLst');
        //本地生活商品
        Route::get('/local/:id', 'StoreSpu/local');
        //复制口令
        Route::get('/copy', 'StoreSpu/copy');
        Route::get('/get/:id', 'StoreSpu/get');
        //优惠券商品列表
        Route::get('/coupon_product', 'StoreSpu/getProductByCoupon');
        //热卖排行
        Route::get('/get_hot_ranking', 'StoreSpu/getHotRanking');
        //商品参数
        Route::get('/params', 'StoreParams/select');
        //商品参数值
        Route::get('/params_value/:id', 'StoreParams/getValue');
        //商品热搜
        Route::get('/hot_lst', 'StoreProduct/getHotList');
        Route::get('/hot_top', 'StoreProduct/getHotTop');
    })->prefix('api.store.product.');
    //直播
    Route::group('broadcast', function () {
        Route::get('/lst', 'BroadcastRoom/lst');
        Route::get('/hot', 'BroadcastRoom/hot');
    })->prefix('api.store.broadcast.');
    //优惠券
    Route::group('coupon', function () {
        Route::get('product', 'api.store.product.StoreCoupon/coupon');
        Route::get('store/:id', 'api.store.product.StoreCoupon/merCoupon');
        Route::get('list', 'api.store.product.StoreCoupon/lst');
        Route::get('getlst', 'api.store.product.StoreCoupon/getList');
        Route::get('new_people', 'api.store.product.StoreCoupon/newPeople');
    });
    //商户
    Route::group('store/merchant/', function () {
        Route::get('/lst', 'Merchant/lst');
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
        Route::get('/lst/:cid', 'Article/lst');
        Route::get('/list', 'Article/list');
        Route::get('detail/:id', 'Article/detail');
        Route::get('/category/lst', 'ArticleCategory/lst');
    })->prefix('api.article.');

    //申请商户
    Route::post('intention/create', 'api.store.merchant.MerchantIntention/create');
    Route::get('intention/cate', 'api.store.merchant.MerchantIntention/cateLst');
    Route::get('intention/type', 'api.store.merchant.MerchantIntention/typeLst');
    Route::get('intention/circles', 'api.store.merchant.MerchantIntention/circles');
    Route::get('intention/business', 'api.store.merchant.MerchantIntention/business');
    //浏览
    Route::post('common/visit', 'api.Common/visit');
    Route::get('store/product/assist/count', 'api.store.product.StoreProductAssist/userCount');
    Route::get('store/expr/temps', 'admin.system.serve.Export/getExportTemp');
    //复制口令
    Route::get('command/copy', 'api.Common/getCommand');
    Route::group('discounts', function () {
        Route::get('lst', '/lst');
    })->prefix('api.store.product.Discounts');
    //test
    Route::any('store/test', 'api.Test/test');
    Route::get('subscribe', 'api.Common/subscribe');
    Route::group('points', function () {
        Route::get('home', '/home');
        Route::get('scope', '/points_mall_scope');
        Route::get('lst', '/lst');
        Route::get('detail/:id', '/detail');
    })->prefix('api.store.product.PointsProduct');
    Route::group('diy', function () {
        //普通商品详情
        Route::get('/product_detail', '/productDetail');
        //秒杀
        Route::get('/seckill', '/seckill');
        //助力
        Route::get('/assist', '/assist');
        //预售
        Route::get('/presell', '/presell');
        //拼团
        Route::get('/group', '/group');
        //商品列表
        Route::get('/spu', '/spu');
        //社区
        Route::get('/community', '/community');
        //优惠券
        Route::get('/coupon', '/coupon');
        //品牌好店
        Route::get('/store', '/store');
        //二级分类
        Route::get('/category', '/category');
        //直播
        Route::get('/broadcast', '/broadcast');
        //热卖排行
        Route::get('/hot_top', '/hot_top');
        // 悬浮按钮
        Route::get('/fab', '/fab');
        // 平台商品分类
        Route::get('/productCategory', '/productCategory');
        // 盲盒店铺
        Route::get('/blindbox', '/blindbox');
    })->prefix('api.Diy');
    Route::group('system/form', function () {
        Route::get('/lst', '/lst');
        Route::get('/detail/:id', '/detail');
        Route::get('/info/:form_id', '/getFormInfo');
        Route::get('/share_posters/:id', '/getSharePosters');
    })->prefix('api.store.form.Form');
    // 分组推荐
    Route::group('store/group', function () {
        Route::get('/recommend', '/recommendList');
        Route::get('/options', '/options');
    })->prefix('api.store.StoreGroup');
    // 用户
    Route::group('user', function () {
        // 签到数据信息获取
        Route::get('sign/info', 'UserSign/info');
    })->prefix('api.user.');

    //获取商户基本信息
    Route::get('service/info/:id', 'api.store.service.Service/merchantInfo');
    Route::get('has_service/:id', 'api.store.service.Service/hasService');
    //上传图片
    Route::post('upload/image/:field', 'api.Common/uploadImage');
    Route::post('scan_upload/image/:field/:token', 'api.Common/scanUploadImage');
    //附件上传（pdf/doc/image，最大20MB）
    Route::post('upload/attachment/:field', 'api.Common/uploadAttachment');
    //入住商户上传证件接口
    Route::post('upload/certificate/:field', 'api.Common/uploadCertificate');
    //公共配置
    Route::get('config', 'api.Common/config');
    Route::get('navigation', 'api.Common/getNavigation');
})->middleware(UserTokenMiddleware::class, false);
