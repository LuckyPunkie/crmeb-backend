<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2020 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace crmeb\services\wechat;

use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;
use crmeb\services\wechat\v3pay\CombineClient;
use crmeb\services\wechat\v3pay\MerchantClient;
use crmeb\services\wechat\v3pay\SubjectClient;
use crmeb\services\wechat\config\ServicePaymentConfig;
use crmeb\services\wechat\util\XML;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Throwable;
use think\Response;
use ReflectionException;
use think\facade\Event;
use EasyWeChat\Pay\Application;
use crmeb\exceptions\PayException;
use crmeb\services\wechat\v3pay\PayClient;
use crmeb\services\wechat\config\OpenAppConfig;
use crmeb\services\wechat\config\OpenWebConfig;
use crmeb\services\wechat\v3pay\PartnerClient;
use crmeb\services\wechat\config\PaymentConfig;
use crmeb\services\wechat\config\V3PaymentConfig;
use crmeb\services\wechat\client\pay\OrderClient;
use EasyWeChat\Kernel\Exceptions\RuntimeException;
use crmeb\services\wechat\config\MiniProgramConfig;
use EasyWeChat\Kernel\Exceptions\InvalidConfigException;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;


/**
 *  微信支付
 * Class Payment
 * @package crmeb\services\wechat
 */
class Payment extends BaseApplication
{

    const BASE_PAY_URL = 'https://api.mch.weixin.qq.com';

    /**
     * @var PaymentConfig
     */
    protected PaymentConfig $config;

    /**
     * @var
     */
    protected V3PaymentConfig $v3Config;

    /**
     * @var PayClient
     */
    protected PayClient $payClient;

	/**
	 * @var ServicePaymentConfig
	 */
	protected ServicePaymentConfig $serviceConfig;
    protected PartnerClient $partnerClient;
    protected CombineClient $combineClient;
    protected MerchantClient $merchantClient;
    protected SubjectClient $subjectClient;

    /**
     * 是否v3支付
     * @var bool
     */
    public bool $isV3PAy = false;

    /**
     * 是否小程序支付
     * @var bool
     */
    public bool $isMiniPay = false;

	/**
	 * 是否是服务商支付
	 * @var bool
	 */
	public bool $isServicePay = false;

	/**
	 * 门店ID
	 * @var int
	 */
	public int $storeId = 0;

    /**
     * @var array
     */
    protected array $application = [];

    public array $channelConfig = [];
    public string $channel = self::WEB;

    /**
     * Payment constructor.
     * @param PaymentConfig $config
     * @param V3PaymentConfig $v3Config
     * @param PayClient $payClient
     */
    public function __construct(
        PaymentConfig $config,
        V3PaymentConfig $v3Config,
        PayClient $payClient,
        ServicePaymentConfig $serviceConfig,
        PartnerClient $partnerClient,
        CombineClient $CombineClient,
        MerchantClient $merchantClient,
        SubjectClient $subjectClient
    )
    {
        $this->config   = $config;
        $this->v3Config = $v3Config;
        $this->payClient= $payClient;
        $this->serviceConfig = $serviceConfig;
        $this->partnerClient = $partnerClient;
        $this->combineClient = $CombineClient;
        $this->merchantClient = $merchantClient;
        $this->subjectClient  = $subjectClient;
        $this->debug    = !!DefaultConfig::value('logger');
    }

    /**
     * @return Payment
     */
    public static function instance(): self
    {
        return app()->make(static::class);
    }

    public function isV3Pay()
    {
        $this->isV3PAy = $this->config->isV3PAy;
        return $this->isV3PAy;
    }

    public function setChannel($channel)
    {
        $this->channel = $channel;
        $this->config->setChannel($channel);
        return $this;
    }


     /**
     * v3 jspay 支付
     * @return PayClient
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/10/10
     */
    public function payClient()
    {
        return $this->payClient->setChannel($this->accessEnd);
    }

    public function partnerClient()
    {
        return $this->partnerClient->setChannel($this->accessEnd);
    }

    public function CombineClient()
    {
        return $this->combineClient->setChannel($this->accessEnd);
    }

