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

class PayOrderDto
{
    public string $driver;
    public string $payType;
    public string $orderSn = '';
    public string $payPrice = '';
    public string $body = '';
    public string $attach = '';
    public string $returnUrl = '';
    public string $authCode = '';
    public int $wechatUserId;
    public string $openid = '';
    public string $orderId = '';
    public bool $isApp = false;
    public array $subOrders = [];
    public array $extra = [];

    public function __construct(string $driver = '', string $payType = '')
    {
        $this->driver = $driver;
        $this->payType = $payType;
        $this->wechatUserId = 0;
    }

    public static function fromArray(
        string $driver,
        string $payType,
        array $params,
        int $wechatUserId = 0,
        string $openid = '',
        string $attach = '',
        string $orderId = '',
        bool $isApp = false
    ): self {
        $dto = new self($driver, $payType);
        $dto->orderSn = (string)($params['order_sn'] ?? '');
        $dto->payPrice = isset($params['pay_price']) ? (string)$params['pay_price'] : '';
        $dto->body = (string)($params['body'] ?? '');
        $dto->attach = $attach ?: (string)($params['attach'] ?? '');
        $dto->returnUrl = (string)($params['return_url'] ?? '');
        $dto->authCode = (string)($params['auth_code'] ?? ($params['authCode'] ?? ''));
        $dto->wechatUserId = $wechatUserId;
        $dto->openid = $openid;
        $dto->orderId = $orderId;
        $dto->isApp = $isApp;
        $dto->subOrders = $params['sub_orders'] ?? [];
        $dto->extra = self::withoutReserved($params);
        return $dto;
    }

    public function toPayParams(): array
    {
        $params = $this->extra;
        $this->put($params, 'order_sn', $this->orderSn);
        $this->put($params, 'pay_price', $this->payPrice);
        $this->put($params, 'body', $this->body);
        $this->put($params, 'attach', $this->attach);
        $this->put($params, 'return_url', $this->returnUrl);

        if ($this->authCode !== '') {
            $params['auth_code'] = $this->authCode;
            $params['authCode'] = $this->authCode;
        }

        if ($this->subOrders !== []) {
            $params['sub_orders'] = $this->subOrders;
        }

        return $params;
    }

    protected static function withoutReserved(array $params): array
    {
        foreach (['order_sn', 'pay_price', 'body', 'attach', 'return_url', 'auth_code', 'authCode', 'sub_orders'] as $key) {
            unset($params[$key]);
        }
        return $params;
    }

    protected function put(array &$params, string $key, $value): void
    {
        if ($value !== '' && $value !== [] && $value !== null) {
            $params[$key] = $value;
        }
    }
}
