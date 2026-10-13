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
namespace crmeb\services\pay;

use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\pay\dto\PayOrderDto;
use crmeb\services\pay\dto\PayRefundDto;
use think\exception\ValidateException;

class PayServices
{
    protected const DRIVER_PAY_TYPES = [
        'weixin' => ['weixin', 'routine', 'h5', 'weixinQr', 'weixinApp', 'weixinBarCode'],
        'alipay' => ['alipay', 'alipayQr', 'alipayApp', 'alipayBarCode'],
        'combine' => ['weixin', 'routine', 'h5', 'weixinQr', 'weixinApp', 'weixinBarCode'],
        'partner' => ['weixin', 'routine', 'h5', 'weixinQr', 'weixinApp', 'weixinBarCode'],
    ];

    protected const REFUND_DRIVERS = ['weixin', 'alipay', 'combine', 'partner'];

    protected const OPENID_PAY_TYPES = ['weixin', 'routine'];

    protected const REFUND_CHANNELS = ['web', 'mini', 'app'];

    public function pay(PayOrderDto $dto): array
    {
        $this->validatePayDto($dto);
        return (new Pay($dto->driver))->pay(
            $dto->payType,
            $dto->toPayParams(),
            $this->resolveOpenid($dto)
        );
    }

    public function refund(PayRefundDto $dto): array
    {
        try {
            $this->validateRefundDto($dto);
            return (new Pay($dto->driver))->payOrderRefund(
                $dto->refundOrderNo(),
                $dto->toRefundParams(),
                $dto->channel
            );
        } catch (\Throwable $e) {
            if ($e instanceof ValidateException && $this->isSafeExceptionMessage($e->getMessage())) {
                throw $e;
            }
            throw new ValidateException($this->safeExceptionMessage($e, '退款失败'));
        }
    }

    /**
     * @deprecated Use refund(PayRefundDto::fromPayType(...)) instead.
     */
    public function refundByPayType($payType, string $outTradeNo, array $options): array
    {
        return $this->refund(PayRefundDto::fromPayType($payType, $outTradeNo, $options));
    }

    /**
     * @deprecated Use refund(PayRefundDto::fromServerType(...)) instead.
     */
    public function refundByServerType($serverType, string $outTradeNo, array $options, $payType = null): array
    {
        return $this->refund(PayRefundDto::fromServerType($serverType, $outTradeNo, $options, $payType));
    }

    protected function resolveOpenid(PayOrderDto $dto): string
    {
        if ($dto->openid !== '') {
            return $dto->openid;
        }

        if (!in_array($dto->payType, self::OPENID_PAY_TYPES, true)) {
            return '';
        }

        if (!$dto->wechatUserId) {
            throw new ValidateException('支付用户微信信息缺失');
        }

        $wechatUserRepository = app()->make(WechatUserRepository::class);
        if ($dto->payType === 'weixin') {
            $openid = $wechatUserRepository->idByOpenId($dto->wechatUserId);
            if (!$openid) {
                throw new ValidateException('请关联微信公众号!');
            }
            return $openid;
        }

        if ($dto->payType === 'routine') {
            $openid = $wechatUserRepository->idByRoutineId($dto->wechatUserId);
            if (!$openid) {
                throw new ValidateException('请关联微信小程序!');
            }
            return $openid;
        }

        return '';
    }

    protected function validatePayDto(PayOrderDto $dto): void
    {
        if ($dto->driver === '' || $dto->payType === '') {
            throw new ValidateException('支付驱动或支付方式不能为空');
        }
        if (!isset(self::DRIVER_PAY_TYPES[$dto->driver])) {
            throw new ValidateException('不支持的支付驱动');
        }
        if (!in_array($dto->payType, self::DRIVER_PAY_TYPES[$dto->driver], true)) {
            throw new ValidateException('支付方式与支付驱动不匹配');
        }
        if ($dto->orderSn === '') {
            throw new ValidateException('支付订单号不能为空');
        }
        if ($dto->body === '') {
            throw new ValidateException('支付订单描述不能为空');
        }
        if ($dto->attach === '') {
            throw new ValidateException('支付业务标识不能为空');
        }
        if ($dto->payPrice === '' && $dto->subOrders === []) {
            throw new ValidateException('支付金额不能为空');
        }
        if ($dto->payPrice !== '') {
            $this->validatePositiveAmount($dto->payPrice, '支付金额格式错误');
        }
        foreach ($dto->subOrders as $subOrder) {
            if (!is_array($subOrder) || empty($subOrder['order_sn'])) {
                throw new ValidateException('子订单号不能为空');
            }
            $this->validatePositiveAmount((string)($subOrder['pay_price'] ?? ''), '子订单支付金额格式错误');
        }
        if (in_array($dto->payType, self::OPENID_PAY_TYPES, true) && $dto->openid === '' && !$dto->wechatUserId) {
            throw new ValidateException('支付用户微信信息缺失');
        }
    }

    protected function validateRefundDto(PayRefundDto $dto): void
    {
        if ($dto->driver === '') {
            throw new ValidateException('退款驱动不能为空');
        }
        if (!in_array($dto->driver, self::REFUND_DRIVERS, true)) {
            throw new ValidateException('不支持的退款驱动');
        }
        if (!in_array($dto->channel, self::REFUND_CHANNELS, true)) {
            throw new ValidateException('不支持的退款渠道');
        }
        if ($dto->refundOrderNo() === '') {
            throw new ValidateException('退款原订单号不能为空');
        }
        if ($dto->refundNo === '' && $dto->refundId === '') {
            throw new ValidateException('退款单号不能为空');
        }
        if ($dto->isRefundByTransactionId() && $dto->transactionId === '') {
            throw new ValidateException('微信支付单号不能为空');
        }
        if ($dto->payPrice === '') {
            throw new ValidateException('原订单支付金额不能为空');
        }
        if ($dto->refundPrice === '') {
            throw new ValidateException('退款金额不能为空');
        }
        $this->validatePositiveAmount($dto->payPrice, '原订单支付金额格式错误');
        $this->validatePositiveAmount($dto->refundPrice, '退款金额格式错误');
        if (bccomp($dto->refundPrice, $dto->payPrice, 2) === 1) {
            throw new ValidateException('退款金额不能超过原订单支付金额');
        }
    }

    protected function validatePositiveAmount(string $amount, string $message): void
    {
        if (preg_match('/^\d+(?:\.\d{1,2})?$/', $amount) !== 1 || bccomp($amount, '0', 2) !== 1) {
            throw new ValidateException($message);
        }
    }

    protected function safeExceptionMessage(\Throwable $e, string $default): string
    {
        $message = $e->getMessage();
        return $this->isSafeExceptionMessage($message) ? $message : $default;
    }

    protected function isSafeExceptionMessage(string $message): bool
    {
        if ($message === '' || preg_match('//u', $message) !== 1) {
            return false;
        }
        return stripos($message, 'Malformed UTF-8') === false;
    }
}
