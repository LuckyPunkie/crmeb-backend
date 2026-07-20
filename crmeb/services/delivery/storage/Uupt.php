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
use think\facade\Db;

class Uupt extends BaseStorage implements DeliveryInterface
{
    // 域名
    const BASE_URL = 'https://api-open.uupt.com';
    // const BASE_URL = 'http://api-open.test.uupt.com';
    // 发布订单
    const ADD_ORDER = '/openapi/v3/order/storeAddOrder';
    // 计算价格
    const GET_ORDER_PRICE = '/openapi/v3/order/storeOrderPrice';
    // 详情
    const GET_ORDER_DETAIL = '/openapi/v3/order/orderDetail';
    // 充值
    const GET_RECHARGE = '/openapi/v3/user/recharge';
    // 取消
    const CANCEL_ORDER = '/openapi/v3/order/cancelOrder';
    // 余额
    const GET_BALANCEDE = '/openapi/v3/user/account';
    // 添加门店
    const ADD_SHOP = '/openapi/v3/store/addStore';
    // 更新门店
    const UPDATE_SHOP = '/openapi/v3/store/editStore';
    // 配送员位置
    const DEIVER_TRACK = '/openapi/v3/order/driverTrack';
    // 取件码
    const PICKUP_CODE = '/openapi/v3/order/syncPickupCode';
    // 获取城市编码列表
    const GET_CITY_CODE = '/openapi/v3/order/openCityList';

    public $config;

