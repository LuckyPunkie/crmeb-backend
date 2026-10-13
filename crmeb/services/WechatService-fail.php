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


use app\common\repositories\store\order\StoreOrderRepository;
use app\common\repositories\store\product\ProductAssistSetRepository;
use app\common\repositories\store\product\ProductGroupBuyingRepository;
use app\common\repositories\store\product\ProductGroupRepository;
use app\common\repositories\store\product\ProductPresellRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\store\StoreActivityRepository;
use app\common\repositories\system\config\ConfigValueRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\wechat\WechatQrcodeRepository;
use app\common\repositories\wechat\WechatReplyRepository;
use app\common\repositories\wechat\WechatUserRepository;
use crmeb\exceptions\WechatException;
use crmeb\utils\ApiErrorCode;
use EasyWeChat\Core\Exceptions\FaultException;
use EasyWeChat\Core\Exceptions\InvalidArgumentException;
use EasyWeChat\Core\Exceptions\RuntimeException;
use EasyWeChat\Foundation\Application;
use EasyWeChat\Message\Article;
use EasyWeChat\Message\Image;
use EasyWeChat\Message\Material;
use EasyWeChat\Message\News;
use EasyWeChat\Message\Text;
use EasyWeChat\Message\Video;
use EasyWeChat\Message\Voice;
use EasyWeChat\Payment\Order;
use EasyWeChat\Server\BadRequestException;
use EasyWeChat\Support\Collection;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Log;
use think\facade\Route;
use think\Response;

/**
 * Class WechatService
 * @package crmeb\services
 * @author xaboy
 * @day 2020-04-20
 */
class WechatService
{
    /**
     * @var Application
     */
    protected $application;

    protected $config;

    /**
     * WechatService constructor.
     * @param $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->application = new Application($config);
        $this->application->register(new \crmeb\services\easywechat\certficates\ServiceProvider());
        $this->application->register(new \crmeb\services\easywechat\merchant\ServiceProvider);
        $this->application->register(new \crmeb\services\easywechat\combinePay\ServiceProvider);
        $this->application->register(new \crmeb\services\easywechat\partner\ServiceProvider);
        $this->application->register(new \crmeb\services\easywechat\pay\ServiceProvider);
        $this->application->register(new \crmeb\services\easywechat\batches\ServiceProvider);
        $this->application->register(new \crmeb\services\easywechat\wechatTemplate\ServiceProvider);
        $this->application->register(new \crmeb\services\easywechat\subject\ServiceProvider);
    }

    /**
     * @return array
     * @author xaboy
     * @day 2020-04-24
     */
    public static function getConfig($isApp, $isPartner = false)
    {
        /** @var ConfigValueRepository $make */
        $make = app()->make(ConfigValueRepository::class);
        $wechat = $make->more(['wechat_appid', 'wechat_appsecret', 'wechat_token', 'wechat_encodingaeskey', 'wechat_encode', 'wecaht_app_appid', 'wechat_app_appsecret'], 0);
        if ($isApp ?? request()->isApp()) {
            $wechat['wechat_appid']     = trim($wechat['wecaht_app_appid']);
            $wechat['wechat_appsecret'] = trim($wechat['wechat_app_appsecret']);
        }

        $payment = systemConfig([
            'site_url',
            //公众号支付配置
            'pay_weixin_mchid',
            'pay_weixin_client_cert',
            'pay_weixin_client_key',
            'pay_weixin_key',
            'pay_weixin_v3_key',
            'pay_weixin_open',
            'pay_wechat_serial_no_v3',
            'pay_wechat_type',
            'pay_weixin_public_id',
            'pay_weixin_public_key',
            //服务商支付配置
            'wechat_service_merid',
            'wechat_service_key',
            'wechat_service_v3key',
            'wechat_service_client_cert',
            'wechat_service_client_key',
            'wechat_service_serial_no',
            'wechat_service_public_id',
            'wechat_service_public_key',
            'wechat_service_tool',  //0 微信平台收付通  1 微信服务商
        ]);

        $config = systemConfig(['routine_appId','pay_weixin_open','open_wx_combine']);
        $config = array_merge($config, [
            'type' => 'wechat',
            'debug'  => false,
            'app_id' => trim($wechat['wechat_appid']),
            'secret' => trim($wechat['wechat_appsecret']),
            'token'  => trim($wechat['wechat_token']),
            'is_v3'  => $payment['pay_wechat_type'] ? true : false,
            'guzzle' => ['timeout' => 10.0, 'verify' => false],
        ]);

        if ($wechat['wechat_encode'] > 0 && $wechat['wechat_encodingaeskey'])
            $config['aes_key'] = trim($wechat['wechat_encodingaeskey']);

        $config['payment'] = [
            'key' => $isPartner ? trim($payment['wechat_service_key']) : trim($payment['pay_weixin_key']),
            'apiv3_key' => trim($payment['pay_weixin_v3_key']),
            'serial_no' => trim($payment['pay_wechat_serial_no_v3']),
            'cert_path' => (app()->getRootPath() . 'public' . $payment['pay_weixin_client_cert']),
            'key_path'  => (app()->getRootPath() . 'public' . $payment['pay_weixin_client_key']),
            'merchant_id' => $isPartner ? trim($payment['wechat_service_merid']) : trim($payment['pay_weixin_mchid']),
            'pay_weixin_client_cert' => $payment['pay_weixin_client_cert'],
            'pay_weixin_client_key'  => $payment['pay_weixin_client_key'],
            'pay_weixin_public_key'  => $payment['pay_weixin_public_key'],
            'pay_weixin_public_id'   => $payment['pay_weixin_public_id'],
            'notify_url'  => $payment['site_url'] . Route::buildUrl('wechatNotify')->build(),
        ];

        $config['service_payment'] = [
            'key' => trim($payment['wechat_service_key']),
            'apiv3_key' => trim($payment['wechat_service_v3key']),
            'serial_no' => trim($payment['wechat_service_serial_no']),
            'cert_path' => (app()->getRootPath() . 'public' . $payment['wechat_service_client_cert']),
            'key_path' => (app()->getRootPath() . 'public' . $payment['wechat_service_client_key']),
            'merchant_id' => trim($payment['wechat_service_merid']),
            'pay_weixin_client_cert' => $payment['wechat_service_client_cert'],
            'pay_weixin_client_key' => $payment['wechat_service_client_key'],

            'wechat_service_tool' => trim($payment['wechat_service_tool']),
            'pay_weixin_public_id' => trim($payment['wechat_service_public_id']),
            'pay_weixin_public_key' => trim($payment['wechat_service_public_key']),

            'notify_url'  => $payment['site_url'] . Route::buildUrl('wechatCombinePayNotify')
                    ->build(),
        ];
        return $config;
    }

