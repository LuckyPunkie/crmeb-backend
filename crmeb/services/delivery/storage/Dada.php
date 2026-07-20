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
namespace crmeb\services\delivery\storage;

use crmeb\basic\BaseStorage;
use crmeb\interfaces\DeliveryInterface;
use think\exception\ValidateException;
use think\facade\Log;

class Dada extends BaseStorage implements DeliveryInterface
{
    const BASE_URL = 'https://newopen.imdada.cn';
    // const BASE_URL = 'https://newopen.qa.imdada.cn';

    const ADD_MERCHANT = '/merchantApi/merchant/add';
    // 添加门店
    const ADD_SHOP = '/api/shop/add';
    // 更新门店
    const UPDATE_SHOP = '/api/shop/update';
    // 获取城市编码列表
    const GET_CITY_CODE = '/api/cityCode/list';
    // 获取门店详情
    const GET_SHOP_DETAIL = '/api/shop/detail';
    // 计算订单价格
    const GET_ORDER_PRICE = '/api/order/queryDeliverFee';
    // 发布订单
    const ADD_ORDER_AFTER_QUERY = '/api/order/addAfterQuery';
    // 订单查询
    const ADD_ORDER_STSATUS_QUERY = '/api/order/status/query';
    // 获取取消原因列表
    const GET_REASONS = '/api/order/cancel/reasons';
    // 取消订单
    const CANCEL_ORDER = '/api/order/formalCancel';
    // 获取余额
    const GET_BALANCE = '/api/balance/query';
    // 充值余额
    const GET_RECHARGE = '/api/recharge';
    // 获取配送员轨迹信息
    const DEIVER_TRACK = '/api/order/transporter/position';

    public $config;

