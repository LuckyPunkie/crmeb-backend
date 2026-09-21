<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use GuzzleHttp\Client;
use think\facade\Log;

/**
 * 唯品会唯享客联盟 VOP 官方直连
 * 网关: https://vop.vipapis.com/
 */
class VipOfficialService extends BaseServices
{
    protected string $appKey;
    protected string $appSecret;
    protected string $defaultChanTag;
    protected string $apiUrl;

    protected string $goodsService = 'com.vip.adp.api.open.service.UnionGoodsV2Service';
    protected string $urlService = 'com.vip.adp.api.open.service.UnionUrlV2Service';
    protected string $apiVersion = '2.0.0';

    public function __construct()
    {
        parent::__construct('vip', [], 'taoke');
        $this->setHttpClient(new Client([
            'timeout' => 30,
            'connect_timeout' => 8,
            'verify' => false,
        ]));
        $this->appKey = (string) config('taoke.vip.appkey');
        $this->appSecret = (string) config('taoke.vip.appsecret');
        $this->defaultChanTag = (string) config('taoke.vip.chan_tag');
        if ($this->defaultChanTag === '') {
            $this->defaultChanTag = 'default_pid';
        }
        $this->apiUrl = (string) (config('taoke.vip.api_url') ?: 'https://vop.vipapis.com/');
    }

    /** Swoole 常驻：每次调用前刷新 .env 配置 */
    protected function syncConfig(): void
    {
        $this->appKey = (string) config('taoke.vip.appkey');
        $this->appSecret = (string) config('taoke.vip.appsecret');
        $this->defaultChanTag = (string) config('taoke.vip.chan_tag');
        if ($this->defaultChanTag === '') {
            $this->defaultChanTag = 'default_pid';
        }
        $this->apiUrl = (string) (config('taoke.vip.api_url') ?: 'https://vop.vipapis.com/');
    }

    public function getDefaultChanTag(): string
    {
        $this->syncConfig();
        return $this->defaultChanTag;
    }

    public function isConfigured(): bool
    {
        $this->syncConfig();
        return $this->appKey !== '' && $this->appSecret !== '';
    }

    /**
     * 关键词搜索；无关键词走在推列表
     *
     * @return array{list: array<int, array>, total: int, raw: array}
     */
    public function fetchSearch(string $keyword, int $page = 1, int $pageSize = 20, string $openId = '', bool $realCall = false): array
    {
        $keyword = trim($keyword);
        if ($keyword === '' || $keyword === '热销') {
            return $this->fetchFeed($page, $pageSize, $openId, $realCall);
        }
        $request = $this->baseGoodsRequest($openId, $realCall);
        $request['keyword'] = $keyword;
        $request['page'] = max(1, $page);
        $request['pageSize'] = $this->clampPageSize($pageSize);
        $raw = $this->invoke($this->goodsService, 'query', ['request' => $request]);
        return $this->parseGoodsListResponse($raw);
    }

    /**
     * 联盟在推商品列表（无关键词推荐）
     *
     * @return array{list: array<int, array>, total: int, raw: array}
     */
    public function fetchFeed(int $page = 1, int $pageSize = 20, string $openId = '', bool $realCall = false): array
    {
        $request = $this->baseGoodsRequest($openId, $realCall);
        $request['page'] = max(1, $page);
        $request['pageSize'] = $this->clampPageSize($pageSize);
        $raw = $this->invoke($this->goodsService, 'goodsListV2', ['request' => $request]);
        $parsed = $this->parseGoodsListResponse($raw);
        if ($parsed['list'] !== []) {
            return $parsed;
        }
        // 部分账号 goodsListV2 为空时，用泛关键词兜底
        $request['keyword'] = '热销';
        $raw = $this->invoke($this->goodsService, 'query', ['request' => $request]);
        return $this->parseGoodsListResponse($raw);
    }

