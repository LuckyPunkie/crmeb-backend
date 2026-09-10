<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use GuzzleHttp\Client;
use think\facade\Log;

/**
 * 京东联盟官方直连服务
 * 网关: https://api.jd.com/routerjson
 * 签名: MD5( appSecret + ksort(拼k v k v ...) + appSecret ) 大写
 * 文档: https://union.jd.com/openplatform/api
 */
class JdOfficialService extends BaseServices
{
    protected $apiUrl;
    protected $appKey;
    protected $appSecret;
    protected $unionId;
    protected $siteId;      // 媒体ID (pid 第2段, promotion.common.get 的 siteId)
    protected $pid;         // 推广位ID (第3段, 如 3108306777)
    protected $fullPid;     // 完整pid字符串 "unionId_siteId_positionId"

    const JSON_PARAM_KEY = '360buy_param_json';

    public function __construct()
    {
        parent::__construct('jd', [], 'taoke');
        $this->setHttpClient(new Client([
            'timeout' => 30,
            'connect_timeout' => 8,
            'verify' => false,
        ]));
        $this->apiUrl    = config('taoke.jd.api_url') ?: 'https://api.jd.com/routerjson';
        $this->appKey    = (string) config('taoke.jd.appkey');
        $this->appSecret = (string) config('taoke.jd.secret');
        $this->unionId   = (string) config('taoke.jd.unionid');
        $this->siteId    = (string) config('taoke.jd.site_id');
        $this->pid       = (string) config('taoke.jd.pid');
        $this->fullPid   = (string) config('taoke.jd.full_pid');
        if ($this->fullPid !== '' && $this->siteId === '') {
            $parts = explode('_', $this->fullPid);
            if (count($parts) >= 2) {
                $this->siteId = $parts[1];
            }
        }
    }

    /**
     * 关键词搜索商品
     * method: jd.union.open.goods.query
     */
    public function goodsQuery(string $keyword, int $page = 1, int $pageSize = 20, array $extra = []): array
    {
        $data = array_merge([
            'goodsReqDTO' => array_merge([
                'keyword'  => $keyword,
                'pageIndex' => $page,
                'pageSize' => $pageSize,
                'sortName' => 'inOrderCount30DaysSku',
                'sort'     => 'desc',
            ], $extra),
        ]);
        return $this->call('jd.union.open.goods.query', $data);
    }

    /**
     * 京粉精选/物料库
     * method: jd.union.open.goods.jingfen.query
     * 注意：业务入参键名为 goodsReq（不是 goodsReqDTO），pid 为三段式 unionId_siteId_positionId
     */
    public function goodsJingfen(int $eliteId = 1, int $page = 1, int $pageSize = 20): array
    {
        $req = [
            'eliteId'   => $eliteId,
            'pageIndex' => $page,
            'pageSize'  => $pageSize,
        ];
        if ($this->fullPid !== '') {
            $req['pid'] = $this->fullPid;
        }
        return $this->call('jd.union.open.goods.jingfen.query', ['goodsReq' => $req]);
    }

    /**
     * 商品详情（大字段）
     * method: jd.union.open.goods.bigfield.query
     * @param int $sceneId 场景ID：1=常规推广，2=微信/QQ推广，其他视账号权限
     */
    public function goodsBigfield(array $skuIds, int $sceneId = 1, array $returnFields = []): array
    {
        // JD 要求 sceneId 放顶层，也在 goodsReq 里冗余一份
        $data = [
            'sceneId' => $sceneId,
            'goodsReq' => [
                'skuIds' => array_values($skuIds),
                'sceneId' => $sceneId,
                'returnFields' => $returnFields ?: ['skuName', 'imageInfo', 'shopInfo'],
            ],
        ];
        return $this->call('jd.union.open.goods.bigfield.query', $data);
    }

    /**
     * 通过 skuId 查询推广商品基本信息（轻量版详情）
     * method: jd.union.open.goods.promotiongoodsinfo.query
     */
    public function goodsPromotionInfo(array $skuIds): array
    {
        $ids = implode(',', array_map('strval', array_values($skuIds)));
        return $this->call('jd.union.open.goods.promotiongoodsinfo.query', [
            'skuIds' => $ids,
        ]);
    }

