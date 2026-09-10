<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use GuzzleHttp\Client;
use think\facade\Log;

/**
 * 淘宝联盟（淘宝客）官方直连服务
 * 网关: https://eco.taobao.com/router/rest
 * 签名: MD5( appSecret + ksort(拼k v k v ...) + appSecret ) 大写
 * 文档: https://open.taobao.com/api.htm
 */
class TaobaoOfficialService extends BaseServices
{
    protected $apiUrl;
    protected $appKey;
    protected $appSecret;
    protected $adzoneId;
    protected $pid;
    protected $session;

    public function __construct()
    {
        parent::__construct('taobao', [], 'taoke');
        $this->setHttpClient(new Client([
            'timeout' => 30,
            'connect_timeout' => 8,
            'verify' => false,
        ]));
        $this->apiUrl    = 'https://eco.taobao.com/router/rest';
        $this->appKey    = (string) config('taoke.taobao.appkey');
        $this->appSecret = (string) config('taoke.taobao.appsecret');
        $this->session   = (string) config('taoke.taobao.session');
        $this->pid       = (string) config('taoke.taobao.pid');
        $this->adzoneId  = (string) config('taoke.taobao.adzone_id');

        // pid 格式 mm_uid_siteid_adzoneid，若 adzone 未单独配则从 pid 拆出
        if ($this->adzoneId === '' && $this->pid !== '') {
            $parts = explode('_', $this->pid);
            if (count($parts) >= 4) {
                $this->adzoneId = $parts[3];
            }
        }
    }

    /**
     * 商品简版详情 (SDK 里存在: TbkItemInfoGetRequest)
     * method: taobao.tbk.item.info.get
     */
    public function itemInfo(string $numIids): array
    {
        return $this->call('taobao.tbk.item.info.get', [
            'num_iids'  => $numIids,
            'platform'  => '2',
        ]);
    }

    /**
     * 淘口令 (SDK 里存在: TbkTpwdCreateRequest)
     * method: taobao.tbk.tpwd.create
     */
    public function tpwdCreate(string $text, string $url, string $logo = ''): array
    {
        $params = [
            'text' => $text,
            'url'  => $url,
        ];
        if ($logo !== '') $params['logo'] = $logo;
        return $this->call('taobao.tbk.tpwd.create', $params);
    }

    /**
     * 物料精选推荐（对应订单侠 tbk/material_recommend，爆款 material_id=86589）
     * method: taobao.tbk.dg.material.recommend
     */
    public function materialRecommend(int $materialId = 86589, int $page = 1, int $pageSize = 20): array
    {
        return $this->call('taobao.tbk.dg.material.recommend', [
            'material_id' => $materialId,
            'page_no'     => $page,
            'page_size'   => $pageSize,
            'adzone_id'   => $this->adzoneId,
        ]);
    }

    /**
     * 物料库/官方推荐 (SDK 里存在: TbkDgOptimusMaterialRequest)
     * method: taobao.tbk.dg.optimus.material
     * material_id 常用值: 6708 猜你喜欢 / 3756 精选 / 6707 淘抢购
     */
    public function optimusMaterial(int $materialId = 3756, int $page = 1, int $pageSize = 20): array
    {
        return $this->call('taobao.tbk.dg.optimus.material', [
            'material_id' => $materialId,
            'page_no'     => $page,
            'page_size'   => $pageSize,
            'adzone_id'   => $this->adzoneId,
        ]);
    }

    /**
     * 关键词搜索（需权限包 16516 物料搜索；用 upgrade 版，旧版 optional 已限权）
     * method: taobao.tbk.dg.material.optional.upgrade
     */
    public function materialOptional(string $q, int $page = 1, int $pageSize = 20): array
    {
        return $this->call('taobao.tbk.dg.material.optional.upgrade', [
            'q'          => $q,
            'page_no'    => $page,
            'page_size'  => $pageSize,
            'adzone_id'  => $this->adzoneId,
            'platform'   => '2',
        ]);
    }

    /**
     * 高佣转链 (SDK 里疑似缺失: TbkDgMaterialOptionalRequest 也能返回但独立方法更明确)
     * method: taobao.tbk.privilege.get
     */
    public function privilegeGet(string $numIid, string $adzoneId = ''): array
    {
        return $this->call('taobao.tbk.privilege.get', [
            'item_id'   => $numIid,
            'adzone_id' => $adzoneId ?: $this->adzoneId,
            'platform'  => '2',
        ], true);
    }

