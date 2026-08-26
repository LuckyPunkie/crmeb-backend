<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use GuzzleHttp\Client;
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
        $this->setHttpClient(new Client([
            'timeout' => 45,
            'connect_timeout' => 10,
            'verify' => false,
        ]));
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
    
    public function taobaoLiveGoods(int $page = 1, int $pageSize = 20): array
    {
        $params = [
            'page_no' => $page,
            'page_size' => $pageSize,
            'material_id' => '117935',
            'final_promotion_target_type' => 10,
        ];
        return $this->request('tbk/material_recommend', $params, 'POST');
    }

    public function taobaoGoodsSearch(int $page = 1, int $pageSize = 20,$q='',$cate=0): array
    {
        $params = [
            'page_no' => $page,
            'page_size' => $pageSize,
            'q' => $q,
            'has_coupon' => 'false',
        ];
        if ((int)$cate > 0) {
            $params['cat'] = (int)$cate;
        }
        $result = $this->request('tbk/super_search_material', $params, 'POST');
        if (!empty($result)) {
            return $result;
        }
        // 超级搜索超时/失败时回退物料推荐，避免列表空白
        Log::warning('淘宝超级搜索无结果，回退物料推荐', [
            'keyword' => $q,
            'page' => $page,
        ]);
        return $this->taobaoGoods($page, $pageSize);
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
     * 淘宝客商品详情（优先 item_detailinfo；失败则高佣转链 + 标题搜索，并用主图/多图补详情长图）
     */
    public function taobaoGoodsDetail(string $goodsId, string $title = ''): array
    {
        $goodsId = trim($goodsId);
        $title = trim($title);
        if ($goodsId === '') {
            return [];
        }

        $detailInfo = $this->fetchTaobaoItemDetailInfo($goodsId);
        if (!empty($detailInfo['desc_images']) || !empty($detailInfo['desc_img'])) {
            return $this->ensureTaobaoDescImages($detailInfo);
        }

        $privilege = $this->request('tbk/id_privilege', ['id' => $goodsId], 'POST');
        if (!empty($privilege['itemInfo']) || !empty($privilege['item_url'])) {
            $merged = $this->ensureTaobaoDescImages(
                $this->mergeTaobaoDetailImages(
                    $this->buildTaobaoDetailFromPrivilege($privilege, $goodsId),
                    $detailInfo
                )
            );
            if ($this->isUsableTaobaoDetail($merged)) {
                return $merged;
            }
        }

        $fallback = $this->fetchTaobaoDetailFallback($goodsId, $title);
        if ($this->isUsableTaobaoDetail($fallback)) {
            return $this->ensureTaobaoDescImages(
                $this->mergeTaobaoDetailImages($fallback, $detailInfo)
            );
        }

        if ($this->isUsableTaobaoDetail($detailInfo)) {
            return $this->ensureTaobaoDescImages($detailInfo);
        }

        return $fallback;
    }

    protected function fetchTaobaoDetailFallback(string $goodsId, string $title = ''): array
    {
        if ($title !== '') {
            $search = $this->taobaoGoodsSearch(1, 1, $title, 0);
            if (!empty($search[0]) && is_array($search[0]) && $this->isUsableTaobaoDetail($search[0])) {
                if (empty($search[0]['item_id'])) {
                    $search[0]['item_id'] = $goodsId;
                }
                return $search[0];
            }
        }

        if (!ctype_digit($goodsId)) {
            $search = $this->taobaoGoodsSearch(1, 1, $goodsId, 0);
            if (!empty($search[0]) && is_array($search[0]) && $this->isUsableTaobaoDetail($search[0])) {
                if (empty($search[0]['item_id'])) {
                    $search[0]['item_id'] = $goodsId;
                }
                return $search[0];
            }
            return [];
        }

        $result = $this->request('tbk/item_info', [
            'num_iids' => $goodsId,
        ], 'POST');
        if (isset($result[0]) && is_array($result[0])) {
            return $result[0];
        }
        return is_array($result) ? $result : [];
    }

    protected function buildTaobaoDetailFromPrivilege(array $privilege, string $goodsId): array
    {
        $itemInfo = is_array($privilege['itemInfo'] ?? null) ? $privilege['itemInfo'] : [];
        $smallImages = $itemInfo['small_images'] ?? [];
        $descImages = $this->extractTaobaoSmallImages($smallImages);
        $pictUrl = (string)($itemInfo['pict_url'] ?? '');
        if ($pictUrl !== '') {
            array_unshift($descImages, $pictUrl);
        }
        $descImages = array_values(array_unique(array_filter($descImages)));

        return [
            'item_id' => $privilege['item_id'] ?? $goodsId,
            'item_basic_info' => [
                'title' => $itemInfo['title'] ?? '',
                'pict_url' => $pictUrl,
                'small_images' => $smallImages,
                'nick' => $itemInfo['nick'] ?? '',
                'shop_title' => $itemInfo['nick'] ?? '',
                'user_type' => $itemInfo['user_type'] ?? '',
                'volume' => $itemInfo['volume'] ?? 0,
                'tk_total_sales' => $itemInfo['volume'] ?? 0,
            ],
            'price_promotion_info' => [
                'final_promotion_price' => $itemInfo['qh_final_price'] ?? ($itemInfo['zk_final_price'] ?? ''),
                'reserve_price' => $itemInfo['reserve_price'] ?? '',
                'zk_final_price' => $itemInfo['zk_final_price'] ?? '',
            ],
            'desc_images' => $descImages,
            'desc_img' => $descImages,
            'item_url' => $privilege['item_url'] ?? '',
            'coupon_tpwd' => $privilege['coupon_tpwd'] ?? '',
            'item_tpwd' => $privilege['item_tpwd'] ?? '',
            'long_item_tpwd' => $privilege['long_item_tpwd'] ?? '',
        ];
    }

    protected function extractTaobaoSmallImages($smallImages): array
    {
        if (is_array($smallImages)) {
            if (isset($smallImages['string']) && is_array($smallImages['string'])) {
                return array_values(array_filter($smallImages['string']));
            }
            return array_values(array_filter($smallImages, 'is_string'));
        }
        return [];
    }

    protected function ensureTaobaoDescImages(array $detail): array
    {
        $descImages = [];
        if (!empty($detail['desc_images']) && is_array($detail['desc_images'])) {
            $descImages = $detail['desc_images'];
        } elseif (!empty($detail['desc_img']) && is_array($detail['desc_img'])) {
            $descImages = $detail['desc_img'];
        }

        if (empty($descImages)) {
            $basic = $detail['item_basic_info'] ?? [];
            $descImages = $this->extractTaobaoSmallImages($basic['small_images'] ?? ($detail['small_images'] ?? []));
            $pictUrl = (string)($detail['pict_url'] ?? ($basic['pict_url'] ?? ''));
            if ($pictUrl !== '') {
                array_unshift($descImages, $pictUrl);
            }
            $descImages = array_values(array_unique(array_filter($descImages)));
            if (!empty($descImages)) {
                $detail['desc_images'] = $descImages;
                $detail['desc_img'] = $descImages;
            }
        }

        return $detail;
    }

    protected function fetchTaobaoItemDetailInfo(string $goodsId): array
    {
        $detail = $this->request('tbk/item_detailinfo', [
            'id' => $goodsId,
        ], 'POST');
        return is_array($detail) ? $detail : [];
    }

    protected function isUsableTaobaoDetail(array $detail): bool
    {
        if (empty($detail)) {
            return false;
        }
        if (!empty($detail['desc_images']) && is_array($detail['desc_images'])) {
            return true;
        }
        if (!empty($detail['desc_img']) && is_array($detail['desc_img'])) {
            return true;
        }
        $title = trim((string)($detail['title'] ?? ($detail['item_basic_info']['title'] ?? '')));
        $image = trim((string)($detail['pict_url'] ?? ($detail['item_basic_info']['pict_url'] ?? '')));
        return $title !== '' || $image !== '';
    }

    protected function mergeTaobaoDetailImages(array $primary, array $detailInfo): array
    {
        if (empty($detailInfo)) {
            return $primary;
        }
        $descImages = $detailInfo['desc_images'] ?? [];
        if (empty($descImages) || !is_array($descImages)) {
            $descImages = $detailInfo['desc_img'] ?? [];
        }
        if (!empty($descImages) && is_array($descImages)) {
            $primary['desc_images'] = $descImages;
            if (empty($primary['desc_img'])) {
                $primary['desc_img'] = $descImages;
            }
        }
        foreach (['title', 'pict_url', 'small_images', 'volume', 'nick', 'zk_final_price', 'reserve_price', 'item_id'] as $field) {
            if (empty($primary[$field]) && !empty($detailInfo[$field])) {
                $primary[$field] = $detailInfo[$field];
            }
        }
        return $primary;
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
        $page = max(1, (int)$page);
        $pageSize = max(1, (int)$pageSize);
        $params = [
            'offset' => ($page - 1) * $pageSize,
            'limit' => $pageSize,
        ];
        if ((int)$cate > 0) {
            $params['activity_tags'] = '[' . (int)$cate . ']';
        }
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
        $eliteIds = $cate > 0 ? [(int)$cate] : [2, 1, 3, 4];
        foreach ($eliteIds as $eliteId) {
            $raw = $this->request('jd/material_query', [
                'pageIndex' => max(1, $page),
                'pageSize' => $pageSize,
                'eliteId' => $eliteId,
            ], 'POST');
            $rows = $this->normalizeJdMaterialRows($raw);
            if (!empty($rows)) {
                return $rows;
            }
        }
        return [];
    }

    protected function normalizeJdMaterialRows($raw): array
    {
        if (!is_array($raw) || $raw === []) {
            return [];
        }
        if (isset($raw['list']) && is_array($raw['list'])) {
            return array_values(array_filter($raw['list'], 'is_array'));
        }
        if (isset($raw['goodsList']) && is_array($raw['goodsList'])) {
            return array_values(array_filter($raw['goodsList'], 'is_array'));
        }
        if (isset($raw[0]) && is_array($raw[0])) {
            return $raw;
        }
        if (isset($raw['itemId']) || isset($raw['skuName'])) {
            return [$raw];
        }
        return [];
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

    /**
     * 京东关键词搜索
     */
    public function jdGoodsSearch(string $keyword, int $page = 1, int $pageSize = 20): array
    {
        $raw = $this->request('jd/query', [
            'keyword' => $keyword,
            'pageIndex' => $page,
            'pageSize' => $pageSize,
        ], 'POST');
        $rows = $this->normalizeJdMaterialRows($raw);
        if (!empty($rows)) {
            return $rows;
        }
        return $this->jdGoods($page, $pageSize, 0);
    }

    /**
     * 抖音精选联盟商品搜索
     */
    public function douyinGoodsSearch(string $title = '', int $page = 1, int $pageSize = 20): array
    {
        $page = max(1, (int)$page);
        $pageSize = max(1, min(20, (int)$pageSize));
        $title = trim($title);
        if ($title === '') {
            $title = '热销';
        }
        $params = [
            'page' => $page,
            'page_size' => $pageSize,
            'search_type' => 1,
            'sort_type' => 1,
            'title' => $title,
        ];
        return $this->request('douyin/product_search', $params, 'POST');
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
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('订单侠API请求异常', [
                'url' => $url,
                'params' => $params,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }



}