    public function initialize(array $config)
    {
        // 沙箱环境配置
        // $config['app_id'] = '9e7b00d7a5d5406fb24f56709518bc6b';
        // $config['open_id'] = '910a0dfd12bb4bc0acec147bcb1ae246';
        // $config['app_key'] = '6ba86f556a984c299b68dd43ba92bbaf';

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
        return true;
    }
    /**
     * 创建门店
     *
     * @param array $data
     * @return void
     */
    public function addShop($data)
    {
        if (!($data['lng']) || !($data['lat'])) {
            throw new ValidateException('【UU跑腿提示错误】:经纬度不能为空');
        }
        if (!($data['phone']) || !($data['contact_name'])) {
            throw new ValidateException('【UU跑腿提示错误】:联系人信息不能为空');
        }
        if (!($data['business'])) {
            throw new ValidateException('【UU跑腿提示错误】:配送物品分类不能为空');
        }
        if (!($data['station_name']) || !$data['station_address']) {
            throw new ValidateException('【UU跑腿提示错误】:门店信息不能为空');
        }

        $params['storeNo'] = $data['origin_shop_id'];
        $params['storeName'] = $data['station_name'];
        $params['contactName'] = $data['contact_name'];
        $params['phone'] = $data['phone'];
        $params['locationX'] = (float)$data['lng'];
        $params['locationY'] = (float)$data['lat'];
        $params['stationAddress'] = $data['station_address'];
        $params['businessCate'] = $data['business'];
        $params['cityName'] = $data['city_name'] ?? '';
        $params['coordinateType'] = 0;

        return $this->sendRequest(self::ADD_SHOP, $params);
    }
    /**
     * 更新门店
     *
     * @param array $data
     * @return void
     */
    public function updateShop($data)
    {
        if (!($data['lng']) || !($data['lat'])) {
            throw new ValidateException('【UU跑腿提示错误】:经纬度不能为空');
        }
        if (!($data['phone']) || !($data['contact_name'])) {
            throw new ValidateException('【UU跑腿提示错误】:联系人信息不能为空');
        }
        if (!($data['business'])) {
            throw new ValidateException('【UU跑腿提示错误】:配送物品分类不能为空');
        }
        if (!($data['station_name']) || !$data['station_address']) {
            throw new ValidateException('【UU跑腿提示错误】:门店信息不能为空');
        }

        $params['storeNo'] = $data['origin_shop_id'];
        $params['storeName'] = $data['station_name'];
        $params['contactName'] = $data['contact_name'];
        $params['phone'] = $data['phone'];
        $params['locationX'] = (float)$data['lng'];
        $params['locationY'] = (float)$data['lat'];
        $params['stationAddress'] = $data['station_address'];
        $params['businessCate'] = $data['business'];
        $params['cityName'] = $data['city_name'];
        $params['coordinateType'] = 0;

        return $this->sendRequest(self::UPDATE_SHOP, $params);
    }
    /**
     * 发布订单
     *
     * @param array $data
     * @return void
     */
    public function addOrder($data)
    {
        $params['storeNo'] = $data['shop_no']; // 门店编号
        $params['priceToken'] = $data['priceToken']; // 金额令牌，计算订单价格接口返回的price_token
        $params['receiver'] = $data['receiver']; // 收件人名称
        $params['receiverPhone'] = $data['receiver_phone']; // 收件人电话
        $params['callbackUrl'] = $data['callback_url']; // 回调地址
        $params['pushType'] = $data['push_type']; // 推送方式
        $params['orderSource'] = 'OTHER'; // 订单来源,默认其他
        $params['specialType'] = $data['special_type']; // 是否需要保温箱
        $params['payType'] = 'BALANCE_PAY'; // 支付方式
        $params['note'] = $data['note']; // 订单备注

        return $this->sendRequest(self::ADD_ORDER, $params);
    }
    /**
     * 同步取件码
     *
     * @param array $data
     * @return void
     */
    public function getPickupCode($data)
    {
        $params['originId'] = $data['order_sn'];
        $params['orderCode'] = $data['order_code'];
        $params['pickupCode'] = $this->createPickupCode();

        $this->sendRequest(self::PICKUP_CODE, $params);

        return $params['pickupCode'];
    }
    /**
     * 配送员位置
     *
     * @param array $data
     * @return void
     */
    public function getDeliveryTrack($data)
    {
        $params['originId'] = $data['origin_id'];
        $params['orderCode'] = $data['order_code'];

        $res = $this->sendRequest(self::DEIVER_TRACK, $params);

        [$lng, $lat] = explode(',', $res['driverLastLoc']);
        return [
            'lng' => $lng,
            'lat' => $lat,
            'driverMobile' => $res['driverMobile'],
            'driverName' => $res['driverName'],
            'orderCode' => $res['orderCode'],
            'originId' => $res['originId'],
        ];
    }
    /**
     * 计算订单价格
     *
     * @param array $data
     * @return void
     */
    public function getOrderPrice($data)
    {
        $params['storeNo'] = $data['shop_no']; // 门店编号
        $params['originId'] = $data['origin_id']; // 三方对接平台订单ID
        $params['sendType'] = 'SEND'; // 订单小类，参考枚举值
        $params['toAddress'] = $data['to_address']; // 收货人地址
        $params['toLat'] = $data['to_lat']; // 收货地址坐标纬度(坐标系为百度地图坐标系)
        $params['toLng'] = $data['to_lng']; // 收货地址坐标经度(坐标系为百度地图坐标系)
        $params['goodsType'] = 'OTHER'; // 物品类型，参考物品类型枚举
        $params['goodsWeight'] = $data['cargo_weight']; // 货物重量（单位KG）

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
        if($data['order_sn'] && $data['order_code']) {
            throw new ValidateException('【UU跑腿提示错误】:订单号和UU跑腿订单号不能同时为空');
        }

        $params['originId'] = $data['order_sn'];
        $params['orderCode'] = $data['order_code'];

        return $this->sendRequest(self::GET_ORDER_DETAIL, $params);
    }
    /**
     * 取消订单
     *
     * @param array $data
     * @return void
     */
    public function cancelOrder($data)
    {
        $params['originId']  = $data['origin_id'];
        $params['orderCode'] = $data['order_code'];
        $params['reason'] = $data['reason'] ? $data['reason'] : '用户取消';

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
        return $this->sendRequest(self::GET_RECHARGE, $data);
    }
    /**
     * 获取余额
     *
     * @param array $data
     * @return void
     */
    public function getBalance($data)
    {
        $params['payTypeEnum'] = 'BALANCE_PAY';

        $res = $this->sendRequest(self::GET_BALANCEDE, $params);
        return ['deliverBalance' => $res['balance']];
    }
    /**
     * 添加小费
     *
     * @param array $data
     * @return void
     */
    public function addTip($data)
    {
        return true;
    }
    /**
     * 获取城市支持列表
     *
     * @param array $data
     * @return void
     */
    public function getCity($data = "")
    {
        $res = $this->sendRequest(self::GET_CITY_CODE, $data);

        $openCityList = $res['openCityList'];
        foreach ($openCityList as $item) {
            $name = $item['cityName'] ? $item['cityName'] : $item['countyName'];
            $data[$name] = [
                'key' => $name,
                'label' => $name,
            ];
            
        }
        $data = array_values($data);

        return $data;
    }
    /**
     * 获取配送物品分类
     *
     * @param array $data
     * @return void
     */
    public function getBusiness()
    {
        return [
            ["key" => 10000000, "label" => "餐饮服务"],
            ["key" => 11000000, "label" => "商超百货"],
            ["key" => 12000000, "label" => "垂直零售"],
            ["key" => 13000000, "label" => "电子产品"],
            ["key" => 14000000, "label" => "鲜花绿植"],
            ["key" => 15000000, "label" => "果蔬生鲜"],
            ["key" => 16000000, "label" => "服装纺织"],
            ["key" => 17000000, "label" => "医药保健"],
            ["key" => 18000000, "label" => "文体办公"],
            ["key" => 19000000, "label" => "家居建材"],
            ["key" => 20000000, "label" => "汽修配件"],
            ["key" => 21000000, "label" => "公司企业"],
            ["key" => 22000000, "label" => "生活服务"],
            ["key" => 23000000, "label" => "其他行业"]
        ];
    }
    /**
     * 发送请求
     *
     * @param string $api
     * @param array $params
     * @return void
     */
    public function sendRequest($api, $params = [])
    {
        $url = self::BASE_URL . $api;
        $requestParams = $this->bulidRequestParams($params);
        $response = $this->httpRequestWithPost($url, json_encode($requestParams));
        $data = $this->getMessage($response);
        return $data['body'];
    }
    /**
     * 构建请求参数
     *
     * @param array $params
     * @return array
     */
    public function bulidRequestParams(array $params = [])
    {
        $requestParams['biz'] = $params ? json_encode($params) : '';
        $requestParams['openid'] = $this->config['open_id'];
        $requestParams['timestamp'] = time();
        $requestParams['sign'] = $this->_sign($requestParams);

        return $requestParams;
    }
    /**
     * 生成签名
     *
     * @param array $params
     * @return string
     */
    public function _sign($params)
    {
        ksort($params);
        $str = $params['biz'] . $this->config['app_key'] . $params['timestamp'];
        return strtoupper(md5($str));
    }
    /**
     * 发送POST请求
     *
     * @param string $url
     * @param string $data
     * @return void
     */
    public function httpRequestWithPost($url, $data)
    {
        $appId = $this->config['app_id'];
        $headers = array(
            "Content-Type: application/json",
            "X-App-Id: {$appId}"
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
        return $resp;
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
        if (!$data) {
            Log::info('【UU跑腿提示错误】:' . $message);
            throw new ValidateException('【UU跑腿提示错误】:' . $message);
        }
        if ($data['code'] !== 1) {
            Log::info('【UU跑腿提示错误】:' . $data['msg']);
            throw new ValidateException('【UU跑腿提示错误】:' . $data['msg']);
        }

        return $data;
    }

    /**
     * 生成取件码四位数
     * 
     * @return string
     */
    public function createPickupCode()
    {
        return str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }
}