    /**
     * 二级商户进件 / 分账接收方管理 / 媒体上传
     * @return MerchantClient
     */
    public function merchantClient(): MerchantClient
    {
        return $this->merchantClient->setChannel($this->accessEnd);
    }

    /**
     * 特约商户申请 / 银行列表查询
     * @return SubjectClient
     */
    public function subjectClient(): SubjectClient
    {
        return $this->subjectClient->setChannel($this->accessEnd);
    }


    /**
     * @return Application
     * @throws InvalidArgumentException
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2022/10/11
     */
    public function application(): Application
    {
        $request = request();
        $config = $this->config->all();
        $accessEnd = $this->getAuthAccessEnd($request);
        switch ($accessEnd) {
            case self::APP:
                /** @var OpenAppConfig $make */
                $make = app()->make(OpenAppConfig::class);
                $config['app_id'] = $make->appId;
                $config['notify_url'] = trim($make->getConfig(DefaultConfig::COMMENT_URL)) . DefaultConfig::value('app.notifyUrl');
                break;
            case self::PC:
                /** @var OpenWebConfig $make */
                $make = app()->make(OpenWebConfig::class);
                $config['app_id'] = $make->appId;
                break;
            case self::MINI:
                /** @var MiniProgramConfig $make */
                $make = app()->make(MiniProgramConfig::class);
                $config['app_id'] = $make->appId;
                $config['notify_url'] = trim($make->getConfig(DefaultConfig::COMMENT_URL)) . DefaultConfig::value('pay.mini.notifyUrl');
                break;
        }
        if (!isset($this->application[$accessEnd])) {
            $this->application[$accessEnd] = new Application($config);
            $this->setHttpClient($this->application[$accessEnd], self::BASE_PAY_URL);
            $this->setRequest($this->application[$accessEnd]);
        }

        return $this->application[$accessEnd];
    }


	/**
	 * 发起订单支付接口入口
	 * @param bool $isService
	 * @return OrderClient
	 * @throws InvalidArgumentException
	 * @throws InvalidConfigException
	 */
    public function order()
    {
        $config = $this->application()->getConfig();
		$api = $this->application()->getClient();
        return new OrderClient($api, $config);
    }

	/**
	 * 通过微信付款码获取用户openid
	 * @param string $authCode
	 * @return array
	 */
	public static function authCodeToOpenid(string $authCode)
	{
		$class = self::instance();
		$wechatAppId = $class->config->getConfig('official.appid', '');
		$miniAppId = $class->config->getConfig('mini.appid', '');
		$openid = [];
		if (!$wechatAppId && !$miniAppId) {
//			throw new PayException('请先配置公众号或者小程序');
			return $openid;
		}
		if ($wechatAppId) {//是否配置公众号
			$response = $class->setAccessEnd('web')->order()->authCodeToOpenid($authCode);
			if ($response['return_code'] === 'SUCCESS' && $response['result_code'] === 'SUCCESS' && isset($response['openid'])) {
				$openid[] = $response['openid'];
			}
		}
		if ($miniAppId) {//小程序
			$response = $class->setAccessEnd('mini')->order()->authCodeToOpenid($authCode);
			if ($response['return_code'] === 'SUCCESS' && $response['result_code'] === 'SUCCESS' && isset($response['openid'])) {
				$openid[] = $response['openid'];
			}
		}
		return $openid;
	}

