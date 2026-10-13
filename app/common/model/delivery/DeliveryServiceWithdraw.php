<?php

namespace app\common\model\delivery;

use app\common\model\BaseModel;
use app\common\model\system\admin\Admin;
use app\common\model\user\User;

class DeliveryServiceWithdraw extends BaseModel
{
    public const STATUS_CANCELLED = -2;
    public const STATUS_REJECTED = -1;
    public const STATUS_PENDING = 0;
    public const STATUS_TO_PAY = 1;
    public const STATUS_PAID = 2;

    public const STATUS_TEXT = [
        self::STATUS_PENDING => '待审核',
        self::STATUS_TO_PAY => '已审核',
        self::STATUS_PAID => '已打款',
        self::STATUS_REJECTED => '已拒绝',
        self::STATUS_CANCELLED => '已取消',
    ];

    public const PAYMENT_METHOD_BANK = 0;
    public const PAYMENT_METHOD_WECHAT = 1;
    public const PAYMENT_METHOD_ALIPAY = 2;
    public const PAYMENT_METHOD_WECHAT_BALANCE = 3;
    public const PAYMENT_METHOD_USER_BALANCE = 4;

    public const PAYMENT_METHOD_TEXT = [
        self::PAYMENT_METHOD_BANK => '银行卡',
        self::PAYMENT_METHOD_WECHAT => '微信',
        self::PAYMENT_METHOD_ALIPAY => '支付宝',
        self::PAYMENT_METHOD_WECHAT_BALANCE => '微信转账到零钱',
        self::PAYMENT_METHOD_USER_BALANCE => '转入用户余额',
    ];

    protected $updateTime = 'update_time';

    public static function tablePk(): string
    {
        return 'delivery_service_withdraw_id';
    }

    public static function tableName(): string
    {
        return 'delivery_service_withdraw';
    }

    public function setPaymentInfoAttr($value)
    {
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;
    }

    public function getPaymentInfoAttr($value)
    {
        if (is_array($value)) {
            return $value;
        }
        $paymentInfo = json_decode((string)$value, true);
        return json_last_error() === JSON_ERROR_NONE ? $paymentInfo : $value;
    }

    public function setPaymentVouchersAttr($value): string
    {
        return is_array($value) ? json_encode(array_values($value), JSON_UNESCAPED_UNICODE) : (string)$value;
    }

    public function getPaymentVouchersAttr($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $vouchers = json_decode((string)$value, true);
        return is_array($vouchers) ? $vouchers : [];
    }

    public function auditAdmin()
    {
        return $this->hasOne(Admin::class, 'admin_id', 'audit_admin_id')
            ->field('admin_id,real_name,account');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'uid', 'uid')
            ->field('uid,nickname,phone,avatar');
    }
}