    /**
     * @return self
     * @author xaboy
     * @day 2020-04-24
     */
    public static function create($isApp = null, $isPartner = false)
    {
        return new self(self::getConfig($isApp,$isPartner));
    }

    public function isV3()
    {
        return $this->config['is_v3'] ?? false;
    }

    public function v3Pay()
    {
        return $this->application->v3Pay;
    }

    /**
     * @return Application
     * @author xaboy
     * @day 2020-04-20
     */
    public function getApplication()
    {
        return $this->application;
    }

    public function subject()
    {
        return $this->application->subject;
    }

    /**
     * @param \think\Request $request
     * @return Response
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @author xaboy
     * @day 2020-04-26
     */
    public function serve(\think\Request $request)
    {
        $this->serverRequest($request);
        $this->wechatEventHook();
        return response($this->application->server->serve()->getContent());
    }

    protected function serverRequest(\think\Request $request)
    {
        $this->application->server->setRequest(new Request($request->get(), $request->post(), [], [], [], $request->server(), $request->getContent()));
    }

    /**
     * @throws InvalidArgumentException
     * @author xaboy
     * @day 2020-04-20
     */
    public function wechatEventHook()
    {
        $this->application->server->setMessageHandler(function ($message) {
            $openId = $message->FromUserName;
            $message->EventKey = str_replace('qrscene_', '', $message->EventKey);
            $userInfo = $this->getUserInfo($openId);
            $wechatUserRepository = app()->make(WechatUserRepository::class);
            $users = $wechatUserRepository->syncUser($openId, $userInfo, true);
            $scanLogin = function () use ($message, $users) {
                $ticket = $message->EventKey;
                if (strpos($ticket, '_sys_scan_login.') === 0) {
                    $key = str_replace('_sys_scan_login.', '', $ticket);
                    if(Cache::has('_scan_login' . $key) && ($users[1]['uid'] ?? 0)){
                        Cache::set('_scan_login' . $key, $users[1]['uid'] );
                    }
                }
            };
            $response = null;
            /** @var WechatReplyRepository $make * */
            $make = app()->make(WechatReplyRepository::class);
            event('wechat.message', compact('message'));
            switch ($message->MsgType) {
                case 'event':
                    event('wechat.event', compact('message'));
                    switch (strtolower($message->Event)) {
                        case 'subscribe':
                            $scanLogin();
                            $response = $this->qrKeyByMessage($message->EventKey) ?: $make->reply('subscribe');
                            if (isset($message->EventKey) && $message->EventKey) {
                                /** @var WechatQrcodeRepository $qr */
                                $qr = app()->make(WechatQrcodeRepository::class);
                                if ($qrInfo = $qr->ticketByQrcode($message->Ticket)) {
                                    $qrInfo->incTicket();
                                    if (strtolower($qrInfo['third_type']) == 'spread' && $users) {
                                        $spreadUid = $qrInfo['third_id'];
                                        if ($users[1]['uid'] == $spreadUid)
                                            return '自己不能推荐自己';
                                        else if ($users[1]['spread_uid'])
                                            return '已有推荐人!';
                                        try {
                                            $users[1]->setSpread($spreadUid);
                                        } catch (Exception $e) {
                                            return '绑定推荐人失败';
                                        }
                                    }
                                }
                            }
                            event('wechat.event.subscribe', compact('message'));
                            break;
                        case 'unsubscribe':
                            $wechatUserRepository->unsubscribe($openId);
                            event('wechat.event.unsubscribe', compact('message'));
                            break;
                        case 'scan':
                            $scanLogin();
                            $response = $this->qrKeyByMessage($message->EventKey) ?: $make->reply('subscribe');
                            /** @var WechatQrcodeRepository $qr */
                            $qr = app()->make(WechatQrcodeRepository::class);
                            if ($message->EventKey && ($qrInfo = $qr->ticketByQrcode($message->Ticket))) {
                                $qrInfo->incTicket();
                                if (strtolower($qrInfo['third_type']) == 'spread' && $users) {
                                    $spreadUid = $qrInfo['third_id'];
                                    if ($users[1]['uid'] == $spreadUid)
                                        return '自己不能推荐自己';
                                    else if ($users[1]['spread_uid'])
                                        return '已有推荐人!';
                                    try {
                                        $users[1]->setSpread($spreadUid);
                                    } catch (Exception $e) {
                                        return '绑定推荐人失败';
                                    }
                                }
                            }
                            event('wechat.event.scan', compact('message'));
                            break;
                        case 'location':
                            event('wechat.event.location', compact('message'));
                            break;
                        case 'click':
                            $response = $make->reply($message->EventKey);
                            event('wechat.event.click', compact('message'));
                            break;
                        case 'view':
                            event('wechat.event.view', compact('message'));
                            break;
                        case 'funds_order_pay':
                            if (($count = strpos($message['order_info']['trade_no'], '_')) !== false) {
                                $trade_no = substr($message['order_info']['trade_no'], $count + 1);
                            } else {
                                $trade_no = $message['order_info']['trade_no'];
                            }
                            $prefix = substr($trade_no, 0, 3);
                            //处理一下参数
                            switch ($prefix) {
                                case StoreOrderRepository::TYPE_SN_ORDER:
                                    $attach = 'order';
                                    break;
                                case StoreOrderRepository::TYPE_SN_PRESELL:
                                    $attach = 'presell';
                                    break;
                                case StoreOrderRepository::TYPE_SN_USER_ORDER:
                                    $attach = 'user_order';
                                    break;
                                case StoreOrderRepository::TYPE_SN_USER_RECHARGE:
                                    $attach = 'user_recharge';
                                    break;
                            }
                            event('pay_success_' . $attach, ['order_sn' => $message['order_info']['trade_no'], 'data' => $message, 'is_combine' => 0]);
                            break;
                    }
                    break;
                case 'text':
                    if (preg_match('/^(\/@[1-9]{1}).*\*\//', $message->Content)) {
                        $command = app()->make(CopyCommand::class)->getMassage($message->Content);
                        if (empty($command)) {
                            $response = self::textMessage('无效口令');
                        } else {
                            if ($command['type'] == 30) $command['type'] = 3;
                            $key = '_scan_url_' . $command['type'] . '_' . $command['id'] . '_' . $command['uid'];
                            $response = $this->qrKeyByMessage($key);
                        }
                    } else {
                        $response = $make->reply($message->Content);
                    }
                    event('wechat.message.text', compact('message'));
                    break;
                case 'image':
                    event('wechat.message.image', compact('message'));
                    break;
                case 'voice':
                    event('wechat.message.voice', compact('message'));
                    break;
                case 'video':
                    event('wechat.message.video', compact('message'));
                    break;
                case 'location':
                    event('wechat.message.location', compact('message'));
                    break;
                case 'link':
                    event('wechat.message.link', compact('message'));
                    break;
                // ... 其它消息
                default:
                    event('wechat.message.other', compact('message'));
                    break;
            }

            return $response ?? false;
        });
    }