    /**
     * 付款码支付
     * @param string $authCode
     * @param string $outTradeNo
     * @param string $totalFee
     * @param string $attach
     * @param string $body
     * @param string $detail
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws TransportExceptionInterface
     */
    public static function microPay(string $authCode, string $outTradeNo, string $totalFee, string $attach, string $body, string $detail = '')
    {
		$self = self::instance();
		$wechatAppId = $self->config->getConfig('official.appid', '');
		if ($wechatAppId) {//是否配置公众号
			$self->setAccessEnd('web');
		} else {
			$miniAppId = $self->config->getConfig('mini.appid', '');
			if ($miniAppId) {//小程序
				$self->setAccessEnd('mini');
			} else {
				throw new PayException('请先配置公众号或者小程序');
			}
		}
		$self->autoInjectServicePay();
		$isService = false;
		$totalFee = bcmul($totalFee, 100, 0);
		$data = [
			'auth_code' => $authCode,
			'out_trade_no' => $outTradeNo,
			'total_fee' => (int)$totalFee,
			'attach' => $attach,
			'body' => $body,
			'detail' => $detail
		];
		//开启服务商分账
		if ($self->serviceConfig->isServicePay && $self->serviceConfig->subMchid) {
			$data['sub_mch_id'] = $self->serviceConfig->subMchid;//特约子商户商户号
			$data['profit_sharing'] = "Y";
			$isService = true;
		}
        $response = $self->order($isService)->pay($data);
        $response = $response->toArray();

        self::logger('付款码支付', compact('authCode', 'outTradeNo', 'totalFee', 'attach', 'body', 'detail'), $response);

        //下单成功
        if ($response['return_code'] === 'SUCCESS') {
            //扫码付款直接支付成功
            if ($response['result_code'] === 'SUCCESS' && $response['trade_type'] === 'MICROPAY') {
                return [
                    'paid' => 1,
                    'message' => '支付成功',
                    'payInfo' => $response,
                ];
            } else {
                return [
                    'paid' => 0,
                    'message' => $response['err_code_des'],
                    'payInfo' => $response
                ];
            }
        } else {
            throw new PayException($response['return_msg']);
        }
    }

    /**
     * 撤销订单
     * @param string $outTradeNo
     * @return bool
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws TransportExceptionInterface
     */
    public static function reverseOrder(string $outTradeNo): bool
    {
        $response = self::instance()->order()->reverseByOutTradeNumber($outTradeNo);
        $response = $response->toArray();
        self::logger('撤销订单', compact('outTradeNo'), $response);

        if ($response['return_code'] === 'SUCCESS') {
            return true;
        } else {
            throw new PayException($response['return_msg']);
        }
    }

    /**
     * 查询订单支付状态
     * @param string $outTradeNo
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws TransportExceptionInterface
     */
    public static function queryOrder(string $outTradeNo)
    {
        $response = self::instance()->order()->queryByOutTradeNumber($outTradeNo);
        $response = $response->toArray();

        self::logger('查询订单支付状态', compact('outTradeNo'), $response);

        if ($response['return_code'] === 'SUCCESS') {
            if ($response['result_code'] === 'SUCCESS') {
                return [
                    'paid' => 1,
                    'out_trade_no' => $outTradeNo,
                    'payInfo' => $response
                ];
            } else {
                return [
                    'paid' => 0,
                    'out_trade_no' => $outTradeNo,
                    'payInfo' => $response
                ];
            }
        } else {
            throw new PayException($response['return_msg']);
        }
    }