    /**
     * 商品详情（单条）
     */
    public function fetchDetail(string $goodsId, string $openId = '', bool $realCall = false): array
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '') {
            return [];
        }
        $request = $this->baseGoodsRequest($openId, $realCall);
        $request['goodsIds'] = [$goodsId];
        $raw = $this->invoke($this->goodsService, 'getByGoodsIdsV2', ['request' => $request]);
        if (($raw['returnCode'] ?? '') !== '0') {
            Log::warning('唯品会详情失败', ['goodsId' => $goodsId, 'raw' => $raw]);
            return [];
        }
        $result = $raw['result'] ?? [];
        if (is_array($result) && isset($result[0]) && is_array($result[0])) {
            return $result[0];
        }
        if (is_array($result) && isset($result['goodsId'])) {
            return $result;
        }
        return [];
    }

    /**
     * CPS 转链（goodsId）；失败时返回 fallback 结构供前端用 destUrl
     *
     * @param array{adCode?: string, destUrl?: string} $context
     */
    public function generateLinkByGoodsId(string $goodsId, string $openId, string $chanTag = '', array $context = []): array
    {
        $goodsId = trim($goodsId);
        if ($goodsId === '') {
            return ['_error' => 'empty_goods_id'];
        }
        $openId = $this->normalizeOpenId($openId, true);
        $chanTag = trim($chanTag !== '' ? $chanTag : $this->defaultChanTag);
        $adCode = trim((string) ($context['adCode'] ?? ''));
        $destUrl = trim((string) ($context['destUrl'] ?? ''));

        $body = [
            'goodsIdList' => [$goodsId],
            'chanTag' => $chanTag,
            'requestId' => $this->newRequestId(),
            'statParam' => (string) ($context['statParam'] ?? ''),
            'urlGenRequest' => [
                'openId' => $openId,
                'realCall' => true,
                'adCode' => $adCode,
                'genShortUrl' => true,
            ],
        ];
        $raw = $this->invoke($this->urlService, 'genByGoodsId', $body);
        if (($raw['returnCode'] ?? '') === '0') {
            $list = $raw['result']['urlInfoList'] ?? [];
            if (is_array($list) && isset($list[0]) && is_array($list[0])) {
                return $list[0];
            }
        }

        Log::warning('唯品会 genByGoodsId 未成功，使用 destUrl 兜底', [
            'goodsId' => $goodsId,
            'returnCode' => $raw['returnCode'] ?? '',
            'returnMessage' => $raw['returnMessage'] ?? '',
        ]);

        if ($destUrl === '') {
            $detail = $this->fetchDetail($goodsId, $openId, true);
            $destUrl = trim((string) ($detail['destUrl'] ?? ($detail['destUrlPc'] ?? '')));
        }

        return [
            '_link_fallback' => true,
            '_official_error' => (string) ($raw['returnMessage'] ?? 'genByGoodsId failed'),
            'url' => $destUrl,
            'longUrl' => $destUrl,
            'source' => $goodsId,
        ];
    }

    protected function baseGoodsRequest(string $openId, bool $realCall): array
    {
        return [
            'requestId' => $this->newRequestId(),
            'openId' => $this->normalizeOpenId($openId, $realCall),
            'realCall' => $realCall,
            'chanTag' => $this->defaultChanTag,
        ];
    }

    protected function normalizeOpenId(string $openId, bool $realCall): string
    {
        $openId = preg_replace('/[^a-zA-Z0-9_]/', '', trim($openId)) ?? '';
        if ($openId === '') {
            return $realCall ? 'default_open_id' : 'default_open_id';
        }
        return substr($openId, 0, 32);
    }

    protected function clampPageSize(int $pageSize): int
    {
        $pageSize = max(10, min(50, $pageSize));
        return $pageSize;
    }

    protected function newRequestId(): string
    {
        return substr(md5(uniqid('vip', true)), 0, 32);
    }

    /**
     * @return array{list: array<int, array>, total: int, raw: array}
     */
    protected function parseGoodsListResponse(array $raw): array
    {
        if (($raw['returnCode'] ?? '') !== '0') {
            Log::warning('唯品会列表失败', [
                'returnCode' => $raw['returnCode'] ?? '',
                'returnMessage' => is_scalar($raw['returnMessage'] ?? null)
                    ? (string) ($raw['returnMessage'] ?? '')
                    : json_encode($raw['returnMessage'], JSON_UNESCAPED_UNICODE),
            ]);
            return ['list' => [], 'total' => 0, 'raw' => $raw];
        }
        $result = $raw['result'] ?? [];
        $list = [];
        if (is_array($result)) {
            $list = $result['goodsInfoList'] ?? $result['goodsList'] ?? [];
        }
        if (!is_array($list)) {
            $list = [];
        }
        $total = (int) ($result['total'] ?? count($list));
        return ['list' => $list, 'total' => $total, 'raw' => $raw];
    }

    /**
     * @param array<string, mixed> $appParams
     */
    protected function invoke(string $service, string $method, array $appParams): array
    {
        $this->syncConfig();
        if (!$this->isConfigured()) {
            return ['returnCode' => 'config', 'returnMessage' => 'missing vip appkey/appsecret'];
        }

        $body = json_encode($appParams, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            $body = '{}';
        }

        $system = [
            'service' => $service,
            'method' => $method,
            'version' => $this->apiVersion,
            'timestamp' => time(),
            'format' => 'json',
            'appKey' => $this->appKey,
        ];
        $system['sign'] = $this->sign($system, $body);

        $url = rtrim($this->apiUrl, '/') . '/?' . http_build_query($system);

        try {
            $response = $this->httpClient->request('POST', $url, [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/json;charset=UTF-8',
                ],
            ]);
            $text = (string) $response->getBody();
            $decoded = json_decode($text, true);
            return is_array($decoded) ? $decoded : ['returnCode' => 'parse', 'returnMessage' => $text];
        } catch (\Throwable $e) {
            Log::error('唯品会 VOP 请求异常', [
                'service' => $service,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
            return ['returnCode' => 'http', 'returnMessage' => $e->getMessage()];
        }
    }

    /**
     * @param array<string, scalar> $systemParams
     */
    protected function sign(array $systemParams, string $appBody): string
    {
        $keys = array_keys($systemParams);
        sort($keys);
        $buf = '';
        foreach ($keys as $key) {
            if ($key === 'sign' || $key === 'appSecret') {
                continue;
            }
            $val = $systemParams[$key];
            if ($val === '' || $val === null) {
                continue;
            }
            $buf .= $key . $val;
        }
        $buf .= $appBody;
        return strtoupper(hash_hmac('md5', $buf, $this->appSecret));
    }
}
