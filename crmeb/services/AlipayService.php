<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services;

use app\common\repositories\alipay\AlipayUserRepository;
use crmeb\utils\Hook;
use think\facade\Event;
use think\facade\Log;
use think\facade\Route;
use think\facade\Route as Url;
use Alipay\EasySDK\Kernel\Config;
use Alipay\EasySDK\Kernel\Factory;
use crmeb\exceptions\PayException;
use app\services\pay\PayNotifyServices;
use Alipay\EasySDK\Kernel\Util\ResponseChecker;
use AlibabaCloud\Tea\Tea;

/**
 * Class AliPayService
 * @package crmeb\services
 */
class AliPayService
{

    /**
     * 配置
     * @var array
     */
    protected $config = [
        'appId' => '',
        'merchantPrivateKey' => '',//应用私钥
        'alipayPublicKey' => '',//支付宝公钥
        'notifyUrl' => '',//可设置异步通知接收服务地址
        'encryptKey' => '',//可设置AES密钥，调用AES加解密相关接口时需要（可选）
    ];

    /**
     * @var ResponseChecker
     */
    protected $response;

    /**
     * 付款码
     * @var string
     */
    protected ?string $authCode = null;

    /**
     * AliPayService constructor.
     * @param array $config
     */
    protected function __construct(array $config = [])
    {
        if (!$config) {
            $alipay = systemConfig(['site_url', 'alipay_app_id', 'alipay_public_key', 'alipay_private_key', 'alipay_open','site_url']);
            $siteUrl = $alipay['site_url'];
            $config = [
                'appId' => $alipay['alipay_app_id'] ?? '',
                'merchantPrivateKey' => $alipay['alipay_private_key'] ?? '',
                'alipayPublicKey' => $alipay['alipay_public_key'] ?? '',
                'notifyUrl' => rtrim($siteUrl, '/') . Route::buildUrl('alipayNotify')->build(),
            ];
        }
        $this->config = array_merge($this->config, $config);
        $this->initialize();
        $this->response = new ResponseChecker();
    }

    /**
     * 实例化
     * @param array $config
     * @return static
     */
    public static function make(array $config = []): self
    {
        return new static($config);
    }

    /**
     * 支付宝小程序实例：用后台「支付宝小程序支付配置」tab 的 app_id / 支付密钥 / 支付宝公钥，与 App 分开配
     * @return static
     */
    public static function makeMini(): self
    {
        $alipay = systemConfig(['site_url', 'alipay_mini_app_id', 'alipay_mini_public_key', 'alipay_mini_private_key']);
        if (empty($alipay['alipay_mini_app_id']) || empty($alipay['alipay_mini_public_key']) || empty($alipay['alipay_mini_private_key'])) {
            throw new PayException('支付宝小程序支付配置不完整，请在后台「支付宝小程序支付配置」填写 app_id、支付宝公钥、支付密钥');
        }
        return new static([
            'appId' => $alipay['alipay_mini_app_id'],
            'merchantPrivateKey' => $alipay['alipay_mini_private_key'],
            'alipayPublicKey' => $alipay['alipay_mini_public_key'],
            'notifyUrl' => rtrim($alipay['site_url'], '/') . Route::buildUrl('alipayNotify')->build(),
        ]);
    }

    /**
     * 是否是支付宝付款码
     * @param string $authCode
     * @return bool
     */
    public static function isAliPayAuthCode(string $authCode)
    {
        return preg_match('/^[0-9]{16,24}$/', $authCode) && in_array(substr($authCode, 0, 2), ['25', '26', '27', '28', '29', '30']);
    }

