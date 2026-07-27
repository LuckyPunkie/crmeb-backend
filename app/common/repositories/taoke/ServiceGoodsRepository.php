<?php

namespace app\common\repositories\taoke;

use crmeb\services\taoke\DingDanXiaService;
use crmeb\services\taoke\JuTuiKeService;
use think\facade\Log;

/**
 * 服务页联盟商品聚合 / 品牌检索
 */
class ServiceGoodsRepository
{
    protected DingDanXiaService $dingdanxia;
    protected JuTuiKeService $jutuike;

    public function __construct(DingDanXiaService $dingdanxia, JuTuiKeService $jutuike)
    {
        $this->dingdanxia = $dingdanxia;
        $this->jutuike = $jutuike;
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
                    return $this->normalizeJd($this->dingdanxia->jdGoods($page, $limit, (int)$cate));
                case 'pdd':
                    $raw = $this->dingdanxia->pddGoods($page, $limit, $cate ?: 4);
                    return $this->normalizePdd($raw['list'] ?? (is_array($raw) ? $raw : []));
                case 'douyin':
                    return $this->normalizeDouyin($this->dingdanxia->douyinGoodsSearch('', $page, $limit));
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
                    return $this->normalizeJd($this->dingdanxia->jdGoodsSearch($keyword, $page, $limit));
                case 'pdd':
                    $raw = $this->jutuike->pddGoodsSearchFull($keyword, $page, $limit);
                    return $this->normalizePddSearch($raw);
                case 'douyin':
                    return $this->normalizeDouyin($this->dingdanxia->douyinGoodsSearch($keyword, $page, $limit));
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
                'sales' => isset($itemBasic['tk_total_sales']) ? (int)$itemBasic['tk_total_sales'] : 0,
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
                'sales' => isset($val['inOrderCount30Days']) ? (int)$val['inOrderCount30Days'] : 0,
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
            $goodsId = (string)($val['product_id'] ?? ($val['productId'] ?? ''));
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
                'title' => $val['title'] ?? '',
                'image' => $val['cover'] ?? ($val['image'] ?? ''),
                'sales' => (int)($val['sales'] ?? 0),
                'price' => $price ?: '0.00',
                'ot_price' => '0.00',
                'shop_name' => $val['shop_name'] ?? '',
                'detail_url' => $val['detail_url'] ?? '',
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
