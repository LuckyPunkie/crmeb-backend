<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use GuzzleHttp\Client;
use think\facade\Log;

/**
 * 快手电商开放平台 / 快赚客官方直连
 * OAuth: open.kwaixiaodian.com
 * API:   openapi.kwaixiaodian.com
 */
class KuaishouOfficialService extends BaseServices
{
    protected $appKey;
    protected $appSecret;
    protected $signSecret;
    protected $accessToken;
    protected $refreshToken;
    protected $pid;
    protected $apiUrl;

    protected int $tokenRefreshLeeway = 600;

    public function __construct()
    {
        parent::__construct('kuaishou', [], 'taoke');
        $this->setHttpClient(new Client([
            'timeout' => 30,
            'connect_timeout' => 8,
            'verify' => false,
        ]));
        $this->appKey = (string) config('taoke.kuaishou.appkey');
        $this->appSecret = (string) config('taoke.kuaishou.secret');
        $this->signSecret = (string) config('taoke.kuaishou.sign_secret');
        if ($this->signSecret === '') {
            $this->signSecret = $this->appSecret;
        }
        $this->accessToken = (string) config('taoke.kuaishou.access_token');
        $this->refreshToken = (string) config('taoke.kuaishou.refresh_token');
        $this->pid = (string) config('taoke.kuaishou.pid');
        $this->apiUrl = (string) (config('taoke.kuaishou.api_url') ?: 'https://openapi.kwaixiaodian.com');
    }

    public function getOAuthCallbackUrl(): string
    {
        return (string) (config('taoke.kuaishou.oauth_callback')
            ?: 'https://0626tbcs.ohlegend.com/api/taoke/oauth/kuaishou/callback');
    }

    public function getOAuthScope(): string
    {
        return (string) (config('taoke.kuaishou.oauth_scope') ?: 'merchant_distribution');
    }

    /**
     * 跳转快手授权页
     */
    public function buildAuthorizeUrl(string $state = 'taoke'): string
    {
        $query = http_build_query([
            'app_id' => $this->appKey,
            'redirect_uri' => $this->getOAuthCallbackUrl(),
            'scope' => $this->getOAuthScope(),
            'response_type' => 'code',
            'state' => $state,
        ]);
        return 'https://open.kwaixiaodian.com/oauth/authorize?' . $query;
    }

    public function exchangeAuthorizationCode(string $code): array
    {
        $code = trim($code);
        if ($code === '') {
            return ['error' => 'empty_code', 'result' => 0];
        }
        return $this->requestOAuthToken([
            'app_id' => $this->appKey,
            'app_secret' => $this->appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ]);
    }