    /**
     * 订单查询
     * method: taobao.tbk.order.details.get
     */
    public function orderDetailsGet(string $startTime, string $endTime, int $span = 600, int $page = 1, int $pageSize = 100, int $queryType = 1): array
    {
        return $this->call('taobao.tbk.order.details.get', [
            'start_time' => $startTime,
            'end_time'   => $endTime,
            'span'       => $span,
            'page_no'    => $page,
            'page_size'  => $pageSize,
            'query_type' => $queryType,
        ], true);
    }

    /**
     * 从 TOP 响应中提取商品行数组
     */
    public function parseRows(array $response): array
    {
        if (isset($response['error_response']) || !is_array($response)) {
            return [];
        }
        foreach ($response as $key => $inner) {
            if (!is_array($inner) || strpos((string) $key, '_response') === false) {
                continue;
            }
            if (isset($inner['result_list']['map_data']) && is_array($inner['result_list']['map_data'])) {
                return $inner['result_list']['map_data'];
            }
            if (isset($inner['results']['n_tbk_item']) && is_array($inner['results']['n_tbk_item'])) {
                return $inner['results']['n_tbk_item'];
            }
            if (isset($inner['results']['u_tbk_item']) && is_array($inner['results']['u_tbk_item'])) {
                return $inner['results']['u_tbk_item'];
            }
            if (isset($inner['result']['data']) && is_array($inner['result']['data'])) {
                return [$inner['result']['data']];
            }
        }
        return [];
    }

    /**
     * 物料推荐列表（轮询 material_id，权限未开时返回空）
     */
    public function fetchFeed(int $page = 1, int $pageSize = 20, int $materialId = 0): array
    {
        $materialIds = $materialId > 0 ? [(int) $materialId] : [86589, 3756, 6708];
        foreach ($materialIds as $mid) {
            $rows = $this->parseRows($this->materialRecommend($mid, $page, $pageSize));
            if (!empty($rows)) {
                return $rows;
            }
            $rows = $this->parseRows($this->optimusMaterial($mid, $page, $pageSize));
            if (!empty($rows)) {
                return $rows;
            }
        }
        return [];
    }

