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

namespace app\common\model\store\product;

use app\common\model\BaseModel;
use app\common\model\store\order\StoreOrder;
use app\common\model\system\merchant\Merchant;
use app\common\model\store\service\StoreService;
use app\common\model\system\merchant\MerchantAdmin;

class StoreUserCardVerifyLog extends BaseModel
{
    public const VERIFY_TYPE_MERCHANT = 1;
    public const VERIFY_TYPE_SERVICE = 2;
    public const VERIFY_TYPE_SYSTEM = 3;
    public const VERIFY_TYPES = [
        self::VERIFY_TYPE_MERCHANT,
        self::VERIFY_TYPE_SERVICE,
        self::VERIFY_TYPE_SYSTEM,
    ];

    public static function tablePk(): ?string
    {
        return 'verify_log_id';
    }

    public static function tableName(): string
    {
        return 'store_user_card_verify_log';
    }

    public function service()
    {
        return$this->hasOne(StoreService::class,'service_id','service_id');
    }

    public function adminInfo()
    {
        return $this->hasOne(MerchantAdmin::class,'merchant_admin_id','admin_id');
    }

    public function userCard()
    {
        return $this->hasOne(StoreUserCard::class, 'user_card_id', 'user_card_id');
    }

    public function orderInfo()
    {
        return $this->hasOne(StoreOrder::class,'order_id','order_id');
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class,'mer_id','mer_id');
    }
}
