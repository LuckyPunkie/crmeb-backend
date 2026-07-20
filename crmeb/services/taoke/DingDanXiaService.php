<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use think\facade\Log;

/**
 * 订单侠API服务
 * 文档地址: https://www.dingdanxia.com/apidocument
 */
class DingDanXiaService extends BaseServices
{
    /**
     * @var string API地址
     */
    protected $apiUrl;

    /**
     * @var string AppKey
     */
    protected $appKey;

    /**
     * @var string AppSecret
     */
    protected $appSecret;

    public function __construct()
    {
        parent::__construct('dingdanxia', [], 'taoke');
        $this->apiUrl = config('taoke.dingdanxia.api_url');
        $this->appKey = config('taoke.dingdanxia.appkey');
    }
    
    public function taobaoGoods(int $page = 1, int $pageSize = 20): array
    {
        $params = [
            'page_no' => $page,
            'page_size' => $pageSize,
            'material_id' => '86589',//爆款
        ];
        return $this->request('tbk/material_recommend', $params, 'POST');
    }
    
    public function taobaoGoodsSearch(int $page = 1, int $pageSize = 20,$q='',$cate=0): array
    {
        $params = [
            'page_no' => $page,
            'page_size' => $pageSize,
            'q' => $q,//搜索
        ];
        return $this->request('tbk/super_search_material', $params, 'POST');
    }
    
    /**
     * 淘宝高佣转链（通过商品ID）
     * @param string $goodsId
     * @param string $pid
     * @param string $relationId
     * @return array
     */
    public function taobaoHighCommission(string $goodsId,$relation_id): array
    {
        return $this->request('tbk/id_privilege', [
            'id' => $goodsId,
            'relation_id' => $relation_id
        ]);
    }
     /**
     * 淘宝订单查询
     * @param string $startTime
     * @param string $endTime
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function taobaoOrderQuery(int $page = 1, int $limit = 20, string $startTime = '', string $endTime = ''): array
    {
        return $this->request('tbk/order_details', [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'page_no' => $page,
            'page_size' => $limit
        ],'POST');
    }
    
    /**
     * 拼多多活动标签列表
     * @return array
     */
    public function pdd_tags(): array
    {
        return $this->request('pdd/activity_tags', [], 'GET');
    }

    //拼多多商品
    public function pddGoods(int $page = 1, int $pageSize = 20, $cate = 0): array
    {
        $params = [
            'offset' => $page,
            'limit' => $pageSize,
            'activity_tags' => '[' . $cate . ']'

        ];
        return $this->request('pdd/recommend', $params, 'POST');
    }
     //京东商品
    public function pddGoodsDetail($goods_sign): array
    {
        $params = [
            'goods_sign' => $goods_sign

        ];
        return $this->request('pdd/goods_detail2', $params, 'POST');
    }
    /**
     * 拼多多高佣转链（通过商品ID）
     * @param string $goodsId
     * @param string $pid
     * @param string $relationId
     * @return array
     */
    public function pddHighCommission(string $goods_sign,string $pid,string $custom_parameters): array
    {
        return $this->request('pdd/convert', [
            'goods_sign' => $goods_sign,
            'p_id' => $pid,
            'custom_parameters' => $custom_parameters,
            'generate_we_app' => 'true'
        ]);
    }
    public function pddPromUrlGenerate(string $pid,string $pdd_custom_parameters): array
    {
        return $this->request('pdd/prom_url_generate', [
            'p_id_list' => $pid,
            'custom_parameters' => $pdd_custom_parameters,
            'channel_type'=>10
        ]);
    }
    public function createPddPid(): array
    {
        return $this->request('pdd/pidgenerate', [
            'number'=>1
        ]);
    }
    
    //京东商品
    public function jdGoods(int $page = 1, int $pageSize = 20, int $cate = 0): array
    {
        $params = [
            'pageIndex' => $page,
            'pageSize' => $pageSize,
            'eliteId' => 4
            
        ];
        return $this->request('jd/material_query', $params, 'POST');
    }
     //京东商品
    public function jdGoodsDetail($itemIds): array
    {
        $params = [
            'itemIds' => $itemIds,
            'sceneId' => 1,
            
        ];
        return $this->request('jd/item_detail', $params, 'POST');
    }
    public function createJdPid(): array
    {
        $params = [
            'type' => 1,
            'unionType' => 1,
            'spaceNameList' => 'gj',
            'siteId' => 'BXTDvTMIUac1TvAKEQj11TvAKEQj1T_3klNtUx5wmDT9j4yO9'
            
        ];
        return $this->request('jd/create_position', $params, 'POST');
    }
    
    /**
     * 京东高佣转链（通过商品ID）
     * @param string $goodsId
     * @param string $pid
     * @param string $relationId
     * @return array
     */
    public function jdHighCommission(string $materialUrl,int $pid = 0): array
    {
        return $this->request('jd/url_privilege', [
            'materialId' => $materialUrl
        ]);
    }
    
