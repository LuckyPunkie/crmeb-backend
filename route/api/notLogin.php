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
//微信公众号回调
Route::any('wechat/serve', [app\controller\WechatNotice::class, 'serve']);
// 支付回调
Route::group('notice', function () {
    // 兼容 v4.0 的支付宝回调地址（升级前创建、升级后才付款的订单仍回调旧地址），须放在 pay/:type 之前
    Route::any('pay/alipay', [app\controller\api\Common::class, 'alipayNotify']);
    //微信支付回调:微信 服务商 收付通
    Route::any('pay/:type', [app\controller\api\Common::class, 'notify']);
    //支付宝支付回调
    Route::any('alipay', [app\controller\api\Common::class, 'alipayNotify'])->name('alipayNotify');
    //同城配送回调
    Route::any('callback', [app\controller\api\Common::class, 'deliveryNotify']);
    // 兼容 v4.0 的微信支付回调地址 notice/{wechat|routine|partner|app...}，须放在最后
    Route::any(':type', [app\controller\api\Common::class, 'notify']);
});

// 微信退款结果回调
Route::any('pay/refund/:type', [app\controller\api\Common::class, 'refundNotify']);


Route::post('upload/video', [app\controller\admin\system\attachment\Attachment::class, 'uploadVideo']);

// 直传 OSS：上传完成回调（OSS RSA 验签）、IMM 转码完成通知（MNS HTTP 推送验签）
Route::post('oss/callback', [app\controller\api\OssUpload::class, 'callback']);
Route::any('oss/imm-notify', [app\controller\api\OssUpload::class, 'immNotify']);
Route::get('excel/download/:id', [app\controller\merchant\store\order\Order::class, 'download']);

//企业付款到零钱回调
Route::any('notice/mchNotify/:type', [app\controller\api\Common::class, 'mchNotify'])->name('mchNotify');
//小程序消息推送（内容安全异步检测结果等），不能挂在 notice/ 下，否则会被 notice/:type 截走
Route::any('routine/msg_push', 'WechatNotice/routineServe');

Route::any('order_call_back', [app\controller\api\Common::class, 'callBackNotify'])->name('mchNotify');
//城市列表
Route::get('system/city/lst', [app\controller\merchant\store\shipping\City::class, 'getlist']);
Route::get('v2/system/city/lst/:pid', [app\controller\merchant\store\shipping\City::class, 'lstV2']);
Route::get('v2/system/city', [app\controller\merchant\store\shipping\City::class, 'cityList']);