    /**
     * 个性化商品推荐 / 频道物料
     * method: jd.union.open.goods.material.query
     * @param int $eliteId 频道ID：1=精选爆款 2=好券商品 3=京东超市 4=配送到家 10=秒杀 22=女神 25=PLUS 30=高佣榜单
     */
    public function goodsMaterial(int $eliteId = 2, int $page = 1, int $pageSize = 20, array $extra = []): array
    {
        $req = array_merge([
            'eliteId'  => $eliteId,
            'pageIndex' => $page,
            'pageSize' => $pageSize,
        ], $extra);
        if ($this->fullPid !== '' && !isset($req['pid'])) {
            $req['pid'] = $this->fullPid;
        }
        return $this->call('jd.union.open.goods.material.query', ['goodsReq' => $req]);
    }

    /**
     * 热销榜商品
     * method: jd.union.open.goods.rank.query
     * @param int $rankType 1=实时热销榜 2=同类热销榜
     */
    public function goodsRank(int $rankType = 1, int $page = 1, int $pageSize = 20): array
    {
        $req = [
            'rankType' => $rankType,
            'pageIndex' => $page,
            'pageSize' => $pageSize,
        ];
        $pidStr = $this->fullPid ?: $this->pid;
        if ($pidStr !== '') {
            $req['pid'] = $pidStr;
        }
        return $this->call('jd.union.open.goods.rank.query', ['rankReq' => $req]);
    }

    /**
     * 工具商专用转链（当主账号是导购媒体、promotion.common.get 拒绝时用）
     * method: jd.union.open.selling.promotion.get
     */
    public function sellingPromotion(string $materialId, string $subUnionId = ''): array
    {
        $req = [
            'materialId' => $materialId,
        ];
        if ($this->unionId !== '') {
            $req['unionId'] = (int) $this->unionId;
        }
        $pidStr = $this->fullPid ?: $this->pid;
        if ($pidStr !== '') {
            $req['pid'] = $pidStr;
        }
        if ($subUnionId !== '') {
            $req['subUnionId'] = $subUnionId;
        }
        return $this->call('jd.union.open.selling.promotion.get', ['promotionCodeReq' => $req]);
    }

    /**
     * 通用转链
     * method: jd.union.open.promotion.common.get
     * @param string $materialId 商品/落地页 URL
     * @param string $positionId 推广位ID
     * @param string $ext         subUnionId / ext1 等（回传对账用）
     */
    public function promotionCommon(string $materialId, string $positionId = '', string $ext = ''): array
    {
        $promotionCodeReq = [
            'materialId' => $materialId,
            'siteId'     => $positionId ?: ($this->siteId ?: $this->pid),
        ];
        if ($this->unionId !== '') {
            $promotionCodeReq['unionId'] = (int) $this->unionId;
        }
        if ($ext !== '') {
            $promotionCodeReq['ext1'] = $ext;
        }
        return $this->call('jd.union.open.promotion.common.get', ['promotionCodeReq' => $promotionCodeReq]);
    }

    /**
     * 订单查询
     * method: jd.union.open.order.query
     * @param int $timeType 1=下单时间 2=完成时间 3=更新时间
     */
    public function orderQuery(int $startTime, int $endTime, int $page = 1, int $pageSize = 100, int $timeType = 1): array
    {
        $data = [
            'orderReq' => [
                'pageNo'    => $page,
                'pageSize'  => $pageSize,
                'time'      => date('YmdHi', $startTime),
                'type'      => $timeType,
            ],
        ];
        return $this->call('jd.union.open.order.query', $data);
    }

    /**
     * 查询本账号已建推广位列表（低门槛诊断接口）
     * method: jd.union.open.position.query
     */
    public function positionQuery(int $type = 1, int $page = 1, int $pageSize = 20): array
    {
        $data = [
            'positionReq' => [
                'type'     => $type,          // 1=APP 2=微信/QQ 3=PC 4=无线
                'pageIndex' => $page,
                'pageSize' => $pageSize,
                'unionId'  => (int) ($this->unionId ?: 0),
            ],
        ];
        return $this->call('jd.union.open.position.query', $data);
    }

    /**
     * 查询父子账号身份（最低门槛，只要 appKey 有效就应返回）
     * method: jd.union.open.user.pid.get
     */
    public function userPidGet(): array
    {
        return $this->call('jd.union.open.user.pid.get', [
            'unionId' => (int) ($this->unionId ?: 0),
        ]);
    }

