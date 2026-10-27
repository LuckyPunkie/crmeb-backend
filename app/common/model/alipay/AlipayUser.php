<?php

namespace app\common\model\alipay;

use app\common\model\BaseModel;

/**
 * 支付宝用户身份（对标 WechatUser）
 */
class AlipayUser extends BaseModel
{
    public static function tablePk(): string
    {
        return 'alipay_user_id';
    }

    public static function tableName(): string
    {
        return 'alipay_user';
    }
}