    public function refreshAccessToken(bool $force = false): array
    {
        $refresh = trim($this->refreshToken);
        if ($refresh === '') {
            return ['error' => 'missing_refresh_token', 'result' => 0];
        }

        $lockFile = runtime_path() . 'kuaishou_token_refresh.lock';
        $fp = @fopen($lockFile, 'c+');
        if ($fp === false) {
            Log::warning('快手 refresh 无法创建锁文件', ['path' => $lockFile]);
        } elseif (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return ['error' => 'refresh_locked', 'result' => 0];
        }

        try {
            if (!$force && !$this->isAccessTokenExpired() && $this->accessToken !== '') {
                return [
                    'result' => 1,
                    'access_token' => $this->accessToken,
                    'refresh_token' => $this->refreshToken,
                    'cached' => true,
                ];
            }

            $result = $this->requestOAuthToken([
                'app_id' => $this->appKey,
                'app_secret' => $this->appSecret,
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ]);
            if (!empty($result['access_token']) && (int) ($result['result'] ?? 0) === 1) {
                $this->applyOAuthTokenResponse($result);
            }
            return $result;
        } finally {
            if ($fp !== false) {
                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }

    public function applyOAuthTokenResponse(array $token): bool
    {
        $accessToken = trim((string) ($token['access_token'] ?? ''));
        if ($accessToken === '') {
            return false;
        }

        $expiresIn = (int) ($token['expires_in'] ?? 172800);
        $expireAt = time() + max(60, $expiresIn);
        $refresh = trim((string) ($token['refresh_token'] ?? $this->refreshToken));

        $this->accessToken = $accessToken;
        if ($refresh !== '') {
            $this->refreshToken = $refresh;
        }

        return $this->persistOAuthTokensToEnv($this->accessToken, $this->refreshToken, $expireAt);
    }

    public function ensureAccessToken(bool $required = false): bool
    {
        if ($this->accessToken !== '' && !$this->isAccessTokenExpired()) {
            return true;
        }
        if ($this->refreshToken === '') {
            return !$required && $this->accessToken !== '';
        }

        $result = $this->refreshAccessToken();
        return !empty($result['access_token']) && (int) ($result['result'] ?? 0) === 1;
    }

    protected function isAccessTokenExpired(): bool
    {
        $expireAt = (int) config('taoke.kuaishou.access_token_expire_at');
        if ($expireAt <= 0) {
            return false;
        }
        return time() >= ($expireAt - $this->tokenRefreshLeeway);
    }

    protected function requestOAuthToken(array $params): array
    {
        $endpoint = isset($params['refresh_token'])
            ? '/oauth2/refresh_token'
            : '/oauth2/access_token';

        try {
            $response = $this->httpClient->get($this->apiUrl . $endpoint, [
                'query' => $params,
            ]);
            $raw = (string) $response->getBody();
            $result = json_decode($raw, true);
            if (!is_array($result)) {
                return ['error' => 'invalid_token_response', 'raw' => $raw, 'result' => 0];
            }
            if ((int) ($result['result'] ?? 0) !== 1) {
                Log::warning('快手 OAuth token 请求失败', ['endpoint' => $endpoint, 'result' => $result]);
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error('快手 OAuth token 请求异常', [
                'endpoint' => $endpoint,
                'msg' => $e->getMessage(),
            ]);
            return ['error' => $e->getMessage(), 'result' => 0];
        }
    }

    protected function persistOAuthTokensToEnv(string $accessToken, string $refreshToken, int $expireAt): bool
    {
        $envPath = app()->getRootPath() . '.env';
        if (!is_file($envPath) || !is_writable($envPath)) {
            Log::warning('快手 OAuth 无法写入 .env', ['path' => $envPath]);
            return false;
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            return false;
        }

        $updates = [
            'KUAISHOU_ACCESS_TOKEN' => $accessToken,
            'KUAISHOU_REFRESH_TOKEN' => $refreshToken,
            'KUAISHOU_ACCESS_TOKEN_EXPIRE_AT' => (string) $expireAt,
        ];

        foreach ($updates as $key => $value) {
            $quoted = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
            $pattern = '/^(' . preg_quote($key, '/') . '\s*=\s*)(?:"[^"]*"|\S*)?\s*$/m';
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, '$1' . $quoted, $content, 1);
            } elseif (preg_match('/(\[TAOKE\][\s\S]*?)(\r?\n\[)/', $content, $m, PREG_OFFSET_CAPTURE)) {
                $insertPos = $m[1][1] + strlen($m[1][0]);
                $prefix = ($insertPos > 0 && !in_array(substr($content, $insertPos - 1, 1), ["\n", "\r"], true))
                    ? "\n"
                    : '';
                $insert = $prefix . $key . ' = ' . $quoted . "\n";
                $content = substr($content, 0, $insertPos) . $insert . substr($content, $insertPos);
            } else {
                Log::warning('快手 OAuth .env 缺少 [TAOKE] 段，无法写入', ['key' => $key]);
                return false;
            }
        }

        $ok = file_put_contents($envPath, $content) !== false;
        if ($ok) {
            Log::info('快手 OAuth token 已写入 .env', [
                'expire_at' => $expireAt,
                'expire_at_human' => date('Y-m-d H:i:s', $expireAt),
            ]);
        }
        return $ok;
    }

    /**
     * 分销 OpenAPI（HMAC_SHA256 + param JSON）
     *
     * @return array{result?:int,data?:mixed,error_msg?:string,sub_msg?:string}
     */
    public function callDistributionApi(string $method, string $httpPath, array $param): array
    {
        if ($this->appKey === '' || $this->signSecret === '') {
            throw new \think\exception\ValidateException('快手 appKey/signSecret 未配置');
        }
        if (!$this->ensureAccessToken(true)) {
            return ['result' => 0, 'error_msg' => 'missing_or_expired_access_token'];
        }

        $path = '/' . ltrim($httpPath, '/');
        $paramJson = json_encode($param, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (int) round(microtime(true) * 1000);
        $meta = [
            'method' => $method,
            'appkey' => $this->appKey,
            'access_token' => $this->accessToken,
            'signMethod' => 'HMAC_SHA256',
            'version' => 1,
            'timestamp' => $timestamp,
            'param' => $paramJson,
        ];
        $sign = $this->signOpenApi($meta);
        $body = [
            'method' => $method,
            'access_token' => $this->accessToken,
            'signMethod' => 'HMAC_SHA256',
            'version' => 1,
            'timestamp' => $timestamp,
            'param' => $paramJson,
            'sign' => $sign,
        ];

        try {
            $url = rtrim($this->apiUrl, '/') . $path . '?appkey=' . rawurlencode($this->appKey);
            $response = $this->httpClient->post($url, [
                'form_params' => $body,
            ]);
            $raw = (string) $response->getBody();
            $result = json_decode($raw, true);
            if (!is_array($result)) {
                Log::error('快手 API 响应解析失败', ['method' => $method, 'raw' => $raw]);
                return [];
            }
            if ((int) ($result['result'] ?? 0) !== 1) {
                Log::warning('快手 API 业务错误', [
                    'method' => $method,
                    'result' => $result,
                ]);
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error('快手 API 请求异常', [
                'method' => $method,
                'msg' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 站外分销选品列表（快赚客官方 catalog）
     * method: open.distribution.cps.kwaimoney.selection.item.list
     * 文档入参：pageIndex、pageSize、planType(必填)、channelId(List<Long>)、keyword 等
     */
    public function fetchSearch(string $keyword, int $page = 1, int $pageSize = 20, int $channelId = 0, array $rangeList = []): array
    {
        $pageSize = max(1, min(200, $pageSize));
        $page = max(1, $page);
        $param = [
            'pageIndex' => $page <= 1 ? '' : (string) $page,
            'pageSize' => $pageSize,
            'planType' => (int) (config('taoke.kuaishou.plan_type') ?: 1),
        ];
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $param['keyword'] = $keyword;
        }
        if ($channelId > 0) {
            $param['channelId'] = [(int) $channelId];
        }
        if ($rangeList !== []) {
            $param['rangeList'] = $rangeList;
        }
        $raw = $this->callDistributionApi(
            'open.distribution.cps.kwaimoney.selection.item.list',
            '/open/distribution/cps/kwaimoney/selection/item/list',
            $param
        );
        return $this->extractItemList($raw);
    }

    public function fetchFeed(int $page = 1, int $pageSize = 20, int $channelId = 0, array $rangeList = []): array
    {
        return $this->fetchSearch('', $page, $pageSize, $channelId, $rangeList);
    }

    /**
     * method: open.distribution.cps.kwaimoney.selection.item.detail
     * 文档入参：itemId List&lt;Long&gt; 必填，最多 10 个（勿用 goodsId+cpsPid，会返回无关推荐列表）
     *
     * @param array<string, mixed> $context 可选 relItemId 等，用于组装 itemId 列表
     */
    public function fetchDetail(string $goodsId, array $context = []): array
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '') {
            return [];
        }
        $itemIds = [];
        $pushId = function ($id) use (&$itemIds) {
            $id = trim((string) $id);
            if ($id !== '' && ctype_digit($id)) {
                $itemIds[(int) $id] = (int) $id;
            }
        };
        $pushId($goodsId);
        foreach (['goodsId', 'goods_id', 'relItemId', 'rel_item_id', 'itemId', 'item_id'] as $key) {
            if (isset($context[$key])) {
                $pushId($context[$key]);
            }
        }
        if ($itemIds === []) {
            return [];
        }
        $param = [
            'itemId' => array_slice(array_values($itemIds), 0, 10),
        ];
        $raw = $this->callDistributionApi(
            'open.distribution.cps.kwaimoney.selection.item.detail',
            '/open/distribution/cps/kwaimoney/selection/item/detail',
            $param
        );
        if ((int) ($raw['result'] ?? 0) !== 1) {
            return [];
        }
        $rows = $this->normalizeDetailItemRows($raw['data'] ?? []);
        foreach ($rows as $row) {
            if ($this->detailRowMatchesGoodsId($row, $goodsId, $context)) {
                return $row;
            }
        }
        if (count($rows) === 1 && is_array($rows[0])) {
            return $rows[0];
        }
        return [];
    }

    /**
     * 列表跳转 summary 或选品 list 反查（均非订单侠）
     *
     * @return array{row: array, via: string}|null  via=summary|list_search
     */
    public function resolveSelectionRow(string $goodsId, array $context = []): ?array
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '' || !is_array($context)) {
            return null;
        }
        if ($this->contextSummaryMatchesGoodsId($context, $goodsId)) {
            return [
                'row' => $this->normalizeSummaryToSelectionRow($context),
                'via' => 'summary',
            ];
        }
        $found = $this->findSelectionListItem($goodsId, $context);
        if ($found === null) {
            return null;
        }
        return ['row' => $found, 'via' => 'list_search'];
    }

    /**
     * 详情 API 未按 id 过滤时，从选品列表分页/关键词反查
     */
    public function findSelectionListItem(string $goodsId, array $context = []): ?array
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '') {
            return null;
        }
        $channelId = (int) ($context['channelId'] ?? ($context['channel_id'] ?? 0));
        $searchKey = trim((string) ($context['searchKey'] ?? ($context['title'] ?? ($context['store_name'] ?? ($context['itemTitle'] ?? '')))));
        if ($searchKey !== '') {
            $searchKey = mb_substr($searchKey, 0, 24);
        }

        $plans = [];
        if ($searchKey !== '') {
            $plans[] = ['keyword' => $searchKey, 'channelId' => $channelId];
            if ($channelId > 0) {
                $plans[] = ['keyword' => $searchKey, 'channelId' => 0];
            }
        }
        $plans[] = ['keyword' => '', 'channelId' => $channelId];
        $plans[] = ['keyword' => '', 'channelId' => 0];

        foreach ($plans as $plan) {
            for ($page = 1; $page <= 3; $page++) {
                $items = $plan['keyword'] !== ''
                    ? $this->fetchSearch($plan['keyword'], $page, 50, (int) $plan['channelId'])
                    : $this->fetchFeed($page, 50, (int) $plan['channelId']);
                foreach ($items as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    if ($this->detailRowMatchesGoodsId($row, $goodsId, $context)) {
                        return $row;
                    }
                }
                if (count($items) < 50) {
                    break;
                }
            }
        }
        return null;
    }