//热门搜索
Route::get('common/hot_keyword', [app\controller\api\Common::class, 'hotKeyword'])->append(['type' => 0]);
//社区热门搜索
Route::get('common/commuunity/hot_keyword', [app\controller\api\Common::class, 'hotKeyword'])->append(['type' => 1]);
//推荐页 banner
Route::get('common/hot_banner/:type', [app\controller\api\Common::class, 'hotBanner']);
Route::get('common/pay_success_banner', 'api.Common/paySuccessBanner');
//退款原因
Route::get('common/refund_message', [app\controller\api\Common::class, 'refundMessage']);
//充值赠送
Route::get('common/recharge_quota', [app\controller\api\Common::class, 'userRechargeQuota']);
//快递公司
Route::get('common/express', [app\controller\api\Common::class, 'express']);
//图片转 base64
Route::post('common/base64', [app\controller\api\Common::class, 'get_image_base64']);
//个人中心菜单
Route::get('common/menus', [app\controller\api\Common::class, 'menus']);
//首页数据
Route::get('common/home', [app\controller\api\Common::class, 'home']);
//经纬度转位置信息
Route::get('lbs/geocoder', [app\controller\api\Common::class, 'lbs_geocoder']);
//经纬度转位置信息（天地图）
Route::get('lbs/tianditu_geocoder', 'api.Common/tianditu_geocoder');
//地点搜索（天地图）
Route::get('lbs/tianditu_suggestion', 'api.Common/tianditu_suggestion');
Route::get('lbs/tianditu_city_center', 'api.Common/tianditu_city_center');
//位置信息转经纬度
Route::get('lbs/address', [app\controller\api\Common::class, 'lbs_address']);
//地点输入提示（多 POI，可按城市限制）
Route::get('lbs/suggestion', 'api.Common/lbs_suggestion');
//获取支付宝支付链接
Route::get('common/pay_key/:key', [app\controller\api\Common::class, 'pay_key']);
//用户反馈类型
Route::get('common/feedback_type', [app\controller\api\user\FeedBackCategory::class, 'lst']);
//交友资料字段选项（运营后台可编辑；筛选面板/资料页读取）
Route::get('user/profile/field_options', 'api.user.UserProfile/fieldOptions');
Route::get('user/profile/filter_schema', 'api.user.UserProfile/filterSchema');
//找人查询页：banner + 套餐 + 会员状态（登录可选，未登录只返回 banner / 套餐）
Route::group(function () {
    Route::get('user/find_me/pay_info', 'api.user.UserProfile/findMePayInfo');
})->middleware(\app\common\middleware\UserTokenMiddleware::class, false);
//登录
Route::post('auth/login', [app\controller\api\Auth::class, 'login']);
//获取小程序登录是否需绑定手机号处理
Route::post('auth/mp_login_type', [app\controller\api\Auth::class, 'mpLoginType']);
//登录
Route::post('auth', [app\controller\api\Auth::class, 'authLogin']);
//短信登录
Route::post('auth/smslogin', [app\controller\api\Auth::class, 'smsLogin']);
//注册
Route::post('auth/register', [app\controller\api\Auth::class, 'register']);
//小程序手机号注册
Route::post('auth/mp_phone', [app\controller\api\Auth::class, 'mpPhone']);
//微信授权
Route::get('auth/wechat', [app\controller\api\Auth::class, 'auth']);
//小程序授权
Route::post('auth/mp', [app\controller\api\Auth::class, 'mpAuth']);
//app授权
Route::post('auth/app', [app\controller\api\Auth::class, 'appAuth']);
//apple授权
Route::post('auth/apple', [app\controller\api\Auth::class, 'appleAuth']);
//支付宝小程序授权
Route::post('auth/alipay_mini', [app\controller\api\Auth::class, 'alipayMiniAuth']);
//支付宝小程序登录前检查是否需绑手机号
Route::post('auth/alipay_mini_type', [app\controller\api\Auth::class, 'alipayMiniLoginType']);
//支付宝小程序手机号登录
Route::post('auth/alipay_mini_phone', [app\controller\api\Auth::class, 'alipayMiniPhone']);
//Sign in with Apple 服务器通知（填到苹果后台 Server-to-Server Notification Endpoint）
Route::post('apple/notify', 'api.AppleNotify/notify');
//修改密码
Route::post('user/change_pwd', [app\controller\api\Auth::class, 'changePassword']);
//验证码
Route::post('auth/verify', [app\controller\api\Auth::class, 'verify']);
//微信配置
Route::get('wechat/config', [app\controller\api\Wechat::class, 'jsConfig']);
//图片验证码
Route::get('captcha', [app\controller\api\Auth::class, 'getCaptcha']);
//获取协议列表
Route::get('agreement_lst', [app\controller\admin\system\Cache::class, 'getKeyLst'])->append(['type' => 1]);
//获取协议内容
Route::get('agreement/:key', [app\controller\admin\system\Cache::class, 'getAgree']);

Route::get('copyright', [app\controller\api\Common::class, 'copyright']);

Route::get('script', [app\controller\api\Common::class, 'script']);
Route::get('appVersion', [app\controller\api\Common::class, 'appVersion']);
Route::get('micro', [app\controller\api\Common::class, 'micro']);
Route::get('version', [app\controller\admin\Common::class, 'version']);
Route::any('getVersion', [app\controller\api\Common::class, 'getVersion'])->name('getVersion');

Route::get('diy', [app\controller\api\Common::class, 'diy']);
//滑块验证码
Route::get('ajcaptcha', [app\controller\api\Auth::class, 'mobileAjcaptcha']);
Route::post('ajcheck', [app\controller\api\Auth::class, 'mobileAjcheck']);

Route::get('open_screen', [app\controller\api\Common::class, 'open_screen']);

//可以查看所有路由的接口 - 开发者使用
Route::get('route/list', [app\controller\api\Route::class, 'list']);
Route::any('store/test', [app\controller\api\Test::class, 'test']);
