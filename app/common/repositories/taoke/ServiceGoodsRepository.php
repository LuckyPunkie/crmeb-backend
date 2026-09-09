<?php

namespace app\common\repositories\taoke;

use crmeb\services\taoke\DingDanXiaService;
use crmeb\services\taoke\JdOfficialService;
use crmeb\services\taoke\JuTuiKeService;
use think\facade\Log;

/**
 * 服务页联盟商品聚合 / 品牌检索
 */
class ServiceGoodsRepository
{
    protected DingDanXiaService $dingdanxia;
    protected JuTuiKeService $jutuike;
    protected JdOfficialService $jdOfficial;

    public function __construct(
        DingDanXiaService $dingdanxia,
        JuTuiKeService $jutuike,
        JdOfficialService $jdOfficial
    ) {
        $this->dingdanxia = $dingdanxia;
        $this->jutuike = $jutuike;
        $this->jdOfficial = $jdOfficial;
    }

    protected function isJdOfficial(): bool
    {
        return config('taoke.driver.jd') === 'official';
    }

    /**
     * 推荐：多平台汇总
     */
    public function aggregateRecommend(int $page = 1, int $limit = 20, string $platform = ''): array
    {
        $platform = strtolower(trim($platform));
        $per = max(4, (int)ceil($limit / 2));
        $list = [];

        $platforms = $platform !== '' ? [$platform] : ['taobao', 'jd', 'pdd', 'douyin'];
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
        foreach (['taobao', 'jd', 'pdd', 'douyin'] as $platform) {
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

    public function searchPlatform(string $platform, string $keyword, int $page = 1, int $limit = 20, $cate = 0): array
    {
        $platform = strtolower(trim($platform));
        if ($keyword !== '') {
            return $this->safePlatformSearch($platform, $keyword, $page, $limit);
        }
        return $this->safePlatformFeed($platform, $page, $limit, $cate);
    }

    protected function safePlatformFeed(string $platform, int $page, int $limit, $cate = 0): array
    {
        try {
            switch ($platform) {
                case 'taobao':
                    return $this->normalizeTaobao($this->dingdanxia->taobaoGoods($page, $limit));
                case 'jd':
                    if ($this->isJdOfficial()) {
                        return $this->normalizeJd($this->jdOfficial->fetchFeed($page, $limit, (int) $cate));
                    }
                    // [官方直连切换 2026-09-09] 原订单侠/聚推客调用（driver_jd=legacy 时生效）：
                    $list = $this->normalizeJd($this->dingdanxia->jdGoods($page, $limit, (int)$cate));
                    if (!empty($list)) {
                        return $list;
                    }
                    return $this->normalizeJd($this->jutuike->jdSelection($page, $limit));
                case 'pdd':
                    $raw = $this->dingdanxia->pddGoods($page, $limit, $cate);
                    return $this->normalizePdd($raw['list'] ?? (is_array($raw) ? $raw : []));
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

    protected function safePlatformSearch(string $platform, string $keyword, int $page, int $limit): array
    {
        try {
            switch ($platform) {
                case 'taobao':
                    return $this->normalizeTaobao($this->dingdanxia->taobaoGoodsSearch($page, $limit, $keyword));
                case 'jd':
                    if ($this->isJdOfficial()) {
                        return $this->normalizeJd($this->jdOfficial->fetchSearch($keyword, $page, $limit));
                    }
                    // [官方直连切换 2026-09-09] 原订单侠调用（driver_jd=legacy 时生效）：
                    return $this->normalizeJd($this->dingdanxia->jdGoodsSearch($keyword, $page, $limit));
                case 'pdd':
                    $raw = $this->jutuike->pddGoodsSearchFull($keyword, $page, $limit);
                    return $this->normalizePddSearch($raw);
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
        foreach ($result as $val) {
            if (!is_array($val)) {
                continue;
            }
            $itemBasic = $val['item_basic_info'] ?? [];
            $priceInfo = $val['price_promotion_info'] ?? [];
            $goodsId = (string)($val['item_id'] ?? '');
            if ($goodsId === '') {
                continue;
            }
            $list[] = [
                'platform' => 'taobao',
                'goods_id' => $goodsId,
                'title' => $itemBasic['title'] ?? '',
                'image' => $itemBasic['pict_url'] ?? '',
                'sales' => isset($itemBasic['tk_total_sales']) ? (int)$itemBasic['tk_total_sales'] : (int)($itemBasic['volume'] ?? 0),
                'sales_text' => trim((string)($itemBasic['annual_vol'] ?? '')),
                'annual_vol' => trim((string)($itemBasic['annual_vol'] ?? '')),
                'price' => $priceInfo['final_promotion_price'] ?? '0.00',
                'ot_price' => $priceInfo['reserve_price'] ?? '0.00',
            ];
        }
        return $list;
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
            if (!empty($val['imageInfo']['imageList'][0]['url'])) {
                $image = $val['imageInfo']['imageList'][0]['url'];
            } elseif (!empty($val['imageUrl'])) {
                $image = $val['imageUrl'];
            }
            $shopInfo = $val['shopInfo'] ?? [];
            $promotionInfo = $val['promotionInfo'] ?? [];
            $clickURL = (string)($promotionInfo['clickURL'] ?? ($promotionInfo['clickUrl'] ?? ''));
            $list[] = [
                'platform' => 'jd',
                'goods_id' => $goodsId,
                'title' => $val['skuName'] ?? ($val['goodsName'] ?? ''),
                'store_name' => $val['skuName'] ?? ($val['goodsName'] ?? ''),
                'image' => $image,
                'sales' => (int) ($val['inOrderCount30Days'] ?? ($val['inOrderCount30DaysSku'] ?? ($val['comments'] ?? 0))),
                'price' => $priceInfo['lowestCouponPrice'] ?? ($priceInfo['price'] ?? ($val['price'] ?? '0.00')),
                'ot_price' => $priceInfo['price'] ?? '0.00',
                'is_hot' => $val['isHot'] ?? 0,
                'materialUrl' => $val['materialUrl'] ?? '',
                'clickURL' => $clickURL,
                'clickUrl' => $clickURL,
                'shopName' => $shopInfo['shopName'] ?? '',
                'shopId' => $shopInfo['shopId'] ?? '',
                'shopLevel' => $shopInfo['shopLevel'] ?? '',
            ];
        }
        return $list;
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
            $list[] = [
                'platform' => 'pdd',
                'goods_id' => (string)($val['goods_id'] ?? '0'),
                'title' => $val['goods_name'] ?? '',
                'image' => $val['goods_image_url'] ?? ($val['goods_thumbnail_url'] ?? ''),
                'sales' => isset($val['sales_tip']) ? (int)$val['sales_tip'] : (int)($val['sales'] ?? 0),
                'price' => isset($val['min_normal_price']) ? ($val['min_normal_price'] / 100) : ($val['min_group_price'] ?? 0) / 100,
                'ot_price' => '0.00',
                'goods_sign' => $val['goods_sign'] ?? '',
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
            $key = ($item['platform'] ?? '') . ':' . ($item['goods_sign'] ?? ($item['goods_id'] ?? ''));
            if ($key === ':' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $item;
        }
        return $out;
    }
}
