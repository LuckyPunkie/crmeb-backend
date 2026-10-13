<?php
/**
 *  +----------------------------------------------------------------------
 *  | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
 *  +----------------------------------------------------------------------
 *  | Copyright (c) 2016~2022 https://www.crmeb.com All rights reserved.
 *  +----------------------------------------------------------------------
 *  | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
 *  +----------------------------------------------------------------------
 *  | Author: CRMEB Team <admin@crmeb.com>
 *  +----------------------------------------------------------------------
 */

namespace crmeb\services\wechat\client\miniprogram;


use Redis;
use crmeb\exceptions\AdminException;
use crmeb\services\wechat\config\MiniProgramConfig;
use crmeb\services\wechat\config\PaymentConfig;
use crmeb\services\wechat\util\Helper;
use crmeb\services\wechat\WechatException;
use crmeb\services\wechat\WechatResponse;
use EasyWeChat\Kernel\HttpClient\AccessTokenAwareClient;
use EasyWeChat\Kernel\HttpClient\Response;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as ResponseInterfaceAlias;
use think\facade\Cache;

/**
 * 订单管理
 * Class OrderClient
 * @author 等风来
 * @email 136327134@qq.com
 * @date 2023/9/15
 * @package crmeb\services\wechat\client\miniprogram
 */
class OrderClient
{
    const redis_prefix = 'mini_order';

    const express_company = 'ZTO';   // 默认发货快递公司为（中通快递）

    /**
     * @var PaymentConfig
     */
    protected PaymentConfig $config;

    /**
     * @var MiniProgramConfig
     */
    protected MiniProgramConfig $miniProgramConfig;

    /**
     * @var Redis
     */
    protected Redis $redis;

    /**
     * UserClient constructor.
     * @param AccessTokenAwareClient $api
     */
    public function __construct(protected AccessTokenAwareClient $api)
    {
        $this->config = app()->make(PaymentConfig::class);
        $this->miniProgramConfig = app()->make(MiniProgramConfig::class);
    }

