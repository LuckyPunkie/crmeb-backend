<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use GuzzleHttp\Client;
use think\facade\Log;

/**
 * 穿山甲电商联盟 — 抖音 CPS（商品 / 直播 / 订单）
 * 文档：https://www.csjplatform.com/supportcenter/28733
 */
class PangleCpsService extends BaseServices
{
    protected string $appId = '';
    protected string $secureKey = '';
    protected string $apiUrl = '';

    public function __construct()
    {
        parent::__construct('pangle', [], 'taoke');
        $this->setHttpClient(new Client([
            'timeout' => 30,
            'connect_timeout' => 8,
            'verify' => false,
        ]));
        $this->appId = (string) config('taoke.pangle.app_id');
        $this->secureKey = (string) config('taoke.pangle.secure_key');
        $this->apiUrl = rtrim((string) (config('taoke.pangle.api_url') ?: 'https://ecom.pangolin-sdk-toutiao.com'), '/');
        if ($this->secureKey === '') {
            $keyFile = runtime_path() . 'pangle_secure_key.txt';
            if (is_file($keyFile)) {
                $this->secureKey = trim((string) file_get_contents($keyFile));
            }
        }
    }

    public function isConfigured(): bool
    {
        return $this->appId !== '' && $this->secureKey !== '';
    }

    /**
     * @return array{code:int,message:string,data:array<string,mixed>|string|null,raw:array<string,mixed>}
     */
    public function searchProducts(int $page = 1, int $pageSize = 20, string $title = ''): array
    {
        $data = [
            'page' => max(1, $page),
            'page_size' => $this->clampPageSize($pageSize),
        ];
        $title = trim($title);
        if ($title !== '') {
            $data['title'] = $title;
        }
        return $this->call('/product/search', $data);
    }

    /**
     * 直播间列表（默认在播；need_products=0 保证分页条数）
     *
     * @param array<string,mixed> $extra author_info, sort_by, sort_type, status, need_products
     */
    public function searchLive(int $page = 1, int $pageSize = 20, array $extra = []): array
    {
        $data = array_merge([
            'page' => max(1, $page),
            'page_size' => $this->clampPageSize($pageSize, 100),
            'status' => 1,
            'need_products' => 0,
        ], $extra);
        return $this->call('/live/search', $data);
    }

    /**
     * @param array<int> $productIds
     */
    public function productDetail(array $productIds): array
    {
        $ids = [];
        foreach ($productIds as $id) {
            $id = trim((string) $id);
            if ($id !== '') {
                $ids[] = $id;
            }
        }
        if ($ids === []) {
            return ['code' => -1, 'message' => 'empty_product_ids', 'data' => null, 'raw' => []];
        }
        return $this->call('/product/detail', ['product_ids' => $ids]);
    }

    /**
     * @param array<string,mixed> $biz product_url, product_ext, external_info, share_type
     */
    public function createProductLink(array $biz): array
    {
        return $this->call('/product/link', $biz);
    }

    /**
     * @param array<string,mixed> $biz author_openid|author_buyin_id, live_ext, external_info, share_type, product_id, platform
     */
    public function createLiveLink(array $biz): array
    {
        return $this->call('/live/link', $biz);
    }

    /**
     * @param array<string,mixed> $data
     * @return array{code:int,message:string,data:array<string,mixed>|null,raw:array<string,mixed>}
     */
    protected function call(string $path, array $data): array
    {
        if (!$this->isConfigured()) {
            return [
                'code' => -1,
                'message' => 'pangle_not_configured',
                'data' => null,
                'raw' => [],
            ];
        }

        $path = '/' . ltrim($path, '/');
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $reqId = $this->uuid();
        $sign = md5("app_id={$this->appId}&data={$dataJson}&req_id={$reqId}&timestamp={$timestamp}{$this->secureKey}");

        $body = [
            'app_id' => $this->appId,
            'timestamp' => $timestamp,
            'version' => '1',
            'sign_type' => 'MD5',
            'req_id' => $reqId,
            'data' => $dataJson,
            'sign' => $sign,
        ];

        try {
            $response = $this->httpClient->post($this->apiUrl . $path, [
                'json' => $body,
                'headers' => ['Content-Type' => 'application/json'],
            ]);
            $raw = json_decode((string) $response->getBody(), true);
            if (!is_array($raw)) {
                return ['code' => -1, 'message' => 'invalid_json', 'data' => null, 'raw' => []];
            }
        } catch (\Throwable $e) {
            Log::error('穿山甲 CPS 请求失败', ['path' => $path, 'error' => $e->getMessage()]);
            return ['code' => -1, 'message' => $e->getMessage(), 'data' => null, 'raw' => []];
        }

        $code = (int) ($raw['code'] ?? -1);
        $message = (string) ($raw['message'] ?? ($raw['msg'] ?? ''));
        $parsed = $this->parseDataField($raw['data'] ?? null);

        return [
            'code' => $code,
            'message' => $message,
            'data' => is_array($parsed) ? $parsed : null,
            'raw' => $raw,
        ];
    }

    /**
     * @param mixed $data
     * @return array<string,mixed>|null
     */
    protected function parseDataField($data): ?array
    {
        if (is_array($data)) {
            return $data;
        }
        if (!is_string($data) || trim($data) === '') {
            return null;
        }
        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function clampPageSize(int $size, int $max = 50): int
    {
        return max(1, min($max, $size));
    }

    protected function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
