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
namespace crmeb\services\pay\dto;

class PayRefundDto
{
    public const REFUND_BY_OUT_TRADE_NO = 'out_trade_no';
    public const REFUND_BY_TRANSACTION_ID = 'transaction_id';
    public const CHANNEL_WEB = 'web';
    public const CHANNEL_MINI = 'mini';
    public const CHANNEL_APP = 'app';

    protected const RESERVED_KEYS = [
        'refund_id',
        'refund_no',
        'pay_price',
        'refund_price',
        'refund_message',
        'transaction_id',
        'open_id',
        'attach',
        'desc',
        'refund_by',
        'refund_identifier_type',
        'pay_type',
        'pay_channel',
        'payment_channel',
    ];

    protected const DRIVER_EXTRA_KEYS = [
        'weixin' => [
            'type',
            'out_trade_no',
            'refund_account',
            'pay_routine_open',
            'routine_order_id',
            'reason',
            'notify_url',
            'funds_account',
            'goods_detail',
        ],
        'combine' => [
            'sub_mchid',
            'order_sn',
            'refund_order_sn',
            'reason',
            'refund_account',
        ],
        'partner' => [
            'sub_mchid',
            'order_sn',
            'refund_order_sn',
            'reason',
            'refund_account',
        ],
    ];

    public string $driver;
    public string $outTradeNo;
    public string $refundNo = '';
    public string $refundId = '';
    public string $payPrice = '';
    public string $refundPrice = '';
    public string $refundMessage = '';
    public string $transactionId = '';
    public string $openId = '';
    public string $attach = '';
    public string $desc = '';
    public string $refundBy = self::REFUND_BY_OUT_TRADE_NO;
    public string $channel = self::CHANNEL_WEB;
    public array $extra = [];

    public function __construct(string $driver = '', string $outTradeNo = '')
    {
        $this->driver = $driver;
        $this->outTradeNo = $outTradeNo;
    }

    public static function fromPayType($payType, string $outTradeNo, array $options): self
    {
        return self::fromArray(self::driverByPayType($payType), $outTradeNo, $options, $payType);
    }

    public static function fromServerType($serverType, string $outTradeNo, array $options, $payType = null): self
    {
        $payType = $payType ?? ($options['pay_type'] ?? $serverType);
        return self::fromArray(self::driverByServerType($serverType), $outTradeNo, $options, $payType);
    }

    public static function fromArray(string $driver, string $outTradeNo, array $options, $payType = null): self
    {
        $dto = new self($driver, $outTradeNo);
        $dto->refundNo = (string)($options['refund_no'] ?? ($options['refund_id'] ?? ($options['refund_order_sn'] ?? '')));
        $dto->refundId = (string)($options['refund_id'] ?? $dto->refundNo);
        $dto->payPrice = isset($options['pay_price']) ? (string)$options['pay_price'] : '';
        $dto->refundPrice = isset($options['refund_price']) ? (string)$options['refund_price'] : '';
        $dto->refundMessage = (string)($options['refund_message'] ?? '');
        $dto->transactionId = (string)($options['transaction_id'] ?? '');
        $dto->openId = (string)($options['open_id'] ?? '');
        $dto->attach = (string)($options['attach'] ?? '');
        $dto->desc = (string)($options['desc'] ?? '');
        $dto->refundBy = self::resolveRefundBy($driver, $dto->transactionId, $options);
        $dto->channel = self::resolveChannel($payType, $options);
        $dto->extra = self::withoutReserved($options);
        return $dto;
    }

    public function toRefundParams(): array
    {
        $params = $this->driverExtraParams();

        $this->put($params, 'refund_id', $this->refundId ?: $this->refundNo);
        $this->put($params, 'refund_no', $this->refundNo ?: $this->refundId);
        $this->put($params, 'pay_price', $this->payPrice);
        $this->put($params, 'refund_price', $this->refundPrice);
        $this->put($params, 'refund_message', $this->refundMessage);
        if ($this->driver === 'weixin') {
            $this->put($params, 'type', $this->refundBy);
            if ($this->refundBy === self::REFUND_BY_TRANSACTION_ID) {
                unset($params['out_trade_no']);
                $this->put($params, 'transaction_id', $this->transactionId);
            } else {
                $this->put($params, 'out_trade_no', $this->outTradeNo);
            }
        }
        $this->put($params, 'open_id', $this->openId);
        $this->put($params, 'attach', $this->attach);
        $this->put($params, 'desc', $this->desc);

        if (in_array($this->driver, ['combine', 'partner'], true)) {
            if (!isset($params['order_sn'])) {
                $this->put($params, 'order_sn', $this->outTradeNo);
            }
            if (!isset($params['refund_order_sn'])) {
                $this->put($params, 'refund_order_sn', $this->refundNo ?: $this->refundId);
            }
        }

        return $params;
    }