      //唯品会商品
    public function wphGoods($keyword,int $page = 1, int $pageSize = 20): array
    {
        $params = [
            'page' => $page,
            'pageSize' => $pageSize,
            'keyword' => $keyword
            
        ];
        return $this->request('vip/query', $params, 'POST');
    }
    
     //唯品会商品
    public function vipGoodsDetail(int $goods_id): array
    {
        $params = [
            'id' => $goods_id

        ];
        return $this->request('vip/item_info', $params, 'POST');
    }
     //京东商品
    public function vipHighCommission(int $goods_id): array
    {
        $params = [
            'id' => $goods_id

        ];
        return $this->request('vip/id_privilege', $params, 'POST');
    }
    
    
    
    /*订单相关*/
     //淘宝
    public function taobaoOrder(string $start_time,string $end_time,string $status,int $page,int $limit): array
    {
        $params = [
            'page_no' => $page,
            'page_size' => $limit,
            'start_time' => $start_time,
            'end_time' => $end_time
        ];
        if ($status != '') {
            $params['tk_status'] = $status;
        }
        return $this->request('tbk/order_details', $params, 'POST');
    }
    
     //pdd
    public function pddOrder(string $start_time,string $end_time,int $page,int $limit): array
    {
        $params = [
            'page' => $page,
            'page_size' => $limit,
            'start_update_time' => $start_time,
            'end_update_time' => $end_time
        ];
        return $this->request('pdd/orderlist', $params, 'POST');
    }
    public function pddOrderDetail(string $order_sn): array
    {
        $params = [
            'order_sn' => $order_sn
        ];
        return $this->request('pdd/order_detail', $params, 'POST');
    }
    //  jd
    public function jdOrder(string $start_time,string $end_time,string $orderId,int $page,int $limit): array
    {
        $params = [
            'pageIndex' => $page,
            'pageSize' => $limit,
            'startTime' => $start_time,
            'ndTime' => $end_time,
            'type' => 1
        ];
        if (!empty($orderId)) {
            $params['orderId'] = $orderId;
        }
        return $this->request('jd/order_details2', $params, 'POST');
    }
    
    // //vip
    public function vipOrder(string $start_time,string $end_time,string $status,int $page,int $limit): array
    {
        $params = [
            'page' => $page,
            'pageSize' => $limit,
            'orderTimeStart' => $start_time,
            'orderTimeEnd' => $end_time
        ];
        if ($status != '') {
            $params['status'] = $status;
        }
        return $this->request('vip/order_details2', $params, 'POST');
    }
    public function vipOrderDetail(string $orderSn): array
    {
        $params = [
            'orderSn' => $orderSn
        ];
        return $this->request('vip/order_details2', $params, 'POST');
    }
    
    
    /**
     * 
    
     /**
     * 发送HTTP请求
     * @param string $url
     * @param array $params
     * @return array
     */
    protected function request(string $url, array $params = [], string $method = 'GET'): array
    {
        // var_dump('密钥===='.$this->appKey);
        // var_dump('接口===='.$this->apiUrl . $url);
        // 检查appkey是否配置
        if (empty($this->appKey)) {
            Log::error('订单侠API请求失败', [
                'url' => $url,
                'error' => '订单侠APPKEY未配置，请在.env文件中设置APIKEY'
            ]);
            throw new \think\exception\ValidateException('订单侠APIKEY未配置');
        }

        // 检查apiUrl是否配置
        if (empty($this->apiUrl)) {
            Log::error('订单侠API请求失败', [
                'url' => $url,
                'error' => '订单侠API地址未配置'
            ]);
            throw new \think\exception\ValidateException('订单侠API地址未配置');
        }

        $params['apikey'] = $this->appKey;

        try {
            $method = strtoupper($method);
            if ($method === 'POST') {
                $response = $this->httpClient->post($this->apiUrl . $url, [
                    'form_params' => $params
                ]);
            } else {
                $response = $this->httpClient->get($this->apiUrl . $url, [
                    'query' => $params
                ]);
            }
            //$response = $this->httpClient->get($this->apiUrl . $url, ['query' => $params]);

            $result = json_decode($response->getBody()->getContents(), true);
            //var_dump($result);

            // 检查JSON解析是否成功
            if (!is_array($result)) {
                Log::error('订单侠API响应解析失败', [
                    'url' => $url,
                    'params' => $params,
                    'response' => $response->getBody()->getContents()
                ]);
                return [];
            }

            if (($result['code'] ?? 0) != 200) {
                Log::error('订单侠API请求失败', [
                    'url' => $url,
                    'params' => $params,
                    'response' => $result
                ]);
                return [];
            }

            return $result['data'] ?? [];
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            Log::error('订单侠API请求异常', [
                'url' => $url,
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \think\exception\ValidateException('订单侠API请求失败: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('订单侠API请求异常', [
                'url' => $url,
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \think\exception\ValidateException('订单侠API请求异常: ' . $e->getMessage());
        }
    }



}