    public function contextSummaryMatchesGoodsId(array $context, string $goodsId): bool
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '') {
            return false;
        }
        foreach (['goods_id', 'goodsId', 'relItemId', 'rel_item_id', 'product_id'] as $key) {
            if ((string) ($context[$key] ?? '') === $goodsId) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeSummaryToSelectionRow(array $summary): array
    {
        $goodsId = (string) ($summary['goodsId'] ?? ($summary['goods_id'] ?? ''));
        $rel = (string) ($summary['relItemId'] ?? ($summary['rel_item_id'] ?? $goodsId));
        $title = (string) ($summary['itemTitle'] ?? ($summary['title'] ?? ($summary['store_name'] ?? '')));
        $image = (string) ($summary['image'] ?? ($summary['itemImgUrl'] ?? ''));
        $expressId = $summary['expressId'] ?? ($summary['express_id'] ?? 0);
        $expressType = $summary['expressType'] ?? ($summary['express_type'] ?? 0);
        $priceRaw = $summary['price'] ?? '0';
        $zk = is_numeric($priceRaw) ? (int) round(((float) $priceRaw) * 100) : 0;

        return array_filter([
            'goodsId' => ctype_digit($goodsId) ? (int) $goodsId : $goodsId,
            'relItemId' => ctype_digit($rel) ? (int) $rel : $rel,
            'itemTitle' => $title,
            'itemImgUrl' => $image,
            'expressId' => is_numeric($expressId) ? (int) $expressId : 0,
            'expressType' => is_numeric($expressType) ? (int) $expressType : 0,
            'zkFinalPrice' => $zk > 0 ? $zk : null,
            'soldCount' => (int) ($summary['sales'] ?? 0),
            'soldCountDesc' => (string) ($summary['sales_text'] ?? ''),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param mixed $data
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDetailItemRows($data): array
    {
        if (!is_array($data)) {
            return [];
        }
        if (isset($data['goodsId']) || isset($data['itemTitle'])) {
            return [$data];
        }
        if (isset($data[0]) && is_array($data[0])) {
            return array_values(array_filter($data, 'is_array'));
        }
        foreach (['itemList', 'list', 'items'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values(array_filter($data[$key], 'is_array'));
            }
        }
        return [];
    }

    protected function detailRowMatchesGoodsId(array $row, string $goodsId, array $context): bool
    {
        $candidates = [$goodsId];
        foreach (['goodsId', 'goods_id', 'relItemId', 'rel_item_id'] as $key) {
            if (!empty($context[$key])) {
                $candidates[] = (string) $context[$key];
            }
        }
        $candidates = array_values(array_unique(array_filter(array_map('strval', $candidates))));
        foreach ($candidates as $want) {
            if ($want === '') {
                continue;
            }
            foreach (['goodsId', 'relItemId'] as $key) {
                if ((string) ($row[$key] ?? '') === $want) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * method: open.distribution.cps.kwaimoney.link.create
     *
     * 官方必填：linkType(100直播间/101商品)、linkCarrierId(商品id或达人id)、comments(订单透传)、cpsPid
     *
     * @param array<string, mixed> $context linkType, linkCarrierId, comments, genPoster, customContent
     */
    public function createCpsLink(string $goodsId, string $pid = '', array $context = []): array
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '') {
            return ['result' => 0, 'error_msg' => 'empty goodsId'];
        }
        $cpsPid = trim($pid !== '' ? $pid : $this->pid);
        if ($cpsPid === '') {
            return ['result' => 0, 'error_msg' => 'empty cpsPid'];
        }

        $linkType = (int) ($context['linkType'] ?? ($context['link_type'] ?? 101));
        if ($linkType !== 100 && $linkType !== 101) {
            $linkType = 101;
        }

        $linkCarrierId = trim((string) ($context['linkCarrierId'] ?? ($context['link_carrier_id'] ?? '')));
        if ($linkCarrierId === '') {
            $linkCarrierId = $goodsId;
        }

        $comments = trim((string) ($context['comments'] ?? ''));
        if ($comments === '') {
            $comments = trim((string) ($context['externalId'] ?? ($context['external_id'] ?? '')));
        }
        if ($comments === '') {
            $comments = trim((string) ($context['customParameters'] ?? ($context['custom_parameters'] ?? '')));
        }
        if ($comments === '') {
            $comments = 'naimeng01';
        }

        $param = [
            'linkType' => $linkType,
            'linkCarrierId' => $linkCarrierId,
            'comments' => $comments,
            'cpsPid' => $cpsPid,
        ];

        if (array_key_exists('genPoster', $context)) {
            $param['genPoster'] = (bool) $context['genPoster'];
        }
        $customContent = trim((string) ($context['customContent'] ?? ($context['custom_content'] ?? '')));
        if ($customContent !== '') {
            $param['customContent'] = $customContent;
        }
        $activityId = (int) ($context['activityId'] ?? ($context['activity_id'] ?? 0));
        if ($activityId > 0) {
            $param['activityId'] = $activityId;
        }
        $secondActivityId = (int) ($context['secondActivityId'] ?? ($context['second_activity_id'] ?? 0));
        if ($secondActivityId > 0) {
            $param['secondActivityId'] = $secondActivityId;
        }

        Log::info('快手 link.create 请求', [
            'goodsId' => $goodsId,
            'param' => $param,
        ]);

        return $this->callDistributionApi(
            'open.distribution.cps.kwaimoney.link.create',
            '/open/distribution/cps/kwaimoney/link/create',
            $param
        );
    }

    /**
     * 推广位列表（文档 pagination 为 page + size，非 pageNo/pageSize）
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchCpsPidList(int $page = 1, int $size = 20): array
    {
        $raw = $this->callDistributionApi(
            'open.distribution.cps.kwaimoney.pid.list',
            '/open/distribution/cps/kwaimoney/pid/list',
            [
                'page' => max(1, $page),
                'size' => max(1, min(100, $size)),
            ]
        );
        if ((int) ($raw['result'] ?? 0) !== 1) {
            return [];
        }
        $data = $raw['data'] ?? [];
        if (!is_array($data)) {
            return [];
        }
        $rows = $data['cpsPidData'] ?? $data['list'] ?? [];
        return is_array($rows) ? $rows : [];
    }

    /**
     * 选品频道 Tab（首页/女装等）
     */
    public function fetchSelectionChannels(): array
    {
        $raw = $this->callDistributionApi(
            'open.distribution.cps.kwaimoney.selection.channel.list',
            '/open/distribution/cps/kwaimoney/selection/channel/list',
            []
        );
        if ((int) ($raw['result'] ?? 0) !== 1) {
            return [];
        }
        $data = $raw['data'] ?? [];
        return is_array($data) ? $data : [];
    }

    protected function signOpenApi(array $meta): string
    {
        $items = [];
        foreach ($meta as $k => $v) {
            if ($k === 'sign' || $v === null || $v === '') {
                continue;
            }
            $items[$k] = (string) $v;
        }
        ksort($items);
        $metaStr = '';
        foreach ($items as $k => $v) {
            $metaStr .= ($metaStr === '' ? '' : '&') . $k . '=' . $v;
        }
        $full = $metaStr . '&signSecret=' . $this->signSecret;
        return base64_encode(hash_hmac('sha256', $full, $this->signSecret, true));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractItemList(array $raw): array
    {
        if ((int) ($raw['result'] ?? 0) !== 1) {
            return [];
        }
        $data = $raw['data'] ?? [];
        if (is_array($data) && isset($data[0]) && is_array($data[0]) && !isset($data['itemList'])) {
            return $data;
        }
        if (!is_array($data)) {
            return [];
        }
        foreach (['itemList', 'list', 'items', 'records'] as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return $data[$key];
            }
        }
        return [];
    }
}
