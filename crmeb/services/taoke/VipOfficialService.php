<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use GuzzleHttp\Client;
use think\facade\Cache;
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
    /**
     * @param int $categoryId 唯品会一级类目 id（UnionGoodsV2Service.query fieldName=CATEGORY）
     */
    public function fetchSearch(
        string $keyword,
        int $page = 1,
        int $pageSize = 20,
        string $openId = '',
        bool $realCall = false,
        int $categoryId = 0
    ): array {
        $keyword = trim($keyword);
        $categoryId = max(0, $categoryId);
        if ($keyword === '' && $categoryId === 0) {
            $keyword = '热销';
        }
        if ($keyword === '热销' && $categoryId === 0) {
            return $this->fetchFeed($page, $pageSize, $openId, $realCall);
        }
        $request = $this->baseGoodsRequest($openId, $realCall);
        if ($keyword !== '') {
            $request['keyword'] = $keyword;
        }
        if ($categoryId > 0) {
            $request['fieldName'] = 'CATEGORY';
            $request['fieldValue'] = (string) $categoryId;
        }
        $request['page'] = max(1, $page);
        $request['pageSize'] = $this->clampPageSize($pageSize);
        // 2=返回小程序 CPS 参数（cpsInfo），便于列表直接带转链信息
        $request['queryCpsInfo'] = 2;
        $raw = $this->invoke($this->goodsService, 'query', ['request' => $request]);
        return $this->parseGoodsListResponse($raw);
    }

    /**
     * 服务页 Tab：唯品会一级类目（getCategorys grade=1）
     *
     * @return array<int, array{id: int, text: string}>
     */
    public function fetchTopCategoryTags(int $limit = 20): array
    {
        $this->syncConfig();
        if (!$this->isConfigured()) {
            return [];
        }
        $limit = max(4, min(40, $limit));
        $cacheKey = 'taoke_vip_category_grade1_v3_' . $limit;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }
        $request = $this->baseGoodsRequest('', false);
        $request['parentId'] = 0;
        $request['grade'] = 1;
        $raw = $this->invoke($this->goodsService, 'getCategorys', ['request' => $request]);
        if (($raw['returnCode'] ?? '') !== '0') {
            Log::warning('唯品会类目获取失败', [
                'returnCode' => $raw['returnCode'] ?? '',
                'returnMessage' => is_scalar($raw['returnMessage'] ?? null)
                    ? (string) ($raw['returnMessage'] ?? '')
                    : '',
            ]);
            return [];
        }
        $result = $raw['result'] ?? [];
        $rows = [];
        if (is_array($result)) {
            $rows = $result['data'] ?? $result['categoryList'] ?? [];
        }
        if (!is_array($rows)) {
            $rows = [];
        }
        $tags = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            $text = trim((string) ($row['name'] ?? ''));
            if ($id <= 0 || $text === '') {
                continue;
            }
            // 联盟类目树里偶发测试/占位节点（勿用中文正则，避免部署编码损坏）
            if (in_array($id, [81821], true) || preg_match('/^test$/i', $text)) {
                continue;
            }
            $tags[] = ['id' => $id, 'text' => $text];
            if (count($tags) >= $limit) {
                break;
            }
        }
        if ($tags !== []) {
            Cache::set($cacheKey, $tags, 86400);
        }
        return $tags;
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
        $request['queryCpsInfo'] = 2;
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
        // 默认 false 时不返 goodsCarouselPictures / goodsDetailPictures（见 VOP getByGoodsIdsV2）
        $request['queryDetail'] = true;
        $request['queryPMSAct'] = true;
        $request['queryPrepay'] = true;
        $request['queryReputation'] = true;
        $request['queryStoreServiceCapability'] = true;
        $request['queryStock'] = true;
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
     * CPS 转链（goodsId）
     * 优先 UnionUrlV2Service.genByGoodsId；失败则用 query+queryCpsInfo 回搜同 id 取小程序 CPS 路径。
     *
     * @param array{adCode?: string, destUrl?: string, goodsName?: string} $context
     */
    public function generateLinkByGoodsId(string $goodsId, string $openId, string $chanTag = '', array $context = []): array
    {
        $this->syncConfig();
        $goodsId = trim($goodsId);
        if ($goodsId === '') {
            return ['_error' => 'empty_goods_id'];
        }
        $openId = $this->normalizeOpenId($openId, true);
        $chanTag = trim($chanTag !== '' ? $chanTag : $this->defaultChanTag);
        $adCode = trim((string) ($context['adCode'] ?? ''));
        $destUrl = trim((string) ($context['destUrl'] ?? ''));
        $goodsName = trim((string) ($context['goodsName'] ?? ''));

        // 文档：openId/realCall/adCode 放在 urlGenByGoodsIdRequest（UnionUrlV2Service.genByGoodsId 2.0.0）
        $adCode = $adCode !== '' ? $adCode : 'vendoapi';
        $body = [
            'goodsIdList' => [$goodsId],
            'chanTag' => $chanTag,
            'requestId' => $this->newRequestId(),
            'statParam' => (string) ($context['statParam'] ?? ''),
            'genShortUrl' => true,
            'urlGenByGoodsIdRequest' => [
                'openId' => $openId,
                'realCall' => true,
                'adCode' => $adCode,
            ],
        ];
        $raw = $this->invoke($this->urlService, 'genByGoodsId', $body);
        if (($raw['returnCode'] ?? '') === '0') {
            $payload = $raw['result'] ?? $raw['success'] ?? [];
            $list = is_array($payload) ? ($payload['urlInfoList'] ?? []) : [];
            if (is_array($list) && isset($list[0]) && is_array($list[0])) {
                return $list[0];
            }
        }

        Log::warning('唯品会 genByGoodsId 未成功，改用 queryCpsInfo 兜底', [
            'goodsId' => $goodsId,
            'returnCode' => $raw['returnCode'] ?? '',
            'returnMessage' => is_scalar($raw['returnMessage'] ?? null)
                ? (string) ($raw['returnMessage'] ?? '')
                : '',
        ]);

        $detail = [];
        if ($destUrl === '' || $goodsName === '' || $adCode === '') {
            $detail = $this->fetchDetail($goodsId, $openId, true);
            if ($destUrl === '') {
                $destUrl = trim((string) ($detail['destUrl'] ?? ($detail['destUrlPc'] ?? '')));
            }
            if ($goodsName === '') {
                $goodsName = trim((string) ($detail['goodsName'] ?? ($detail['shortTitle'] ?? '')));
            }
            if ($adCode === '') {
                $adCode = trim((string) ($detail['adCode'] ?? ''));
            }
        }

        $cpsFallback = $this->resolveCpsLinkBySearch($goodsId, $goodsName, $openId);
        if ($cpsFallback !== []) {
            if ($destUrl !== '' && empty($cpsFallback['url'])) {
                $cpsFallback['url'] = $destUrl;
                $cpsFallback['longUrl'] = $destUrl;
            }
            $cpsFallback['_link_fallback'] = true;
            $cpsFallback['_fallback_via'] = 'queryCpsInfo';
            $cpsFallback['_official_error'] = (string) ($raw['returnMessage'] ?? 'genByGoodsId failed');
            $cpsFallback['source'] = $goodsId;
            return $cpsFallback;
        }

        return [
            '_link_fallback' => true,
            '_fallback_via' => 'destUrl',
            '_official_error' => (string) ($raw['returnMessage'] ?? 'genByGoodsId failed'),
            'url' => $destUrl,
            'longUrl' => $destUrl,
            'source' => $goodsId,
        ];
    }

    /**
     * 用商品名关键词搜索并匹配 goodsId，提取 cpsInfo 小程序路径
     *
     * @return array<string, mixed>
     */
    protected function resolveCpsLinkBySearch(string $goodsId, string $goodsName, string $openId): array
    {
        $keyword = $goodsName;
        if ($keyword === '') {
            return [];
        }
        // 关键词过长时截断，提高命中率
        if (mb_strlen($keyword) > 20) {
            $keyword = mb_substr($keyword, 0, 20);
        }
        $parsed = $this->fetchSearch($keyword, 1, 30, $openId, true);
        $hit = null;
        foreach ($parsed['list'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((string) ($row['goodsId'] ?? '') === $goodsId) {
                $hit = $row;
                break;
            }
        }
        if ($hit === null) {
            return [];
        }
        return $this->mapCpsInfoToLinkPayload($hit);
    }

    /**
     * @param array<string, mixed> $goods
     * @return array<string, mixed>
     */
    protected function mapCpsInfoToLinkPayload(array $goods): array
    {
        $cps = $goods['cpsInfo'] ?? [];
        if (!is_array($cps)) {
            $cps = [];
        }
        // queryCpsInfo=2 → cpsInfo['2'] 为小程序 path
        $wxPath = trim((string) ($cps['2'] ?? ($cps[2] ?? '')));
        $traFrom = trim((string) ($cps['1'] ?? ($cps[1] ?? '')));
        $destUrl = trim((string) ($goods['destUrl'] ?? ($goods['destUrlPc'] ?? '')));

        $out = [
            'url' => $destUrl,
            'longUrl' => $destUrl,
            'source' => (string) ($goods['goodsId'] ?? ''),
            'adCode' => (string) ($goods['adCode'] ?? ''),
            'cpsInfo' => $cps,
        ];
        if ($wxPath !== '') {
            $out['vipWxUrl'] = $wxPath;
        }
        if ($traFrom !== '') {
            $out['traFrom'] = $traFrom;
        }
        return $out;
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