    /**
     * 类目查询（比 goods.query 门槛低）
     * method: jd.union.open.category.goods.get
     */
    public function categoryGoodsGet(int $parentId = 0, int $grade = 0): array
    {
        return $this->call('jd.union.open.category.goods.get', [
            'req' => [
                'parentId' => $parentId,
                'grade'    => $grade,
            ],
        ]);
    }

    /**
     * 解析联盟 API 业务层 payload（code=200 时返回 data 数组）
     */
    public function parseBizPayload(array $response): ?array
    {
        if (isset($response['error_response'])) {
            return null;
        }
        foreach ($response as $key => $inner) {
            if (!is_array($inner) || (substr($key, -9) !== '_response' && substr($key, -9) !== '_responce')) {
                continue;
            }
            foreach ($inner as $field => $value) {
                if (substr($field, -6) !== 'result' && substr($field, -6) !== 'Result') {
                    continue;
                }
                $parsed = is_string($value) ? json_decode($value, true) : $value;
                if (!is_array($parsed)) {
                    return null;
                }
                $code = (int) ($parsed['code'] ?? 0);
                if ($code === 200 && isset($parsed['data']) && is_array($parsed['data'])) {
                    return $parsed['data'];
                }
                return null;
            }
        }
        return null;
    }

    /**
     * 商品列表（京粉精选，轮询 eliteId）
     */
    public function fetchFeed(int $page = 1, int $pageSize = 20, int $cate = 0): array
    {
        $eliteIds = $cate > 0 ? [(int) $cate] : [22, 2, 1, 3, 10];
        foreach ($eliteIds as $eliteId) {
            $rows = $this->parseBizPayload($this->goodsJingfen($eliteId, $page, $pageSize));
            if (!empty($rows)) {
                return $rows;
            }
        }
        return [];
    }

