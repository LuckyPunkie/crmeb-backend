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

Route::post('upload/video', 'admin.system.attachment.Attachment/uploadVideo');
Route::get('excel/download/:id', 'merchant.store.order.Order/download');

//微信支付回调:微信 服务商 收付通
Route::any('notice/:type', 'api.Common/notify')->name('wechatNotify');
//企业付款到零钱回调
Route::any('notice/mchNotify/:type', 'api.Common/mchNotify')->name('mchNotify');
//支付宝支付回调
Route::any('notice/pay/alipay', 'api.Common/alipayNotify')->name('alipayNotify');

Route::any('notice/callback', 'api.Common/deliveryNotify');
Route::any('order_call_back', 'api.Common/callBackNotify')->name('mchNotify');

//城市列表
Route::get('system/city/lst', 'merchant.store.shipping.City/getlist');
Route::get('v2/system/city/lst/:pid', 'merchant.store.shipping.City/lstV2');
Route::get('v2/system/city', 'merchant.store.shipping.City/cityList');

//热门搜索
Route::get('common/hot_keyword', 'api.Common/hotKeyword')->append(['type' => 0]);
//社区热门搜索
Route::get('common/commuunity/hot_keyword', 'api.Common/hotKeyword')->append(['type' => 1]);
//推荐页 banner
Route::get('common/hot_banner/:type', 'api.Common/hotBanner');
Route::get('common/pay_success_banner', 'api.Common/paySuccessBanner');
//退款原因
Route::get('common/refund_message', 'api.Common/refundMessage');
//充值赠送
Route::get('common/recharge_quota', 'api.Common/userRechargeQuota');
//快递公司
Route::get('common/express', 'api.Common/express');
//图片转 base64
Route::post('common/base64', 'api.Common/get_image_base64');
//个人中心菜单
Route::get('common/menus', 'api.Common/menus');
//首页数据
Route::get('common/home', 'api.Common/home');
//经纬度转位置信息
Route::get('lbs/geocoder', 'api.Common/lbs_geocoder');
//位置信息转经纬度
Route::get('lbs/address', 'api.Common/lbs_address');
//获取支付宝支付链接
Route::get('common/pay_key/:key', 'api.Common/pay_key');
//用户反馈类型
Route::get('common/feedback_type', 'api.user.FeedBackCategory/lst');
//登录
Route::post('auth/login', 'api.Auth/login');
//获取小程序登录是否需绑定手机号处理
Route::post('auth/mp_login_type', 'api.Auth/mpLoginType');
//登录
Route::post('auth', 'api.Auth/authLogin');
//短信登录
Route::post('auth/smslogin', 'api.Auth/smsLogin');
//注册
Route::post('auth/register', 'api.Auth/register');
//小程序手机号注册
Route::post('auth/mp_phone', 'api.Auth/mpPhone');
//微信授权
Route::get('auth/wechat', 'api.Auth/auth');
//小程序授权
Route::post('auth/mp', 'api.Auth/mpAuth');
//app授权
Route::post('auth/app', 'api.Auth/appAuth');
//apple授权
Route::post('auth/apple', 'api.Auth/appleAuth');
//修改密码
Route::post('user/change_pwd', 'api.Auth/changePassword');
//验证码
Route::post('auth/verify', 'api.Auth/verify');
//微信配置
Route::get('wechat/config', 'api.Wechat/jsConfig');
//图片验证码
Route::get('captcha', 'api.Auth/getCaptcha');
//获取协议列表
Route::get('agreement_lst', 'admin.system.Cache/getKeyLst')->append(['type' => 1]);
//获取协议内容
Route::get('agreement/:key', 'admin.system.Cache/getAgree');

Route::get('copyright', 'api.Common/copyright');

Route::get('script', 'api.Common/script');
Route::get('appVersion', 'api.Common/appVersion');
Route::get('micro', 'api.Common/micro');
Route::get('version', 'admin.Common/version');
Route::any('getVersion', 'api.Common/getVersion')->name('getVersion');

Route::get('diy', 'api.Common/diy');
//滑块验证码
Route::get('ajcaptcha', 'api.Auth/ajcaptcha');
Route::post('ajcheck', 'api.Auth/ajcheck');

Route::get('open_screen', 'api.Common/open_screen');

//可以查看所有路由的接口 - 开发者使用
Route::get('route/list', 'api.Route/list');