    /**
     * @param $value
     * @return Collection
     * @author xaboy
     * @day 2020-04-20
     */
    public function qrcodeForever($value)
    {
        return $this->application->qrcode->forever($value);
    }

    /**
     * @param $value
     * @param int $expireSeconds
     * @return Collection
     * @author xaboy
     * @day 2020-04-20
     */
    public function qrcodeTemp($value, $expireSeconds = 2592000)
    {
        return $this->application->qrcode->temporary($value, $expireSeconds);
    }

    /**
     * @param string $openid
     * @param string $templateId
     * @param array $data
     * @param null $url
     * @param null $defaultColor
     * @return mixed
     * @author xaboy
     * @day 2020-04-20
     */
    public function sendTemplate(string $openid, string $templateId, array $data, $url = null, $defaultColor = null, $miniprogram = [])
    {
        $notice = $this->application->notice->to($openid)->template($templateId)->andData($data);
        if ($url !== null) $notice->url($url);
        if ($defaultColor !== null) $notice->defaultColor($defaultColor);
        return $notice->send($miniprogram);
    }

    /**
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return Order
     * @author xaboy
     * @day 2020-04-20
     */
    protected function paymentOrder($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'JSAPI', $options = [])
    {
        $total_fee = bcmul($total_fee, 100, 0);
        $order = array_merge(compact('out_trade_no', 'total_fee', 'attach', 'body', 'detail', 'trade_type'), $options);
        if (!is_null($openid)) $order['openid'] = $openid;
        if ($order['detail'] == '') unset($order['detail']);
        $order['spbill_create_ip'] = \request()->ip();
        $order['time_expire'] = date('YmdHis', strtotime("+14 minute"));
        return new Order($order);
    }

