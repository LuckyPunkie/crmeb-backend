<?php

namespace crmeb\services\taoke;

use crmeb\basic\BaseServices;
use GuzzleHttp\Client;
use think\facade\Cache;
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
    protected $refreshToken;

    /** 过期前多少秒主动 refresh */
    protected int $sessionRefreshLeeway = 300;

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
        $this->session       = (string) config('taoke.taobao.session');
        $this->refreshToken  = (string) config('taoke.taobao.refresh_token');
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
     * 物料精选升级版（公开接口，响应含 publish_info.click_url 推广链接）
     * method: taobao.tbk.dg.material.recommend
     * @see https://open.taobao.com/api.htm?docId=64759&docType=2&scopeId=27939
     */
    public function materialRecommend(int $materialId = 86589, int $page = 1, int $pageSize = 20, string $itemId = ''): array
    {
        $biz = [
            'material_id' => $materialId,
            'page_no'     => $page,
            'page_size'   => $pageSize,
            'adzone_id'   => $this->adzoneId,
        ];
        if (trim($itemId) !== '') {
            $biz['item_id'] = trim($itemId);
        }
        return $this->call('taobao.tbk.dg.material.recommend', $biz);
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
    public function materialOptional(string $q, int $page = 1, int $pageSize = 20, string $cat = ''): array
    {
        $biz = [
            'page_no'    => $page,
            'page_size'  => $pageSize,
            'adzone_id'  => $this->adzoneId,
            'platform'   => '2',
        ];
        $q = trim($q);
        if ($q !== '') {
            $biz['q'] = $q;
        }
        $cat = trim($cat);
        if ($cat !== '') {
            $biz['cat'] = $cat;
        }
        return $this->call('taobao.tbk.dg.material.optional.upgrade', $biz);
    }

    /**
     * 淘宝商品后台类目（一级），供 Tab 筛选；cid 用于 optional.upgrade 的 cat 参数
     * method: taobao.itemcats.get
     */
    public function itemCatsGet(int $parentCid = 0): array
    {
        // 公开类目接口，勿带推广者 session（过期 session 会导致 Invalid session）
        return $this->call('taobao.itemcats.get', [
            'parent_cid' => $parentCid,
            'fields'     => 'cid,parent_cid,name,is_parent',
        ], false, true);
    }

    /**
     * @return array<int, array{id: int|string, text: string}>
     */
    public function fetchTopCategoryTags(int $limit = 16): array
    {
        $limit = max(4, min(24, $limit));
        $cacheKey = 'taoke_taobao_itemcats_top_' . $limit;
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $tags = $this->parseItemCatTags($this->itemCatsGet(0), $limit);
        if ($tags === []) {
            $tags = $this->fallbackTopCategoryTags();
        }
        if ($tags !== []) {
            Cache::set($cacheKey, $tags, 86400);
        }
        return $tags;
    }

    /**
     * @return array<int, array{id: int, text: string}>
     */
    protected function fallbackTopCategoryTags(): array
    {
        return [
            ['id' => 16, 'text' => '女装/女士精品'],
            ['id' => 30, 'text' => '男装'],
            ['id' => 50006843, 'text' => '女鞋'],
            ['id' => 50011740, 'text' => '男装/男鞋'],
            ['id' => 1101, 'text' => '笔记本电脑'],
            ['id' => 1512, 'text' => '手机'],
            ['id' => 50008163, 'text' => '床上用品'],
            ['id' => 50013886, 'text' => '户外/登山'],
            ['id' => 50010788, 'text' => '彩妆/香水'],
            ['id' => 50002766, 'text' => '零食/坚果'],
            ['id' => 122952001, 'text' => '餐饮具'],
            ['id' => 25, 'text' => '玩具/童车'],
        ];
    }

    /**
     * @return array<int, array{id: int, text: string}>
     */
    protected function parseItemCatTags(array $response, int $limit): array
    {
        $root = $response['itemcats_get_response'] ?? $response;
        $itemCats = $root['item_cats']['item_cat'] ?? ($root['item_cats'] ?? []);
        if (!is_array($itemCats)) {
            return [];
        }
        if (isset($itemCats['cid'])) {
            $itemCats = [$itemCats];
        }
        $tags = [];
        foreach ($itemCats as $row) {
            if (!is_array($row)) {
                continue;
            }
            $cid = (int) ($row['cid'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($cid <= 0 || $name === '') {
                continue;
            }
            $tags[] = ['id' => $cid, 'text' => $name];
            if (count($tags) >= $limit) {
                break;
            }
        }
        return $tags;
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
     * 关键词 / 后台类目筛选（空词且无类目则物料推荐榜）
     */
    public function fetchSearch(string $keyword, int $page = 1, int $pageSize = 20, int $catId = 0): array
    {
        $keyword = trim($keyword);
        $cat = $catId > 0 ? (string) $catId : '';
        if ($keyword === '' && $cat === '') {
            return $this->fetchFeed($page, $pageSize);
        }
        if ($keyword === '' && $cat !== '') {
            return $this->parseRows($this->materialOptional('', $page, $pageSize, $cat));
        }
        return $this->parseRows($this->materialOptional($keyword, $page, $pageSize, $cat));
    }

    /**
     * 商品详情：数字 num_iid 走 item.info；加密 item_id 优先 summary，再搜索兜底
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchDetail(string $goodsId, string $title = '', array $summary = []): array
    {
        return $this->fetchDetailWithMeta($goodsId, $title, $summary)['rows'];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    public function fetchDetailWithMeta(string $goodsId, string $title = '', array $summary = []): array
    {
        $goodsId = trim($goodsId);
        $meta = [
            'goods_id'            => $goodsId,
            'gateway'             => $this->apiUrl,
            'platform_only'       => true,
            'summary_used'        => false,
            'detail_from'         => '',
            'top_method'          => '',
            'pipeline'            => [],
            'official_fetch_empty' => false,
            'note'                => 'detail 字段为淘宝 TOP 响应归一化；platform_only=true 表示非客户端 summary 拼装、非订单侠。',
        ];

        if ($goodsId === '') {
            $meta['official_fetch_empty'] = true;
            $meta['pipeline'][] = 'abort: empty goods_id';
            return ['rows' => [], 'meta' => $meta];
        }

        if ($this->hasSummary($summary)) {
            $meta['summary_used'] = true;
            $meta['platform_only'] = false;
            $meta['detail_from'] = 'summary.client';
            $meta['top_method'] = '';
            $meta['pipeline'][] = 'buildDetailFromSummary（POST summary，非 TOP 直出）';
            $row = $this->buildDetailFromSummary($summary, $goodsId);
            return ['rows' => [$row], 'meta' => $meta];
        }

        $meta['pipeline'][] = 'TaobaoOfficialService::itemInfo num_iids=' . $goodsId;
        $infoRows = $this->parseRows($this->itemInfo($goodsId));
        if (!empty($infoRows)) {
            $meta['detail_from'] = 'taobao.tbk.item.info.get';
            $meta['top_method'] = 'taobao.tbk.item.info.get';
            $meta['pipeline'][] = 'hit: tbk.item.info.get';
            return ['rows' => $this->wrapItemInfoDetailRows($infoRows, $goodsId), 'meta' => $meta];
        }
        $meta['pipeline'][] = 'miss: tbk.item.info.get';

        $meta['pipeline'][] = 'findMaterialRowByItemId(material.recommend / optional.upgrade)';
        $materialRow = $this->findMaterialRowByItemId($goodsId, $title);
        if ($materialRow !== []) {
            $via = (string) ($materialRow['_detail_via'] ?? 'taobao.tbk.dg.material.upgrade');
            $meta['detail_from'] = $via;
            $meta['top_method'] = $via;
            $meta['pipeline'][] = 'hit: ' . $via;
            if (!isset($materialRow['_source'])) {
                $materialRow['_source'] = 'official';
            }
            return ['rows' => [$materialRow], 'meta' => $meta];
        }
        $meta['pipeline'][] = 'miss: material row by item_id';

        $q = trim($title) !== '' ? trim($title) : $goodsId;
        $meta['pipeline'][] = 'fetchSearch q=' . mb_substr($q, 0, 48);
        $rows = $this->fetchSearch($q, 1, 1);
        if (!empty($rows[0]) && is_array($rows[0])) {
            if (empty($rows[0]['item_id'])) {
                $rows[0]['item_id'] = $goodsId;
            }
            $meta['detail_from'] = 'taobao.tbk.dg.material.optional.upgrade';
            $meta['top_method'] = 'taobao.tbk.dg.material.optional.upgrade';
            $meta['pipeline'][] = 'hit: material.optional.upgrade search';
            $rows[0]['_source'] = $rows[0]['_source'] ?? 'official';
            $rows[0]['_detail_via'] = $rows[0]['_detail_via'] ?? 'tbk.dg.material.optional.upgrade';
            return ['rows' => [$rows[0]], 'meta' => $meta];
        }

        $meta['official_fetch_empty'] = true;
        $meta['pipeline'][] = 'all official paths empty';
        return ['rows' => [], 'meta' => $meta];
    }

    /**
     * @deprecated 官方已下线 privilege.get，请用 createPromotionLink()
     */
    public function fetchPrivilege(string $itemId): array
    {
        return $this->call('taobao.tbk.privilege.get', [
            'item_id'   => $itemId,
            'adzone_id' => $this->adzoneId,
            'platform'  => '2',
        ], true);
    }

    /**
     * 官方公开转链：物料搜索/精选升级版返回的 publish_info（无需 privilege / 万能转链权限）
     */
    public function createPromotionLink(string $itemId, string $title = ''): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return [];
        }
        $row = $this->findMaterialRowByItemId($itemId, $title);
        if ($row === []) {
            return [];
        }
        return $this->buildLinkPayloadFromMaterialRow($row);
    }

    /**
     * 详情 item.info 返回的 item_url（常为 uland/edetail），物料查不到 publish_info 时兜底
     */
    public function createLinkFromItemInfo(string $itemId): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return [];
        }
        $rows = $this->parseRows($this->itemInfo($itemId));
        if (empty($rows[0]) || !is_array($rows[0])) {
            return [];
        }
        $row = $rows[0];
        $url = $this->normalizeTbkUrl((string) ($row['item_url'] ?? ''));
        if ($url === '') {
            return [];
        }
        $title = (string) ($row['title'] ?? '');
        $payload = [
            'item_id'          => $itemId,
            'item_url'         => $url,
            'coupon_click_url' => $url,
            '_source'          => 'official',
            '_link_via'        => 'tbk.item.info.get.item_url',
        ];
        return $this->attachTpwdToLinkPayload($payload, $title);
    }

    /**
     * 推广链接在微信/淘宝内需用淘口令打开；对 s.click / uland 等 URL 调 tpwd.create
     */
    public function attachTpwdToLinkPayload(array $payload, string $title = ''): array
    {
        if ($payload === []) {
            return [];
        }
        if (!empty($payload['tpwd']) || !empty($payload['item_tpwd']) || !empty($payload['taoke_item_tpwd'])) {
            return $payload;
        }
        $url = trim((string) ($payload['coupon_click_url'] ?? ($payload['item_url'] ?? '')));
        if ($url === '') {
            return $payload;
        }
        $text = trim($title);
        if ($text === '') {
            $text = trim((string) ($payload['itemInfo']['title'] ?? ''));
        }
        if ($text === '') {
            $text = '查看详情';
        }
        if (function_exists('mb_substr')) {
            $text = mb_substr($text, 0, 20);
        } else {
            $text = substr($text, 0, 20);
        }
        $model = $this->parseTpwdModel($this->tpwdCreate($text, $url));
        if ($model === '') {
            return $payload;
        }
        $payload['tpwd'] = $model;
        $payload['item_tpwd'] = $model;
        $payload['taoke_item_tpwd'] = $model;
        $payload['_tpwd_via'] = 'taobao.tbk.tpwd.create';
        return $payload;
    }

    protected function parseTpwdModel(array $response): string
    {
        if (isset($response['error_response']) || !is_array($response)) {
            return '';
        }
        foreach ($response as $key => $inner) {
            if (!is_array($inner) || strpos((string) $key, '_response') === false) {
                continue;
            }
            if (isset($inner['data']['model'])) {
                return (string) $inner['data']['model'];
            }
            if (isset($inner['model'])) {
                return (string) $inner['model'];
            }
        }
        return '';
    }

    /**
     * 按 item_id 在物料升级版结果中定位一行（含 publish_info）
     */
    public function findMaterialRowByItemId(string $itemId, string $title = ''): array
    {
        $itemId = trim($itemId);
        if ($itemId === '') {
            return [];
        }

        // 与列表默认源一致（86589 等），从当前页物料里直接命中 publish_info
        for ($page = 1; $page <= 3; $page++) {
            $rows = $this->fetchFeed($page, 100);
            $hit = $this->pickMaterialRowByItemId($rows, $itemId);
            if ($hit !== []) {
                return $hit;
            }
            if (count($rows) < 100) {
                break;
            }
        }

        $queries = array_values(array_unique(array_filter([$itemId, trim($title)])));
        foreach ($queries as $q) {
            for ($page = 1; $page <= 2; $page++) {
                $rows = $this->parseRows($this->materialOptional($q, $page, 50));
                $hit = $this->pickMaterialRowByItemId($rows, $itemId);
                if ($hit !== []) {
                    return $hit;
                }
                if (count($rows) < 50) {
                    break;
                }
            }
        }

        // 相似品物料（文档：material_id=13256 可配合 item_id）
        $rows = $this->parseRows($this->materialRecommend(13256, 1, 20, $itemId));
        $hit = $this->pickMaterialRowByItemId($rows, $itemId);
        if ($hit !== []) {
            return $hit;
        }

        foreach ([86589, 3756, 80309] as $materialId) {
            for ($page = 1; $page <= 2; $page++) {
                $rows = $this->parseRows($this->materialRecommend($materialId, $page, 50));
                $hit = $this->pickMaterialRowByItemId($rows, $itemId);
                if ($hit !== []) {
                    return $hit;
                }
                if (count($rows) < 50) {
                    break;
                }
            }
        }

        return [];
    }

    protected function pickMaterialRowByItemId(array $rows, string $itemId): array
    {
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ($this->materialRowItemId($row) === $itemId) {
                return $row;
            }
        }
        return [];
    }

    protected function materialRowItemId(array $row): string
    {
        return (string) ($row['item_id'] ?? '');
    }

    /**
     * 与订单侠 id_privilege 返回字段对齐，供 uniapp normalizeTaokeStoreInfo 使用
     */
    public function buildLinkPayloadFromMaterialRow(array $row): array
    {
        $publish = $row['publish_info'] ?? [];
        if (!is_array($publish)) {
            $publish = [];
        }
        $click = $this->normalizeTbkUrl((string) ($publish['click_url'] ?? ''));
        $coupon = $this->normalizeTbkUrl((string) ($publish['coupon_share_url'] ?? ''));
        $itemUrl = $coupon !== '' ? $coupon : $click;
        if ($itemUrl === '') {
            return [];
        }
        $basic = is_array($row['item_basic_info'] ?? null) ? $row['item_basic_info'] : [];
        $incomeRate = (string) ($publish['income_rate'] ?? '');
        if ($incomeRate === '' && isset($publish['income_info']['commission_rate'])) {
            $incomeRate = (string) $publish['income_info']['commission_rate'];
        }

        return [
            'item_id' => $this->materialRowItemId($row),
            'item_url' => $itemUrl,
            'coupon_click_url' => $coupon !== '' ? $coupon : $itemUrl,
            'max_commission_rate' => $incomeRate,
            '_source' => 'official',
            '_link_via' => 'tbk.dg.material.upgrade',
            'itemInfo' => [
                'title' => (string) ($basic['title'] ?? ''),
                'pict_url' => (string) ($basic['pict_url'] ?? ''),
            ],
        ];
    }

    protected function normalizeTbkUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        if (strpos($url, 'http://') === 0) {
            return 'https://' . substr($url, 7);
        }
        return $url;
    }

    public function hasSummary(array $summary): bool
    {
        return trim((string) ($summary['title'] ?? ($summary['store_name'] ?? ($summary['image'] ?? '')))) !== '';
    }

    /**
     * taobao.tbk.item.info.get 旧版 n_tbk_item → 与物料升级版接近的结构，供 uniapp normalize
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function wrapItemInfoDetailRows(array $rows, string $requestedId): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $numIid = (string) ($row['num_iid'] ?? ($row['input_num_iid'] ?? ''));
            $itemId = trim($requestedId) !== '' ? trim($requestedId) : $numIid;
            $small = $row['small_images']['string'] ?? $row['small_images'] ?? [];
            if (!is_array($small)) {
                $small = [];
            }
            $out[] = [
                'item_id'          => $itemId,
                'num_iid'          => $numIid,
                'item_basic_info'  => [
                    'title'        => (string) ($row['title'] ?? ''),
                    'pict_url'     => (string) ($row['pict_url'] ?? ''),
                    'small_images' => ['string' => $small],
                    'nick'         => (string) ($row['nick'] ?? ''),
                    'volume'       => (int) ($row['volume'] ?? 0),
                ],
                'price_promotion_info' => [
                    'final_promotion_price' => (string) ($row['zk_final_price'] ?? ($row['reserve_price'] ?? '')),
                    'reserve_price'         => (string) ($row['reserve_price'] ?? ''),
                ],
                'item_url'     => (string) ($row['item_url'] ?? ''),
                '_source'      => 'official',
                '_detail_via'  => 'tbk.item.info.get',
            ];
        }
        return $out;
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
            '_source' => 'official',
            '_detail_via' => 'summary.client',
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
        return $this->requestOAuthToken([
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->getOAuthCallbackUrl(),
        ]);
    }

    /**
     * 用 refresh_token 续期 access_token
     */
    public function refreshAccessToken(bool $force = false): array
    {
        $refresh = trim($this->refreshToken);
        if ($refresh === '') {
            return ['error' => 'missing_refresh_token'];
        }

        $lockFile = runtime_path() . 'taobao_token_refresh.lock';
        $fp = @fopen($lockFile, 'c+');
        if ($fp === false) {
            Log::warning('淘宝 refresh_token 无法创建锁文件', ['path' => $lockFile]);
        } elseif (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return ['error' => 'refresh_locked'];
        }

        try {
            if (!$force && !$this->isSessionExpired() && $this->session !== '') {
                return [
                    'access_token'  => $this->session,
                    'refresh_token' => $this->refreshToken,
                    'cached'        => true,
                ];
            }

            $result = $this->requestOAuthToken([
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh,
            ]);
            if (!empty($result['access_token'])) {
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

    /**
     * 将 OAuth/refresh 返回写入内存并持久化到 .env
     */
    public function applyOAuthTokenResponse(array $token): bool
    {
        $accessToken = trim((string) ($token['access_token'] ?? ''));
        if ($accessToken === '') {
            return false;
        }

        $expiresIn = (int) ($token['expires_in'] ?? 86400);
        $expireAt = time() + max(60, $expiresIn);
        $refresh = trim((string) ($token['refresh_token'] ?? $this->refreshToken));

        $this->session = $accessToken;
        if ($refresh !== '') {
            $this->refreshToken = $refresh;
        }

        return $this->persistOAuthTokensToEnv($this->session, $this->refreshToken, $expireAt);
    }

    /**
     * session 缺失或即将过期时尝试 refresh
     */
    public function ensureSession(bool $required = false): bool
    {
        if ($this->session !== '' && !$this->isSessionExpired()) {
            return true;
        }
        if ($this->refreshToken === '') {
            return !$required && $this->session !== '';
        }

        $result = $this->refreshAccessToken();
        return !empty($result['access_token']);
    }

    protected function isSessionExpired(): bool
    {
        $expireAt = (int) config('taoke.taobao.session_expire_at');
        if ($expireAt <= 0) {
            return false;
        }
        return time() >= ($expireAt - $this->sessionRefreshLeeway);
    }

    protected function isSessionError(array $result): bool
    {
        if (!isset($result['error_response']) || !is_array($result['error_response'])) {
            return false;
        }
        $err = $result['error_response'];
        $code = (int) ($err['code'] ?? 0);
        $msg = strtolower((string) (($err['msg'] ?? '') . ' ' . ($err['sub_msg'] ?? '')));
        if (in_array($code, [26, 27], true)) {
            return true;
        }
        return strpos($msg, 'session') !== false
            || strpos($msg, 'access_token') !== false
            || strpos($msg, 'invalid-session') !== false;
    }

    protected function requestOAuthToken(array $formParams): array
    {
        try {
            $response = $this->httpClient->post('https://oauth.taobao.com/token', [
                'form_params' => array_merge([
                    'client_id'     => $this->appKey,
                    'client_secret' => $this->appSecret,
                ], $formParams),
            ]);
            $raw = (string) $response->getBody();
            $result = json_decode($raw, true);
            if (!is_array($result)) {
                return ['error' => 'invalid_token_response', 'raw' => $raw];
            }
            if (!empty($result['error'])) {
                Log::warning('淘宝 OAuth token 请求失败', ['result' => $result]);
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error('淘宝 OAuth token 请求异常', ['msg' => $e->getMessage()]);
            return ['error' => $e->getMessage()];
        }
    }

    protected function persistOAuthTokensToEnv(string $session, string $refreshToken, int $expireAt): bool
    {
        $envPath = app()->getRootPath() . '.env';
        if (!is_file($envPath) || !is_writable($envPath)) {
            Log::warning('淘宝 OAuth 无法写入 .env', ['path' => $envPath]);
            return false;
        }

        $content = file_get_contents($envPath);
        if ($content === false) {
            return false;
        }

        $updates = [
            'TAOBAO_SESSION'            => $session,
            'TAOBAO_REFRESH_TOKEN'      => $refreshToken,
            'TAOBAO_SESSION_EXPIRE_AT'    => (string) $expireAt,
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
                Log::warning('淘宝 OAuth .env 缺少 [TAOKE] 段，无法写入', ['key' => $key]);
                return false;
            }
        }

        $ok = file_put_contents($envPath, $content) !== false;
        if ($ok) {
            Log::info('淘宝 OAuth token 已写入 .env', [
                'expire_at' => $expireAt,
                'expire_at_human' => date('Y-m-d H:i:s', $expireAt),
            ]);
        }
        return $ok;
    }

    // ==================== 底层 ====================

    protected function call(string $method, array $bizData, bool $requireSession = false, bool $omitSession = false): array
    {
        if ($this->appKey === '' || $this->appSecret === '') {
            throw new \think\exception\ValidateException('淘宝联盟 appKey/appSecret 未配置');
        }

        if ($requireSession && !$this->ensureSession(true)) {
            Log::warning('淘宝联盟API缺少session', ['method' => $method]);
            return ['error_response' => ['code' => 26, 'msg' => 'Missing session', 'sub_msg' => 'TAOBAO_SESSION 未配置或 refresh 失败']];
        }

        $result = $this->executeCall($method, $bizData, $omitSession);
        if ($requireSession && $this->isSessionError($result) && $this->refreshToken !== '') {
            $refresh = $this->refreshAccessToken();
            if (!empty($refresh['access_token'])) {
                $result = $this->executeCall($method, $bizData, $omitSession);
            }
        }
        return $result;
    }

    protected function executeCall(string $method, array $bizData, bool $omitSession = false): array
    {
        $sysParams = [
            'method'      => $method,
            'app_key'     => $this->appKey,
            'timestamp'   => date('Y-m-d H:i:s'),
            'format'      => 'json',
            'v'           => '2.0',
            'sign_method' => 'md5',
        ];
        if (!$omitSession && $this->session !== '') {
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
