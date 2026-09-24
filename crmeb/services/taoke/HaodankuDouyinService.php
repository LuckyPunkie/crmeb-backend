<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use GuzzleHttp\Client;
use think\facade\Log;

/**
 * 好单库 — 抖音电商 API
 * 文档：https://www.haodanku.com/openapi/ai_docs
 */
class HaodankuDouyinService extends BaseServices
{
    protected string $apiKey = '';
    protected string $apiUrl = '';

    public function __construct()
    {
        parent::__construct('haodanku_douyin', [], 'taoke');
        $this->setHttpClient(new Client([
            'timeout' => 30,
            'connect_timeout' => 8,
            'verify' => false,
        ]));
        $this->apiKey = (string) config('taoke.haodanku.apikey');
        $this->apiUrl = rtrim((string) (config('taoke.haodanku.api_url') ?: 'https://v3.api.haodanku.com'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * 商品列表/搜索 dy_itemlist_simplify
     */
    public function fetchItemList(string $keyword = '', $minId = 1, int $back = 20, array $extra = []): array
    {
        $query = array_merge([
            'apikey' => $this->apiKey,
            'min_id' => $minId,
            'back' => max(1, min(100, $back)),
        ], $extra);
        $keyword = trim($keyword);
        if ($keyword !== '') {
            $query['keyword'] = $keyword;
        }
        return $this->requestGet('dy_itemlist_simplify', $query);
    }

    /**
     * 商品详情 dy_item_info
     */
    public function fetchItemInfo(string $itemId): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return $this->failPayload('empty_itemid');
        }
        return $this->requestGet('dy_item_info', [
            'apikey' => $this->apiKey,
            'itemid' => $itemId,
        ]);
    }

    /**
     * 直播列表 dy_live_list
     */
    public function fetchLiveList($minId = 1, int $back = 20, array $extra = []): array
    {
        $query = array_merge([
            'apikey' => $this->apiKey,
            'min_id' => $minId,
            'back' => max(1, min(100, $back)),
        ], $extra);
        return $this->requestGet('dy_live_list', $query);
    }

    /**
     * 商品转链 get_dyitem_link
     */
    public function createProductLink(string $itemId, array $options = []): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return $this->failPayload('empty_itemid');
        }
        $form = [
            'apikey' => $this->apiKey,
            'itemid' => $itemId,
        ];
        $shareType = $options['share_type'] ?? '1,3';
        if (is_array($shareType)) {
            $shareType = implode(',', array_map('strval', $shareType));
        }
        $form['share_type'] = (string) $shareType;
        if (!empty($options['channel'])) {
            $form['channel'] = preg_replace('/[^\w]/', '_', (string) $options['channel']);
        }
        if (isset($options['platform'])) {
            $form['platform'] = (int) $options['platform'];
        }
        return $this->requestPost('get_dyitem_link', $form);
    }

    /**
     * 直播转链 get_dylive_link
     */
    public function createLiveLink(array $options): array
    {
        $form = ['apikey' => $this->apiKey];
        $map = [
            'room_id', 'author_id', 'buyin_id', 'author_buyin_id', 'product_id', 'itemid', 'platform',
        ];
        foreach ($map as $key) {
            if (!empty($options[$key])) {
                $form[$key] = (string) $options[$key];
            }
        }
        if (empty($form['room_id']) && empty($form['author_id']) && empty($form['buyin_id']) && empty($form['author_buyin_id'])) {
            return $this->failPayload('missing_live_target');
        }
        $shareType = $options['share_type'] ?? '1,3';
        if (is_array($shareType)) {
            $shareType = implode(',', array_map('strval', $shareType));
        }
        $form['share_type'] = (string) $shareType;
        if (!empty($options['channel'])) {
            $form['channel'] = preg_replace('/[^\w]/', '_', (string) $options['channel']);
        }
        return $this->requestPost('get_dylive_link', $form);
    }

    /**
     * 口令解析 dy_analyze_code
     */
    public function analyzeCode(string $content): array
    {
        $content = trim($content);
        if ($content === '') {
            return $this->failPayload('empty_content');
        }
        return $this->requestPost('dy_analyze_code', [
            'apikey' => $this->apiKey,
            'content' => $content,
        ]);
    }

    protected function requestGet(string $path, array $query): array
    {
        if (!$this->isConfigured()) {
            return $this->failPayload('haodanku_not_configured');
        }
        $path = ltrim($path, '/');
        try {
            $response = $this->httpClient->get($this->apiUrl . '/' . $path, ['query' => $query]);
            return $this->parseResponse((string) $response->getBody());
        } catch (\Throwable $e) {
            Log::error('好单库抖音 GET 失败', ['path' => $path, 'error' => $e->getMessage()]);
            return $this->failPayload($e->getMessage());
        }
    }

    protected function requestPost(string $path, array $form): array
    {
        if (!$this->isConfigured()) {
            return $this->failPayload('haodanku_not_configured');
        }
        $path = ltrim($path, '/');
        try {
            $response = $this->httpClient->post($this->apiUrl . '/' . $path, [
                'form_params' => $form,
            ]);
            return $this->parseResponse((string) $response->getBody());
        } catch (\Throwable $e) {
            Log::error('好单库抖音 POST 失败', ['path' => $path, 'error' => $e->getMessage()]);
            return $this->failPayload($e->getMessage());
        }
    }

    /**
     * @return array{ok:bool,code:int,msg:string,data:mixed,min_id:mixed,raw:array}
     */
    protected function parseResponse(string $body): array
    {
        $raw = json_decode($body, true);
        if (!is_array($raw)) {
            return [
                'ok' => false,
                'code' => -1,
                'msg' => 'invalid_json',
                'data' => [],
                'min_id' => null,
                'raw' => [],
            ];
        }
        $code = (int) ($raw['code'] ?? 0);
        $ok = ($code === 1 || $code === 200);
        return [
            'ok' => $ok,
            'code' => $code,
            'msg' => (string) ($raw['msg'] ?? ''),
            'data' => $raw['data'] ?? [],
            'min_id' => $raw['min_id'] ?? null,
            'raw' => $raw,
        ];
    }

    protected function failPayload(string $msg): array
    {
        return [
            'ok' => false,
            'code' => -1,
            'msg' => $msg,
            'data' => [],
            'min_id' => null,
            'raw' => [],
        ];
    }
}
