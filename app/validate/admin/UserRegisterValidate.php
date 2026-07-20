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


namespace app\validate\admin;


use think\Validate;

class UserRegisterValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'is_phone_login|商城用户强制手机号登录(绑定)' => 'require|in:0,1',
        'open_update_info|用户修改头像和昵称' => 'require|in:0,1',
        'first_avatar_switch|小程序首次登录获取头像昵称' => 'require|in:0,1',
        'wechat_phone_switch|手机号快速验证组件' => 'require|in:0,1',
        'newcomer_status|注册有礼启用' => 'require|in:0,1',
        'register_give_integral|注册赠送积分' => 'require|min:0|number',
        'register_give_money|赠送余额' => 'float|min:0',
    ];
}