    /**
     * 企业付款到零钱
     * @param string $openid openid
     * @param string $orderId 订单号
     * @param string $amount 金额
     * @param string $desc 说明
     * @param string $type 类型
     * @return bool
     * @throws InvalidArgumentException
     * @throws TransportExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException|InvalidConfigException
     */
    public static function merchantPay(string $channel_type, int $uid, string $orderId, string $amount, string $desc, string $user_name = '', array $transferDetailList = [])
    {
        /** @var WechatUserServices $wechatServices */
        $wechatServices = app()->make(WechatUserServices::class);
        $typeMap = [
            'routine' => Payment::MINI,
            'wechat' => Payment::WEB,
            'app' => Payment::APP
        ];

        $channelType = $channel_type;
        $openid = '';
        $type = null;

        if (isset($typeMap[$channelType])) {
            $openid = $wechatServices->getWechatOpenid($uid, $channelType);
            $type = $typeMap[$channelType];
        }
        if (!$openid) {
            throw new PayException('该用户暂不支持企业付款到零钱，请手动转账');
        }
        $application = self::instance()->setAccessEnd($type)->application();
        $config = $application->getConfig();
        if (empty($config['certificate'])) {
            throw new PayException('企业付款到零钱需要支付cert证书，检测到您没有上传！');
        }
        if (empty($config['private_key'])) {
            throw new PayException('企业付款到零钱需要支付key证书，检测到您没有上传！');
        }
        if (self::instance()->isV3PAy) {
//            if (sys_config('v3_pay_public_key') != '') {
                $transfer_scene_id = sys_config('pay_weixin_scene_id');
                if (!$transfer_scene_id) {
                    throw new PayException('请配置微信v3新支付场景ID！');
                }
                $user_name = $user_name ?: app()->make(UserServices::class)->value(['uid' => $uid], 'nickname');
                //v3新支付
                $res = self::instance()->payClient()->setType($type)->transferBills(
                    outBatchNo: $orderId,
                    amount: $amount,
                    openid: $openid,
                    userName: $user_name,
                    remark: $desc,
                    perception: $desc,
                    transferDetailList: $transferDetailList,
                    transfer_scene_id: $transfer_scene_id
                );
                self::logger('商家转账到零钱', compact('orderId', 'amount', 'openid', 'desc', 'user_name', 'transferDetailList', 'transfer_scene_id'), $res);
//            } else {
//                //v3支付使用发起商家转账API
//                $res = self::instance()->payClient()->setType($type)->batches(
//                    outBatchNo: $orderId,
//                    amount: $amount,
//                    batchName: $desc,
//                    remark: $desc,
//                    transferDetailList: [
//                        [
//                            'out_detail_no' => $orderId,
//                            'transfer_amount' => $amount,
//                            'transfer_remark' => $desc,
//                            'openid' => $openid
//                        ]
//                    ]
//                );
//            }
            return $res;

        } else {
            $merchantPayData = [
                'partner_trade_no' => $orderId, //随机字符串作为订单号，跟红包和支付一个概念。
                'openid' => $openid, //收款人的openid
                'check_name' => 'NO_CHECK',  //文档中有三种校验实名的方法 NO_CHECK OPTION_CHECK FORCE_CHECK
                'amount' => (int)bcmul($amount, '100', 0),  //单位为分
                'desc' => $desc,
                'spbill_create_ip' => request()->ip(),  //发起交易的IP地址
            ];
            $result = self::instance()->order()->toBalance($merchantPayData);
            $result = $result->toArray();

            self::logger('企业付款到零钱', compact('merchantPayData'), $result);

            if ($result['return_code'] == 'SUCCESS' && $result['result_code'] != 'FAIL') {
                return true;
            } else {
                throw new PayException(($result['return_msg'] ?? '支付失败') . ':' . ($result['err_code_des'] ?? '发起企业支付到零钱失败'));
            }
        }

    }

