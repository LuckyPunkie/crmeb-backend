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


return [
    //token 有效期
    'token_exp' => 24, //6小时
    //token超时多久可自动续期(后台)
    'token_valid_exp' => 120, //30分钟
    //token超时多久可自动续期(用户)
    'user_token_valid_exp' => 365, //7天
    'openapi_token_valid_exp' => 1, //小时
    //登录验证码有效期
    'captcha_exp' => 30, //30分钟
    'admin_prefix' => 'admin',
    //代理
    'agent_prefix' => 'agent',
    //商户/商圈
    'circle_prefix' => 'circle',
    //店铺后台路径
    'merchant_prefix' => 'merchant',
    //客服后台路径
    'service_prefix' => 'kefu',

    //平台接口前缀
    'api_admin_prefix' => 'sys',
    //店铺接口前缀
    'api_merchant_prefix' => 'mer',
    //客服接口前缀
    'api_service_prefix' => 'ser',
    //开放接口前缀
    'api_openapi_prefix' => 'openapi',
    'vic_word_status' => 0,
];
