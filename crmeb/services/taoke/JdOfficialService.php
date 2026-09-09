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
    protected $pid;

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
        $this->pid       = (string) config('taoke.jd.pid');
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
     */
    public function goodsJingfen(int $eliteId = 1, int $page = 1, int $pageSize = 20): array
    {
        $data = [
            'goodsReqDTO' => [
                'eliteId'   => $eliteId,
                'pageIndex' => $page,
                'pageSize'  => $pageSize,
            ],
        ];
        return $this->call('jd.union.open.goods.jingfen.query', $data);
    }

    /**
     * 商品详情（大字段）
     * method: jd.union.open.goods.bigfield.query
     */
    public function goodsBigfield(array $skuIds, array $returnFields = []): array
    {
        $data = [
            'goodsReq' => [
                'skuIds' => array_values($skuIds),
                'returnFields' => $returnFields ?: ['skuName', 'imageInfo', 'shopInfo'],
            ],
        ];
        return $this->call('jd.union.open.goods.bigfield.query', $data);
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
            'siteId'     => $positionId ?: $this->pid,
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
