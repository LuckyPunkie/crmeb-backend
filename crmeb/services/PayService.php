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


namespace crmeb\services;


use app\common\model\user\User;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\services\pay\Pay;
use crmeb\services\wechat\Payment;
use think\exception\ValidateException;
use think\facade\Cache;

class PayService
{
    protected $type;
    protected $options;
    protected $affect;

    public function __construct(string $type, array $options, string $affect = 'order')
    {
        $this->type = $type;
        $this->affect = $affect;
        $this->options = $options;
    }

    public function pay(?User $user)
    {
        $method = 'pay' . ucfirst($this->type);
        if (!method_exists($this, $method)) {
            throw new ValidateException('不支持该支付方式');
        }
        return $this->{$method}($user);
    }

    public function payWeixin(User $user)
    {
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $openId = $wechatUserRepository->idByOpenId($user['wechat_user_id']);
        if (!$openId)
            throw new ValidateException('请关联微信公众号!');
        $services = Payment::instance()->setAccessEnd(Payment::WEB);
        if ($services->isV3Pay()) {
            $config = $services->payClient()->jsapiPay(
                $openId,
                $this->options['order_sn'],
                $this->options['pay_price'],
                $this->options['body'],
                $this->options['attach']
            );
        } else {
            $config = $services->jsPay(
                $openId,
                $this->options['order_sn'],
                $this->options['pay_price'],
                $this->options['attach'],
                $this->options['body']
            );
        }
        return compact('config');
    }

    public function payWeixinQr(?User $user)
    {
        $services = Payment::instance()->setAccessEnd(Payment::WEB);
        if ($services->isV3Pay()) {
            $config = $services->payClient()->nativePay(
                $this->options['order_sn'],
                $this->options['pay_price'],
                $this->options['body'],
                $this->options['attach']
            );
            $config = $config['code_url'] ?? '';
        } else {
            $config = $services->paymentOrder(
                '',
                $this->options['order_sn'],
                $this->options['pay_price'],
                $this->options['attach'],
                $this->options['body'],
                '',
                'NATIVE'
            )['code_url'] ?? '';
        }
        return ['config' => $config, 'time_expire' => time() + (15 * 60)];
    }

    public function payRoutine(User $user)
    {
        $wechatUserRepository = app()->make(WechatUserRepository::class);
        $openId = $wechatUserRepository->idByRoutineId($user['wechat_user_id']);
        if (!$openId)
            throw new ValidateException('请关联微信小程序!');
        $services = Payment::instance()->setAccessEnd(Payment::MINI);
        if (systemConfig('pay_routine_new_mchid')) {
            $config = $services->miniPay(
                $openId,
                $this->options['order_sn'],
                $this->options['pay_price'],
                $this->options['attach'],
                $this->options['body']
            );
        } elseif ($services->isV3Pay()) {
            $config = $services->payClient()->miniprogPay(
                $openId,
                $this->options['order_sn'],
                $this->options['pay_price'],
                $this->options['body'],
                $this->options['attach']
            );
        } else {
            $config = $services->jsPay(
                $openId,
                $this->options['order_sn'],
                $this->options['pay_price'],
                $this->options['attach'],
                $this->options['body']
            );
        }
        return compact('config');
    }

    public function payH5(User $user)
    {
        $services = Payment::instance()->setAccessEnd(Payment::WEB);
        if ($services->isV3Pay()) {
            $config = $services->payClient()->h5Pay(
                $this->options['order_sn'],
                $this->options['pay_price'],
                $this->options['body'],
                $this->options['attach']
            );
        } else {
            $config = $services->paymentOrder(
                null,
                $this->options['order_sn'],
                $this->options['pay_price'],
                $this->options['attach'],
                $this->options['body'],
                '',
                'MWEB'
            );
        }
        return compact('config');
    }

    /** APP 微信支付不依赖 openid，允许游客买单 */
    public function payWeixinApp(?User $user = null)
    {
        $services = Payment::instance()->setAccessEnd(Payment::APP);
        if ($services->isV3Pay()) {
            $config = $services->payClient()->appPay(
                $this->options['order_sn'],
                $this->options['pay_price'],
                $this->options['body'],
                $this->options['attach']
            );
        } else {
            $config = $services->appPay(
                null,
                $this->options['order_sn'],
                $this->options['pay_price'],
                $this->options['attach'],
                $this->options['body']
            );
        }
        return compact('config');
    }

    public function payAlipay(?User $user = null)
    {
        return (new Pay('alipay'))->pay('alipay', $this->getAlipayOrder(), '');
    }

    public function payAlipayQr(? User $user)
    {
        return (new Pay('alipay'))->pay('alipayQr', $this->getAlipayOrder(), '');
    }

    /** APP 支付宝支付允许游客买单 */
    public function payAlipayApp(?User $user = null)
    {
        return (new Pay('alipay'))->pay('alipayApp', $this->getAlipayOrder(), '');
    }

    public function payWeixinBarCode()
    {
        $config = Payment::microPay(
            $this->options['auth_code'],
            $this->options['order_sn'],
            $this->options['pay_price'],
            $this->options['attach'],
            $this->options['body']
        );
        if (!($config['paid'] ?? 0)) {
            if (($config['payInfo']['err_code'] ?? '') === 'USERPAYING') {
                $redis = Cache::store('redis')->handler();
                $redis->hSet('bar_code_pay', $this->options['order_sn'], $this->type);
                throw new ValidateException($config['message'] ?? '用户支付中');
            }
            throw new ValidateException('微信扫码枪支付错误返回：' . ($config['message'] ?? '支付失败'));
        }
        return $config;
    }

    public function payAlipayBarCode()
    {
        return (new Pay('alipay'))->pay('alipayBarCode', $this->getAlipayOrder(), '');
    }

    protected function getAlipayOrder(): array
    {
        return [
            'order_sn' => $this->options['order_sn'],
            'pay_price' => $this->options['pay_price'],
            'body' => $this->options['body'],
            'attach' => $this->options['attach'] ?? $this->affect,
            'return_url' => $this->options['return_url'] ?? '',
            'auth_code' => $this->options['auth_code'] ?? '',
        ];
    }
}