    /**
     * 生成支付订单对象
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return \EasyWeChat\Kernel\HttpClient\Response
     * @throws TransportExceptionInterface
     */
    public function paymentOrder($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'JSAPI', array $options = [])
    {
        $total_fee = bcmul($total_fee, 100, 0);
        $order = array_merge(compact('out_trade_no', 'total_fee', 'attach', 'body', 'detail', 'trade_type'), $options);
        if (!is_null($openid)) $order['openid'] = $openid;
        if ($order['detail'] == '') unset($order['detail']);
        $result = $this->order()->unify($order);
        $result = $result->toArray();
        self::logger('生成支付订单对象', compact('order'), $result);
        if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
            return $result;
        } else {
            if ($result['return_code'] == 'FAIL') {
                throw new PayException('微信支付错误返回：' . $result['return_msg']);
            } else if (isset($result['err_code'])) {
                throw new PayException('微信支付错误返回：' . $result['err_code_des']);
            } else {
                throw new PayException('没有获取微信支付的预支付ID，请重新发起支付!');
            }
        }
    }

    /**
     * 生成支付订单对象(小程序商户号支付时)
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return \EasyWeChat\Kernel\HttpClient\Response|ResponseInterface
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws TransportExceptionInterface
     */
    public static function paymentMiniOrder($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'JSAPI', array $options = [])
    {
        $total_fee = bcmul($total_fee, 100, 0);
        $order = array_merge(compact('out_trade_no', 'total_fee', 'attach', 'body', 'detail', 'trade_type'), $options);
        if (!is_null($openid)) $order['openid'] = $openid;
        if ($order['detail'] == '') unset($order['detail']);
        $order['spbill_create_ip'] = request()->ip();
        $result = self::instance()->order()->createorder($order);
        $result = $result->toArray();
        self::logger('生成支付订单对象', compact('order'), $result);
        if ($result['errcode'] == '0') {
            return $result;
        } else {
            throw new PayException('微信支付错误返回：' . $result['errmsg']);
        }
    }


    /**
     * 获得jsSdk支付参数
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return array
     * @throws InvalidArgumentException
     * @throws InvalidConfigException|TransportExceptionInterface
     */
    public function jsPay($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'JSAPI', $options = [])
    {
        //$pay = self::instance();
        $paymentPrepare = $this->paymentOrder($openid, $out_trade_no, $total_fee, $attach, $body, $detail, $trade_type,
            $options);

        return $this->application()
            ->getUtils()
            ->buildSdkConfig($paymentPrepare['prepay_id'], $this->application()->getConfig()->get('app_id'), 'MD5');
    }

    /**
     * 获得jsSdk支付参数(小程序商户号支付时)
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return array
     * @throws TransportExceptionInterface
     */
    public static function miniPay($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'JSAPI', $options = [])
    {
        $paymentPrepare = self::paymentMiniOrder($openid, $out_trade_no, $total_fee, $attach, $body, $detail, $trade_type, $options);
        $paymentPrepare['payment_params']['timestamp'] = $paymentPrepare['payment_params']['timeStamp'];
        return $paymentPrepare['payment_params'] ?? [];
    }

    /**
     * 获得APP付参数
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return array|string
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws TransportExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function appPay($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'APP', $options = [])
    {
        $paymentPrepare =$this->paymentOrder($openid, $out_trade_no, $total_fee, $attach, $body, $detail,
            $trade_type, $options);
        return $this->application()->getUtils()->buildAppConfig($paymentPrepare['prepay_id'], $this->application()
            ->getConfig()->get('app_id'));
    }



    /**
     * 获得native支付参数
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return array
     * @throws TransportExceptionInterface
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public static function nativePay($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'NATIVE', $options = [])
    {
        $instance = self::instance();

        if ($instance->isV3PAy) {
            $data = $instance->payClient()->nativePay($out_trade_no, $total_fee, $body, $attach);
            $res['code_url'] = $data['code_url'];
            $res['invalid'] = time() + 60;
            $res['logo'] = [];
            return $res;
        }

        $data = $instance->setAccessEnd(self::WEB)->paymentOrder($openid, $out_trade_no, $total_fee, $attach, $body, $detail, $trade_type, $options);
        if ($data) {
            $res['code_url'] = $data['code_url'];
            $res['invalid'] = time() + 60;
            $res['logo'] = [];
        } else $res = [];
        return $res;
    }

    /**
     * 使用商户订单号退款
     * @param $orderNo
     * @param $refundNo
     * @param $totalFee
     * @param null $refundFee
     * @param null $opUserId
     * @param string $refundReason
     * @param string $type
     * @param string $refundAccount
     * @return \EasyWeChat\Kernel\HttpClient\Response
     * @throws TransportExceptionInterface
     */
    public function refund($orderNo, $refundNo, $totalFee, $refundFee = null, $opUserId = null, string $refundReason = '', string $type = 'out_trade_no', string $refundAccount = 'REFUND_SOURCE_UNSETTLED_FUNDS')
    {
        $totalFee = floatval($totalFee);
        $refundFee = floatval($refundFee);
        if ($type == 'out_trade_no') {
            $result = $this->order()->byOutTradeNumber($orderNo, $refundNo, $totalFee, $refundFee, [
                'refund_account' => $refundAccount,
                'notify_url' => $this->config->refundUrl,
                'refund_desc' => $refundReason
            ]);
        } else {
            $result = $this->order()->byTransactionId($orderNo, $refundNo, $totalFee, $refundFee, [
                'refund_account' => $refundAccount,
                'notify_url' => $this->config->refundUrl,
                'refund_desc' => $refundReason
            ]);
        }

        self::logger('使用商户订单号退款', compact('orderNo', 'refundNo', 'totalFee', 'refundFee', 'opUserId', 'refundReason', 'type', 'refundAccount'), $result);

        return $result;
    }

    /**
     * 小程序商户退款
     * @param $orderNo //微信支付单号
     * @param $refundNo //微信退款单号
     * @param $totalFee
     * @param null $refundFee
     * @param array $opt
     * @return \EasyWeChat\Kernel\HttpClient\Response|ResponseInterface
     * @throws InvalidArgumentException
     * @throws InvalidConfigException
     * @throws TransportExceptionInterface
     */
    public function miniRefund($orderNo, $refundNo, $totalFee, $refundFee = null, array $opt = [])
    {
        $totalFee = floatval($totalFee);
        $refundFee = floatval($refundFee);

        $order = [
            'openid' => $opt['open_id'],
            'trade_no' => $opt['routine_order_id'],
            'transaction_id' => $orderNo,
            'refund_no' => $refundNo,
            'total_amount' => $totalFee,
            'refund_amount' => $refundFee,
        ];
        $result = $this->order()->refundorder($order);

        self::logger('使用商户订单号退款', compact('orderNo', 'refundNo', 'totalFee', 'refundFee', 'opt'), $result);

        return $result;
    }

    /**
     * 退款
     * @param $orderNo
     * @param array $opt
     * @return bool
     * @throws \Psr\SimpleCache\InvalidArgumentException|TransportExceptionInterface
     */
    public function payOrderRefund($orderNo, array $opt)
    {
        if (isset($opt['pay_routine_open']) && $opt['pay_routine_open']) {
            return $this->payMiniOrderRefund($orderNo, $opt);
        }
        if (!isset($opt['pay_price'])) {
            throw new PayException('缺少pay_price');
        }
        $certPath = $this->config->certPath;
        if (!$certPath) {
            throw new PayException('请上传支付证书cert');
        }
        $keyPath = $this->config->keyPath;
        if (!$keyPath) {
            throw new PayException('请上传支付证书key');
        }
        if (!is_file($certPath)) {
            throw new PayException('支付证书cert不存在');
        }
        if (!is_file($keyPath)) {
            throw new PayException('支付证书key不存在');
        }

        if ($this->isV3PAy) {
            return $this->payClient()->refund($orderNo, $opt);
        }

        $totalFee = floatval(bcmul($opt['pay_price'], 100, 0));
        $refundFee = isset($opt['refund_price']) ? floatval(bcmul($opt['refund_price'], 100, 0)) : null;
        $refundReason = $opt['desc'] ?? '';
        $refundNo = $opt['refund_id'] ?? $orderNo;
        $opUserId = $opt['open_id'] ?? null;
        $type = $opt['type'] ?? 'out_trade_no';
        if ($type == 'out_trade_no') {
            $orderNo = $opt['out_trade_no'] ?? $orderNo;
        }
        /*
         * 仅针对老资金流商户使用
         * REFUND_SOURCE_UNSETTLED_FUNDS---未结算资金退款（默认使用未结算资金退款）
         * REFUND_SOURCE_RECHARGE_FUNDS---可用余额退款
         */
        $refundAccount = $opt['refund_account'] ?? 'REFUND_SOURCE_UNSETTLED_FUNDS';
        try {
            $res = $this->refund($orderNo, $refundNo, $totalFee, $refundFee, $opUserId, $refundReason, $type, $refundAccount);
            $res = $res->toArray();
            if ($res['return_code'] == 'FAIL') {
                throw new PayException('退款失败[1]:' . $res['return_msg']);
            }
            if (isset($res['err_code'])) {
                throw new PayException('退款失败[2]:' . $res['err_code_des']);
            }
        } catch (\Exception $e) {

            self::error($e);

            throw new PayException($e->getMessage());
        }
        return true;
    }

    /**
     * 小程序商户退款
     * @param $orderNo
     * @param array $opt
     * @return bool
     * @throws TransportExceptionInterface
     */
    public function payMiniOrderRefund($orderNo, array $opt)
    {
        if (!isset($opt['pay_price'])) {
            throw new PayException('缺少pay_price');
        }
        if (!isset($opt['routine_order_id'])) {
            throw new PayException('缺少订单单号');
        }
        $totalFee = floatval(bcmul($opt['pay_price'], 100, 0));
        $refundFee = isset($opt['refund_price']) ? floatval(bcmul($opt['refund_price'], 100, 0)) : null;
        $refundNo = $opt['refund_no'];
        try {
            $result = $this->miniRefund($orderNo, $refundNo, $totalFee, $refundFee, $opt);
            $result = $result->toArray();
            if ($result['errcode'] == '0') {
                return true;
            } else {
                throw new PayException('退款失败：' . $result['errmsg']);
            }
        } catch (\Exception $e) {

            self::error($e);

            throw new PayException($e->getMessage());
        }
    }

    /**
     * 商家转账通知
     * @return Response
     * User: liusl
     * DateTime: 2025/2/14 下午2:53
     */
    public function handleMchNotify()
    {
        $response = $this->payClient()->handleNotify(function ($notify, $success) {
            self::logger('商家转账成功回调接口', [], $notify);
            if (isset($notify['transfer_bill_no']) && $success) {
                $res = Event::until('pay.mchNotify', [$notify]);
                if ($res) {
                    return $res;
                } else {
                    return false;
                }
            }

        });

        return response($response->getContent());
    }

    /**
     * 微信支付成功回调接口
     * @return Response
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws ReflectionException
     * @throws Throwable
     */
    public function handleNotify()
    {
        if ($this->isV3PAy) {
            $response = $this->payClient()->handleNotify(function ($notify, $success) {
                self::logger('微信支付成功回调接口', [], $notify);
                if (isset($notify['out_trade_no']) && $success) {
                    $res = Event::until('pay.notify', [$notify, 'wechat']);
                    if ($res) {
                        return $res;
                    } else {
                        return false;
                    }
                }
            });
            return response($response->getContent());
        } else {
            //Log::info('微信支付回调信息[getContent]:'.var_export(request()->getContent(), true));
            $message = $this->application()->getServer()->getRequestMessage();
            $message = $message->toArray();
            //Log::info('微信支付回调信息[$message]:'.var_export($message, true));
            if ($this->checkSignV2($message)) {
                self::logger('微信支付成功回调接口', [], $message);
                event(
                    'pay_success_' . $message['attach'],
                    ['order_sn' => $message['out_trade_no'], 'data' => $message, 'is_combine' => 0]
                );
                //Event::until('pay.notify', [$message, 'wechat']);
                return response(XML::build(['return_code' => 'success', 'return_message' => 'OK']), 200, [], 'xml');
            } else {
                return response(XML::build(['return_code' => 'fail', 'return_message' => 'FAIL']), 500, [], 'xml');
            }
        }
    }

    /**
     * 验签名
     * @param $message
     * @return bool
     */
    public function checkSignV2($message)
    {
        $sign = $message['sign'];
        unset($message['sign']);
        if ($this->generate_sign($message, $this->config->key) !== $sign) {
            return false;
        }
        return true;
    }

    /**
     * @param array $attributes
     * @param $key
     * @param $encryptMethod
     * @return string
     */
    function generate_sign(array $attributes, $key, $encryptMethod = 'md5')
    {
        ksort($attributes);

        $attributes['key'] = $key;

        return strtoupper(call_user_func_array($encryptMethod, [urldecode(http_build_query($attributes))]));
    }

    /**
     * 退款结果通知
     * @return Response
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws RuntimeException
     * @throws Throwable
     */
    public function handleRefundedNotify()
    {
        $response = $this->application()->getServer()->handleRefunded(function ($message, \Closure $next) {

            self::logger('退款结果通知', [], compact('message'));

            Event::until('pay.refunded.notify', [$message]);

            return $next($message);
        });

        return response($response->serve());
    }

    /**
     * 是否是微信付款二维码值
     * @param string $authCode
     * @return bool
     */
    public static function isWechatAuthCode(string $authCode)
    {
        return preg_match('/^[0-9]{18}$/', $authCode) && in_array(substr($authCode, 0, 2), ['10', '11', '12', '13', '14', '15']);
    }
}
