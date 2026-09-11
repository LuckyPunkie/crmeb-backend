<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use GuzzleHttp\Client;
use think\facade\Log;

/**
 * 拼多多多多进宝官方直连
 * 网关: https://gw-api.pinduoduo.com/api/router
 */
class PddOfficialService extends BaseServices
{
    protected $clientId;
    protected $clientSecret;
    protected $defaultPid;
    protected $mediaId;
    protected $defaultCustomParameters;
    protected $defaultActivityTag;
    protected $apiUrl;

    public function __construct()
    {
        parent::__construct('pdd', [], 'taoke');
        $this->setHttpClient(new Client([
            'timeout' => 30,
            'connect_timeout' => 8,
            'verify' => false,
        ]));
        $this->clientId = (string) config('taoke.pdd.client_id');
        $this->clientSecret = (string) config('taoke.pdd.client_secret');
        $this->defaultPid = (string) config('taoke.pdd.pid');
        $this->mediaId = (string) config('taoke.pdd.media_id');
        $this->defaultCustomParameters = (string) config('taoke.pdd.default_custom_parameters');
        $this->defaultActivityTag = (int) config('taoke.pdd.default_activity_tag');
        $this->apiUrl = (string) (config('taoke.pdd.api_url') ?: 'https://gw-api.pinduoduo.com/api/router');
    }

    public function getDefaultCustomParameters(): string
    {
        return $this->defaultCustomParameters;
    }

    /**
     * 推荐流 / 活动标签列表（无关键词）
     */
    public function fetchFeed(int $page = 1, int $pageSize = 20, int $activityTag = 0): array
    {
        $tag = $activityTag > 0 ? $activityTag : ($this->defaultActivityTag > 0 ? $this->defaultActivityTag : 4);
        $biz = array_merge($this->basePidBiz(), [
            'page' => max(1, $page),
            'page_size' => $this->clampPageSize($pageSize),
            'activity_tags' => json_encode([(int) $tag], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
        $raw = $this->call('pdd.ddk.goods.search', $biz);
        return $this->extractGoodsList($raw);
    }

    /**
     * 关键词搜索
     */
    public function fetchSearch(string $keyword, int $page = 1, int $pageSize = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return $this->fetchFeed($page, $pageSize);
        }
        $biz = array_merge($this->basePidBiz(), [
            'keyword' => $keyword,
            'page' => max(1, $page),
            'page_size' => $this->clampPageSize($pageSize),
        ]);
        $raw = $this->call('pdd.ddk.goods.search', $biz);
        return $this->extractGoodsList($raw);
    }

    /**
     * 商品详情（goods_sign）
     */
    public function fetchDetail(string $goodsSign): array
    {
        $goodsSign = trim($goodsSign);
        if ($goodsSign === '') {
            return [];
        }
        $raw = $this->call('pdd.ddk.goods.detail', ['goods_sign' => $goodsSign]);
        if (isset($raw['error_response'])) {
            return $raw;
        }
        $key = $this->responseKey($raw);
        if ($key === '') {
            return [];
        }
        $list = $raw[$key]['goods_details'] ?? $raw[$key]['goods_detail_list'] ?? [];
        if (is_array($list) && isset($list[0])) {
            return $list[0];
        }
        return is_array($list) ? $list : [];
    }

    /**
     * 高佣转链
     */
    public function generateGoodsPromotionUrl(string $goodsSign, string $pid = '', string $customParameters = ''): array
    {
        $goodsSign = trim($goodsSign);
        if ($goodsSign === '') {
            return ['error_response' => ['error_msg' => 'empty goods_sign']];
        }
        $pid = trim($pid !== '' ? $pid : $this->defaultPid);
        $customParameters = trim($customParameters !== '' ? $customParameters : $this->defaultCustomParameters);
        return $this->call('pdd.ddk.goods.promotion.url.generate', [
            'p_id' => $pid,
            'custom_parameters' => $customParameters,
            'goods_sign_list' => json_encode([$goodsSign], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'generate_we_app' => 'true',
        ]);
    }

    protected function basePidBiz(): array
    {
        $biz = [];
        if ($this->defaultPid !== '') {
            $biz['pid'] = $this->defaultPid;
        }
        if ($this->defaultCustomParameters !== '') {
            $biz['custom_parameters'] = $this->defaultCustomParameters;
        }
        return $biz;
    }

    protected function clampPageSize(int $pageSize): int
    {
        return max(10, min(100, max(1, $pageSize)));
    }

    protected function responseKey(array $raw): string
    {
        foreach ($raw as $key => $val) {
            if (is_string($key) && str_ends_with($key, '_response')) {
                return $key;
            }
        }
        return '';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function extractGoodsList(array $raw): array
    {
        if (isset($raw['error_response'])) {
            Log::warning('拼多多商品列表 API 错误', ['error' => $raw['error_response']]);
            return [];
        }
        $key = $this->responseKey($raw);
        if ($key === '') {
            return [];
        }
        $list = $raw[$key]['goods_list'] ?? [];
        return is_array($list) ? $list : [];
    }

    /**
     * 查询 PID / custom_parameters 是否已授权备案
     * bind=1 已备案
     */
    public function memberAuthorityQuery(string $pid, string $customParameters = ''): array
    {
        $biz = ['pid' => $pid];
        if ($customParameters !== '') {
            $biz['custom_parameters'] = $customParameters;
        }
        return $this->call('pdd.ddk.member.authority.query', $biz);
    }

    /**
     * 生成授权备案链接（channel_type=10）
     */
    public function generateAuthorityPromUrl(string $pid, string $customParameters): array
    {
        return $this->call('pdd.ddk.rp.prom.url.generate', [
            'p_id_list' => json_encode([$pid], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'channel_type' => 10,
            'custom_parameters' => $customParameters,
        ]);
    }

    public function getDefaultPid(): string
    {
        return $this->defaultPid;
    }

    protected function call(string $type, array $bizParams): array
    {
        if ($this->clientId === '' || $this->clientSecret === '') {
            throw new \think\exception\ValidateException('拼多多 client_id/client_secret 未配置');
        }

        $params = [
            'type' => $type,
            'client_id' => $this->clientId,
            'timestamp' => (string) time(),
            'data_type' => 'JSON',
        ];
        foreach ($bizParams as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $params[$k] = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
        }
        $params['sign'] = $this->sign($params);

        try {
            $response = $this->httpClient->post($this->apiUrl, [
                'form_params' => $params,
            ]);
            $raw = (string) $response->getBody();
            $result = json_decode($raw, true);
            if (!is_array($result)) {
                Log::error('拼多多API响应解析失败', ['type' => $type, 'raw' => $raw]);
                return [];
            }
            if (isset($result['error_response'])) {
                Log::warning('拼多多API业务错误', [
                    'type' => $type,
                    'error' => $result['error_response'],
                ]);
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error('拼多多API请求异常', [
                'type' => $type,
                'msg' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * sign = MD5( client_secret + ksort(k+v...) + client_secret ) 大写
     */
    protected function sign(array $params): string
    {
        ksort($params);
        $str = $this->clientSecret;
        foreach ($params as $k => $v) {
            if ($k === 'sign' || $v === null || $v === '') {
                continue;
            }
            $str .= $k . $v;
        }
        $str .= $this->clientSecret;
        return strtoupper(md5($str));
    }
}