    public function initialize(array $config)
    {
        // $config['app_key'] = 'dadaf3b5d27430a6e64';
        // $config['app_secret'] = 'a457576705673ee60e109ed9fc4c5d1e';
        // $config['source_id'] = '2111832530';

        $this->config = $config;
    }
    /**
     * 创建商户
     *
     * @param array $data
     * @return void
     */
    public function addMerchant($data)
    {
        return $this->sendRequest(self::ADD_MERCHANT, $data);
    }
    /**
     * 创建门店
     *
     * @param array $data
     * @return void
     */
    public function addShop($data)
    {
        $parmas = [];

        if (!($data['lng']) || !($data['lat']))
            throw new ValidateException('经纬度不能为空');
        if (!($data['phone']) || !($data['contact_name']))
            throw new ValidateException('联系人信息不能为空');
        if (!($data['business']))
            throw new ValidateException('配送物品分类不能为空');
        if (!($data['station_name']) || !$data['station_address'])
            throw new ValidateException('门店信息不能为空');
        $parmas = [
            'lng' => (float)$data['lng'],
            'lat' => (float)$data['lat'],
            'phone' => $data['phone'],
            'business' => (int)$data['business'],
            'contact_name' => $data['contact_name'],
            'station_name' => $data['station_name'],
            'station_address' => $data['station_address'],
            'status' => 1,
            'origin_shop_id' => $data['origin_shop_id'],
        ];
        if (isset($data['username']) && $data['username']) $parmas['username'] = $data['username'];
        if (isset($data['password']) && $data['password']) $parmas['password'] = $data['password'];

        return $this->sendRequest(self::ADD_SHOP, [$parmas]);
    }
    /**
     * 更新门店
     *
     * @param array $data
     * @return void
     */
    public function updateShop($data)
    {
        $params['origin_shop_id'] = $data['origin_shop_id'];
        if (isset($data['new_shop_id'])) $params['new_shop_id'] = $data['new_shop_id'];
        if (isset($data['station_name'])) $params['station_name'] = $data['station_name'];
        if (isset($data['business'])) $params['business'] = $data['business'];
        if (isset($data['station_address'])) $params['station_address'] = $data['station_address'];
        if (isset($data['lng'])) $params['lng'] = $data['lng'];
        if (isset($data['lat'])) $params['lat'] = $data['lat'];
        if (isset($data['contact_name'])) $params['contact_name'] = $data['contact_name'];
        if (isset($data['phone'])) $params['phone'] = $data['phone'];
        if (isset($data['status'])) $params['status'] = $data['status'];
        return $this->sendRequest(self::UPDATE_SHOP, $params);
    }
    /**
     * 预发布订单
     *
     * @param array $data
     * @return void
     */
    public function addOrder($data)
    {
        $params = [
            'deliveryNo' => $data['deliveryNo'],
        ];
        return $this->sendRequest(self::ADD_ORDER_AFTER_QUERY, $params);
    }
    /**
     * 计算订单价格
     *
     * @param array $data
     * @return void
     */
    public function getOrderPrice($data)
    {
        $params = [
            'shop_no'         => $data['shop_no'],
            'origin_id'       => $data['origin_id'],
            'city_code'       => $data['city_code'],
            'cargo_price'     => $data['cargo_price'],
            'is_prepay'       => $data['is_prepay'],
            'receiver_name'   => $data['receiver_name'],
            'receiver_address' => $data['receiver_address'],
            'callback'        => $data['callback_url'],
            'cargo_weight'    => $data['cargo_weight'],
            'receiver_phone'  => $data['receiver_phone'],
            'is_finish_code_needed' => $data['is_finish_code_needed'],
            'receiver_lat' => $data['receiver_lat'],
            'receiver_lng' => $data['receiver_lng'],
        ];
        return $this->sendRequest(self::GET_ORDER_PRICE, $params);
    }
    /**
     * 获取订单详情
     *
     * @param array $data
     * @return void
     */
    public function getOrderDetail($data)
    {
        $params['order_id'] = $data['origin_id'];
        return $this->sendRequest(self::ADD_ORDER_STSATUS_QUERY, $params);
    }
    /**
     * 配送员位置
     *
     * @param array $data
     * @return void
     */
    public function getDeliveryTrack($data)
    {
        $params['orderIds'] = [$data['origin_id']];
        $res = $this->sendRequest(self::DEIVER_TRACK, $params);
        
        return [
            'lng' => $res['transporterLng'],
            'lat' => $res['transporterLat'],
            'driverMobile' => $res['transporterPhone'],
            'driverName' => $res['transporterName'],
            'orderCode' => '',
            'originId' => $res['orderId'],
        ];
    }
    /**
     * 取消订单
     *
     * @param array $data
     * @return void
     */
    public function cancelOrder($data)
    {
        $params['order_id'] = $data['origin_id'];
        $params['cancel_reason'] = $data['cancel_reason'] ?? '无';
        $params['cancel_reason_id'] = $data['reason'];
        return $this->sendRequest(self::CANCEL_ORDER, $params);
    }
    /**
     * 获取充值地址
     *
     * @param array $data
     * @return void
     */
    public function getRecharge($data = [])
    {
        $params = [
            'amount' => $data['amount'] ?? 100,
            'category' => $data['category'] ?? 'PC',
        ];
        return $this->sendRequest(self::GET_RECHARGE, $params);
    }
    /**
     * 获取余额
     *
     * @param array $data
     * @return void
     */
    public function getBalance($data)
    {
        $params['category'] = $data['category'] ?? 3;
        $res = $this->sendRequest(self::GET_BALANCE, $params);
        return [
            'deliverBalance' => $res['deliverBalance']
        ];
    }
    /**
     * 支付小费
     *
     * @param array $data
     * @return void
     */
    public function addTip($data)
    {
        return true;
    }
    /**
     * 取消原因
     *
     * @param string $data
     * @return void
     */
    public function reasons($data = '')
    {
        $options = $this->sendRequest(self::GET_REASONS, $data);
        foreach ($options as $option) {
            $value = $option['id'];
            $label = $option['reason'];
            $res[] = compact('value', 'label');
        }
        return $res;
    }
    /**
     * 获取城市信息
     *
     * @param string $data
     * @return void
     */
    public function getCity($data = '')
    {
        $res = $this->sendRequest(self::GET_CITY_CODE, $data);
        foreach ($res as $item) {
            $name = $item['cityName'];
            $data[$name] = [
                'key' => $name,
                'label' => $name,
            ];
        }
        $data = array_values($data);

        return $data;
    }
    /**
     * 获取门店详情
     *
     * @param string $id
     * @return void
     */
    public function getShopDetail($id)
    {
        $data = ['origin_shop_id' => $id];
        return $this->sendRequest(self::GET_SHOP_DETAIL, $data);
    }
    /**
     * 配送物品分类
     *
     * @return void
     */
    public function getBusiness()
    {
        return [
            ['key' => 1, 'label' => '食品小吃'],
            ['key' => 2, 'label' => '饮料'],
            ['key' => 3, 'label' => '鲜花绿植'],
            ['key' => 5, 'label' => '其他'],
            ['key' => 8, 'label' => '文印票务'],
            ['key' => 9, 'label' => '便利店'],
            ['key' => 13, 'label'  => '水果生鲜'],
            ['key' => 19, 'label'  => '同城电商'],
            ['key' => 20, 'label'  => '医药'],
            ['key' => 21, 'label'  => '蛋糕'],
            ['key' => 24, 'label'  => '酒品'],
            ['key' => 25, 'label'  => '小商品市场'],
            ['key' => 26, 'label'  => '服装'],
            ['key' => 27, 'label'  => '汽修零配'],
            ['key' => 28, 'label'  => '数码家电'],
            ['key' => 29, 'label'  => '小龙虾/烧烤'],
            ['key' => 31, 'label'  => '超市'],
            ['key' => 51, 'label'  => '火锅'],
            ['key' => 53, 'label'  => '个护美妆'],
            ['key' => 55, 'label'  => '母婴'],
            ['key' => 57, 'label'  => '家居家纺'],
            ['key' => 59, 'label'  => '手机'],
            ['key' => 61, 'label'  => '家装'],
            ['key' => 63, 'label'  => '成人用品'],
        ];
    }
    /**
     * 发送请求
     *
     * @param string $api
     * @param array $params
     * @return void
     */
    public function sendRequest($api, $params)
    {
        $url = self::BASE_URL . $api;
        $params = $this->bulidRequestParams($params);
        $response = $this->httpRequestWithPost($url, $params);
        $data = $this->getMessage($response);
        return $data;
    }
    /**
     * 构造请求数据
     *
     * @param array $params
     * @return void
     */
    public function bulidRequestParams($params)
    {
        $requestParams = array();
        $requestParams['app_key'] = $this->config['app_key'];
        $requestParams['body'] = json_encode($params);
        $requestParams['format'] = 'json';
        $requestParams['v'] = '1.0';
        $requestParams['source_id'] = $this->config['source_id'];
        $requestParams['timestamp'] = time();
        $requestParams['signature'] = $this->_sign($requestParams);
        return json_encode($requestParams);
    }
    /**
     * 签名生成signature
     *
     * @param array $data
     * @return void
     */
    public function _sign($data)
    {
        //1.升序排序
        ksort($data);
        //2.字符串拼接
        $args = "";
        foreach ($data as $key => $value) {
            $args .= $key . $value;
        }
        $args = $this->config['app_secret'] . $args . $this->config['app_secret'];
        //3.MD5签名,转为大写
        $sign = strtoupper(md5($args));
        return $sign;
    }
    /**
     * 发送请求,POST
     *
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return void
     */
    public function httpRequestWithPost($url, $data, $headers = [])
    {
        $headers = array(
            'Content-Type: application/json',
        );
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_TIMEOUT, 3);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        $resp = curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        if (isset($info['http_code']) && $info['http_code'] == 200) {
            return $resp;
        }
        return;
    }
    /**
     * 获取错误信息
     *
     * @param $json
     * @param string $message
     * @return void
     */
    protected function getMessage($json, $message = '接口请求异常,请稍后重试')
    {
        $data = json_decode($json, true);
        if(!$data) {
            Log::info('【达达错误提示】:' . $message);
            throw new ValidateException('【达达错误提示】:' . $message);
        }

        if ($data['code'] !== 0) {
            isset($data['msg']) && $message = $data['msg'];
            if ($data['errorCode'] == 7718) {
                foreach ($data['result']['failedList'] as $datum) {
                    $message .= ':' . $datum['shopName'] . '/' . $datum['msg'] . ';';
                }
            }
            Log::info('【达达错误提示】:' . $message . ', 错误码:' . $data['errorCode']);
            throw new ValidateException('【达达错误提示】:' . $message);
        } else {
            if ($data['status'] == 'success') return $data['result'] ?? $data;
            return $data;
        }
    }
}