    /**
     * 关键词搜索（空词回落物料推荐）
     */
    public function fetchSearch(string $keyword, int $page = 1, int $pageSize = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return $this->fetchFeed($page, $pageSize);
        }
        return $this->parseRows($this->materialOptional($keyword, $page, $pageSize));
    }

    /**
     * 商品详情：数字 num_iid 走 item.info；加密 item_id 优先 summary，再搜索兜底
     */
    public function fetchDetail(string $goodsId, string $title = '', array $summary = []): array
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '') {
            return [];
        }

        if ($this->hasSummary($summary)) {
            return [$this->buildDetailFromSummary($summary, $goodsId)];
        }

        if (ctype_digit($goodsId)) {
            $rows = $this->parseRows($this->itemInfo($goodsId));
            if (!empty($rows)) {
                return $rows;
            }
        }

        $q = trim($title) !== '' ? trim($title) : $goodsId;
        $rows = $this->fetchSearch($q, 1, 1);
        if (!empty($rows[0]) && is_array($rows[0])) {
            if (empty($rows[0]['item_id'])) {
                $rows[0]['item_id'] = $goodsId;
            }
            return [$rows[0]];
        }

        return [];
    }

    /**
     * 高佣转链原始响应
     */
    public function fetchPrivilege(string $itemId): array
    {
        return $this->call('taobao.tbk.privilege.get', [
            'item_id'   => $itemId,
            'adzone_id' => $this->adzoneId,
            'platform'  => '2',
        ], true);
    }

    protected function hasSummary(array $summary): bool
    {
        return trim((string) ($summary['title'] ?? ($summary['store_name'] ?? ($summary['image'] ?? '')))) !== '';
    }

    protected function buildDetailFromSummary(array $summary, string $goodsId): array
    {
        $image = (string) ($summary['image'] ?? '');
        if ($image !== '' && strpos($image, '//') === 0) {
            $image = 'https:' . $image;
        }
        return [
            'item_id' => $goodsId,
            'item_basic_info' => [
                'title' => (string) ($summary['title'] ?? ($summary['store_name'] ?? '')),
                'pict_url' => $image,
                'volume' => (int) ($summary['sales'] ?? 0),
                'annual_vol' => (string) ($summary['sales_text'] ?? ($summary['annual_vol'] ?? '')),
            ],
            'price_promotion_info' => [
                'final_promotion_price' => (string) ($summary['price'] ?? '0.00'),
                'reserve_price' => (string) ($summary['ot_price'] ?? '0.00'),
            ],
        ];
    }

    public function getOAuthCallbackUrl(): string
    {
        return (string) (config('taoke.taobao.oauth_callback')
            ?: 'https://0626tbcs.ohlegend.com/api/taoke/oauth/taobao/callback');
    }

    /**
     * 生成 OAuth 授权页 URL（Server-side: response_type=code）
     */
    public function buildAuthorizeUrl(string $state = 'taoke'): string
    {
        $query = http_build_query([
            'response_type' => 'code',
            'client_id'     => $this->appKey,
            'redirect_uri'  => $this->getOAuthCallbackUrl(),
            'state'         => $state,
            'view'          => 'web',
        ]);
        return 'https://oauth.taobao.com/authorize?' . $query;
    }

    /**
     * 用授权码换取 access_token（即 TAOBAO_SESSION）
     */
    public function exchangeAuthorizationCode(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['error' => 'empty_code'];
        }
        try {
            $response = $this->httpClient->post('https://oauth.taobao.com/token', [
                'form_params' => [
                    'grant_type'    => 'authorization_code',
                    'code'          => $code,
                    'client_id'     => $this->appKey,
                    'client_secret' => $this->appSecret,
                    'redirect_uri'  => $this->getOAuthCallbackUrl(),
                ],
            ]);
            $raw = (string) $response->getBody();
            $result = json_decode($raw, true);
            return is_array($result) ? $result : ['error' => 'invalid_token_response', 'raw' => $raw];
        } catch (\Throwable $e) {
            Log::error('淘宝 OAuth 换 token 失败', ['msg' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    // ==================== 底层 ====================

    protected function call(string $method, array $bizData, bool $requireSession = false): array
    {
        if ($this->appKey === '' || $this->appSecret === '') {
            throw new \think\exception\ValidateException('淘宝联盟 appKey/appSecret 未配置');
        }
        if ($requireSession && $this->session === '') {
            Log::warning('淘宝联盟API缺少session', ['method' => $method]);
            return ['error_response' => ['code' => 26, 'msg' => 'Missing session', 'sub_msg' => 'TAOBAO_SESSION 未配置']];
        }

        $sysParams = [
            'method'      => $method,
            'app_key'     => $this->appKey,
            'timestamp'   => date('Y-m-d H:i:s'),
            'format'      => 'json',
            'v'           => '2.0',
            'sign_method' => 'md5',
        ];
        if ($this->session !== '') {
            $sysParams['session'] = $this->session;
        }
        $allParams = array_merge($sysParams, $bizData);
        $allParams['sign'] = $this->sign($allParams);

        try {
            $response = $this->httpClient->post($this->apiUrl, [
                'form_params' => $allParams,
            ]);
            $raw = (string) $response->getBody();
            $result = json_decode($raw, true);
            if (!is_array($result)) {
                Log::error('淘宝联盟API响应解析失败', ['method' => $method, 'raw' => $raw]);
                return [];
            }
            if (isset($result['error_response'])) {
                Log::warning('淘宝联盟API业务错误', [
                    'method' => $method,
                    'error'  => $result['error_response'],
                ]);
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error('淘宝联盟API请求异常', [
                'method' => $method,
                'msg'    => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 淘宝 TOP 签名: MD5( appSecret + ksort(拼k v k v ...) + appSecret ), 大写
     * 排除 sign 本身和 @开头的文件参数
     */
    protected function sign(array $params): string
    {
        ksort($params);
        $str = $this->appSecret;
        foreach ($params as $k => $v) {
            if ($k === 'sign') continue;
            if (is_array($v)) continue;
            if (strlen((string)$v) > 0 && substr((string)$v, 0, 1) === '@') continue;
            $str .= $k . $v;
        }
        $str .= $this->appSecret;
        return strtoupper(md5($str));
    }
}