    /**
     * 初始化
     */
    protected function initialize()
    {
        // EasySDK 的 Factory 是单例，setOptions 只认进程里第一次的配置；Swoole 常驻下会导致
        // 后台改的密钥要重启才生效、App 与小程序 app_id 互相串，所以每次先清掉单例再设置
        $instance = new \ReflectionProperty(Factory::class, 'instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);
        Factory::setOptions($this->getOptions());
    }

    /**
     * 设置配置
     * @return Config
     */
    protected function getOptions()
    {
        $options = new Config();
        $options->protocol = 'https';
        $options->gatewayHost = 'openapi.alipay.com';
        $options->signType = 'RSA2';

        $options->appId = $this->config['appId'];
        // 为避免私钥随源码泄露，推荐从文件中读取私钥字符串而不是写入源码中
        $options->merchantPrivateKey = $this->config['merchantPrivateKey'];
        //注：如果采用非证书模式，则无需赋值上面的三个证书路径，改为赋值如下的支付宝公钥字符串即可
        $options->alipayPublicKey = $this->config['alipayPublicKey'];
        //可设置异步通知接收服务地址（可选）
        $options->notifyUrl = $this->config['notifyUrl'];
        //可设置AES密钥，调用AES加解密相关接口时需要（可选）
        if ($this->config['encryptKey']) {
            $options->encryptKey = $this->config['encryptKey'];
        }
        Tea::config(['verify' => false]);


        return $options;
    }

    /**
     * 付款码付款
     * @param string $authCode
     * @param string $title
     * @param string $orderId
     * @param string $totalAmount
     * @param string $passbackParams
     * @return array
     */
    public function microPay(string $authCode, string $title, string $orderId, string $totalAmount, string $passbackParams)
    {
        $title = trim($title);
        try {
            $result = Factory::payment()->faceToFace()->optional('passback_params', $passbackParams)->pay($title, $orderId, $totalAmount, $authCode);

            $data = [
                "subject" => $title,
                "out_trade_no" => $orderId,
                "total_amount" => $totalAmount,
                "auth_code" => $authCode,
                "scene" => "bar_code",
                "passback_params" => $passbackParams
            ];
            Event::until('pay.create.order', [$data, $orderId, 'alipay']);

            if ($this->response->success($result)) {
                $response = $result->toMap();
                return [
                    'paid' => $response['code'] === '10000' ? 1 : 0,
                    'message' => $response['sub_msg'] ?? '支付成功',
                    'payInfo' => $response
                ];
            } else {
                throw new PayException('失败原因:' . $result->msg . ',' . $result->subMsg);
            }
        } catch (\Exception $e) {
            throw new PayException($e->getMessage());
        }
    }


    public function h5($orderId,$totalAmount,$quitUrl,$title,$passbackParams,$siteUrl = '')
    {
        try{
            //h5支付
            $result = Factory::payment()->wap()->optional('passback_params', $passbackParams)->pay($title, $orderId, $totalAmount, $quitUrl, $siteUrl);
            if ($this->response->success($result)) {
                return isset($result->body) ? $result->body : $result;
            } else {
                throw new PayException('失败原因:' . $result->msg . ',' . $result->subMsg);
            }
        } catch (\Exception $e) {
            throw new PayException($e->getMessage());
        }
    }

    public function precreate($orderId,$totalAmount,$quitUrl,$title,$passbackParams,$siteUrl = '')
    {
        try{
            //二维码支付
            $result = Factory::payment()->faceToFace()->optional('passback_params', $passbackParams)->precreate($title, $orderId, $totalAmount);
            if ($this->response->success($result)) {
                return isset($result->body) ? $result->body : $result;
            } else {
                throw new PayException('失败原因:' . $result->msg . ',' . $result->subMsg);
            }
        } catch (\Exception $e) {
            throw new PayException($e->getMessage());
        }
    }


    /**
     * 创建订单
     * @param string $title 商品名称
     * @param string $orderId 订单号
     * @param string $totalAmount 支付金额
     * @param string $passbackParams 备注
     * @param string $quitUrl 同步跳转地址
     * @param string $siteUrl
     * @return \Alipay\EasySDK\Payment\Wap\Models\AlipayTradeWapPayResponse
     */
    public function create(string $title, string $orderId, string $totalAmount, string $passbackParams, string $quitUrl = '', string $siteUrl = '', bool $isCode = false)
    {
        $title = trim($title);
        try {
            if ($isCode) {
                $data = [
                    "subject" => $title,
                    "out_trade_no" => $orderId,
                    "total_amount" => $totalAmount,
                    "passback_params" => $passbackParams
                ];
                //二维码支付
                $result = Factory::payment()->faceToFace()->optional('passback_params', $passbackParams)->precreate($title, $orderId, $totalAmount);
            } else if (request()->isApp()) {
                $data = [
                    "subject" => $title,
                    "out_trade_no" => $orderId,
                    "total_amount" => $totalAmount,
                    "passback_params" => $passbackParams
                ];
                //app支付
                $result = Factory::payment()->app()->optional('passback_params', $passbackParams)->pay($title, $orderId, $totalAmount);
            } else {
                $data = [
                    "subject" => $title,
                    "out_trade_no" => $orderId,
                    "total_amount" => $totalAmount,
                    "quit_url" => $quitUrl,
                    "product_code" => "QUICK_WAP_WAY",
                    "passback_params" => $passbackParams
                ];
                //h5支付
                $result = Factory::payment()->wap()->optional('passback_params', $passbackParams)->pay($title, $orderId, $totalAmount, $quitUrl, $siteUrl);
            }
            Event::until('pay.create.order', [$data, $orderId, 'alipay']);
            if ($this->response->success($result)) {
                return isset($result->body) ? $result->body : $result;
            } else {
                throw new PayException('失败原因:' . $result->msg . ',' . $result->subMsg);
            }
        } catch (\Exception $e) {
            throw new PayException($e->getMessage());
        }
    }

    /**
     * 小程序授权码（my.getAuthCode auth_base）换支付宝用户标识
     * 应用开了 OpenID 时返回 open_id，否则返回 user_id（2088 开头）；顺手把 open_id / union_id 记进 eb_alipay_user
     * @param string $authCode
     * @return string
     */
    public function miniOpenId(string $authCode): string
    {
        return $this->miniIdentity($authCode)['open_id'];
    }

    /**
     * 小程序授权码换身份：['open_id' => 下单用的买家标识, 'union_id' => 可能为空, 'alipay_user_id' => eb_alipay_user 主键，记失败为 0]
     * @param string $authCode
     * @return array
     */
    public function miniIdentity(string $authCode): array
    {
        $this->initialize();
        try {
            $result = Factory::util()->generic()->execute('alipay.system.oauth.token', [
                'grant_type' => 'authorization_code',
                'code' => $authCode,
            ], []);
        } catch (\Exception $e) {
            throw new PayException('支付宝授权失败:' . $e->getMessage());
        }
        $body = json_decode((string)$result->httpBody, true) ?: [];
        $data = $body['alipay_system_oauth_token_response'] ?? [];
        $openId = $data['open_id'] ?? ($data['user_id'] ?? '');
        if (!$openId) {
            $error = $body['error_response'] ?? $data;
            throw new PayException('支付宝授权失败:' . ($error['sub_msg'] ?? ($error['msg'] ?? '未获取到用户标识')));
        }
        $unionId = (string)($data['union_id'] ?? '');
        $alipayUserId = app()->make(AlipayUserRepository::class)->sync($this->config['appId'], 'routine', $openId, $unionId);
        return ['open_id' => $openId, 'union_id' => $unionId, 'alipay_user_id' => $alipayUserId];
    }

    /**
     * 解出 my.getPhoneNumber 返回的会员手机号
     * @param string $response my.getPhoneNumber 成功回调里的 response（JSON 字符串）
     * @return string
     */
    public function miniPhoneNumber(string $response): string
    {
        $result = $this->miniOpenResponse($response);
        if (empty($result['mobile'])) {
            throw new PayException('获取手机号失败:未返回手机号');
        }
        return (string)$result['mobile'];
    }

    /**
     * 解开小程序开放数据（my.getPhoneNumber / my.getOpenUserInfo 的 response）：
     * 密文先用支付宝公钥验签、再用「接口内容加密方式」AES 密钥解密；明文直接取
     * @param string $response 回调里的 response（JSON 字符串）
     * @return array 业务数据（code=10000 的那层）
     */
    public function miniOpenResponse(string $response): array
    {
        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['response'])) {
            throw new PayException('授权数据有误，请重试');
        }
        if (is_array($data['response'])) {
            $result = $data['response'];
        } else {
            $content = (string)$data['response'];
            $aesKey = base64_decode((string)systemConfig('alipay_mini_aes_key'));
            if (!$aesKey) {
                throw new PayException('请在后台「支付宝小程序支付配置」填写接口内容加密密钥');
            }
            $publicKey = "-----BEGIN PUBLIC KEY-----\n" . wordwrap($this->config['alipayPublicKey'], 64, "\n", true) . "\n-----END PUBLIC KEY-----";
            $sign = base64_decode((string)($data['sign'] ?? ''));
            // 密文验签内容是带双引号的密文串；兼容不带引号的情况
            $verified = $sign && (openssl_verify('"' . $content . '"', $sign, $publicKey, OPENSSL_ALGO_SHA256) === 1
                || openssl_verify($content, $sign, $publicKey, OPENSSL_ALGO_SHA256) === 1);
            if (!$verified) {
                throw new PayException('授权数据验签失败');
            }
            $plain = openssl_decrypt(base64_decode($content), 'aes-' . (strlen($aesKey) * 8) . '-cbc', $aesKey, OPENSSL_RAW_DATA, str_repeat("\0", 16));
            $result = $plain ? json_decode($plain, true) : null;
            if (!is_array($result)) {
                throw new PayException('授权数据解密失败，请检查接口内容加密密钥');
            }
        }
        if (($result['code'] ?? '') !== '10000') {
            throw new PayException('支付宝授权失败:' . ($result['subMsg'] ?? ($result['msg'] ?? '未知错误')));
        }
        return $result;
    }

    /**
     * 小程序支付下单（alipay.trade.create），返回给 my.tradePay 用的支付宝交易号
     * @param string $title
     * @param string $orderId
     * @param string $totalAmount
     * @param string $passbackParams
     * @param string $buyer miniOpenId() 的返回值
     * @return string
     */
    public function miniCreate(string $title, string $orderId, string $totalAmount, string $passbackParams, string $buyer): string
    {
        $data = [
            'subject' => trim($title),
            'out_trade_no' => $orderId,
            'total_amount' => $totalAmount,
            'product_code' => 'JSAPI_PAY',
            'passback_params' => $passbackParams,
        ];
        $data[preg_match('/^2088\d+$/', $buyer) ? 'buyer_id' : 'buyer_open_id'] = $buyer;
        // miniOpenId 发请求期间协程可能被切走，别的请求会改掉 Factory，下单前重新设一次
        $this->initialize();
        try {
            $result = Factory::util()->generic()->execute('alipay.trade.create', ['notify_url' => $this->config['notifyUrl']], $data);
        } catch (\Exception $e) {
            throw new PayException($e->getMessage());
        }
        Event::until('pay.create.order', [$data, $orderId, 'alipay']);
        if (!$this->response->success($result)) {
            throw new PayException('失败原因:' . $result->msg . ',' . $result->subMsg);
        }
        $body = json_decode((string)$result->httpBody, true) ?: [];
        $tradeNo = $body['alipay_trade_create_response']['trade_no'] ?? '';
        if (!$tradeNo) {
            throw new PayException('支付宝下单失败:未返回交易号');
        }
        return $tradeNo;
    }

    /**
     * 订单退款
     * @param string $outTradeNo 订单号
     * @param string $totalAmount 退款金额
     * @param string $refund_id 退款单号
     * @return \Alipay\EasySDK\Payment\Common\Models\AlipayTradeRefundResponse
     */
    public function refund(string $outTradeNo, string $totalAmount, string $refund_id)
    {
        try {
            $result = Factory::payment()->common()->refund($outTradeNo, $totalAmount, $refund_id);
            if ($this->response->success($result)) {
                return $result;
            } else {
                throw new PayException('失败原因:' . $result->msg . ',' . $result->subMsg);
            }
        } catch (\Exception $e) {
            throw new PayException($e->getMessage());
        }
    }

    /**
     * 查询交易退款单号信息
     * @param string $outTradeNo
     * @param string $outRequestNo
     * @return \Alipay\EasySDK\Payment\Common\Models\AlipayTradeFastpayRefundQueryResponse
     */
    public function queryRefund(string $outTradeNo, string $outRequestNo)
    {
        try {
            $result = Factory::payment()->common()->queryRefund($outTradeNo, $outRequestNo);
            if ($this->response->success($result)) {
                return $result;
            } else {
                throw new PayException('失败原因:' . $result->msg . ',' . $result->subMsg);
            }
        } catch (\Exception $e) {
            throw new PayException($e->getMessage());
        }
    }

    /**
     * 查询订单
     * @param string $outTradeNo
     * @return array
     */
    public function query(string $outTradeNo): array
    {
        try {
            $result = Factory::payment()->common()->query($outTradeNo);
            if ($this->response->success($result)) {
                return $result->toMap();
            } else {
                throw new PayException('失败原因:' . $result->msg . ',' . $result->subMsg);
            }
        } catch (\Exception $e) {
            throw new PayException($e->getMessage());
        }
    }

    /**
     * 支付异步回调
     * @return string
     */
    public static function handleNotify()
    {
        // 支付宝小程序下的单，回调里 app_id 是小程序的，要用小程序那套支付宝公钥验签
        $miniAppId = systemConfig('alipay_mini_app_id');
        $appId = (string)app()->request->param('app_id', '');
        $isMini = $miniAppId && $appId === $miniAppId;
        $service = $isMini ? self::makeMini() : self::make();
        return $service->notify(function ($notify) use ($appId, $isMini) {
            // 验签通过后记下付款人（回调只有 open_id，没有 union_id），供订单记录付款的支付宝用户
            $notify['alipay_user_id'] = app()->make(AlipayUserRepository::class)->sync(
                $appId, $isMini ? 'routine' : 'app', (string)app()->request->param('buyer_open_id', '')
            );
            Log::info('支付宝回调$notify:' . var_export([$notify], true));
            if (isset($notify['out_trade_no']) && isset($notify['attach']) && $notify['attach']) {
                event('pay_success_' . $notify['attach'], ['order_sn' => $notify['out_trade_no'], 'data' => $notify]);
                return true;
            }
            return false;
        });
    }

    /**
     * 异步回调
     * @param callable $notifyFn
     * @return string
     */
    public function notify(callable $notifyFn)
    {
        $paramInfo = app()->request->param();
        unset($paramInfo['type']);
        //商户订单号
        $postOrder['out_trade_no'] = $paramInfo['out_trade_no'] ?? '';
        //支付宝交易号
        $postOrder['trade_no'] = $paramInfo['trade_no'] ?? '';
        //交易状态
        $postOrder['trade_status'] = $paramInfo['trade_status'] ?? '';
        //备注
        $postOrder['attach'] = isset($paramInfo['passback_params']) ? urldecode($paramInfo['passback_params']) : '';

        if (
            in_array($postOrder['trade_status'], ['TRADE_SUCCESS', 'TRADE_FINISHED'])
            && $this->verifyNotify($paramInfo)
        ) {
            try {
                if ($notifyFn($postOrder)) {
                    return 'success';
                }
            } catch (\Exception $e) {
                Log::error('支付宝异步会回调成功,执行函数错误。错误单号：' . $postOrder['out_trade_no']);
            }
        }
        return 'fail';

    }

    /**
     * 验签
     * @return bool
     */
    protected function verifyNotify(array $param)
    {
        try {
            return Factory::payment()->common()->verifyNotify($param);
        } catch (\Exception $e) {
            Log::error('支付宝回调成功,验签发生错误，错误原因:' . $e->getMessage());
        }
        Log::error('支付宝回调成功,验签发生错误 False:'. $param);
        return false;
    }
}