    /**
     * 关键词搜索（需账号开通 goods.query；未开通时返回空数组）
     */
    public function fetchSearch(string $keyword, int $page = 1, int $pageSize = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return $this->fetchFeed($page, $pageSize);
        }
        $rows = $this->parseBizPayload($this->goodsQuery($keyword, $page, $pageSize));
        return $rows ?? [];
    }

    /**
     * 商品详情（京粉 itemId 回查 / 数字 skuId 走 bigfield）
     */
    public function fetchDetail($itemIds, array $summary = []): array
    {
        $ids = is_array($itemIds) ? $itemIds : preg_split('/\s*,\s*/', (string) $itemIds, -1, PREG_SPLIT_NO_EMPTY);
        $ids = array_values(array_filter(array_map('strval', $ids)));
        if (empty($ids)) {
            return [];
        }

        $targetId = $ids[0];

        // 数字 skuId：走 promotiongoodsinfo / bigfield
        if (ctype_digit($targetId)) {
            $rows = $this->parseBizPayload($this->goodsPromotionInfo($ids));
            if (!empty($rows)) {
                return $this->normalizeDetailRows($rows);
            }
            $fields = ['skuName', 'priceInfo', 'imageInfo', 'categoryInfo', 'detailImages', 'baseBigFieldInfo', 'shopInfo'];
            foreach ([1, 2] as $sceneId) {
                $rows = $this->parseBizPayload($this->goodsBigfield($ids, $sceneId, $fields));
                if (!empty($rows)) {
                    return $this->normalizeDetailRows($rows);
                }
            }
        }

        // 京粉 itemId 每次请求都会变，回查不可靠；优先用列表页传入的 summary
        if ($this->hasJdSummary($summary)) {
            $row = $this->buildDetailFromSummary($summary, $targetId);
            $found = $this->findJingfenItemByItemId($targetId);
            if (!empty($found)) {
                $row = array_replace_recursive($found, $row);
            }
            return [$row];
        }

        $row = $this->findJingfenItemByItemId($targetId);
        if (!empty($row)) {
            return [$row];
        }

        return [];
    }

    /**
     * 在京粉各频道分页查找 itemId 匹配的完整商品对象
     */
    protected function findJingfenItemByItemId(string $itemId): ?array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return null;
        }
        foreach ([22, 2, 1, 3, 10, 30] as $eliteId) {
            for ($page = 1; $page <= 5; $page++) {
                $rows = $this->parseBizPayload($this->goodsJingfen($eliteId, $page, 50));
                if (empty($rows)) {
                    break;
                }
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    if ((string) ($row['itemId'] ?? '') === $itemId) {
                        return $row;
                    }
                }
                if (count($rows) < 50) {
                    break;
                }
            }
        }
        return null;
    }

    protected function hasJdSummary(array $summary): bool
    {
        return trim((string) ($summary['title'] ?? ($summary['store_name'] ?? ($summary['image'] ?? '')))) !== '';
    }

    protected function buildDetailFromSummary(array $summary, string $itemId): array
    {
        $images = [];
        if (!empty($summary['image'])) {
            $images[] = ['url' => (string) $summary['image']];
        }
        if (!empty($summary['slider_image']) && is_array($summary['slider_image'])) {
            foreach ($summary['slider_image'] as $url) {
                if ($url) {
                    $images[] = ['url' => (string) $url];
                }
            }
        }
        return [
            'itemId' => $itemId,
            'skuName' => (string) ($summary['title'] ?? ($summary['store_name'] ?? '')),
            'goods_id' => (string) ($summary['goods_id'] ?? $itemId),
            'imageInfo' => ['imageList' => $images],
            'priceInfo' => [
                'price' => $summary['ot_price'] ?? ($summary['price'] ?? 0),
                'lowestCouponPrice' => $summary['price'] ?? 0,
            ],
            'shopInfo' => [
                'shopName' => $summary['shopName'] ?? '',
                'shopId' => $summary['shopId'] ?? '',
                'shopLevel' => $summary['shopLevel'] ?? '',
            ],
            'materialUrl' => $summary['materialUrl'] ?? '',
            'inOrderCount30Days' => $summary['sales'] ?? 0,
        ];
    }

    protected function normalizeDetailRows($rows): array
    {
        if (!is_array($rows)) {
            return [];
        }
        if (isset($rows['skuId']) || isset($rows['itemId']) || isset($rows['skuName'])) {
            return [$rows];
        }
        return array_values(array_filter($rows, 'is_array'));
    }

    // ==================== 底层 ====================

    /**
     * 统一调用入口
     */
    protected function call(string $method, array $bizData): array
    {
        if ($this->appKey === '' || $this->appSecret === '') {
            throw new \think\exception\ValidateException('京东联盟 appKey/appSecret 未配置');
        }

        $sysParams = [
            'method'      => $method,
            'app_key'     => $this->appKey,
            'timestamp'   => date('Y-m-d H:i:s'),
            'format'      => 'json',
            'v'           => '1.0',
            'sign_method' => 'md5',
            self::JSON_PARAM_KEY => $bizData,
        ];
        $sysParams['sign'] = $this->sign($sysParams);

        // 系统参数走 querystring，业务 JSON 走 form body
        $qs = [];
        foreach ($sysParams as $k => $v) {
            if ($k === self::JSON_PARAM_KEY) continue;
            $qs[$k] = $v;
        }
        $body = self::JSON_PARAM_KEY . '=' . urlencode(json_encode($bizData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        try {
            $response = $this->httpClient->post(
                $this->apiUrl . '?' . http_build_query($qs),
                [
                    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded;charset=UTF-8'],
                    'body'    => $body,
                ]
            );
            $raw = (string) $response->getBody();
            $result = json_decode($raw, true);
            if (!is_array($result)) {
                Log::error('京东联盟API响应解析失败', ['method' => $method, 'raw' => $raw]);
                return [];
            }
            // 顶层错误
            if (isset($result['error_response'])) {
                Log::warning('京东联盟API系统错误', [
                    'method' => $method,
                    'error'  => $result['error_response'],
                ]);
                return $result;
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error('京东联盟API请求异常', [
                'method' => $method,
                'msg'    => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 京东签名: MD5( appSecret + ksort(拼k v k v ...) + appSecret ), 大写
     * 业务参数 360buy_param_json 需要先 json_encode 再参与拼接
     */
    protected function sign(array $params): string
    {
        ksort($params);
        $str = $this->appSecret;
        foreach ($params as $k => $v) {
            if ($k === self::JSON_PARAM_KEY) {
                $str .= $k . json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            } else {
                $str .= $k . $v;
            }
        }
        $str .= $this->appSecret;
        return strtoupper(md5($str));
    }
}