    /**
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return Collection
     * @author xaboy
     * @day 2020-04-20
     */
    public function paymentPrepare($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'JSAPI', $options = [])
    {
        $order = $this->paymentOrder($openid, $out_trade_no, $total_fee, $attach, $body, $detail, $trade_type, $options);

        if ($this->isV3()) {
            if ($trade_type == 'MWEB') $trade_type = 'H5';
            $payFunction = 'pay'.ucfirst($trade_type);
            return $this->application->v3Pay->{$payFunction}($order);
        } else {
            $result = $this->application->payment->prepare($order);
            if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS') {
                return $result;
            } else {
                if ($result->return_code == 'FAIL') {
                    throw new WechatException('微信支付错误返回：' . $result->return_msg);
                } else if (isset($result->err_code)) {
                    throw new WechatException('微信支付错误返回：' . $result->err_code_des);
                } else {
                    throw new WechatException('没有获取微信支付的预支付ID，请重新发起支付!');
                }
            }
        }
    }
    /**
     * 微信扫码枪支付
     * 只有v2版本支持
     *
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param $auth_code
     * @return void
     */
    public function payWeixinBarCode($out_trade_no, $total_fee, $attach, $body, $auth_code, $type, $sub_mchid = '')
    {
        //$order = $this->paymentOrderBarCode($out_trade_no, $total_fee, $attach, $body, $auth_code);
        $order = $this->paymentOrder(null, $out_trade_no, $total_fee, $attach, $body, $detail = '', 'MICROPAY', ['auth_code' => $auth_code]);
        if ($sub_mchid) $order['sub_mch_id'] = $sub_mchid;

        $result = $this->application->payment->pay($order);
        $paid = 0;
        if ($result->return_code === 'SUCCESS'&& $result->result_code === 'SUCCESS' && $result->trade_type === 'MICROPAY') {
            $paid = 1;
            $transaction_id = $result->transaction_id;
        }else if(isset($result->err_code)){
            if($result->err_code == 'USERPAYING') {
                $redis = Cache::store('redis')->handler();
                $redis->hSet('bar_code_pay', $out_trade_no, $type);
                throw new ValidateException($result->err_code_des);
            }
            throw new ValidateException('微信扫码枪支付错误返回：' . $result->err_code_des);
        }else if($result->return_code == 'FAIL'){
            throw new ValidateException('微信扫码枪支付错误返回：' . $result->return_msg);
        }

        return [
            'paid' => $paid,
            'transaction_id' => $transaction_id ?? 0,
            'payInfo' => $result
        ];
    }

    /**
     * @param $openid
     * @param $out_trade_no
     * @param $total_fee
     * @param $attach
     * @param $body
     * @param string $detail
     * @param string $trade_type
     * @param array $options
     * @return array|string
     * @author xaboy
     * @day 2020-04-20
     */
    public function jsPay($openid, $out_trade_no, $total_fee, $attach, $body, $detail = '', $trade_type = 'JSAPI', $options = [])
    {
        $paymentPrepare = $this->paymentPrepare($openid, $out_trade_no, $total_fee, $attach, $body, $detail, $trade_type, $options);
        if ($this->isV3()) {
            return $paymentPrepare;
        }
        return $trade_type === 'APP'
            ? $this->application->payment->configForAppPayment($paymentPrepare->prepay_id)
            : $this->application->payment->configForJSSDKPayment($paymentPrepare->prepay_id);
    }

    /**
     * @param $orderNo
     * @param $refundNo
     * @param $totalFee
     * @param null $refundFee
     * @param null $opUserId
     * @param string $refundReason
     * @param string $type
     * @param string $refundAccount
     * @return Collection
     * @author xaboy
     * @day 2020-04-20
     */
    public function refund($orderNo, $refundNo, $totalFee, $refundFee = null, $opUserId = null, $refundReason = '', $type = 'out_trade_no', $refundAccount = 'REFUND_SOURCE_UNSETTLED_FUNDS')
    {
        if (empty($this->config['payment']['pay_weixin_client_cert']) || empty($this->config['payment']['pay_weixin_client_key'])) {
            throw new \Exception('请配置微信支付证书');
        }
        $totalFee = floatval($totalFee);
        $refundFee = floatval($refundFee);
        if ($this->isV3()) {
            return $this->application->v3Pay->refund($orderNo, $refundNo, $totalFee, $refundFee, $opUserId, $type, $refundAccount, $refundReason);
        } else {
            return $this->application->payment->refund($orderNo, $refundNo, $totalFee, $refundFee, $opUserId, $type, $refundAccount, $refundReason);
        }
    }

    /**
     * @param $orderNo
     * @param array $opt
     * @author xaboy
     * @day 2020-04-20
     */
    public function payOrderRefund($orderNo, array $opt)
    {
        if (!isset($opt['pay_price'])) throw new WechatException('缺少pay_price');
        $totalFee = floatval(bcmul($opt['pay_price'], 100, 0));
        $refundFee = isset($opt['refund_price']) ? floatval(bcmul($opt['refund_price'], 100, 0)) : null;
        $refundReason = isset($opt['refund_message']) ? $opt['refund_message'] : '无';
        $refundNo = isset($opt['refund_id']) ? $opt['refund_id'] : $orderNo;
        $opUserId = isset($opt['op_user_id']) ? $opt['op_user_id'] : null;
        $type = isset($opt['type']) ? $opt['type'] : 'out_trade_no';

        //若传递此参数则使用对应的资金账户退款，否则默认使用未结算资金退款（仅对老资金流商户适用）
        $refundAccount = isset($opt['refund_account']) ? $opt['refund_account'] : 'REFUND_SOURCE_UNSETTLED_FUNDS';
        try {
            $res = ($this->refund($orderNo, $refundNo, $totalFee, $refundFee, $opUserId, $refundReason, $type, $refundAccount));
            if (isset($res->return_code) &&  $res->return_code== 'FAIL')
                throw new WechatException('退款失败:' . $res->return_msg);
            if (isset($res->err_code))
                throw new WechatException('退款失败:' . $res->err_code_des);
        } catch (Exception $e) {
            throw new WechatException($e->getMessage());
        }
    }

    /**
     * array (
     *    'appid' => '****',
     *    'attach' => 'user_recharge',
     *    'bank_type' => 'COMM_CREDIT',
     *    'cash_fee' => '1',
     *    'fee_type' => 'CNY',
     *    'is_subscribe' => 'Y',
     *    'mch_id' => ''*****'',
     *    'nonce_str' => '5ee9dac1bc302',
     *    'openid' => '*****',
     *    'out_trade_no' => ''*****'',
     *    'result_code' => 'SUCCESS',
     *    'return_code' => 'SUCCESS',
     *    'sign' => '51'*****'',
     *    'time_end' => '20200617165651',
     *    'total_fee' => '1',
     *    'trade_type' => 'JSAPI',
     *    'transaction_id' => ''*****'',
     * )
     *
     * @throws FaultException
     * @author xaboy
     * @day 2020-04-20
     */
    public function handleNotify()
    {
        $this->application->payment = new PaymentService($this->application->merchant);
        //TODO 微信支付
        return $this->application->payment->handleNotify(function ($notify, $successful) {
            Log::info('微信支付成功回调' . var_export($notify, 1));
            if (!$successful) return false;
            try {
                event('pay_success_' . $notify['attach'], ['order_sn' => $notify['out_trade_no'], 'data' => $notify, 'is_combine' => 0]);
            } catch (\Exception $e) {
                Log::info('微信支付回调失败:' . $e->getMessage());
                return false;
            }
            return true;
        });
    }

    public function handleNotifyV3()
    {
        return $this->application->v3Pay->handleNotify(function ($notify, $successful) {
            Log::info('微信支付成功回调' . var_export($notify, 1));
            if (!$successful) return false;
            try {
                event('pay_success_' . $notify['attach'], ['order_sn' => $notify['out_trade_no'], 'data' => $notify, 'is_combine' => 0]);
            } catch (\Exception $e) {
                Log::info('微信支付回调失败:' . $e->getMessage());
                return false;
            }
            return true;
        });
    }

    public function handleCombinePayNotify()
    {
        return $this->application->combinePay->handleNotify(function ($notify, $successful) {
            if (!$successful) return false;
            Log::info('微信支付成功回调' . var_export($notify, 1));
            if (isset($notify['combine_out_trade_no'])) {
                $is_combine = 1;
                $order_sn = $notify['combine_out_trade_no'];
            } else {
                $is_combine = 2;
                $order_sn = $notify['out_trade_no'];
            }
            try {
                event('pay_success_order',['order_sn' => $order_sn, 'data' => $notify, 'is_combine' => $is_combine]);
            } catch (\Exception $e) {
                Log::info('微信支付回调失败:' . $e->getMessage());
                return false;
            }
            return true;
        });
    }

    /**
     * @param string $url
     * @return array|string
     * @author xaboy
     * @day 2020-04-20
     */
    public function jsSdk($url)
    {
        $apiList = ['editAddress', 'openAddress', 'updateTimelineShareData', 'updateAppMessageShareData', 'onMenuShareTimeline', 'onMenuShareAppMessage', 'onMenuShareQQ', 'onMenuShareWeibo', 'onMenuShareQZone', 'startRecord', 'stopRecord', 'onVoiceRecordEnd', 'playVoice', 'pauseVoice', 'stopVoice', 'onVoicePlayEnd', 'uploadVoice', 'downloadVoice', 'chooseImage', 'previewImage', 'uploadImage', 'downloadImage', 'translateVoice', 'getNetworkType', 'openLocation', 'getLocation', 'hideOptionMenu', 'showOptionMenu', 'hideMenuItems', 'showMenuItems', 'hideAllNonBaseMenuItem', 'showAllNonBaseMenuItem', 'closeWindow', 'scanQRCode', 'chooseWXPay', 'openProductSpecificView', 'addCard', 'chooseCard', 'openCard'];
        $jsService = $this->application->js;
        $jsService->setUrl($url);
        try {
            return $jsService->config($apiList, false, false, false);
        } catch (Exception $e) {
            Log::info('微信参数获取失败' . $e->getMessage());
            return [];
        }

    }

    /**
     * 回复文本消息
     * @param string $content 文本内容
     * @return Text
     * @author xaboy
     * @day 2020-04-20
     */
    public static function textMessage($content)
    {
        return new Text(compact('content'));
    }

    /**
     * 回复图片消息
     * @param string $media_id 媒体资源 ID
     * @return Image
     * @author xaboy
     * @day 2020-04-20
     */
    public static function imageMessage($media_id)
    {
        return new Image(compact('media_id'));
    }

    /**
     * 回复视频消息
     * @param string $media_id 媒体资源 ID
     * @param string $title 标题
     * @param string $description 描述
     * @param null $thumb_media_id 封面资源 ID
     * @return Video
     * @author xaboy
     * @day 2020-04-20
     */
    public static function videoMessage($media_id, $title = '', $description = '...', $thumb_media_id = null)
    {
        return new Video(compact('media_id', 'title', 'description', 'thumb_media_id'));
    }

    /**
     * 回复声音消息
     * @param string $media_id 媒体资源 ID
     * @return Voice
     * @author xaboy
     * @day 2020-04-20
     */
    public static function voiceMessage($media_id)
    {
        return new Voice(compact('media_id'));
    }

    /**
     * 回复图文消息
     * @param string|array $title 标题
     * @param string $description 描述
     * @param string $url URL
     * @param string $image 图片链接
     * @return News|array<News>
     * @author xaboy
     * @day 2020-04-20
     */
    public static function newsMessage($title, $description = '...', $url = '', $image = '')
    {
        if (is_array($title)) {
            if (isset($title[0]) && is_array($title[0])) {
                $newsList = [];
                foreach ($title as $news) {
                    $newsList[] = self::newsMessage($news);
                }
                return $newsList;
            } else {
                $data = $title;
            }
        } else {
            $data = compact('title', 'description', 'url', 'image');
        }
        return new News($data);
    }

    /**
     * 回复文章消息
     * @param string|array $title 标题
     * @param string $thumb_media_id 图文消息的封面图片素材id（必须是永久 media_ID）
     * @param string $source_url 图文消息的原文地址，即点击“阅读原文”后的URL
     * @param string $content 图文消息的具体内容，支持HTML标签，必须少于2万字符，小于1M，且此处会去除JS
     * @param string $author 作者
     * @param string $digest 图文消息的摘要，仅有单图文消息才有摘要，多图文此处为空
     * @param int $show_cover_pic 是否显示封面，0为false，即不显示，1为true，即显示
     * @param int $need_open_comment 是否打开评论，0不打开，1打开
     * @param int $only_fans_can_comment 是否粉丝才可评论，0所有人可评论，1粉丝才可评论
     * @return Article
     * @author xaboy
     * @day 2020-04-20
     */
    public static function articleMessage($title, $thumb_media_id, $source_url, $content = '', $author = '', $digest = '', $show_cover_pic = 0, $need_open_comment = 0, $only_fans_can_comment = 1)
    {
        $data = is_array($title) ? $title : compact('title', 'thumb_media_id', 'source_url', 'content', 'author', 'digest', 'show_cover_pic', 'need_open_comment', 'only_fans_can_comment');
        return new Article($data);
    }

    /**
     * 回复素材消息
     * @param string $type [mpnews、 mpvideo、voice、image]
     * @param string $media_id 素材 ID
     * @return Material
     * @author xaboy
     * @day 2020-04-20
     */
    public static function materialMessage($type, $media_id)
    {
        return new Material($type, $media_id);
    }

    /**
     * @param $to
     * @param $message
     * @return bool
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @author xaboy
     * @day 2020-04-20
     */
    public function staffTo($to, $message)
    {
        $staff = $this->application->staff;
        $staff = is_callable($message) ? $staff->message($message()) : $staff->message($message);
        $res = $staff->to($to)->send();
        return $res;
    }

    /**
     * @param $openid
     * @return array
     * @author xaboy
     * @day 2020-04-20
     */
    public function getUserInfo($openid)
    {
        $userService = $this->application->user;
        $userInfo = is_array($openid) ? $userService->batchGet($openid) : $userService->get($openid);
        return $userInfo->toArray();
    }


    /**
     * 模板消息接口
     * @return \EasyWeChat\Notice\Notice
     */
    public function noticeService()
    {
        return $this->application->notice;
    }


    /**
     * 微信二维码生成接口
     * @return \EasyWeChat\QRCode\QRCode
     */
    public function qrcodeService()
    {
        return $this->application->qrcode;
    }

    public function storePay()
    {
        return $this->application->storePay;
    }

    /**
     * 商家到零钱回调
     *
     * @param string $type
     * @return void
     */
    public function handleCompanyPayNotify(string $type)
    {
        return $this->application->batches->handleNotify(function ($notify) use ($type) {
            Log::info('公众号转账回调' . var_export($notify, 1));
            try {
                event('company_extract_status', ['type' => $type, 'data' => $notify]);
            } catch (\Exception $e) {
                Log::info('公众号转账回调失败 handle:' . $e->getMessage());
                return false;
            }
            return true;
        });
    }

    /**
     * V3的商家到零钱
     * @author Qinii
     * @day 2023/3/13
     */
    public function companyPay($data, $user = null)
    {
        $outBatchNo = $data['sn'];
        $amount = $data['price'];
        $openid = $data['openid'];
        $userName = $data['realName'] ?? '';
        $remark = $data['mark'] ?? '';
        $transferSceneId = systemConfig('transfer_scene_id');
        $type = 'wechat';
        $transferDetailList = [
            [
                'info_type' => '活动名称',
                'info_content' => '佣金转入余额'
            ],
            [
                'info_type' => '奖励说明',
                'info_content' => '佣金转入余额'
            ]
        ];

        $result = $this->application->batches->transferBills(
            $outBatchNo,
            $amount,
            $openid,
            $userName,
            $remark,
            $transferDetailList,
            $transferSceneId,
            $type
        );
        Log::info('发起转账返回:' . json_encode($result));

        return $result;
    }

    /**
     * V2的企业到零钱
     * @param $data
     * @return mixed
     * @author Qinii
     * @day 2023/3/13
     */
    public function merchantPay($data, $user = null)
    {
        $ret = [
            'partner_trade_no' => $data['sn'], //随机字符串作为订单号，跟红包和支付一个概念。
            'openid' => $data['openid'], //收款人的openid
            'check_name' => 'NO_CHECK',  //文档中有三种校验实名的方法 NO_CHECK OPTION_CHECK FORCE_CHECK
            //'re_user_name'=>'张三',     //OPTION_CHECK FORCE_CHECK 校验实名的时候必须提交
            'amount' => $data['price'] * 100,  //单位为分
            'desc' => $data['mark'] ?? '',
            'spbill_create_ip' => request()->ip(),  //发起交易的IP地址
        ];
        $result = $this->application->merchant_pay->send($ret);
        if ($result->return_code == 'SUCCESS' && $result->result_code == 'SUCCESS') {
            return $result;
        } else {
            if ($result->return_code == 'FAIL') {
                throw new WechatException('微信支付错误返回：' . $result->return_msg);
            } else if (isset($result->err_code)) {
                throw new WechatException('微信支付错误返回：' . $result->err_code_des);
            } else {
                throw new WechatException('微信支付错误返回：' . $result->return_msg);
            }
        }
    }

    /**
     * 分账商户
     * @return mixed
     * @author Qinii
     * @day 6/24/21
     */
    public function applyments()
    {
        return $this->application->sub_merchant;
    }


    /**
     * 上传图片
     * @param array $filed
     * @return mixed
     * @author Qinii
     * @day 6/21/21
     */
    public function uploadImages(array $filed)
    {
        if (empty($filed)) throw new InvalidArgumentException('图片为空');
        foreach ($filed as $file) {
            $item = $this->application->sub_merchant->upload($file['path'], $file['name']);
            $data[] = [
                'dir' => $file['dir'],
                'media_id' => $item['media_id'],
            ];
        }
        return $data;
    }

    public function uploadVideo(array $filed)
    {
        if (empty($filed)) throw new InvalidArgumentException('视频为空');
        foreach ($filed as $file) {
            $item = $this->application->sub_merchant->video_upload($file['path'], $file['name']);
            $data[] = [
                'dir' => $file['dir'],
                'media_id' => $item['media_id'],
            ];
        }
        return $data;
    }

    public function qrKeyByMessage($key)
    {
        if (strpos($key, '_scan_url_') === 0) {
            $key = str_replace('_scan_url_', '', $key);
            $data = explode('_', $key);
            $siteUrl = rtrim(systemConfig('site_url'), '/');
            $make = app()->make(ProductRepository::class);
            if ($data[0] === 'home') {
                $share = systemConfig(['share_title', 'share_info', 'share_pic']);
                $share['url'] = $siteUrl . '?spid=' . $data[1];
            } else if ($data[0] === 'mer') {
                $ret = app()->make(MerchantRepository::class)->get($data[1]);
                if (!$ret) return;
                $share = [
                    'share_title' => $ret['mer_name'],
                    'share_info' => $ret['mer_info'],
                    'share_pic' => $ret['mer_avatar'],
                    'url' => $siteUrl . '/pages/store/home/index?id=' . $data[1],
                ];
            } else if ($data[0] === 'p0') {
                $ret = $make->get($data[1]);
                if (!$ret) return;
                $share = [
                    'share_title' => $ret['store_name'],
                    'share_info' => $ret['store_info'],
                    'share_pic' => $ret['image'],
                    'url' => $siteUrl . '/pages/goods_details/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
                ];
            } else if ($data[0] === 'p1') {
                $ret = $make->get($data[1]);
                if (!$ret) return;
                $share = [
                    'share_title' => $ret['store_name'],
                    'share_info' => $ret['store_info'],
                    'share_pic' => $ret['image'],
                    'url' => $siteUrl . '/pages/activity/goods_seckill_details/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
                ];
            } else if ($data[0] === 'p2') {
                $ret = app()->make(ProductPresellRepository::class)->search(['product_presell_id' => $data[1]])->find();
                $res = $make->get($ret['product_id']);
                if (!$ret) return;
                $share = [
                    'share_title' => $ret['store_name'],
                    'share_info' => $ret['store_info'],
                    'share_pic' => $res['image'],
                    'url' => $siteUrl . '/pages/activity/presell_details/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
                ];
            } else if ($data[0] === 'p3') {
                $ret = app()->make(ProductAssistSetRepository::class)->getSearch(['product_assist_set_id' => $data[1]])->find();
                $res = $make->get($ret['product_id']);
                if (!$ret) return;
                $share = [
                    'share_title' => $res['store_name'],
                    'share_info' => $res['store_info'],
                    'share_pic' => $res['image'],
                    'url' => $siteUrl . '/pages/activity/assist_detail/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
                ];
            }  else if ($data[0] === 'p4') {
                $ret = app()->make(ProductGroupRepository::class)->get($data[1]);
                $res = $make->get($ret['product_id']);
                if (!$ret) return;
                $share = [
                    'share_title' => $res['store_name'],
                    'share_info' => $res['store_info'],
                    'share_pic' => $res['image'],
                    'url' => $siteUrl . '/pages/activity/combination_details/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
                ];
            } else if ($data[0] === 'p40') {
                $res = app()->make(ProductGroupBuyingRepository::class)->getSearch(['group_buying_id' => $data[1]])->find();
                $ret = $make->get($res->productGroup['product_id']);
                if (!$ret) return;
                $share = [
                    'share_title' => $ret['store_name'],
                    'share_info' => $ret['store_info'],
                    'share_pic' => $ret['image'],
                    'url' => $siteUrl . '/pages/activity/combination_status/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
                ];
            } else if ($data[0] === 'form') {
                $res = app()->make(StoreActivityRepository::class)->getSearch(['activity_id' => $data[1]])->find();
                if (!$res) return;
                $share = [
                    'share_title' => $res['activity_name'],
                    'share_info' => $res['info'],
                    'share_pic' => $res['pic'],
                    'url' => $siteUrl . '/pages/activity/registrate_activity/index?id=' . $data[1] . '&spid=' . ($data[2] ?? 0),
                ];
            } else {
                return;
            }
            return \crmeb\services\wechat\Messages::newsMessage($share['share_title'], $share['share_info'], $share['url'], $share['share_pic']);
        }
    }


    /**
     * @return easywechat\combinePay\Client
     */
    public function combinePay()
    {
        return $this->application->combinePay;
    }



    /**
     * 获取模板列表
     * @return \EasyWeChat\Support\Collection
     */
    public function getPrivateTemplates()
    {
        try {
            return $this->application->new_notice->getPrivateTemplates();
        } catch (\Exception $e) {
            throw new ValidateException($this->getMessage($e->getMessage()));
        }
    }

    /**
     * 获得添加模版ID
     * @param $template_id_short
     */
    public  function addTemplateId($template_id_short,$keyword_name_list)
    {
        try {
            return  $this->application->new_notice->addTemplate($template_id_short,$keyword_name_list);
        } catch (\Exception $e) {
            throw new ValidateException($this->getMessage($e->getMessage()));
        }
    }

    /*
    * 根据模版ID删除模版
    */
    public  function deleleTemplate($template_id)
    {
        try {
            return  $this->application->new_notice->deletePrivateTemplate($template_id);
        } catch (\Exception $e) {
            throw new ValidateException($this->getMessage($e->getMessage()));
        }

    }

    /**
     * 处理返回错误信息友好提示
     * @param string $message
     * @return array|mixed|string
     */
    public function getMessage(string $message)
    {
        if (strstr($message, 'Request AccessToken fail') !== false) {
            $message = str_replace('Request AccessToken fail. response:', '', $message);
            $message = json_decode($message, true) ?: [];
            $errcode = $message['errcode'] ?? false;
            if ($errcode) {
                $message = ApiErrorCode::ERROR_WECHAT_MESSAGE[$errcode] ?? $message;
            }
        }
        return $message;
    }
    /**
     * 查询订单支付状态
     *
     * @param $orderNo
     * @return void
     */
    public function query($orderNo)
    {
        return $this->application->payment->query($orderNo);
    }
}
