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

namespace app\validate\merchant;

use think\Validate;

class MerchantWeworkGroupValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'group_name|群名称' => 'max:50',
        'group_last_msg|最新消息' => 'max:100',
        'qrcode_url|入群二维码' => 'max:500',
        'group_avatar|群头像' => 'max:500',
        'branch_id|分店ID' => 'integer|>=:0',
        'status|状态' => 'in:0,1',
    ];
}