    /**
     * 处理联系人
     * @param array $contact
     * @return array
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function handleContact(array $contact = []): array
    {
        if (isset($contact)) {
            if (isset($contact['consignor_contact']) && $contact['consignor_contact']) {
                $contact['consignor_contact'] = Helper::encryptTel($contact['consignor_contact']);
            }
            if (isset($contact['receiver_contact']) && $contact['receiver_contact']) {
                $contact['receiver_contact'] = Helper::encryptTel($contact['receiver_contact']);
            }
        }
        return $contact;
    }

    /**
     * 上传订单
     * @param string $out_trade_no
     * @param int $logistics_type
     * @param array $shipping_list
     * @param string $payer_openid
     * @param $path
     * @param int $delivery_mode
     * @param bool $is_all_delivered
     * @return mixed
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function shippingByTradeNo(string $out_trade_no, int $logistics_type, array $shipping_list, string $payer_openid, $path, int $delivery_mode = 1, bool $is_all_delivered = true, string $sub_mchid = '')
    {
        if (!$this->checkManaged()) {
            throw new AdminException('开通小程序订单管理服务后重试');
        }
        $params = [
            'order_key' => [
                'order_number_type' => 2,
                'mchid' => $sub_mchid == '' ? $this->config->mchId : $sub_mchid,
                'transaction_id' => $out_trade_no
            ],
            'logistics_type' => $logistics_type,
            'delivery_mode' => $delivery_mode,
            'upload_time' => date(DATE_RFC3339),
            'payer' => [
                'openid' => $payer_openid
            ]
        ];

        if ($delivery_mode == 2) {
            $params['is_all_delivered'] = $is_all_delivered;
        }

        foreach ($shipping_list as $shipping) {
            $contact = $this->handleContact($shipping['contact'] ?? []);
            $params['shipping_list'][] = [
                'tracking_no' => $shipping['tracking_no'] ?? '',
                'express_company' => $shipping['express_company'] ?? '',
                'item_desc' => $shipping['item_desc'] ?? '',
                'contact' => $contact
            ];
        }
        return $this->shipping($params);
    }

    /**
     * 合单
     * @param string $out_trade_no
     * @param int $logistics_type
     * @param array $sub_orders
     * @param string $payer_openid
     * @param int $delivery_mode
     * @param bool $is_all_delivered
     * @return mixed
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function combinedShippingByTradeNo(string $out_trade_no, int $logistics_type, array $sub_orders, string $payer_openid, int $delivery_mode = 2, bool $is_all_delivered = false)
    {
        if (!$this->checkManaged()) {
            throw new WechatException('开通小程序订单管理服务后重试');
        }
        $params = [
            'order_key' => [
                'order_number_type' => 1,
                'mchid' => $this->config->mchId,
                'out_trade_no' => $out_trade_no,
            ],
            'upload_time' => date(DATE_RFC3339),
            'payer' => [
                'openid' => $payer_openid
            ]
        ];

        foreach ($sub_orders as $order) {
            $sub_order = [
                'order_key' => [
                    'order_number_type' => 1,
                    'mchid' => $this->config->mchId,
                    'out_trade_no' => $order['out_trade_no'],
                    'logistics_type' => $logistics_type,
                ],
                'delivery_mode' => $delivery_mode,
                'is_all_delivered' => $is_all_delivered
            ];
            foreach ($sub_orders['shipping_list'] as $shipping) {
                $contact = $this->handleContact($shipping['contact'] ?? []);
                $sub_order['shipping_list'][] = [
                    'tracking_no' => $shipping['tracking_no'] ?? '',
                    'express_company' => isset($shipping['express_company']) ? $this->getDelivery($shipping['express_company']) : '',
                    'item_desc' => $shipping['item_desc'],
                    'contact' => $contact
                ];
            }
            $params['sub_orders'][] = $sub_order;
        }

        return $this->resultHandle($this->api->postJson('wxa/sec/order/upload_combined_shipping_info', $params));
    }

    /**
     * 签收通知
     * @param string $merchant_trade_no
     * @param string $received_time
     * @return mixed
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function notifyConfirmByTradeNo(string $merchant_trade_no, string $received_time)
	{
        $params = [
            'merchant_id' => $this->config->mchId,
            'merchant_trade_no' => $merchant_trade_no,
            'received_time' => $received_time
        ];
        return $this->resultHandle($this->api->postJson('wxa/sec/order/notify_confirm_receive', $params));
    }

    /**
     * 设置小修跳转路径
     * @param $path
     * @return mixed
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function setMesJumpPathAndCheck($path)
    {
        if (!$this->checkManaged()) {
            throw new WechatException('开通小程序订单管理服务后重试');
        }

        $params = [
            'path' => $path
        ];
        return $this->resultHandle($this->api->postJson('wxa/sec/order/set_msg_jump_path', $params));
    }

    /**
     * @param $params
     * @return mixed
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function shipping($params)
    {
        return $this->resultHandle($this->api->postJson('wxa/sec/order/upload_shipping_info', $params));
    }

    /**
     * @return object|Redis|null
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    protected function getRedis()
    {
        if (empty($this->redis)) {
            $this->redis = Cache::store('redis')->handler();
        }
        return $this->redis;
    }

    /**
     * 查询小程序是否已开通发货信息管理服务
     * @return mixed
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function isManaged()
    {
        $params = [
            'appid' => $this->miniProgramConfig->appId
        ];
        return $this->resultHandle($this->api->postJson('wxa/sec/order/is_trade_managed', $params));
    }

    /**
     * @param Response|ResponseInterfaceAlias $result
     * @return mixed
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    private function resultHandle(Response|ResponseInterfaceAlias $result)
    {
        return new WechatResponse($result);
    }

    /**
     * 获取运力id列表get_delivery_list
     * @return mixed
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getDeliveryList()
    {
        return $this->resultHandle($this->api->postJson('cgi-bin/express/delivery/open_msg/get_delivery_list', []));
    }

    /**
     * 设置物流列表
     * @return array
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function setDeliveryList()
    {
        $list = $this->getDeliveryList();
        if ($list) {
            $key = self::redis_prefix . '_delivery_list';
            $date = array_column($list['delivery_list'], 'delivery_id', 'delivery_name');
            // 创建缓存
            $this->getRedis()->hMSet($key, $date);

            return $date;
        } else {
            throw new WechatException('物流公司列表异常');
        }
    }

    /**
     * @return bool
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function checkManaged()
    {
        $key = self::redis_prefix . '_is_trade_managed';
        if ($this->getRedis()->exists($key)) {
            return true;
        } else {
            return $this->setManaged();
        }
    }

    /**
     * @return bool
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function setManaged()
    {
        try {
            $res = $this->isManaged();
            if ($res['is_trade_managed']) {
                $key = self::redis_prefix . '_is_trade_managed';
                $this->getRedis()->set($key, $res['is_trade_managed']);
                return true;
            } else {
                return false;
            }
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 获取物流列表
     * @param $company_name
     * @return mixed|string
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     * @author 等风来
     * @email 136327134@qq.com
     * @date 2023/9/15
     */
    public function getDelivery($company_name)
    {
        $key = self::redis_prefix . '_delivery_list';
        if (!$this->getRedis()->exists($key)) {
            $date = $this->setDeliveryList();
            $express_company = $date[$company_name] ?? '';
        } else {
            $express_company = $this->getRedis()->hMGet($key, [$company_name])[$company_name] ?? '';
        }
        if (empty($express_company)) {
            $express_company = self::express_company;
        }

        return $express_company;
    }
}
