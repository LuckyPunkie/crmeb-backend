<?php

namespace app\common\repositories\taoke;

use crmeb\services\taoke\DingDanXiaService;
use crmeb\services\taoke\JdOfficialService;
use crmeb\services\taoke\JuTuiKeService;
use crmeb\services\taoke\KuaishouOfficialService;
use crmeb\services\taoke\PddOfficialService;
use crmeb\services\taoke\TaobaoOfficialService;
use think\facade\Log;

/**
 * 服务页联盟商品聚合 / 品牌检索
 */
class ServiceGoodsRepository
{
    /** null=读 .env；legacy|official=API 通道锁定（与 /taoke/goods vs /taoke/official/goods 对应） */
    protected ?string $driverChannel = null;

    protected DingDanXiaService $dingdanxia;
    protected JuTuiKeService $jutuike;
    protected JdOfficialService $jdOfficial;
    protected TaobaoOfficialService $taobaoOfficial;
    protected PddOfficialService $pddOfficial;
    protected KuaishouOfficialService $kuaishouOfficial;

    public function __construct(
        DingDanXiaService $dingdanxia,
        JuTuiKeService $jutuike,
        JdOfficialService $jdOfficial,
        TaobaoOfficialService $taobaoOfficial,
        PddOfficialService $pddOfficial,
        KuaishouOfficialService $kuaishouOfficial
    ) {
        $this->dingdanxia = $dingdanxia;
        $this->jutuike = $jutuike;
        $this->jdOfficial = $jdOfficial;
        $this->taobaoOfficial = $taobaoOfficial;
        $this->pddOfficial = $pddOfficial;
        $this->kuaishouOfficial = $kuaishouOfficial;
    }

    public function withDriverChannel(string $channel): self
    {
        $clone = clone $this;
        $clone->driverChannel = $channel === 'official' ? 'official' : 'legacy';
        return $clone;
    }

    public function getDriverChannel(): ?string
    {
        return $this->driverChannel;
    }

    /** 官方通道下禁止回退订单侠/聚推客 */
    protected function allowLegacyFallback(): bool
    {
        return $this->driverChannel !== 'official';
    }

    /**
     * official 列表：透传平台 API 原字段，仅补 platform / _source 便于混排去重与前端识别
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function tagOfficialPlatformList(array $rows, string $platform): array
    {
        $platform = strtolower(trim($platform));
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (!isset($row['platform'])) {
                $row['platform'] = $platform;
            }
            if (!isset($row['_source'])) {
                $row['_source'] = 'official';
            }
            $out[] = $row;
        }
        return $out;
    }

    protected function useOfficialFor(string $platform): bool
    {
        if ($this->driverChannel === 'legacy') {
            return false;
        }
        if ($this->driverChannel === 'official') {
            return true;
        }
        return config('taoke.driver.' . $platform) === 'official';
    }

    public function isTaobaoOfficial(): bool
    {
        return $this->useOfficialFor('taobao');
    }

    public function getTaobaoDataSource(): string
    {
        return $this->isTaobaoOfficial() ? 'official' : 'legacy';
    }

    protected function isJdOfficial(): bool
    {
        return $this->useOfficialFor('jd');
    }

    /** 联调标记：京东列表/搜索当前数据源 official | legacy */
    public function getJdDataSource(): string
    {
        return $this->isJdOfficial() ? 'official' : 'legacy';
    }

    public function isPddOfficial(): bool
    {
        return $this->useOfficialFor('pdd');
    }

    public function getPddDataSource(): string
    {
        return $this->isPddOfficial() ? 'official' : 'legacy';
    }

    public function isKuaishouOfficial(): bool
    {
        return $this->useOfficialFor('kuaishou');
    }

    public function getKuaishouDataSource(): string
    {
        return $this->isKuaishouOfficial() ? 'official' : 'legacy';
    }

    /**
     * 拼多多详情（按 driver）
     */
    public function fetchPddDetail(string $goodsSign): array
    {
        return $this->fetchPddDetailWithMeta($goodsSign)['detail'];
    }

    /**
     * @return array{detail: array, meta: array<string, mixed>}
     */
    public function fetchPddDetailWithMeta(string $goodsSign): array
    {
        $goodsSign = trim($goodsSign);
        $meta = [
            'goods_sign' => $goodsSign,
            'driver' => $this->getPddDataSource(),
            'summary_used' => false,
            'pipeline' => [],
            'legacy_full_fallback' => false,
            'official_fetch_empty' => false,
            'detail_from' => '',
            'note' => 'official 通道(OfficialGoods) allowLegacyFallback=false，禁止订单侠；详情走 pdd.ddk.goods.detail(goods_sign)。',
        ];

        if ($this->isPddOfficial()) {
            $meta['pipeline'][] = 'PddOfficialService::fetchDetail(goods_sign)';
            $detail = $this->pddOfficial->fetchDetail($goodsSign);
            if (!empty($detail) && !isset($detail['error_response'])) {
                $meta['detail_from'] = 'pdd.ddk.goods.detail';
                return ['detail' => $detail, 'meta' => $meta];
            }
            $meta['official_fetch_empty'] = true;
            if ($this->allowLegacyFallback()) {
                $meta['pipeline'][] = 'legacy: DingDanXiaService::pddGoodsDetail(仅 legacy 路由)';
                $meta['legacy_full_fallback'] = true;
                $meta['detail_from'] = 'dingdanxia.pddGoodsDetail';
                return ['detail' => $this->dingdanxia->pddGoodsDetail($goodsSign), 'meta' => $meta];
            }
            return ['detail' => [], 'meta' => $meta];
        }

        $meta['pipeline'][] = 'DingDanXiaService::pddGoodsDetail(legacy 路由)';
        $meta['detail_from'] = 'dingdanxia.pddGoodsDetail';
        return ['detail' => $this->dingdanxia->pddGoodsDetail($goodsSign), 'meta' => $meta];
    }

