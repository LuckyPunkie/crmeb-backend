<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use think\facade\Log;

/**
 * 聚推客API服务
 * 文档地址: https://www.jutuike.com/document
 */
class JuTuiKeService extends BaseServices
{
    /**
     * @var string API地址
     */
    protected $apiUrl;

    /**
     * @var string AppKey
     */
    protected $appKey;

    public function __construct()
    {
        parent::__construct('jutuike', [], 'taoke');
        $this->apiUrl = config('taoke.jutuike.api_url');
        $this->appKey = config('taoke.jutuike.appkey');
    }

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
            Log::error('聚推客API请求失败', [
                'url' => $url,
                'error' => '聚推客APPKEY未配置，请在.env文件中设置JUTUIKE_APPKEY'
            ]);
            throw new \think\exception\ValidateException('聚推客APPKEY未配置');
        }

        // 检查apiUrl是否配置
        if (empty($this->apiUrl)) {
            Log::error('聚推客API请求失败', [
                'url' => $url,
                'error' => '聚推客API地址未配置'
            ]);
            throw new \think\exception\ValidateException('聚推客API地址未配置');
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

            if ($result['code'] != 1) {
                Log::error('聚推客API请求失败', [
                    'url' => $url,
                    'params' => $params,
                    'response' => $result
                ]);
                return [];
            }

            return $result['data'] ?? [];
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            Log::error('聚推客API请求异常', [
                'url' => $url,
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \think\exception\ValidateException('聚推客API请求失败: ' . $e->getMessage());
        } catch (\Exception $e) {
            Log::error('聚推客API请求异常', [
                'url' => $url,
                'params' => $params,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \think\exception\ValidateException('聚推客API请求异常: ' . $e->getMessage());
        }
    }

    /**
     * 获取联盟活动列表
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getActivityList(string $cate_name='',int $page = 1, int $limit = 20): array
    {
        return $this->request('union/act_list', [
            'page' => $page,
            'pageSize' => $limit,
            'xcx_spread' => 1,
            'cate_name' => $cate_name
        ]);
    }

    /**
     * 活动转链
     * @param string $activityId
     * @param string $pid
     * @return array
     */
    public function activityUnion(string $activityId): array
    {
        $sid = $this->generateSid();
        return $this->request('union/act', [
            'act_id' => $activityId,
            'sid' => '1234'
        ]);
    }
    
    /**
     * 生成跟单自定义参数sid
     * 格式：毫秒时间戳 + 4位随机数，保证唯一性
     * @return string
     */
    private function generateSid(): string
    {
        [$usec, $sec] = explode(' ', microtime());
        $timestamp = $sec . substr($usec, 2, 3);
        return $timestamp . str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /**
     * 获取活动订单
     * @param string $activityId
     * @param string $startTime
     * @param string $endTime
     * @param int $page
     * @return array
     */
    public function getJutuikeOrder(string $startTime, string $endTime, string $status='',string $order_sn='',int $page = 1, int $limit = 20): array
    {
        $data = [
            'page' => $page,
            'pageSize' => $limit,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'sid' => '1234'
        ];
        if ($status != '') {
            $data['status'] = $status;
        }
        if (!empty($order_sn)) {
            $data['order_sn'] = $order_sn;
        }
        return $this->request('union/orders', $data);
    }

    /**
     * 淘宝高佣转链
     * @param string $goodsId
     * @param string $pid
     * @param string $relationId
     * @return array
     */
    public function taobaoHighCommission(string $goodsId, string $pid, string $relationId = ''): array
    {
        return $this->request('tb/gao_yong', [
            'goods_id' => $goodsId,
            'pid' => $pid,
            'relation_id' => $relationId
        ]);
    }

    /**
     * 淘宝商品搜索
     * @param string $keyword
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function taobaoGoodsSearch(string $keyword, int $page = 1, int $limit = 20): array
    {
        return $this->request('tb/goods_search', [
            'keyword' => $keyword,
            'page' => $page,
            'limit' => $limit
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
    public function taobaoOrderQuery(string $startTime, string $endTime, int $page = 1, int $limit = 100): array
    {
        return $this->request('tb/order_query', [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 京东商品查询
     * @param string $goodsId
     * @param string $pid
     * @return array
     */
    public function jdGoodsDetail(string $goodsId, string $pid): array
    {
        return $this->request('jd/goods_detail', [
            'goods_id' => $goodsId,
            'pid' => $pid
        ]);
    }

    /**
     * 京东订单查询
     * @param string $startTime
     * @param string $endTime
     * @param int $page
     * @return array
     */
    public function jdOrderQuery(string $startTime, string $endTime, int $page = 1): array
    {
        return $this->request('jd/order_query', [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'page' => $page
        ]);
    }

    /**
     * 拼多多商品查询
     * @param string $goodsId
     * @param string $pid
     * @return array
     */
    public function pddGoodsDetail(string $goodsId, string $pid): array
    {
        return $this->request('pdd/goods_detail', [
            'goods_id' => $goodsId,
            'pid' => $pid
        ]);
    }

    /**
     * 拼多多订单查询
     * @param string $startTime
     * @param string $endTime
     * @param int $page
     * @return array
     */
    public function pddOrderQuery(string $startTime, string $endTime, int $page = 1): array
    {
        return $this->request('pdd/order_query', [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'page' => $page
        ]);
    }


  

    // ==================== 电商聚合接口 ====================

    /**
     * 淘宝商品详情（完整版）
     * @param string $goodsId 商品ID
     * @param string $pid 推广位PID
     * @return array
     */
    public function taobaoGoodsDetailFull(string $goodsId, string $pid): array
    {
        return $this->request('tb/goods_detail_full', [
            'goods_id' => $goodsId,
            'pid' => $pid
        ]);
    }

    /**
     * 淘宝优惠券查询
     * @param string $goodsId 商品ID
     * @param string $pid 推广位PID
     * @return array
     */
    public function taobaoCouponQuery(string $goodsId, string $pid): array
    {
        return $this->request('tb/coupon_query', [
            'goods_id' => $goodsId,
            'pid' => $pid
        ]);
    }

    /**
     * 京东精选商品
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function jdSelection(int $page = 1, int $limit = 20): array
    {
        return $this->request('jd/selection', [
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 京东9.9包邮
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function jdNineNineGoods(int $page = 1, int $limit = 20): array
    {
        return $this->request('jd/nine_nine', [
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 京东秒杀商品
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function jdSeckill(int $page = 1, int $limit = 20): array
    {
        return $this->request('jd/seckill', [
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 拼多多商品搜索
     * @param string $keyword 关键词
     * @param int $page 页码
     * @param int $limit 每页数量
     * @return array
     */
    public function pddGoodsSearchFull(string $keyword, int $page = 1, int $limit = 20): array
    {
        return $this->request('pdd/goods_search', [
            'keyword' => $keyword,
            'page' => $page,
            'limit' => $limit
        ]);
    }

    /**
     * 拼多多爆款推荐
     * @param int $limit 数量限制
     * @return array
     */
    public function pddHotRecommend(int $limit = 20): array
    {
        return $this->request('pdd/hot_recommend', [
            'limit' => $limit
        ]);
    }

    /**
     * 唯品会商品搜索
     * @param string $keyword 关键词
     * @param int $page 页码
     * @return array
     */
    public function vipGoodsSearch(string $keyword, int $page = 1): array
    {
        return $this->request('vip/goods_search', [
            'keyword' => $keyword,
            'page' => $page
        ]);
    }

    /**
     * 唯品会商品详情
     * @param string $goodsId 商品ID
     * @param string $pid 推广位PID
     * @return array
     */
    public function vipGoodsDetailFull(string $goodsId, string $pid): array
    {
        return $this->request('vip/goods_detail', [
            'goods_id' => $goodsId,
            'pid' => $pid
        ]);
    }

    /**
     * 唯品会订单查询
     * @param string $startTime 开始时间
     * @param string $endTime 结束时间
     * @param int $page 页码
     * @return array
     */
    public function vipOrderQuery(string $startTime, string $endTime, int $page = 1): array
    {
        return $this->request('vip/order_query', [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'page' => $page
        ]);
    }

    /**
     * 唯品会活动列表
     * @param int $page 页码
     * @return array
     */
    public function vipActivityList(int $page = 1): array
    {
        return $this->request('vip/activity_list', [
            'page' => $page
        ]);
    }

  
}