    public function refundOrderNo(): string
    {
        if ($this->driver === 'weixin' && $this->refundBy === self::REFUND_BY_TRANSACTION_ID) {
            return $this->transactionId;
        }

        if (isset($this->extra['out_trade_no']) && $this->extra['out_trade_no'] !== '') {
            return (string)$this->extra['out_trade_no'];
        }

        return $this->outTradeNo;
    }

    public function isRefundByTransactionId(): bool
    {
        return $this->driver === 'weixin' && $this->refundBy === self::REFUND_BY_TRANSACTION_ID;
    }

    public static function driverByPayType($payType): string
    {
        $payType = (string)$payType;
        if (in_array($payType, ['alipay', 'alipayQr', 'alipayApp', 'alipayBarCode', '4', '5', '9'], true)) {
            return 'alipay';
        }
        if (in_array($payType, ['weixin', 'wechat', 'routine', 'h5', 'weixinQr', 'weixinApp', 'weixinBarCode', '1', '2', '3', '6', '8'], true)) {
            return 'weixin';
        }

        throw new \InvalidArgumentException('不支持的退款支付方式');
    }

    public static function driverByServerType($serverType): string
    {
        $serverType = (string)$serverType;
        if ($serverType === '22') {
            return 'partner';
        }
        if ($serverType === '21') {
            return 'combine';
        }

        return self::driverByPayType($serverType);
    }

    protected static function resolveChannel($payType, array $options): string
    {
        $channel = (string)($options['payment_channel'] ?? ($options['pay_channel'] ?? ''));
        if (in_array($channel, [self::CHANNEL_WEB, self::CHANNEL_MINI, self::CHANNEL_APP], true)) {
            return $channel;
        }

        $payType = (string)($payType ?? ($options['pay_type'] ?? ''));
        if (in_array($payType, ['2', 'routine'], true)) {
            return self::CHANNEL_MINI;
        }
        if (in_array($payType, ['weixinApp', 'alipayApp'], true)) {
            return self::CHANNEL_APP;
        }
        return self::CHANNEL_WEB;
    }

    protected static function withoutReserved(array $options): array
    {
        foreach (self::RESERVED_KEYS as $key) {
            unset($options[$key]);
        }
        return $options;
    }

    protected static function resolveRefundBy(string $driver, string $transactionId, array $options): string
    {
        $refundBy = (string)($options['refund_by'] ?? ($options['refund_identifier_type'] ?? ($options['type'] ?? '')));
        if (!in_array($refundBy, [self::REFUND_BY_OUT_TRADE_NO, self::REFUND_BY_TRANSACTION_ID], true)) {
            $refundBy = $driver === 'weixin' && $transactionId !== '' ? self::REFUND_BY_TRANSACTION_ID : self::REFUND_BY_OUT_TRADE_NO;
        }

        if ($driver !== 'weixin') {
            return self::REFUND_BY_OUT_TRADE_NO;
        }

        return $refundBy;
    }

    protected function driverExtraParams(): array
    {
        $params = [];
        foreach (self::DRIVER_EXTRA_KEYS[$this->driver] ?? [] as $key) {
            if (array_key_exists($key, $this->extra)) {
                $this->put($params, $key, $this->extra[$key]);
            }
        }
        return $params;
    }

    protected function put(array &$params, string $key, $value): void
    {
        if ($value !== '' && $value !== [] && $value !== null) {
            $params[$key] = self::normalizeValue($value);
        }
    }

    protected static function normalizeValue($value)
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = self::normalizeValue($item);
            }
            return $value;
        }

        if (!is_string($value) || $value === '' || preg_match('//u', $value) === 1) {
            return $value;
        }

        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8,GBK,GB2312,BIG5');
            if (is_string($converted) && preg_match('//u', $converted) === 1) {
                return $converted;
            }
        }

        $json = json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE);
        return is_string($json) ? (json_decode($json, true) ?: '') : '';
    }
}