    /**
     * 拼多多高佣转链
     */
    public function getKuaishouChannelTags(): array
    {
        if (!$this->isKuaishouOfficial()) {
            return [];
        }
        $rows = $this->kuaishouOfficial->fetchSelectionChannels();
        $data = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = $row['channelId'] ?? ($row['id'] ?? '');
            $text = $row['channelName'] ?? ($row['name'] ?? '');
            if ($id === '' || $text === '') {
                continue;
            }
            $data[] = ['id' => $id, 'text' => $text];
        }
        return $data;
    }

    public function fetchKuaishouDetail(string $goodsId, array $summary = []): array
    {
        $bundle = $this->fetchKuaishouDetailWithMeta($goodsId, $summary);
        return $bundle['detail'];
    }

    /**
     * @return array{detail: array, meta: array<string, mixed>}
     */
    public function fetchKuaishouDetailWithMeta(string $goodsId, array $summary = []): array
    {
        $goodsId = trim($goodsId);
        $summary = is_array($summary) ? $summary : [];
        $meta = [
            'goods_id' => $goodsId,
            'driver' => $this->getKuaishouDataSource(),
            'summary_used' => $summary !== [],
            'pipeline' => [],
            'legacy_full_fallback' => false,
            'official_fetch_empty' => false,
            'detail_from' => '',
            'note' => '快手 detail 接口常返回多商品数组且不含请求 id；列表 summary=官方选品行。无订单侠。',
            'summary_has_express' => !empty($summary['express_id']) || !empty($summary['expressId']),
        ];

        if ($this->isKuaishouOfficial()) {
            $meta['detail_param_hint'] = '官方文档：selection.item.detail 入参 itemId 为 List<Long>（如 [26957319894510]），勿用 goodsId+cpsPid';
            foreach ($this->kuaishouDetailIdCandidates($goodsId, $summary) as $tryId) {
                $ctx = array_merge($summary, ['goods_id' => $tryId, 'goodsId' => $tryId]);
                $detail = $this->kuaishouOfficial->fetchDetail($tryId, $ctx);
                $detailFromApi = !empty($detail) && isset($detail['goodsId']);
                if (!$detailFromApi) {
                    $meta['pipeline'][] = 'selection.item.detail 无匹配（检查 itemId 数组） tryId=' . $tryId;
                    $resolved = $this->kuaishouOfficial->resolveSelectionRow($tryId, $ctx);
                    if (is_array($resolved) && !empty($resolved['row']['goodsId'])) {
                        $detail = $resolved['row'];
                        if (($resolved['via'] ?? '') === 'summary') {
                            $meta['pipeline'][] = '使用列表 POST summary 还原选品行（与 kuaishou_goods 同源）';
                            $meta['detail_from'] = 'kwaimoney.selection.list_row_summary';
                        } else {
                            $meta['pipeline'][] = 'selection.item.list 关键词/频道反查命中';
                            $meta['detail_from'] = 'kwaimoney.selection.list_refetch';
                        }
                    } else {
                        $meta['pipeline'][] = 'list 反查未命中 tryId=' . $tryId;
                        continue;
                    }
                } else {
                    $meta['pipeline'][] = 'KuaishouOfficialService::fetchDetail(itemId[]) 命中';
                    $meta['detail_from'] = 'kwaimoney.selection.detail';
                }
                if (!$this->kuaishouDetailMatchesList($detail, $summary)) {
                    Log::warning('快手详情与列表摘要不一致，尝试下一 ID', [
                        'try_id' => $tryId,
                        'detail_title' => (string) ($detail['itemTitle'] ?? ''),
                        'summary_title' => (string) ($summary['store_name'] ?? ($summary['title'] ?? '')),
                    ]);
                    continue;
                }
                if ($summary !== []) {
                    $summaryMap = [
                        'expressId' => $summary['expressId'] ?? ($summary['express_id'] ?? null),
                        'expressType' => $summary['expressType'] ?? ($summary['express_type'] ?? null),
                        'relItemId' => $summary['relItemId'] ?? ($summary['rel_item_id'] ?? null),
                        'itemLinkUrl' => $summary['itemLinkUrl'] ?? ($summary['item_link_url'] ?? null),
                        'couponClickUrl' => $summary['couponClickUrl'] ?? ($summary['coupon_click_url'] ?? null),
                    ];
                    foreach ($summaryMap as $key => $summaryVal) {
                        $detailVal = $detail[$key] ?? null;
                        if (($detailVal === null || $detailVal === '' || $detailVal === 0) && $summaryVal !== null && $summaryVal !== '' && $summaryVal !== 0) {
                            $detail[$key] = $summaryVal;
                        }
                    }
                }
                return ['detail' => $this->formatKuaishouDetail($detail), 'meta' => $meta];
            }
            if ($summary !== []) {
                $meta['pipeline'][] = 'official API 未命中 -> 列表 summary（非订单侠）';
                $meta['detail_from'] = 'list_summary_official';
                return ['detail' => $this->formatKuaishouDetail($summary), 'meta' => $meta];
            }
            $meta['official_fetch_empty'] = true;
            return ['detail' => [], 'meta' => $meta];
        }

        if ($summary !== []) {
            $meta['pipeline'][] = 'legacy 路由 + 列表 summary';
            $meta['detail_from'] = 'list_summary';
            return ['detail' => $this->formatKuaishouDetail($summary), 'meta' => $meta];
        }
        return ['detail' => [], 'meta' => $meta];
    }

    /**
     * 列表/详情统一用 goodsId（勿把 distributeItemId 当详情主键）
     */
    protected function kuaishouListItemId(array $val): string
    {
        foreach (['goodsId', 'relItemId', 'itemId', 'item_id', 'kwaiItemId'] as $key) {
            $id = trim((string) ($val[$key] ?? ''));
            if ($id !== '') {
                return $id;
            }
        }

        return trim((string) ($val['distributeItemId'] ?? ''));
    }

    /**
     * @return array<int, string>
     */
    protected function kuaishouDetailIdCandidates(string $goodsId, array $summary = []): array
    {
        $ids = [];
        $push = function ($id) use (&$ids) {
            $id = trim((string) $id);
            if ($id !== '' && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        };
        $push($goodsId);
        foreach (['goods_id', 'goodsId', 'product_id', 'rel_item_id', 'relItemId'] as $key) {
            if (isset($summary[$key])) {
                $push($summary[$key]);
            }
        }

        return $ids;
    }

    protected function kuaishouDetailMatchesList(array $detail, array $summary): bool
    {
        if ($summary === []) {
            return true;
        }
        $want = trim((string) ($summary['store_name'] ?? ($summary['title'] ?? ($summary['goods_name'] ?? ($summary['itemTitle'] ?? '')))));
        if ($want === '') {
            return true;
        }
        $got = trim((string) ($detail['itemTitle'] ?? ($detail['title'] ?? ($detail['goods_name'] ?? ''))));
        if ($got === '') {
            return true;
        }
        if ($want === $got) {
            return true;
        }
        $headWant = mb_substr($want, 0, 12);
        $headGot = mb_substr($got, 0, 12);
        if ($headWant !== '' && (mb_strpos($got, $headWant) !== false || mb_strpos($want, $headGot) !== false)) {
            return true;
        }
        similar_text($want, $got, $pct);

        return $pct >= 35.0;
    }

    public function createKuaishouPromotion(string $goodsId, array $context = []): array
    {
        if (!$this->isKuaishouOfficial()) {
            return [];
        }
        $raw = $this->kuaishouOfficial->createCpsLink($goodsId, '', $context);
        if ((int) ($raw['result'] ?? 0) === 1 && !empty($raw['data'])) {
            return $this->formatKuaishouLinkResponse($raw['data']);
        }
        return is_array($raw) ? $raw : [];
    }

    /**
     * @param mixed $data
     * @return array<string, mixed>
     */
    protected function formatKuaishouLinkResponse($data): array
    {
        if (!is_array($data)) {
            return ['link' => (string) $data];
        }
        $link = (string) ($data['kwaiUrl'] ?? ($data['linkUrl'] ?? ($data['cpsLink'] ?? ($data['promotionLink'] ?? ($data['url'] ?? ($data['itemLinkUrl'] ?? ''))))));
        $pwd = (string) ($data['linkCode'] ?? ($data['commandContent'] ?? ($data['kwaiPassword'] ?? ($data['password'] ?? ($data['shareToken'] ?? '')))));
        $out = $data;
        if ($link !== '') {
            $out['link_url'] = $link;
            $out['click_url'] = $link;
        }
        if (!empty($data['shortContent'])) {
            $out['short_content'] = (string) $data['shortContent'];
        }
        if (!empty($data['nebulaKwaiUrl'])) {
            $out['nebula_kwai_url'] = (string) $data['nebulaKwaiUrl'];
        }
        if ($pwd !== '') {
            $out['password'] = $pwd;
            $out['link_code'] = $pwd;
        }
        $out['_source'] = $this->getKuaishouDataSource();
        return $out;
    }

    protected function snakeCase(string $key): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key) ?? $key);
    }

    protected function formatKuaishouDetail(array $item): array
    {
        $goodsId = (string) ($item['goodsId'] ?? ($item['goods_id'] ?? ''));
        $priceCent = (int) ($item['zkFinalPrice'] ?? ($item['zkGoodsPrice'] ?? ($item['goodsPrice'] ?? 0)));
        if ($priceCent <= 0 && isset($item['price']) && $item['price'] !== '' && $item['price'] !== null) {
            $price = number_format((float) $item['price'], 2, '.', '');
        } else {
            $price = $priceCent > 0 ? number_format($priceCent / 100, 2, '.', '') : '0.00';
        }
        $image = (string) ($item['itemImgUrl'] ?? ($item['itemCdnImgUrl'] ?? ($item['image'] ?? '')));
        $gallery = [];
        if ($image !== '') {
            $gallery[] = $image;
        }
        if (!empty($item['itemDescUrls']) && is_array($item['itemDescUrls'])) {
            $gallery = array_merge($gallery, $item['itemDescUrls']);
        }
        return [
            'platform' => 'kuaishou',
            '_source' => $this->getKuaishouDataSource(),
            'goods_id' => $goodsId,
            'product_id' => $goodsId,
            'goods_name' => (string) ($item['itemTitle'] ?? ($item['title'] ?? ($item['store_name'] ?? ''))),
            'title' => (string) ($item['itemTitle'] ?? ($item['title'] ?? ($item['store_name'] ?? ''))),
            'store_name' => (string) ($item['itemTitle'] ?? ($item['title'] ?? ($item['store_name'] ?? ''))),
            'image' => $image,
            'gallery' => $gallery,
            'mall_name' => (string) ($item['mallName'] ?? ($item['mallFullName'] ?? '')),
            'price' => $price,
            'ot_price' => (string) ($item['ot_price'] ?? $price),
            'sales' => (int) ($item['soldCountThirtyDays'] ?? ($item['salesTip'] ?? 0)),
            'sales_text' => (string) ($item['salesTip'] ?? ''),
            'promotion_rate' => isset($item['promotionRate']) ? ((int) $item['promotionRate']) / 10 : '',
            'item_link_url' => (string) ($item['itemLinkUrl'] ?? ''),
            'express_id' => (int) ($item['expressId'] ?? 0),
            'express_type' => (int) ($item['expressType'] ?? 0),
            'rel_item_id' => (string) ($item['relItemId'] ?? ''),
            'raw' => $item,
        ];
    }

    public function createPddPromotion(string $goodsSign, string $pid, string $customParameters): array
    {
        if ($this->isPddOfficial()) {
            $raw = $this->pddOfficial->generateGoodsPromotionUrl($goodsSign, $pid, $customParameters);
            if (!isset($raw['error_response'])) {
                $key = '';
                foreach ($raw as $k => $v) {
                    if (is_string($k) && str_ends_with($k, '_response')) {
                        $key = $k;
                        break;
                    }
                }
                $list = $key !== '' ? ($raw[$key]['goods_promotion_url_list'] ?? []) : [];
                $first = is_array($list) && isset($list[0]) ? $list[0] : [];
                if (!empty($first)) {
                    return $first;
                }
            }
            if (!$this->allowLegacyFallback()) {
                return [];
            }
            // [官方直连切换 2026-09-10] 原订单侠调用：
        }
        return $this->dingdanxia->pddHighCommission($goodsSign, $pid, $customParameters);
    }

    /**
     * 京东商品详情（按 driver 路由 + 统一 list 结构）
     */
    public function fetchJdDetail($itemIds, array $summary = [], array $hints = []): array
    {
        return $this->fetchJdDetailWithMeta($itemIds, $summary, $hints)['list'];
    }

    /**
     * 详情 + 联调元数据（区分联盟官方 / 后台补全 / 订单侠媒体补图）
     *
     * @return array{list: array, meta: array<string, mixed>}
     */
    public function fetchJdDetailWithMeta($itemIds, array $summary = [], array $hints = []): array
    {
        $meta = [
            'item_ids' => is_array($itemIds) ? implode(',', $itemIds) : (string) $itemIds,
            'driver' => $this->getJdDataSource(),
            'summary_used' => $summary !== [],
            'hints_used' => array_filter(is_array($hints) ? $hints : []),
            'pipeline' => [],
            'official_before_supplement' => null,
            'legacy_media_supplement' => false,
            'legacy_full_fallback' => false,
            'official_fetch_empty' => false,
            'backend_fields_on_list_item' => ['_source', 'goods_id'],
            'note' => '商详主接口 jd.union.open.goods.bigfield.query：sceneId=1+itemIds、sceneId=2+skuIds(需权限)；列表 summary 补价图；京粉 itemId 不稳定。',
            'jd_item_id_unstable' => true,
        ];

        if ($this->isJdOfficial()) {
            if ($summary !== []) {
                $meta['pipeline'][] = 'JdOfficialService::fetchDetail(bigfield itemIds sceneId=1 + summary；hints→sku sceneId=2)';
            } else {
                $meta['pipeline'][] = 'JdOfficialService::fetchDetail(bigfield itemIds/skuIds；无 summary 时价图可能偏少)';
            }
            $raw = $this->jdOfficial->fetchDetail($itemIds, $summary, $hints);
            $beforeRows = $this->normalizeJdDetailRowsForMerge($raw);
            $meta['official_before_supplement'] = $beforeRows[0] ?? null;
            if ($beforeRows === []) {
                $meta['official_fetch_empty'] = true;
            }

            if ($this->allowLegacyFallback() && $beforeRows === []) {
                $meta['pipeline'][] = 'legacy: DingDanXiaService::jdGoodsDetail(仅 driver=legacy 或 allowLegacyFallback)';
                $raw = $this->dingdanxia->jdGoodsDetail($itemIds);
                $meta['legacy_full_fallback'] = true;
                $meta['backend_fields_on_list_item'][] = '_detail_fallback';
            } elseif ($beforeRows !== [] && $this->allowLegacyFallback()) {
                $supplemented = false;
                $raw = $this->supplementJdOfficialDetailMedia($itemIds, $raw, $supplemented);
                $meta['legacy_media_supplement'] = $supplemented;
                if ($supplemented) {
                    $meta['pipeline'][] = 'legacy: supplementJdOfficialDetailMedia';
                    $meta['backend_fields_on_list_item'][] = '_detail_media';
                }
            } else {
                $raw = $beforeRows;
            }
        } else {
            $meta['pipeline'][] = 'DingDanXiaService::jdGoodsDetail(订单侠 jd/item_detail)';
            $raw = $this->dingdanxia->jdGoodsDetail($itemIds);
        }

        $meta['pipeline'][] = 'formatJdDetailList(+_source)';
        $list = $this->formatJdDetailList($raw);
        if (!empty($meta['legacy_full_fallback']) && !empty($list[0]) && is_array($list[0])) {
            $list[0]['_detail_fallback'] = 'legacy_full';
        }

        return ['list' => $list, 'meta' => $meta];
    }

    /**
     * 官方详情缺长图/图文时，用订单侠 jd/item_detail 仅补媒体字段（不改变 _source=official）
     */
    protected function supplementJdOfficialDetailMedia($itemIds, array $raw, bool &$supplemented = false): array
    {
        $supplemented = false;
        $list = $this->normalizeJdDetailRowsForMerge($raw);
        if ($list === []) {
            return $raw;
        }
        $row = $list[0];
        if ($this->jdDetailHasRichMedia($row)) {
            return [$row];
        }
        try {
            $legacyRaw = $this->dingdanxia->jdGoodsDetail($itemIds);
            $legacyList = $this->normalizeJdDetailRowsForMerge($legacyRaw);
            $legacy = $legacyList[0] ?? null;
            if (!is_array($legacy)) {
                return [$row];
            }
            foreach (['detailImages', 'baseBigFieldInfo', 'imageInfo', 'categoryInfo', 'shopInfo', 'promotionInfo'] as $field) {
                if (empty($row[$field]) && !empty($legacy[$field])) {
                    $row[$field] = $legacy[$field];
                }
            }
            if (empty($row['materialUrl']) && !empty($legacy['materialUrl'])) {
                $row['materialUrl'] = $legacy['materialUrl'];
            }
            if (empty($row['skuId']) && !empty($legacy['mainSkuId'])) {
                $row['skuId'] = $legacy['mainSkuId'];
            }
            if (empty($row['spuid']) && !empty($legacy['productId'])) {
                $row['spuid'] = $legacy['productId'];
            }
            $row['_detail_media'] = 'legacy_supplement';
            $supplemented = true;
        } catch (\Throwable $e) {
            Log::warning('京东官方详情媒体补全失败', ['itemIds' => $itemIds, 'error' => $e->getMessage()]);
        }
        return [$row];
    }

    protected function normalizeJdDetailRowsForMerge($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        if (isset($raw['list']) && is_array($raw['list'])) {
            return array_values(array_filter($raw['list'], 'is_array'));
        }
        if (isset($raw['data']) && is_array($raw['data'])) {
            return array_values(array_filter($raw['data'], 'is_array'));
        }
        if (isset($raw[0]) && is_array($raw[0])) {
            return array_values(array_filter($raw, 'is_array'));
        }
        if (isset($raw['itemId']) || isset($raw['skuName']) || isset($raw['skuId'])) {
            return [$raw];
        }
        return [];
    }

    protected function jdDetailHasRichMedia(array $row): bool
    {
        $images = $row['detailImages'] ?? [];
        if (is_array($images) && count($images) > 0) {
            return true;
        }
        $wdis = $row['baseBigFieldInfo']['wdis'] ?? '';
        if (is_string($wdis) && strlen(trim(strip_tags($wdis))) > 30) {
            return true;
        }
        if (is_array($wdis) && !empty($wdis)) {
            return true;
        }
        return false;
    }

    /**
     * 淘宝商品详情（按 driver 路由）
     */
    public function fetchTaobaoDetail(string $goodsId, string $title = '', array $summary = []): array
    {
        if ($this->isTaobaoOfficial()) {
            $rows = $this->taobaoOfficial->fetchDetail($goodsId, $title, $summary);
            if (!empty($rows[0]) && is_array($rows[0])) {
                return $rows[0];
            }
            if (!$this->allowLegacyFallback()) {
                return [];
            }
            // [官方直连切换 2026-09-09] 权限未开或加密 ID 查不到时回退订单侠：
            return $this->dingdanxia->taobaoGoodsDetail($goodsId, $title);
        }
        return $this->dingdanxia->taobaoGoodsDetail($goodsId, $title);
    }

    /**
     * 淘宝高佣转链
     */
    public function createTaobaoLink(string $goodsId, string $relateId = '', string $title = '', array $hints = []): array
    {
        if ($this->isTaobaoOfficial()) {
            $cached = $this->buildTaobaoLinkFromHints($goodsId, $hints);
            if ($cached !== []) {
                return $cached;
            }
            $link = $this->taobaoOfficial->createPromotionLink($goodsId, $title);
            if (!empty($link['item_url']) || !empty($link['coupon_click_url'])) {
                return $link;
            }
            if (!$this->allowLegacyFallback()) {
                return [];
            }
            // [官方直连] privilege.get / general.link.convert 已不可用；物料接口仍无链接时再回退订单侠
            return $this->dingdanxia->taobaoHighCommission($goodsId, $relateId);
        }
        return $this->dingdanxia->taobaoHighCommission($goodsId, $relateId);
    }

    /** 列表/详情已带物料升级版 publish_info 链接时直接复用，避免二次请求拿不到同一商品 */
    protected function buildTaobaoLinkFromHints(string $goodsId, array $hints): array
    {
        $itemUrl = trim((string) ($hints['item_url'] ?? ($hints['taoke_item_url'] ?? '')));
        $couponUrl = trim((string) ($hints['coupon_click_url'] ?? ($hints['taoke_coupon_click_url'] ?? '')));
        $itemUrl = $this->normalizeTbkAffiliateUrl($itemUrl);
        $couponUrl = $this->normalizeTbkAffiliateUrl($couponUrl);
        $promo = $couponUrl !== '' ? $couponUrl : $itemUrl;
        if ($promo === '') {
            return [];
        }
        return [
            'item_id' => $goodsId,
            'item_url' => $promo,
            'coupon_click_url' => $couponUrl !== '' ? $couponUrl : $promo,
            '_source' => 'official',
            '_link_via' => 'tbk.dg.material.upgrade.cached',
        ];
    }

    protected function normalizeTbkAffiliateUrl(string $url): string
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

    protected function formatJdDetailList($raw): array
    {
        if (isset($raw['list']) && is_array($raw['list'])) {
            $list = $raw['list'];
        } elseif (is_array($raw) && (isset($raw[0]) || $raw === [])) {
            $list = $raw;
        } elseif (is_array($raw) && (isset($raw['skuId']) || isset($raw['itemId']) || isset($raw['skuName']))) {
            $list = [$raw];
        } else {
            $list = [];
        }

        $source = $this->getJdDataSource();
        foreach ($list as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $list[$i]['_source'] = $source;
            if (isset($item['detailImages']) && is_string($item['detailImages'])) {
                $parts = array_filter(array_map('trim', explode(',', $item['detailImages'])));
                $list[$i]['detailImages'] = array_values($parts);
            }
        }
        return $list;
    }

    /**
     * 推荐：多平台汇总
     */
    public function aggregateRecommend(int $page = 1, int $limit = 20, string $platform = ''): array
    {
        $platform = strtolower(trim($platform));
        $per = max(4, (int)ceil($limit / 2));
        $list = [];

        $platforms = $platform !== '' ? [$platform] : ['taobao', 'jd', 'pdd', 'kuaishou', 'douyin'];
        foreach ($platforms as $item) {
            $list = array_merge($list, $this->safePlatformFeed($item, $page, $per));
        }

        return array_slice($this->uniqueList($list), 0, $limit);
    }

    /**
     * 品牌 Tab：按品牌名跨平台检索
     */
    public function searchByBrand(string $keyword, int $page = 1, int $limit = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }
        $per = max(4, (int)ceil($limit / 2));
        $list = [];
        foreach (['taobao', 'jd', 'pdd', 'kuaishou', 'douyin'] as $platform) {
            $list = array_merge($list, $this->safePlatformSearch($platform, $keyword, $page, $per));
        }
        return array_slice($this->uniqueList($list), 0, $limit);
    }

    /**
     * 多品牌聚合搜索：对每个 keyword 各调一遍 searchByBrand，合并去重再截断
     */
    public function searchByBrands(array $keywords, int $page = 1, int $limit = 20): array
    {
        $keywords = array_values(array_filter(array_map('trim', $keywords), function ($k) {
            return $k !== '';
        }));
        if (empty($keywords)) return [];
        $per = max(4, (int)ceil($limit / count($keywords)));
        $merged = [];
        foreach ($keywords as $kw) {
            $merged = array_merge($merged, $this->searchByBrand($kw, $page, $per));
            if (count($merged) >= $limit * 3) break; // 早停：够 3 倍就不继续
        }
        return array_slice($this->uniqueList($merged), 0, $limit);
    }

    public function searchPlatform(string $platform, string $keyword, int $page = 1, int $limit = 20, $cate = 0, array $rangeList = []): array
    {
        $platform = strtolower(trim($platform));
        if ($keyword !== '') {
            return $this->safePlatformSearch($platform, $keyword, $page, $limit, $cate, $rangeList);
        }
        return $this->safePlatformFeed($platform, $page, $limit, $cate, $rangeList);
    }

    protected function safePlatformFeed(string $platform, int $page, int $limit, $cate = 0, array $rangeList = []): array
    {
        try {
            switch ($platform) {
                case 'taobao':
                    if ($this->isTaobaoOfficial()) {
                        $list = $this->tagOfficialPlatformList(
                            $this->taobaoOfficial->fetchFeed($page, $limit, (int) $cate),
                            'taobao'
                        );
                        if (!empty($list)) {
                            return $list;
                        }
                        if (!$this->allowLegacyFallback()) {
                            return [];
                        }
                        // [官方直连切换 2026-09-09] 原订单侠调用（driver_taobao=legacy 或 official 空结果时）：
                    }
                    return $this->normalizeTaobao($this->dingdanxia->taobaoGoods($page, $limit));
                case 'jd':
                    if ($this->isJdOfficial()) {
                        $list = $this->normalizeJd(
                            $this->jdOfficial->fetchFeed($page, $limit, (int) $cate)
                        );
                        if (!empty($list)) {
                            return $list;
                        }
                        if (!$this->allowLegacyFallback()) {
                            return [];
                        }
                        // official 空结果时回退 legacy
                    }
                    // [官方直连切换 2026-09-09] 原订单侠/聚推客调用（driver_jd=legacy 时生效）：
                    $list = $this->normalizeJd($this->dingdanxia->jdGoods($page, $limit, (int)$cate));
                    if (!empty($list)) {
                        return $list;
                    }
                    return $this->normalizeJd($this->jutuike->jdSelection($page, $limit));
                case 'pdd':
                    if ($this->isPddOfficial()) {
                        $rows = $this->pddOfficial->fetchFeed($page, $limit, (int) $cate);
                        $list = $this->normalizePdd($rows);
                        if (!empty($list)) {
                            return $list;
                        }
                        if (!$this->allowLegacyFallback()) {
                            return [];
                        }
                        // [官方直连切换 2026-09-10] 原订单侠调用（driver_pdd=legacy 或 official 空结果时）：
                    }
                    $raw = $this->dingdanxia->pddGoods($page, $limit, $cate);
                    return $this->normalizePdd($raw['list'] ?? (is_array($raw) ? $raw : []));
                case 'kuaishou':
                    if ($this->isKuaishouOfficial()) {
                        $channelId = (int) $cate;
                        return $this->normalizeKuaishou(
                            $this->kuaishouOfficial->fetchFeed($page, $limit, $channelId, $rangeList)
                        );
                    }
                    return [];
                case 'douyin':
                    return $this->fetchDouyinList($keyword, $page, $limit);
                default:
                    return [];
            }
        } catch (\Throwable $e) {
            Log::error('服务页平台推荐失败', ['platform' => $platform, 'error' => $e->getMessage()]);
            return [];
        }
    }

    protected function safePlatformSearch(string $platform, string $keyword, int $page, int $limit, $cate = 0, array $rangeList = []): array
    {
        try {
            switch ($platform) {
                case 'taobao':
                    if ($this->isTaobaoOfficial()) {
                        $list = $this->tagOfficialPlatformList(
                            $this->taobaoOfficial->fetchSearch($keyword, $page, $limit),
                            'taobao'
                        );
                        if (!empty($list)) {
                            return $list;
                        }
                        if (!$this->allowLegacyFallback()) {
                            return [];
                        }
                        // [官方直连切换 2026-09-09] 原订单侠调用：
                    }
                    return $this->normalizeTaobao($this->dingdanxia->taobaoGoodsSearch($page, $limit, $keyword));
                case 'jd':
                    if ($this->isJdOfficial()) {
                        return $this->normalizeJd(
                            $this->jdOfficial->fetchSearch($keyword, $page, $limit)
                        );
                    }
                    // [官方直连切换 2026-09-09] 原订单侠调用（driver_jd=legacy 时生效）：
                    return $this->normalizeJd($this->dingdanxia->jdGoodsSearch($keyword, $page, $limit));
                case 'pdd':
                    if ($this->isPddOfficial()) {
                        $rows = $this->pddOfficial->fetchSearch($keyword, $page, $limit);
                        $rows = $this->filterPddOfficialListByPriceKeyword($rows, $keyword);
                        return $this->normalizePdd($rows);
                    }
                    $raw = $this->jutuike->pddGoodsSearchFull($keyword, $page, $limit);
                    return $this->normalizePddSearch($raw);
                case 'kuaishou':
                    if ($this->isKuaishouOfficial()) {
                        $channelId = (int) $cate;
                        return $this->normalizeKuaishou(
                            $this->kuaishouOfficial->fetchSearch($keyword, $page, $limit, $channelId, $rangeList)
                        );
                    }
                    return [];
                case 'douyin':
                    return $this->fetchDouyinList($keyword, $page, $limit);
                case 'wph':
                    return $this->normalizeWph($this->dingdanxia->wphGoods($keyword ?: '热销', $page, $limit));
                default:
                    return [];
            }
        } catch (\Throwable $e) {
            Log::error('服务页品牌检索失败', [
                'platform' => $platform,
                'keyword' => $keyword,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    protected function normalizeWph($result): array
    {
        if (!is_array($result)) return [];
        $list = [];
        foreach ($result as $val) {
            if (!is_array($val)) continue;
            $list[] = [
                'platform'   => 'wph',
                'goods_id'   => (string)($val['goodsId'] ?? ''),
                'title'      => (string)($val['goodsName'] ?? ''),
                'store_name' => (string)($val['goodsName'] ?? ''),
                'image'      => (string)($val['goodsMainPicture'] ?? ''),
                'price'      => (string)($val['vipPrice'] ?? '0.00'),
                'ot_price'   => (string)($val['marketPrice'] ?? '0.00'),
                'sales'      => isset($val['inOrderCount30Days']) ? (int)$val['inOrderCount30Days'] : 0,
                'sales_text' => '',
            ];
        }
        return $list;
    }

    protected function normalizeTaobao($result): array
    {
        $list = [];
        if (!is_array($result)) {
            return $list;
        }
        $source = $this->getTaobaoDataSource();
        foreach ($result as $val) {
            if (!is_array($val)) {
                continue;
            }
            $itemBasic = $val['item_basic_info'] ?? [];
            $priceInfo = $val['price_promotion_info'] ?? [];
            $goodsId = (string)($val['item_id'] ?? ($val['num_iid'] ?? ''));
            if ($goodsId === '') {
                continue;
            }
            $title = (string)($itemBasic['title'] ?? ($val['title'] ?? ''));
            $image = (string)($itemBasic['pict_url'] ?? ($val['pict_url'] ?? ''));
            $sales = isset($itemBasic['tk_total_sales'])
                ? (int)$itemBasic['tk_total_sales']
                : (int)($itemBasic['volume'] ?? ($val['volume'] ?? 0));
            $salesText = trim((string)($itemBasic['annual_vol'] ?? ($val['annual_vol'] ?? '')));
            $price = (string)($priceInfo['final_promotion_price'] ?? ($val['zk_final_price'] ?? '0.00'));
            $otPrice = (string)($priceInfo['reserve_price'] ?? ($val['reserve_price'] ?? '0.00'));
            $publish = is_array($val['publish_info'] ?? null) ? $val['publish_info'] : [];
            $clickUrl = $this->normalizeTbkAffiliateUrl((string) ($publish['click_url'] ?? ''));
            $couponUrl = $this->normalizeTbkAffiliateUrl((string) ($publish['coupon_share_url'] ?? ''));
            $row = [
                'platform' => 'taobao',
                '_source' => $source,
                'goods_id' => $goodsId,
                'title' => $title,
                'image' => $image,
                'sales' => $sales,
                'sales_text' => $salesText,
                'annual_vol' => $salesText,
                'price' => $price,
                'ot_price' => $otPrice,
            ];
            if ($clickUrl !== '') {
                $row['taoke_item_url'] = $couponUrl !== '' ? $couponUrl : $clickUrl;
            }
            if ($couponUrl !== '') {
                $row['taoke_coupon_click_url'] = $couponUrl;
            }
            $list[] = $row;
        }
        return $list;
    }

    /** 京东联盟 priceInfo 为元（整数或小数），统一成列表用的两位小数字符串 */
    protected function formatJdYuanPrice($value): string
    {
        if ($value === '' || $value === null) {
            return '0.00';
        }
        if (!is_numeric($value)) {
            return '0.00';
        }
        return number_format((float) $value, 2, '.', '');
    }

    protected function normalizeJd($result): array
    {
        $list = [];
        if (!is_array($result)) {
            return $list;
        }
        // jd/query 可能包一层 list
        if (isset($result['list']) && is_array($result['list'])) {
            $result = $result['list'];
        }
        foreach ($result as $val) {
            if (!is_array($val)) {
                continue;
            }
            $priceInfo = $val['priceInfo'] ?? [];
            $goodsId = (string)($val['itemId'] ?? ($val['skuId'] ?? ''));
            if ($goodsId === '') {
                continue;
            }
            $image = '';
            $slider = [];
            if (!empty($val['imageInfo']['imageList']) && is_array($val['imageInfo']['imageList'])) {
                foreach ($val['imageInfo']['imageList'] as $img) {
                    $url = is_array($img) ? (string) ($img['url'] ?? '') : (string) $img;
                    if ($url !== '') {
                        $slider[] = $url;
                    }
                }
            }
            if (!empty($slider)) {
                $image = $slider[0];
            } elseif (!empty($val['imageUrl'])) {
                $image = $val['imageUrl'];
            }
            $shopInfo = $val['shopInfo'] ?? [];
            $promotionInfo = $val['promotionInfo'] ?? [];
            $clickURL = (string)($promotionInfo['clickURL'] ?? ($promotionInfo['clickUrl'] ?? ''));
            $couponPrice = $priceInfo['lowestCouponPrice'] ?? ($priceInfo['price'] ?? ($val['price'] ?? 0));
            $originPrice = $priceInfo['price'] ?? ($priceInfo['lowestPrice'] ?? $couponPrice);
            $list[] = [
                'platform' => 'jd',
                '_source' => $this->getJdDataSource(),
                'goods_id' => $goodsId,
                'title' => $val['skuName'] ?? ($val['goodsName'] ?? ''),
                'store_name' => $val['skuName'] ?? ($val['goodsName'] ?? ''),
                'image' => $image,
                'sales' => (int) ($val['inOrderCount30Days'] ?? ($val['inOrderCount30DaysSku'] ?? ($val['comments'] ?? 0))),
                'price' => $this->formatJdYuanPrice($couponPrice),
                'ot_price' => $this->formatJdYuanPrice($originPrice),
                'is_hot' => $val['isHot'] ?? 0,
                'materialUrl' => $val['materialUrl'] ?? '',
                'clickURL' => $clickURL,
                'clickUrl' => $clickURL,
                'shopName' => $shopInfo['shopName'] ?? '',
                'shopId' => $shopInfo['shopId'] ?? '',
                'shopLevel' => $shopInfo['shopLevel'] ?? '',
                'spuid' => $val['spuid'] ?? '',
                'slider_image' => $slider,
            ];
        }
        return $list;
    }

    /**
     * 价格筛选 pill（9.9/19.9 等）：搜索词粗召回后再按券后价（分）收窄
     */
    protected function filterPddOfficialListByPriceKeyword(array $list, string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '' || empty($list)) {
            return $list;
        }
        $tiers = [
            '9.9' => [9.0, 10.49],
            '19.9' => [18.5, 20.49],
            '29.9' => [28.5, 30.49],
            '39.9' => [38.5, 40.49],
        ];
        $range = null;
        foreach ($tiers as $label => $bounds) {
            if (strpos($keyword, $label) !== false) {
                $range = $bounds;
                break;
            }
        }
        if ($range === null) {
            return $list;
        }
        [$minYuan, $maxYuan] = $range;
        $filtered = array_values(array_filter($list, function ($item) use ($minYuan, $maxYuan) {
            if (!is_array($item)) {
                return false;
            }
            $cent = (int) ($item['min_group_price'] ?? 0);
            if ($cent <= 0) {
                $cent = (int) ($item['min_normal_price'] ?? 0);
            }
            if ($cent <= 0 && isset($item['price']) && is_numeric($item['price'])) {
                $cent = (int) round(((float) $item['price']) * 100);
            }
            if ($cent <= 0) {
                return false;
            }
            $yuan = $cent / 100;
            return $yuan >= $minYuan && $yuan <= $maxYuan;
        }));
        return !empty($filtered) ? $filtered : $list;
    }

    protected function normalizePdd($result): array
    {
        $list = [];
        if (!is_array($result)) {
            return $list;
        }
        foreach ($result as $val) {
            if (!is_array($val)) {
                continue;
            }
            $minGroup = (int) ($val['min_group_price'] ?? 0);
            $minNormal = (int) ($val['min_normal_price'] ?? 0);
            $priceCent = $minGroup > 0 ? $minGroup : $minNormal;
            $list[] = [
                'platform' => 'pdd',
                '_source' => $this->getPddDataSource(),
                'goods_id' => (string)($val['goods_id'] ?? '0'),
                'store_name' => $val['goods_name'] ?? '',
                'title' => $val['goods_name'] ?? '',
                'goods_name' => $val['goods_name'] ?? '',
                'image' => $val['goods_image_url'] ?? ($val['goods_thumbnail_url'] ?? ''),
                'goods_image_url' => $val['goods_image_url'] ?? ($val['goods_thumbnail_url'] ?? ''),
                'goods_thumbnail_url' => $val['goods_thumbnail_url'] ?? ($val['goods_image_url'] ?? ''),
                'sales_text' => (string) ($val['sales_tip'] ?? ($val['sales_text'] ?? '')),
                'sales' => isset($val['sales_tip']) ? (int) preg_replace('/[^\d]/', '', (string) $val['sales_tip']) : (int)($val['sales'] ?? 0),
                'price' => $priceCent > 0 ? number_format($priceCent / 100, 2, '.', '') : '0.00',
                'ot_price' => $minNormal > 0 ? number_format($minNormal / 100, 2, '.', '') : '0.00',
                'goods_sign' => $val['goods_sign'] ?? '',
            ];
        }
        return $list;
    }

    protected function normalizeKuaishou($result): array
    {
        if (!is_array($result)) {
            return [];
        }
        $source = $this->getKuaishouDataSource();
        $list = [];
        foreach ($result as $val) {
            if (!is_array($val)) {
                continue;
            }
            $itemId = $this->kuaishouListItemId($val);
            if ($itemId === '') {
                continue;
            }
            $canonicalGoodsId = trim((string) ($val['goodsId'] ?? ''));
            $relItemId = trim((string) ($val['relItemId'] ?? ''));
            $title = (string) ($val['itemTitle'] ?? ($val['title'] ?? ($val['itemName'] ?? '')));
            $image = (string) ($val['itemImgUrl'] ?? ($val['itemCdnImgUrl'] ?? ($val['coverUrl'] ?? ($val['cover_url'] ?? ($val['image'] ?? '')))));
            $priceRaw = $val['zkFinalPrice'] ?? ($val['zkGoodsPrice'] ?? ($val['zkPrice'] ?? ($val['price'] ?? ($val['itemPrice'] ?? 0))));
            $price = $priceRaw;
            if (is_numeric($priceRaw) && (float) $priceRaw >= 100) {
                $price = number_format(((float) $priceRaw) / 100, 2, '.', '');
            }
            $list[] = [
                'platform' => 'kuaishou',
                '_source' => $source,
                'goods_id' => $itemId,
                'goodsId' => $canonicalGoodsId !== '' ? $canonicalGoodsId : $itemId,
                'title' => $title,
                'store_name' => $title,
                'itemTitle' => $title,
                'image' => $image,
                'sales' => (int) ($val['soldCount'] ?? ($val['sales'] ?? 0)),
                'sales_text' => (string) ($val['soldCountDesc'] ?? ($val['sales_text'] ?? '')),
                'price' => $price ?: '0.00',
                'ot_price' => '0.00',
                'express_id' => (int) ($val['expressId'] ?? 0),
                'express_type' => (int) ($val['expressType'] ?? 0),
                'rel_item_id' => $relItemId,
                'relItemId' => $relItemId,
                'distribute_item_id' => (string) ($val['distributeItemId'] ?? ''),
            ];
        }
        return $list;
    }

    protected function normalizePddSearch($result): array
    {
        if (!is_array($result)) {
            return [];
        }
        $rows = $result['list'] ?? $result['goods_list'] ?? $result;
        if (!is_array($rows)) {
            return [];
        }
        // 聚推客字段可能已是元
        $list = [];
        foreach ($rows as $val) {
            if (!is_array($val)) {
                continue;
            }
            $price = $val['min_group_price'] ?? ($val['price'] ?? 0);
            if (is_numeric($price) && (float)$price > 1000) {
                $price = ((float)$price) / 100;
            }
            $list[] = [
                'platform' => 'pdd',
                'goods_id' => (string)($val['goods_id'] ?? '0'),
                'title' => $val['goods_name'] ?? ($val['title'] ?? ''),
                'image' => $val['goods_thumbnail_url'] ?? ($val['goods_image_url'] ?? ($val['image'] ?? '')),
                'sales' => (int)($val['sales_tip'] ?? ($val['sales'] ?? 0)),
                'price' => $price ?: '0.00',
                'ot_price' => '0.00',
                'goods_sign' => $val['goods_sign'] ?? '',
            ];
        }
        return $list;
    }

    protected function fetchDouyinList(string $keyword, int $page, int $limit): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            $keyword = '热销';
        }
        $list = $this->normalizeDouyin($this->dingdanxia->douyinGoodsSearch($keyword, $page, $limit));
        if (!empty($list)) {
            return $list;
        }
        return $this->normalizeDouyin($this->jutuike->douyinProductSearch($keyword, $page, $limit));
    }

    protected function normalizeDouyin($result): array
    {
        $list = [];
        if (!is_array($result)) {
            return $list;
        }
        if (isset($result['products']) && is_array($result['products'])) {
            $result = $result['products'];
        } elseif (isset($result['list']) && is_array($result['list'])) {
            $result = $result['list'];
        }
        foreach ($result as $val) {
            if (!is_array($val)) {
                continue;
            }
            $goodsId = (string)($val['product_id'] ?? ($val['productId'] ?? ($val['goods_id'] ?? '')));
            if ($goodsId === '') {
                continue;
            }
            $price = $val['price'] ?? 0;
            // 抖音价格多为分
            if (is_numeric($price) && (float)$price >= 100) {
                $price = number_format(((float)$price) / 100, 2, '.', '');
            }
            $list[] = [
                'platform' => 'douyin',
                'goods_id' => $goodsId,
                'title' => $val['title'] ?? ($val['product_name'] ?? ($val['goods_name'] ?? '')),
                'store_name' => $val['title'] ?? ($val['product_name'] ?? ($val['goods_name'] ?? '')),
                'image' => $val['cover'] ?? ($val['cover_url'] ?? ($val['image'] ?? ($val['img'] ?? ''))),
                'sales' => (int)($val['sales'] ?? ($val['sell_num'] ?? 0)),
                'sales_text' => (string)($val['sell_num_text'] ?? ($val['sales_text'] ?? '')),
                'price' => $price ?: '0.00',
                'ot_price' => '0.00',
                'shop_name' => $val['shop_name'] ?? '',
                'detail_url' => $val['detail_url'] ?? ($val['product_url'] ?? ''),
            ];
        }
        return $list;
    }

    protected function uniqueList(array $list): array
    {
        $seen = [];
        $out = [];
        foreach ($list as $item) {
            $id = $item['goods_sign']
                ?? ($item['goods_id']
                ?? ($item['item_id']
                ?? ($item['itemId']
                ?? ($item['num_iid']
                ?? ($item['skuId'] ?? '')))));
            $key = ($item['platform'] ?? '') . ':' . $id;
            if ($key === ':' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $item;
        }
        return $out;
    }
}
