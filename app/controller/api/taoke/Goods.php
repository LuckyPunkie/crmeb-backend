<?php

namespace app\controller\api\taoke;

use app\common\repositories\taoke\CommissionRepository;
use app\common\repositories\taoke\ServiceBrandTabRepository;
use app\common\repositories\taoke\ServiceGoodsRepository;
use app\common\repositories\taoke\ServiceTabConfigRepository;
use crmeb\basic\BaseController;
use crmeb\services\taoke\DingDanXiaService;
use crmeb\services\taoke\JuTuiKeService;
use crmeb\services\taoke\PddOfficialService;
use think\App;
use think\facade\Cache;
use think\facade\Log;

/**
 * 淘宝客商品API接口
 */
class Goods extends BaseController
{
    /** legacy：/api/taoke/goods/* 固定订单侠通道（与 .env driver 无关） */
    protected string $goodsDriverChannel = 'legacy';

    /**
     * @var JuTuiKeService
     */
    protected $jutuikeService;

    /**
     * @var DingDanXiaService
     */
    protected $dingdanxiaService;

    /**
     * @var CommissionRepository
     */
    protected $commissionRepository;

    /**
     * @var ServiceGoodsRepository
     */
    protected $serviceGoodsRepository;

    /**
     * @var ServiceBrandTabRepository
     */
    protected $serviceBrandTabRepository;

    /**
     * @var ServiceTabConfigRepository
     */
    protected $serviceTabConfigRepository;

    protected PddOfficialService $pddOfficial;

    public function __construct(
        App $app,
        JuTuiKeService $jutuikeService,
        DingDanXiaService $dingdanxiaService,
        PddOfficialService $pddOfficial,
        CommissionRepository $commissionRepository,
        ServiceGoodsRepository $serviceGoodsRepository,
        ServiceBrandTabRepository $serviceBrandTabRepository,
        ServiceTabConfigRepository $serviceTabConfigRepository
    ) {
        parent::__construct($app);
        $this->jutuikeService = $jutuikeService;
        $this->dingdanxiaService = $dingdanxiaService;
        $this->pddOfficial = $pddOfficial;
        $this->commissionRepository = $commissionRepository;
        $this->serviceGoodsRepository = $serviceGoodsRepository->withDriverChannel($this->goodsDriverChannel);
        $this->serviceBrandTabRepository = $serviceBrandTabRepository;
        $this->serviceTabConfigRepository = $serviceTabConfigRepository;
    }

    /** 推荐/品牌混排：固定各平台官方 TOP，不走 legacy 订单侠 */
    /*
    标签分类
    */
    
    public function category() {
        $type =  $this->request->post('type', 'taobao'); 
        $data = [];
        if ($type == 'taobao') {
            $data = $this->getTaobaoCategoryTags();
        } elseif ($type == 'wph') {
            $data = $this->getWphCategoryTags();
        } elseif ($type == 'pdd') {
            $data = $this->getPddCategoryTags();
        } elseif ($type == 'kuaishou') {
            $data = $this->getKuaishouCategoryTags();
        } elseif ($type == 'jd') {
            $data = $this->getJdCategoryTags();
        } elseif ($type == 'douyin') {
            $data = $this->getDouyinCategoryTags();
        } elseif ($type == 'recommend') {
            // 推荐 Tab 仅跨平台价格筛选，不带各平台类目
            $data = $this->getTaobaoPriceTags();
        } elseif ($type == 'brand') {
            $config = $this->serviceBrandTabRepository->getPublicConfig();
            foreach ($config['brands'] as $idx => $brand) {
                $data[] = [
                    'id' => $idx + 1,
                    'text' => $brand,
                    'keyword' => $brand,
                ];
            }
        }

        return app('json')->success($data);
        
    }

    /**
     * 现网/订单侠 Tab（channel=legacy）
     * GET /api/taoke/goods/service_tabs
     */
    public function serviceTabs()
    {
        return $this->buildServiceTabsPayload(ServiceTabConfigRepository::CHANNEL_LEGACY);
    }

    protected function buildServiceTabsPayload(string $channel, bool $probeOfficialGoods = false)
    {
        $tabs = $this->serviceTabConfigRepository->listEnabled($channel);
        if ($probeOfficialGoods && $channel === ServiceTabConfigRepository::CHANNEL_OFFICIAL) {
            $tabs = array_values(array_filter($tabs, function (array $tab): bool {
                return $this->probeOfficialTabHasGoods($tab);
            }));
        }
        $legacyBrand = ['enabled' => false, 'name' => '', 'brands' => []];
        foreach ($tabs as $t) {
            if ((int) $t['tab_type'] === 2 && !empty($t['brands'])) {
                $legacyBrand = [
                    'enabled' => true,
                    'name'    => $t['name'],
                    'brands'  => $t['brands'],
                    'tab_key' => $t['tab_key'],
                ];
                break;
            }
        }
        return app('json')->success([
            'tabs'      => $tabs,
            'brand_tab' => $legacyBrand,
            'channel'   => $channel,
            'tabs_probed' => $probeOfficialGoods && $channel === ServiceTabConfigRepository::CHANNEL_OFFICIAL,
        ]);
    }

    /**
     * 平台直连：Tab 已开启但当前无货 / 探活失败 → 不下发（App 隐藏 Tab）
     */
    protected function probeOfficialTabHasGoods(array $tab): bool
    {
        $tabKey = (string) ($tab['tab_key'] ?? '');
        $cacheKey = 'taoke_official_tab_probe:' . md5($tabKey . ':' . (int) ($tab['id'] ?? 0));
        $cached = Cache::get($cacheKey);
        if ($cached !== null && $cached !== '') {
            return (int) $cached === 1;
        }
        try {
            $has = $this->serviceGoodsRepository->probeOfficialTabHasGoods($tab);
        } catch (\Throwable $e) {
            Log::warning('服务页 Tab 探活失败', ['tab_key' => $tabKey, 'error' => $e->getMessage()]);
            $has = false;
        }
        Cache::set($cacheKey, $has ? 1 : 0, $has ? 600 : 180);
        return $has;
    }

    /**
     * 推荐：全平台商品汇总（legacy 通道，订单侠/聚推客）
     * POST /api/taoke/goods/aggregate_recommend
     * 官方直连：POST /api/taoke/official/goods/aggregate_recommend → OfficialGoods
     */
    public function aggregateRecommend()
    {
        $page = (int)$this->request->param('page', $this->request->param('page_no', 1));
        $limit = (int)$this->request->param('limit', $this->request->param('page_size', 20));
        $platform = (string)$this->request->param('platform', '');
        $keyword = (string)$this->request->param('keyword', '');
        // 兼容旧调用：platform 传了非平台名时当作关键词（如价格筛选）
        $knownPlatforms = ['taobao', 'jd', 'pdd', 'kuaishou', 'wph', 'douyin'];
        if ($keyword === '' && $platform !== '' && !in_array(strtolower($platform), $knownPlatforms, true)) {
            $keyword = $platform;
            $platform = '';
        }
        try {
            if ($keyword !== '') {
                if ($platform !== '' && in_array(strtolower($platform), $knownPlatforms, true)) {
                    $list = $this->serviceGoodsRepository->searchPlatform($platform, $keyword, $page, $limit);
                } else {
                    $list = $this->serviceGoodsRepository->searchByBrand($keyword, $page, $limit);
                }
            } else {
                $list = $this->serviceGoodsRepository->aggregateRecommend($page, $limit, $platform);
            }
            $payload = ['list' => $list];
            if ($this->goodsDriverChannel === 'official') {
                $payload['_source'] = 'official';
                $payload['channel'] = 'official';
            }
            return app('json')->success($payload);
        } catch (\Exception $e) {
            Log::error('服务页推荐汇总失败', ['error' => $e->getMessage()]);
            return app('json')->fail('获取推荐商品失败');
        }
    }

    /**
     * 品牌类：按品牌名检索全平台商品（legacy）
     * POST /api/taoke/goods/brand_goods
     * 官方直连：POST /api/taoke/official/goods/brand_goods → OfficialGoods
     */
    public function brandGoods()
    {
        $page = (int)$this->request->param('page', $this->request->param('page_no', 1));
        $limit = (int)$this->request->param('limit', $this->request->param('page_size', 20));
        $keyword = (string)$this->request->param('keyword', '');
        $tabKey = (string)$this->request->param('tab_key', '');

        // 模式 1：keyword 直接指定 → 走单关键词聚合
        if ($keyword !== '') {
            try {
                $list = $this->serviceGoodsRepository->searchByBrand($keyword, $page, $limit);
                $payload = ['list' => $list];
                if ($this->goodsDriverChannel === 'official') {
                    $payload['_source'] = 'official';
                    $payload['channel'] = 'official';
                }
                return app('json')->success($payload);
            } catch (\Exception $e) {
                Log::error('服务页品牌商品失败', ['keyword' => $keyword, 'error' => $e->getMessage()]);
                return app('json')->fail('获取品牌商品失败');
            }
        }

        // 模式 2：tab_key 指定 + keyword 空 → 该 tab 全部品牌聚合搜索
        if ($tabKey !== '') {
            $custom = $this->serviceTabConfigRepository->findCustomByKey($tabKey);
            if ($custom && !empty($custom['brands'])) {
                try {
                    $list = $this->serviceGoodsRepository->searchByBrands($custom['brands'], $page, $limit);
                    $payload = ['list' => $list];
                    if ($this->goodsDriverChannel === 'official') {
                        $payload['_source'] = 'official';
                        $payload['channel'] = 'official';
                    }
                    return app('json')->success($payload);
                } catch (\Exception $e) {
                    Log::error('服务页品牌多关键词聚合失败', ['tab_key' => $tabKey, 'error' => $e->getMessage()]);
                    return app('json')->fail('获取品牌商品失败');
                }
            }
        }

        // 模式 3：兜底 —— 回退到旧 service_brand_tab 单条配置
        $config = $this->serviceBrandTabRepository->getPublicConfig();
        $fallback = $config['brands'][0] ?? '';
        if ($fallback === '') {
            return app('json')->success(['list' => []]);
        }
        try {
            $list = $this->serviceGoodsRepository->searchByBrand($fallback, $page, $limit);
            $payload = ['list' => $list];
            if ($this->goodsDriverChannel === 'official') {
                $payload['_source'] = 'official';
                $payload['channel'] = 'official';
            }
            return app('json')->success($payload);
        } catch (\Exception $e) {
            Log::error('服务页品牌兜底失败', ['error' => $e->getMessage()]);
            return app('json')->fail('获取品牌商品失败');
        }
    }

    /**
     * 抖音商品
     * POST /api/taoke/goods/douyin_goods
     */
    public function douyinGoods()
    {
        $page = (int)$this->request->param('page', $this->request->param('page_no', 1));
        $limit = (int)$this->request->param('limit', $this->request->param('page_size', 20));
        $keyword = (string)$this->request->param('keyword', '');
        $filters = $this->buildDouyinListFiltersFromRequest();
        try {
            $list = $this->serviceGoodsRepository->fetchDouyinListWithFilters($keyword, $page, $limit, $filters);
            return app('json')->success([
                'list' => $list,
                '_source' => $this->serviceGoodsRepository->getDouyinDataSource(),
            ]);
        } catch (\Exception $e) {
            Log::error('抖音商品列表获取失败', ['error' => $e->getMessage()]);
            return app('json')->success(['list' => [], '_source' => $this->serviceGoodsRepository->getDouyinDataSource()]);
        }
    }

    /**
     * 抖音商品详情（好单库 dy_item_info）
     * POST /api/taoke/goods/douyin_goods_detail
     */
    public function douyinGoodsDetail()
    {
        $goodsId = (string) $this->request->param('goods_id', $this->request->param('itemid', ''));
        if ($goodsId === '') {
            return app('json')->fail('商品ID不能为空');
        }
        try {
            $detail = $this->serviceGoodsRepository->fetchDouyinDetail($goodsId);
            if ($detail === []) {
                return app('json')->fail('商品详情获取失败');
            }
            return app('json')->success([
                'detail' => $detail,
                '_source' => $this->serviceGoodsRepository->getDouyinDataSource(),
            ]);
        } catch (\Exception $e) {
            Log::error('抖音商品详情获取失败', ['goods_id' => $goodsId, 'error' => $e->getMessage()]);
            return app('json')->fail('商品详情获取失败');
        }
    }

    /**
     * 抖音直播列表（好单库 dy_live_list）
     * POST /api/taoke/goods/douyin_live
     */
    public function douyinLive()
    {
        $page = (int) $this->request->param('page', $this->request->param('page_no', 1));
        $limit = (int) $this->request->param('limit', $this->request->param('page_size', 20));
        $minId = $this->request->param('min_id', null);
        $filters = [
            'min_id' => $minId !== null && $minId !== '' ? $minId : $page,
            'keyword' => (string) $this->request->param('keyword', ''),
        ];
        try {
            $bundle = $this->serviceGoodsRepository->fetchDouyinLiveList($page, $limit, $filters);
            if ($bundle['code'] !== 0 && $bundle['code'] !== -1) {
                return app('json')->fail(
                    $bundle['message'] !== '' ? $bundle['message'] : '抖音直播列表获取失败'
                );
            }
            if ($bundle['code'] === -1 && $bundle['message'] === 'haodanku_not_configured') {
                return app('json')->success([
                    'list' => [],
                    'total' => 0,
                    'min_id' => null,
                    '_source' => 'haodanku',
                    'hdk_message' => 'haodanku_not_configured',
                ]);
            }
            return app('json')->success([
                'list' => $bundle['list'],
                'total' => $bundle['total'],
                'min_id' => $bundle['min_id'] ?? null,
                '_source' => 'haodanku',
                'hdk_message' => $bundle['message'],
            ]);
        } catch (\Exception $e) {
            Log::error('抖音直播列表获取失败', ['error' => $e->getMessage()]);
            return app('json')->fail('获取失败，请稍后重试');
        }
    }

    /**
     * 抖音直播转链（好单库 get_dylive_link）
     * POST /api/taoke/goods/create_douyin_live_link
     */
    public function createDouyinLiveLink()
    {
        $uid = (string) ($this->request->uid() ?? '');
        $context = [
            'room_id' => (string) $this->request->post('room_id', ''),
            'author_id' => (string) $this->request->post('author_id', $this->request->post('author_openid', '')),
            'buyin_id' => (string) $this->request->post('buyin_id', $this->request->post('author_buyin_id', '')),
            'author_buyin_id' => (string) $this->request->post('author_buyin_id', ''),
            'product_id' => (string) $this->request->post('product_id', ''),
            'channel' => (string) $this->request->post('channel', $this->request->post('external_info', $uid)),
            'share_type' => $this->request->post('share_type', '1,3'),
            'platform' => $this->request->post('platform', null),
        ];
        try {
            $result = $this->serviceGoodsRepository->createDouyinLivePromotion($context);
            $code = (int) ($result['code'] ?? -1);
            if ($code !== 0) {
                return app('json')->fail((string) ($result['message'] ?? '生成直播推广链接失败'));
            }
            return app('json')->success($result);
        } catch (\Exception $e) {
            Log::error('抖音直播转链失败', ['error' => $e->getMessage()]);
            return app('json')->fail('生成推广链接失败');
        }
    }

    /**
     * 抖音商品转链（好单库 get_dyitem_link）
     * POST /api/taoke/goods/create_douyin_link
     */
    public function createDouyinLink()
    {
        $uid = (string) ($this->request->uid() ?? '');
        $context = [
            'itemid' => (string) $this->request->post('itemid', $this->request->post('goods_id', '')),
            'goods_id' => (string) $this->request->post('goods_id', ''),
            'channel' => (string) $this->request->post('channel', $this->request->post('external_info', $uid)),
            'share_type' => $this->request->post('share_type', '1,3'),
        ];
        try {
            $result = $this->serviceGoodsRepository->createDouyinProductPromotion($context);
            $code = (int) ($result['code'] ?? -1);
            if ($code !== 0) {
                return app('json')->fail((string) ($result['message'] ?? '生成商品推广链接失败'));
            }
            return app('json')->success($result);
        } catch (\Exception $e) {
            Log::error('抖音商品转链失败', ['error' => $e->getMessage()]);
            return app('json')->fail('生成推广链接失败');
        }
    }

    /**
     * 淘宝商品
     * GET /api/taoke/goods/taobao
     */
    public function taobao()
    {
        $page = (int) $this->request->param('page_no', 1);
        $limit = (int) $this->request->param('page_size', 20);
        try {
            $list = $this->serviceGoodsRepository->searchPlatform('taobao', '', $page, $limit, 0);
            return app('json')->success([
                'list' => $list,
                '_source' => $this->serviceGoodsRepository->getTaobaoDataSource(),
            ]);
        } catch (\Exception $e) {
            Log::error('淘宝商品列表获取失败', [
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }
    /**
     * 淘宝商品搜索（GET）
     * GET /api/taoke/goods/taobao_search
     */
    public function taobaoSearch()
    {
        $page = (int) $this->request->param('page', 1);
        $limit = (int) $this->request->param('limit', 10);
        $q = (string) $this->request->param('keyword', '');
        $cat = (int) $this->request->param('cat', 0);
        try {
            $list = $this->serviceGoodsRepository->searchPlatform('taobao', $q, $page, $limit, $cat);
            return app('json')->success([
                'list' => $list,
                '_source' => $this->serviceGoodsRepository->getTaobaoDataSource(),
            ]);
        } catch (\Exception $e) {
            Log::error('淘宝商品搜索失败', [
                'keyword' => $q,
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试: ' . $e->getMessage());
        }
    }

    /**
     * 淘宝商品搜索（POST）
     * POST /api/taoke/goods/taobao_goods
     */
    public function taobaoGoods()
    {
        $page = (int)$this->request->post('page', 1);
        $limit = (int)$this->request->post('limit', 10);
        $q = (string)$this->request->post('keyword', '');
        $cat = (int)$this->request->post('cat', 0);
        try {
            $list = $this->serviceGoodsRepository->searchPlatform('taobao', $q, $page, $limit, $cat);
            return app('json')->success([
                'list' => $list,
                '_source' => $this->serviceGoodsRepository->getTaobaoDataSource(),
            ]);
        } catch (\Exception $e) {
            Log::error('淘宝商品列表获取失败', [
                'keyword' => $q,
                'page' => $page,
                'error' => $e->getMessage()
            ]);
            return app('json')->success([
                'list' => [],
                '_source' => $this->serviceGoodsRepository->getTaobaoDataSource(),
            ]);
        }
    }
    
    /**
     * 淘宝直播商品列表
     * POST /api/taoke/goods/taobao_live
     */
    public function taobaoLive()
    {
        $page = $this->request->post('page_no', 1);
        $limit = $this->request->post('page_size', 20);
        try {
            // [官方直连切换 2026-09-21] 原订单侠调用：
            // $raw = $this->dingdanxiaService->taobaoLiveGoods($page, $limit);
            $raw = [];
            $list = [];
            foreach ((array)$raw as $val) {
                if (!is_array($val)) continue;
                $basic = $val['item_basic_info'] ?? [];
                $price = $val['price_promotion_info'] ?? [];
                $pub   = $val['publish_info'] ?? [];
                $targetType = (string)($price['final_promotion_target_type'] ?? '');
                $list[] = [
                    'goods_id'  => $val['item_id'] ?? '',
                    'title'     => $basic['title'] ?? $basic['short_title'] ?? '',
                    'image'     => 'https:' . ($basic['pict_url'] ?? ''),
                    'price'     => $price['final_promotion_price'] ?? '0.00',
                    'ot_price'  => $price['reserve_price'] ?? '',
                    'sales'     => (int)($basic['volume'] ?? 0),
                    'click_url' => 'https:' . ltrim($pub['click_url'] ?? '', '/'),
                    'platform'  => 'taobao',
                    'is_live'   => $targetType === '10',
                ];
            }
            return app('json')->success(['list' => $list]);
        } catch (\Exception $e) {
            Log::error('淘宝直播商品获取失败', ['error' => $e->getMessage()]);
            return app('json')->fail('获取失败，请稍后重试');
        }
    }

     /**
     * 生成淘宝推广链接
     * POST /api/taoke/goods/create_taobao_link
     */
    public function createTaobaoLink()
    {
        $goodsId = $this->request->post('goods_id', '');
        $title = (string) $this->request->post('title', $this->request->post('store_name', ''));

        if (empty($goodsId)) {
            return app('json')->fail('商品ID不能为空');
        }
        $relate_id = '3357576229';
        $hints = [
            'item_url' => (string) $this->request->post('item_url', ''),
            'taoke_item_url' => (string) $this->request->post('taoke_item_url', ''),
            'coupon_click_url' => (string) $this->request->post('coupon_click_url', ''),
            'taoke_coupon_click_url' => (string) $this->request->post('taoke_coupon_click_url', ''),
        ];
        try {
            $result = $this->serviceGoodsRepository->createTaobaoLink($goodsId, $relate_id, $title, $hints);
            if (empty($result)) {
                return app('json')->fail('生成推广链接失败');
            }
            return app('json')->success($result);
        } catch (\Exception $e) {
            Log::error('生成淘宝推广链接失败', [
                'goods_id' => $goodsId,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败');
        }
    }



    /**
     * 服务页通用价格 pill（推荐 Tab 仅用此项）
     *
     * @return list<array{id: string, text: string, keyword: string}>
     */
    protected function getTaobaoPriceTags(): array
    {
        return [
            ['id' => 'tb_p99', 'text' => '9.9元包邮', 'keyword' => '9.9包邮'],
            ['id' => 'tb_p199', 'text' => '19.9元包邮', 'keyword' => '19.9包邮'],
            ['id' => 'tb_p299', 'text' => '29.9元包邮', 'keyword' => '29.9包邮'],
            ['id' => 'tb_p399', 'text' => '39.9元包邮', 'keyword' => '39.9包邮'],
        ];
    }

    /**
     * 淘宝 Tab：价格 pill + 联盟后台类目（itemcats.get）
     */
    protected function getTaobaoCategoryTags(): array
    {
        $priceTags = $this->getTaobaoPriceTags();
        try {
            $cats = $this->serviceGoodsRepository->getTaobaoCategoryTags();
            return array_merge($priceTags, $cats ?: []);
        } catch (\Throwable $e) {
            Log::error('淘宝类目标签获取失败', ['error' => $e->getMessage()]);
            return $priceTags;
        }
    }

    /**
     * 唯品会 Tab：价格 pill + 官方一级类目（getCategorys）
     */
    protected function getWphCategoryTags(): array
    {
        $priceTags = [
            ['id' => 'wph_p99', 'text' => '9.9元包邮', 'keyword' => '9.9包邮'],
            ['id' => 'wph_p199', 'text' => '19.9元包邮', 'keyword' => '19.9包邮'],
            ['id' => 'wph_p299', 'text' => '29.9元包邮', 'keyword' => '29.9包邮'],
            ['id' => 'wph_p399', 'text' => '39.9元包邮', 'keyword' => '39.9包邮'],
        ];
        try {
            $cats = $this->serviceGoodsRepository->getWphCategoryTags();
            return array_merge($priceTags, $cats ?: []);
        } catch (\Throwable $e) {
            Log::error('唯品会类目标签获取失败', ['error' => $e->getMessage()]);
            return $priceTags;
        }
    }

    /**
     * 拼多多分类标签（订单侠 activity_tags，失败则兜底）
     */
    protected function getKuaishouCategoryTags(): array
    {
        $priceTags = $this->getKuaishouPriceTags();
        $fallback = [
            ['id' => 99, 'text' => '首页'],
            ['id' => 1, 'text' => '女装女鞋'],
            ['id' => 3, 'text' => '美食生鲜'],
        ];
        try {
            $data = $this->serviceGoodsRepository->getKuaishouChannelTags();
            return array_merge($priceTags, $data ?: $fallback);
        } catch (\Throwable $e) {
            Log::error('快手选品频道获取失败', ['error' => $e->getMessage()]);
            return array_merge($priceTags, $fallback);
        }
    }

    /**
     * 快手价格 pill → 官方 rangeList（PRICE 单位为分）
     */
    protected function getKuaishouPriceTags(): array
    {
        return [
            ['id' => 'ks_p99', 'text' => '9.9元包邮', 'range_id' => 'PRICE', 'range_from' => 0, 'range_to' => 990],
            ['id' => 'ks_p199', 'text' => '19.9元包邮', 'range_id' => 'PRICE', 'range_from' => 0, 'range_to' => 1990],
            ['id' => 'ks_p299', 'text' => '29.9元包邮', 'range_id' => 'PRICE', 'range_from' => 0, 'range_to' => 2990],
            ['id' => 'ks_p399', 'text' => '39.9元包邮', 'range_id' => 'PRICE', 'range_from' => 0, 'range_to' => 3990],
        ];
    }

    /**
     * 京东 Tab：价格 pill + 京粉 eliteId（material/jingfen cate）
     */
    protected function getJdCategoryTags(): array
    {
        $priceTags = [
            ['id' => 'jd_p99', 'text' => '9.9元包邮', 'keyword' => '9.9包邮'],
            ['id' => 'jd_p199', 'text' => '19.9元包邮', 'keyword' => '19.9包邮'],
            ['id' => 'jd_p299', 'text' => '29.9元包邮', 'keyword' => '29.9包邮'],
            ['id' => 'jd_p399', 'text' => '39.9元包邮', 'keyword' => '39.9包邮'],
        ];
        // jd union jingfen/material eliteId
        $eliteTags = [
            ['id' => 1, 'text' => '猜你喜欢'],
            ['id' => 2, 'text' => '实时热销'],
            ['id' => 3, 'text' => '大额券'],
            ['id' => 13270, 'text' => '国家补贴'],
        ];
        return array_merge($priceTags, $eliteTags);
    }

    protected function buildKuaishouRangeListFromRequest(): array
    {
        $to = $this->request->post('price_to', null);
        if ($to === null || $to === '') {
            return [];
        }
        $rangeId = (string) $this->request->post('price_range_id', 'PRICE');
        if ($rangeId === '') {
            $rangeId = 'PRICE';
        }
        return [[
            'rangeId' => $rangeId,
            'rangeFrom' => (int) $this->request->post('price_from', 0),
            'rangeTo' => (int) $to,
        ]];
    }

    /**
     * 抖音 Tab：好单库 price_min / price_max（单位：元）
     *
     * @return array{price_min?:float|int,price_max?:float|int,sort?:int}
     */
    protected function buildDouyinListFiltersFromRequest(): array
    {
        $filters = [];
        $min = $this->request->param('price_min', null);
        $max = $this->request->param('price_max', null);
        if ($min !== null && $min !== '') {
            $filters['price_min'] = $min;
        }
        if ($max !== null && $max !== '') {
            $filters['price_max'] = $max;
        }
        $sort = $this->request->param('sort', null);
        if ($sort !== null && $sort !== '') {
            $filters['sort'] = (int) $sort;
        }
        return $filters;
    }

    /**
     * 抖音 Tab：价格 pill + 类目关键词（好单库 keyword）
     */
    protected function getDouyinCategoryTags(): array
    {
        $priceTags = [
            ['id' => 'dy_p99', 'text' => '9.9元包邮', 'range_id' => 'PRICE', 'range_from' => 0, 'range_to' => 9.9],
            ['id' => 'dy_p199', 'text' => '19.9元包邮', 'range_id' => 'PRICE', 'range_from' => 0, 'range_to' => 19.9],
            ['id' => 'dy_p299', 'text' => '29.9元包邮', 'range_id' => 'PRICE', 'range_from' => 0, 'range_to' => 29.9],
            ['id' => 'dy_p399', 'text' => '39.9元包邮', 'range_id' => 'PRICE', 'range_from' => 0, 'range_to' => 39.9],
        ];
        $categoryTags = [
            ['id' => 1, 'text' => '热销爆款', 'keyword' => '热销'],
            ['id' => 2, 'text' => '美妆护肤', 'keyword' => '美妆护肤'],
            ['id' => 3, 'text' => '服饰鞋包', 'keyword' => '服饰鞋包'],
            ['id' => 4, 'text' => '居家日用', 'keyword' => '居家日用'],
            ['id' => 5, 'text' => '食品零食', 'keyword' => '零食'],
        ];
        return array_merge($priceTags, $categoryTags);
    }

    protected function getPddCategoryTags(): array
    {
        // 带 keyword：走 pdd.ddk.goods.search；仅 id：走 activity_tags（多多进宝活动标）
        $priceTags = [
            ['id' => 'p99', 'text' => '9.9元包邮', 'keyword' => '9.9包邮'],
            ['id' => 'p199', 'text' => '19.9元包邮', 'keyword' => '19.9包邮'],
            ['id' => 'p299', 'text' => '29.9元包邮', 'keyword' => '29.9包邮'],
            ['id' => 'p399', 'text' => '39.9元包邮', 'keyword' => '39.9包邮'],
        ];
        $activityTags = [
            ['id' => 4, 'text' => '秒杀'],
            ['id' => 7, 'text' => '百亿补贴'],
            ['id' => 31, 'text' => '品牌黑标'],
            ['id' => 24, 'text' => '品牌高佣'],
            ['id' => 10564, 'text' => '精选爆品'],
        ];
        $fallback = array_merge($priceTags, $activityTags);
        if (config('taoke.driver.pdd') === 'official') {
            return $fallback;
        }
        try {
            // [官方直连切换 2026-09-21] 原订单侠调用：
            // $raw = $this->dingdanxiaService->pdd_tags();
            $raw = [];
            if (!is_array($raw) || !$raw) {
                return $fallback;
            }
            $rows = isset($raw['list']) && is_array($raw['list']) ? $raw['list'] : $raw;
            $data = [];
            foreach ($rows as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $id = $item['id'] ?? ($item['tag_id'] ?? ($item['activity_tag'] ?? ''));
                $text = $item['name'] ?? ($item['text'] ?? ($item['tag_name'] ?? ''));
                if ($id === '' || $text === '') {
                    continue;
                }
                $data[] = [
                    'id' => $id,
                    'text' => $text,
                ];
            }
            // 价格 pill 固定在前，订单侠活动标接在后面
            return array_merge($priceTags, $data ?: $activityTags);
        } catch (\Throwable $e) {
            Log::error('拼多多分类标签获取失败', ['error' => $e->getMessage()]);
            return $fallback;
        }
    }

    /**
     * 淘宝商品详情
     * GET /api/taoke/goods/taobao_detail
     */
    public function taobaoDetail()
    {
        $goodsId = $this->request->get('goods_id', $this->request->get('id', ''));
        return $this->fetchTaobaoGoodsDetail((string)$goodsId);
    }

    /**
     * 淘宝商品详情
     * POST /api/taoke/goods/taobao_goods_detail
     */
    public function taobaoGoodsDetail()
    {
        $goodsId = $this->request->post('goods_id', $this->request->post('id', ''));
        return $this->fetchTaobaoGoodsDetail((string)$goodsId);
    }

    protected function fetchTaobaoGoodsDetail(string $goodsId)
    {
        if ($goodsId === '') {
            return app('json')->fail('商品ID不能为空');
        }
        $useSummaryRaw = $this->request->post('use_summary', 1);
        $useSummary = !in_array($useSummaryRaw, [0, '0', false, 'false', 'off', 'no'], true);

        $title = (string)$this->request->post('title', $this->request->post('store_name', ''));
        $summary = [];
        if ($useSummary) {
            $summary = $this->request->post('summary', []);
            if (is_string($summary)) {
                $decoded = json_decode($summary, true);
                $summary = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($summary)) {
                $summary = [];
            }
            $summary = $this->mergeTaobaoDetailSummaryFromRequest($summary, $goodsId, $title);
        }

        try {
            $bundle = $this->serviceGoodsRepository->fetchTaobaoDetailWithMeta(
                $goodsId,
                $useSummary ? $title : '',
                $summary
            );
            $result = $bundle['detail'];
            if (isset($result[0]) && is_array($result[0])) {
                $result = $result[0];
            }
            if (!$result || !is_array($result)) {
                return app('json')->fail('商品详情为空');
            }
            $meta = $bundle['meta'];
            if (!$useSummary) {
                $meta['summary_used'] = false;
                $meta['summary_skipped'] = true;
                // 后端不拼列表 summary；销量文案与推广链由前端 extractTaobaoListHints 本地 merge
                $meta['pipeline'][] = 'debug: use_summary=false，后端不合并列表 summary；销量/转链由前端 listHints 本地补（item.info 无 annual_vol）';
                $meta['frontend_list_hints'] = 'sales_text/annual_vol + taoke_item_url/coupon_click_url（URL options.item → extractTaobaoListHints）';
            }
            return app('json')->success([
                'detail' => $result,
                '_source' => $this->serviceGoodsRepository->getTaobaoDataSource(),
                '_meta' => $meta,
            ]);
        } catch (\Exception $e) {
            Log::error('获取淘宝商品详情失败', [
                'goods_id' => $goodsId,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('获取商品详情失败');
        }
    }

    /**
     * 详情 POST 未带 summary 时，用列表已知的标题/图/价补全（官方加密 item_id 场景）
     */
    protected function mergeTaobaoDetailSummaryFromRequest(array $summary, string $goodsId, string $title): array
    {
        $pick = function (array $keys) {
            foreach ($keys as $key) {
                $val = trim((string) $this->request->post($key, ''));
                if ($val !== '') {
                    return $val;
                }
            }
            return '';
        };

        $merged = $summary;
        if ($goodsId !== '') {
            $merged['goods_id'] = $merged['goods_id'] ?? $goodsId;
            $merged['item_id'] = $merged['item_id'] ?? $goodsId;
        }
        if ($title !== '') {
            $merged['title'] = $merged['title'] ?? $title;
            $merged['store_name'] = $merged['store_name'] ?? $title;
        }
        foreach ([
            'image' => ['image', 'goods_pic', 'pict_url'],
            'price' => ['price', 'final_promotion_price'],
            'ot_price' => ['ot_price', 'reserve_price'],
            'sales_text' => ['sales_text', 'annual_vol'],
            'taoke_item_url' => ['taoke_item_url', 'item_url'],
            'taoke_coupon_click_url' => ['taoke_coupon_click_url', 'coupon_click_url'],
        ] as $field => $keys) {
            if (!empty($merged[$field])) {
                continue;
            }
            $val = $pick($keys);
            if ($val !== '') {
                $merged[$field] = $val;
            }
        }
        if (empty($merged['sales']) && $this->request->post('sales', '') !== '') {
            $merged['sales'] = (int) $this->request->post('sales', 0);
        }

        return $merged;
    }

     /**
     * 淘宝订单
     * GET /api/taoke/goods/taobao
     */
    public function taobaoOrders()
    {
        $page = $this->request->post('page_no', 1);
        $limit = $this->request->post('page_size', 20);
        $start_time = $this->request->post('start_time', '2026-06-29 15:00:00');
        $end_time = $this->request->post('end_time', '2026-06-29 16:00:00');
        try {
            // [官方直连切换 2026-09-21] 原订单侠调用：
            // $result = $this->dingdanxiaService->taobaoOrderQuery($page, $limit, $start_time, $end_time);
            // return app('json')->success($result);
            return app('json')->fail('订单侠已停用，淘宝订单请改官方 order.details.get');
        } catch (\Exception $e) {
            Log::error('淘宝订单查询失败', [
                'page' => $page,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('订单查询失败，请稍后重试');
        }
    }

    
    /**
     * 联盟活动列表
     * GET /api/taoke/goods/activity_list
     */
    public function activityList()
    {
        $page = $this->request->get('page', 1);
        $limit = $this->request->get('pageSize', 20);
        $cate_name =  $this->request->get('cate_name', '');

        try {
            $result = $this->jutuikeService->getActivityList($cate_name,$page, $limit);
            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('获取活动列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return app('json')->fail('获取活动列表失败: ' . $e->getMessage());
        }
    }

    /**
     * 活动转链
     * POST /api/taoke/goods/activity_union
     */
    public function activityUnion()
    {
        $activityId = $this->request->post('activity_id', '');

        if (empty($activityId)) {
            return app('json')->fail('活动ID不能为空');
        }


        try {

            // 调用活动转链API
            $result = $this->jutuikeService->activityUnion($activityId);

            if (empty($result)) {
                return app('json')->fail('生成活动链接失败');
            }
            
             // 根据we_app_info中的appid查找对应的小程序原始ID
            // if (!empty($result['we_app_info'])) {
            //     $appidMap = [
            //         'wxde8ac0a21135c07d' => 'gh_870576f3c6f9',
            //         'wx6a96c49f29850eb5' => 'gh_e4c5d4d5bc2f',
            //         'wx6ce7b07bf7fe6048' => 'gh_acbfc5484d03',
            //         'wxd98a20e429ce834b' => 'gh_7a5c4141778f',
            //     ];
            //     $result['we_app_info'] = $this->fillOriginalId($result['we_app_info'], $appidMap);
            // }


            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('活动转链失败', [
                'activity_id' => $activityId,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成活动链接失败');
        }
    }
    
     /**
     * 根据appid映射表填充小程序原始ID到we_app_info
     * @param array $weAppInfo
     * @param array $appidMap
     * @return array
     */
    private function fillOriginalId(array $weAppInfo, array $appidMap): array
    {
        // 判断是单个对象还是数组
        if (isset($weAppInfo['appid'])) {
            // 单个we_app_info对象
            $appid = $weAppInfo['appid'];
            if (isset($appidMap[$appid])) {
                $weAppInfo['original_id'] = $appidMap[$appid];
            }
        } else {
            // 数组形式
            foreach ($weAppInfo as &$item) {
                if (isset($item['appid']) && isset($appidMap[$item['appid']])) {
                    $item['original_id'] = $appidMap[$item['appid']];
                }
            }
            unset($item);
        }

        return $weAppInfo;
    }




    /**
     * 拼多多商品
     * GET /api/taoke/goods/taobao
     */
    public function pddGoods()
    {
        $page = (int)$this->request->post('page_no', $this->request->post('page', 1));
        $limit = (int)$this->request->post('page_size', $this->request->post('limit', 20));
        $cate = (int)$this->request->post('cate', 0);
        $keyword = (string)$this->request->post('keyword', '');
        try {
            $list = $this->serviceGoodsRepository->searchPlatform('pdd', $keyword, $page, $limit, $cate);
            return app('json')->success([
                'list' => $list,
                '_source' => $this->serviceGoodsRepository->getPddDataSource(),
            ]);
        } catch (\Exception $e) {
            Log::error('拼多多商品列表获取失败', [
                'page' => $page,
                'cate' => $cate,
                'error' => $e->getMessage()
            ]);
            return app('json')->success(['list' => []]);
        }
    }

    /**
     * 快手商品列表 / 搜索
     * POST /api/taoke/goods/kuaishou_goods
     */
    public function kuaishouGoods()
    {
        $page = (int) $this->request->post('page_no', $this->request->post('page', 1));
        $limit = (int) $this->request->post('page_size', $this->request->post('limit', 20));
        $cate = (int) $this->request->post('cate', 0);
        $keyword = (string) $this->request->post('keyword', '');
        $rangeList = $this->buildKuaishouRangeListFromRequest();
        try {
            $list = $this->serviceGoodsRepository->searchPlatform('kuaishou', $keyword, $page, $limit, $cate, $rangeList);
            return app('json')->success([
                'list' => $list,
                '_source' => $this->serviceGoodsRepository->getKuaishouDataSource(),
            ]);
        } catch (\Exception $e) {
            Log::error('快手商品列表获取失败', ['error' => $e->getMessage()]);
            return app('json')->success(['list' => []]);
        }
    }

    /**
     * 快手商品详情
     * POST /api/taoke/goods/kuaishou_goods_detail
     */
    public function kuaishouGoodsDetail()
    {
        $goodsId = (string) $this->request->post('goods_id', $this->request->post('itemIds', ''));
        $useSummaryRaw = $this->request->post('use_summary', 1);
        $useSummary = !in_array($useSummaryRaw, [0, '0', false, 'false', 'off', 'no'], true);
        $summary = [];
        if ($useSummary) {
            $summary = $this->request->post('summary', []);
            if (is_string($summary)) {
                $decoded = json_decode($summary, true);
                $summary = is_array($decoded) ? $decoded : [];
            }
        }
        try {
            $bundle = $this->serviceGoodsRepository->fetchKuaishouDetailWithMeta(
                $goodsId,
                is_array($summary) ? $summary : []
            );
            $result = $bundle['detail'];
            if ($result === []) {
                return app('json')->fail('商品详情获取失败');
            }
            $meta = $bundle['meta'];
            if (!$useSummary) {
                $meta['summary_used'] = false;
                $meta['summary_skipped'] = true;
                // 当前 uniapp KS_DETAIL_SKIP_SUMMARY=true：不 POST/不本地应用列表 summary
                $meta['pipeline'][] = 'debug: use_summary=false，后端不合并列表 summary；前端当前 SKIP 不应用 options.item（改 false 后 POST summary=选品行并本地 merge）';
                $meta['frontend_list_hints'] = '可选 URL options.item 官方选品行（goods_id/title/image/price/express 等）；当前 SKIP=true 未启用';
            }
            return app('json')->success([
                'detail' => $result,
                '_source' => $this->serviceGoodsRepository->getKuaishouDataSource(),
                '_meta' => $meta,
            ]);
        } catch (\Exception $e) {
            Log::error('快手商品详情获取失败', ['goods_id' => $goodsId, 'error' => $e->getMessage()]);
            return app('json')->fail('商品详情获取失败');
        }
    }

    /**
     * 快手推广链接
     * POST /api/taoke/goods/create_kuaishou_link
     */
    public function createKuaishouLink()
    {
        $goodsId = (string) $this->request->post('goods_id', '');
        if ($goodsId === '') {
            return app('json')->fail('商品不能为空');
        }
        $context = [
            'linkType' => (int) $this->request->post('link_type', 101),
            'linkCarrierId' => (string) $this->request->post('link_carrier_id', ''),
            'comments' => (string) $this->request->post('comments', ''),
            'externalId' => (string) $this->request->post('external_id', ''),
            'customParameters' => (string) $this->request->post('custom_parameters', ''),
            'genPoster' => (bool) $this->request->post('gen_poster', false),
            'customContent' => (string) $this->request->post('custom_content', ''),
        ];
        try {
            $result = $this->serviceGoodsRepository->createKuaishouPromotion($goodsId, $context);
            if (empty($result)) {
                return app('json')->fail('生成推广链接失败');
            }
            if (isset($result['result']) && (int) $result['result'] !== 1) {
                $msg = (string) ($result['sub_msg'] ?? ($result['error_msg'] ?? '生成推广链接失败'));
                $code = (string) ($result['sub_code'] ?? '');
                if ($code === '1800601') {
                    $msg .= '（请检查 cpsPid、linkCarrierId、comments 是否均已传入）';
                }
                return app('json')->fail($msg);
            }
            return app('json')->success($result);
        } catch (\Exception $e) {
            Log::error('快手推广链接生成失败', ['goods_id' => $goodsId, 'error' => $e->getMessage()]);
            return app('json')->fail('生成推广链接失败');
        }
    }
    
    /**
     * 拼多多商品详情
     * GET /api/taoke/goods/taobao
     */
    public function pddGoodsDetail()
    {
        $goods_sign = $this->request->post('goods_sign', '');
        $useSummaryRaw = $this->request->post('use_summary', 1);
        $useSummary = !in_array($useSummaryRaw, [0, '0', false, 'false', 'off', 'no'], true);
        try {
            $bundle = $this->serviceGoodsRepository->fetchPddDetailWithMeta($goods_sign);
            $result = $bundle['detail'];
            if ($result === []) {
                return app('json')->fail('商品详情获取失败');
            }
            $meta = $bundle['meta'];
            if (!$useSummary) {
                $meta['summary_used'] = false;
                $meta['summary_skipped'] = true;
                // 当前 uniapp PDD_DETAIL_SKIP_SUMMARY=true：不应用 URL item；改 false 时会先 apply 整份列表 summary
                $meta['pipeline'][] = 'debug: use_summary=false，后端不合并列表 summary；前端当前 SKIP 不应用 options.item（改 false 后会本地 merge 列表价/图/销量等）';
                $meta['frontend_list_hints'] = '可选 URL options.item 整份 summary（goods_sign/title/image/price/sales_tip/video 等）；当前 SKIP=true 未启用';
            }
            return app('json')->success([
                'detail' => is_array($result) ? $result : ['raw' => $result],
                '_source' => $this->serviceGoodsRepository->getPddDataSource(),
                '_meta' => $meta,
            ]);
        } catch (\Exception $e) {
            Log::error('商品详情获取失败', [
                'itemIds' => $goods_sign,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }
    
     /**
     * 生成拼多多推广链接
     * POST /api/taoke/goods/create_taobao_link
     */
    public function createPddLink()
    {
        $goods_sign = $this->request->post('goods_sign', '');

        if (empty($goods_sign)) {
            return app('json')->fail('商品不能为空');
        }

        [$pid, $pdd_custom_parameters] = $this->resolvePddPromotionParams();
        if ($pid === '') {
            return app('json')->fail('缺少拼多多 PID，请配置 PDD_PID 或登录后完成推手备案');
        }
        if ($pdd_custom_parameters === '') {
            $pdd_custom_parameters = $this->pddOfficial->getDefaultCustomParameters() ?: 'naimeng01';
        }

        try {
            $result = $this->serviceGoodsRepository->createPddPromotion($goods_sign, $pid, $pdd_custom_parameters);

            if (empty($result)) {
                return app('json')->fail('生成推广链接失败');
            }

            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('生成推广链接失败', [
                'goods_sign' => $goods_sign,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败'.$e->getMessage());
        }
    }
      /**
     * 生成拼多多推广位然后授权
     * POST /api/taoke/goods/create_taobao_link
     */
    public function createPddPid()
    {
        $uid = $this->request->uid() ?? 0;
        //$uid = 1;
        if (!$uid) {
            return app('json')->fail('请先登录');
        }
        try {
            // 获取用户
            $user = \app\common\model\user\User::find($uid);
            if (!$user) {
                return app('json')->fail('用户不存在');
            }
            
            $pid = 0;
            $pdd_custom_parameters = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 8);//随机数

            // 如果已有PID，直接返回
            if (!empty($user->pdd_pid)) {
                $pid = $user->pdd_pid;
            } else {
                // [官方直连切换 2026-09-21] 原订单侠调用：
                // $result = $this->dingdanxiaService->createPddPid();
                // if (empty($result)) {
                //     return app('json')->fail('生成推广位失败');
                // }
                // $pid = $result[0]['p_id'] ?? '';
                // if (empty($pid)) {
                //     return app('json')->fail('生成推广位失败，未获取到PID');
                // }
                // $user->pdd_pid = $pid;
                // $user->pdd_custom_params = $pdd_custom_parameters;
                // $user->save();
                return app('json')->fail('订单侠已停用，请配置平台 PDD_PID 或使用已有用户 PID');
            }
            if (empty($pid)) {
                return app('json')->fail('生成推广位失败');
            }
            //有了pid去生成授权备案链接
            $result = $this->createPddAuthorityUrlInternal($pid, $pdd_custom_parameters);
            if (!empty($result['error'])) {
                return app('json')->fail((string) $result['error']);
            }
            return app('json')->success($result);

        } catch (\Exception $e) {
            Log::error('生成推广位失败', [
                'pdd_pid' => $pid,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败'.$e->getMessage());
        }
    }

    /**
     * 查询拼多多 PID 是否已授权备案（bind=1 已备案）
     * POST /api/taoke/goods/pdd_authority_status
     */
    public function pddAuthorityStatus()
    {
        [$pid, $customParameters] = $this->resolvePddAuthorityParams();
        if ($pid === '') {
            return app('json')->fail('缺少拼多多 PID，请配置 PDD_PID 或登录后使用用户 PID');
        }

        try {
            $raw = $this->pddOfficial->memberAuthorityQuery($pid, $customParameters);
            if (isset($raw['error_response'])) {
                return app('json')->fail($raw['error_response']['sub_msg'] ?? $raw['error_response']['error_msg'] ?? '查询失败');
            }
            $bind = (int) ($raw['authority_query_response']['bind'] ?? 0);
            return app('json')->success([
                'bind' => $bind,
                'filed' => $bind === 1,
                'pid' => $pid,
                'custom_parameters' => $customParameters,
            ]);
        } catch (\Exception $e) {
            Log::error('拼多多备案状态查询失败', ['error' => $e->getMessage()]);
            return app('json')->fail('查询失败，请稍后重试');
        }
    }

    /**
     * 生成拼多多授权备案链接（给经理/推手打开并确认）
     * POST /api/taoke/goods/create_pdd_authority_url
     */
    public function createPddAuthorityUrl()
    {
        [$pid, $customParameters] = $this->resolvePddAuthorityParams();
        if ($pid === '') {
            return app('json')->fail('缺少拼多多 PID');
        }
        if ($customParameters === '') {
            return app('json')->fail('缺少 custom_parameters');
        }

        try {
            $data = $this->createPddAuthorityUrlInternal($pid, $customParameters);
            if (!empty($data['error'])) {
                return app('json')->fail((string) $data['error']);
            }
            return app('json')->success($data);
        } catch (\Exception $e) {
            Log::error('拼多多授权备案链接生成失败', ['error' => $e->getMessage()]);
            return app('json')->fail('生成失败，请稍后重试');
        }
    }

    /**
     * @return array|string JSON fail response 或 success 数据
     */
    protected function createPddAuthorityUrlInternal(string $pid, string $customParameters)
    {
        // [官方直连切换 2026-09-10] 原订单侠：$this->dingdanxiaService->pddPromUrlGenerate($pid, $customParameters);
        $raw = $this->pddOfficial->generateAuthorityPromUrl($pid, $customParameters);
        if (isset($raw['error_response'])) {
            $msg = $raw['error_response']['sub_msg'] ?? $raw['error_response']['error_msg'] ?? '生成失败';
            return ['error' => $msg];
        }

        $list = $raw['rp_promotion_url_generate_response']['url_list'] ?? [];
        $first = is_array($list) && isset($list[0]) ? $list[0] : [];
        $mobileUrl = (string) ($first['mobile_url'] ?? '');
        $url = (string) ($first['url'] ?? $mobileUrl);

        if ($url === '' && $mobileUrl === '') {
            return ['error' => '未获取到授权备案链接'];
        }

        return [
            'pid' => $pid,
            'custom_parameters' => $customParameters,
            'authority_url' => $url,
            'mobile_url' => $mobileUrl,
            'tip' => '请用推手账号打开 mobile_url，在拼多多页面点击确认授权；完成后 bind=1',
        ];
    }

    /**
     * 登录用户优先用 user.pdd_pid；否则用 .env 平台 PID
     *
     * @return array{0:string,1:string} [pid, custom_parameters]
     */
    protected function resolvePddAuthorityParams(): array
    {
        $pid = (string) $this->request->post('pid', '');
        $customParameters = (string) $this->request->post('custom_parameters', '');

        $uid = 0;
        if ($this->request->isLogin()) {
            $uid = (int) $this->request->uid();
        }
        if ($uid > 0) {
            $user = \app\common\model\user\User::find($uid);
            if ($user) {
                if ($pid === '' && !empty($user->pdd_pid)) {
                    $pid = (string) $user->pdd_pid;
                }
                if ($customParameters === '' && !empty($user->pdd_custom_params)) {
                    $customParameters = (string) $user->pdd_custom_params;
                }
            }
        }

        if ($pid === '') {
            $pid = $this->pddOfficial->getDefaultPid();
        }
        if ($customParameters === '') {
            $customParameters = (string) $this->request->post('pdd_custom_parameters', 'naimeng01');
        }

        return [$pid, $customParameters];
    }

    /**
     * 转链用 PID / custom_parameters：登录用户优先，否则 .env 平台 PID
     *
     * @return array{0:string,1:string}
     */
    protected function resolvePddPromotionParams(): array
    {
        $pid = (string) $this->request->post('pid', '');
        $customParameters = (string) $this->request->post('custom_parameters', '');

        if ($this->request->isLogin()) {
            $user = \app\common\model\user\User::find((int) $this->request->uid());
            if ($user) {
                if ($pid === '' && !empty($user->pdd_pid)) {
                    $pid = (string) $user->pdd_pid;
                }
                if ($customParameters === '' && !empty($user->pdd_custom_params)) {
                    $customParameters = (string) $user->pdd_custom_params;
                }
            }
        }

        if ($pid === '') {
            $pid = $this->pddOfficial->getDefaultPid();
        }
        if ($customParameters === '') {
            $customParameters = (string) $this->request->post('pdd_custom_parameters', '');
        }
        if ($customParameters === '') {
            $customParameters = $this->pddOfficial->getDefaultCustomParameters();
        }

        return [$pid, $customParameters];
    }

     /**
     * 域名绑定拼多多推广链接
     * POST /api/taoke/goods/create_taobao_link
     */
    public function createPddUrl()
    {
        $goods_sign = $this->request->post('goods_sign', '');

        if (empty($goods_sign)) {
            return app('json')->fail('商品不能为空');
        }
        try {
            // [官方直连切换 2026-09-21] 原订单侠调用：
            // $result = $this->dingdanxiaService->pddHighCommission($goods_sign);
            // $result = $this->dingdanxiaService->pddPromUrlGenerate();
            // if (empty($result)) {
            //     return app('json')->fail('生成推广链接失败');
            // }
            // return app('json')->success($result);
            return app('json')->fail('订单侠已停用，请走 create_pdd_link 官方转链');
        } catch (\Exception $e) {
            Log::error('生成推广链接失败', [
                'goods_sign' => $goods_sign,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败'.$e->getMessage());
        }
    }
    
    
    /**
     * 京东商品
     * GET /api/taoke/goods/taobao
     */
    public function jdGoods()
    {
        $page = (int)$this->request->post('page_no', $this->request->post('page', 1));
        $limit = (int)$this->request->post('page_size', $this->request->post('limit', 20));
        $cate = (int)$this->request->post('cate', 0);
        $keyword = (string)$this->request->post('keyword', '');
        try {
            $list = $this->serviceGoodsRepository->searchPlatform('jd', $keyword, $page, $limit, $cate);
            return app('json')->success([
                'list' => $list,
                '_source' => $this->serviceGoodsRepository->getJdDataSource(),
            ]);
        } catch (\Exception $e) {
            Log::error('京东商品列表获取失败', [
                'page' => $page,
                'cate' => $cate,
                'error' => $e->getMessage()
            ]);
            return app('json')->success([
                'list' => [],
                '_source' => $this->serviceGoodsRepository->getJdDataSource(),
            ]);
        }
    }
    
     /**
     * 京东商品详情
     * GET /api/taoke/goods/taobao
     */
    public function jdGoodsDetail()
    {
        $itemIds = $this->request->post('itemIds', $this->request->post('skuIds', 0));
        $hints = array_filter([
            'spuid' => (string) $this->request->post('spuid', ''),
            'skuId' => (string) $this->request->post('skuId', $this->request->post('sku_id', '')),
            'materialUrl' => (string) $this->request->post('materialUrl', ''),
        ], static fn ($v) => $v !== '');
        $useSummaryRaw = $this->request->post('use_summary', 1);
        $useSummary = !in_array($useSummaryRaw, [0, '0', false, 'false', 'off', 'no'], true);
        $summary = [];
        if ($useSummary) {
            $summary = $this->request->post('summary', []);
            if (!is_array($summary) || $summary === []) {
                $itemRaw = $this->request->post('item', '');
                if (is_string($itemRaw) && $itemRaw !== '') {
                    $decoded = json_decode($itemRaw, true);
                    if (is_array($decoded)) {
                        $summary = $decoded;
                    }
                }
            }
        }
        try {
            $bundle = $this->serviceGoodsRepository->fetchJdDetailWithMeta($itemIds, $summary, $hints);
            $meta = $bundle['meta'];
            if (!$useSummary) {
                $meta['summary_used'] = false;
                $meta['summary_skipped'] = true;
                // 后端不收 POST summary；前端仍会本地用列表 URL options.item 补价/销量/店名，且可能带 spuid/materialUrl hints
                $meta['pipeline'][] = 'debug: use_summary=false，后端不合并列表 summary；前端 mergeJdListSummaryPricing 本地补价/销量/店名；spuid 可作 hints';
                $meta['frontend_list_hints'] = 'URL options.item→jdListSummary：price/ot_price/sales|sales_text/shopName；另 spuid→请求 hints；购买 materialUrl 来自列表/详情物料链（非订单侠）';
            }
            return app('json')->success([
                'list' => $bundle['list'],
                '_source' => $this->serviceGoodsRepository->getJdDataSource(),
                '_meta' => $meta,
            ]);
        } catch (\Exception $e) {
            Log::error('京东商品详情获取失败', [
                'itemIds' => $itemIds,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }
     /**
     * 生成京东推广链接
     * POST /api/taoke/goods/create_taobao_link
     */
    public function createJdLink()
    {
        $uid = $this->request->uid() ?? 0;
        // $uid = 2;
        if (!$uid) {
            return app('json')->fail('请先登录');
        }
        $materialUrl = $this->request->post('materialUrl', '');

        if (empty($materialUrl)) {
            return app('json')->fail('商品物料不能为空');
        }
        try {
            // 获取用户
            $user = \app\common\model\user\User::find($uid);
            if (!$user) {
                return app('json')->fail('用户不存在');
            }
            
            $pid = 0;
            //暂时不需要推广位
            // // 如果已有PID，直接返回
            // if (!empty($user->jd_pid)) {
            //     $pid = $user->jd_pid;
            // } else {
            //     // 生成推广位
            //     $result = $this->dingdanxiaService->createJdPid();
            //     var_dump($result);die;
            //     if (empty($result)) {
            //         return app('json')->fail('生成推广位失败');
            //     }

            //     $pid = $result[0]['p_id'] ?? '';
            //     if (empty($pid)) {
            //         return app('json')->fail('生成推广位失败，未获取到PID');
            //     }

            //     // 保存到数据库
            //     $user->pdd_pid = $pid;
            //     $user->save();
                
                
            // }
            // if (empty($pid)) {
            //     return app('json')->fail('生成推广位失败');
            // }
            // [官方直连切换 2026-09-21] 原订单侠高佣转链已注释（释放账号）：
            // $result = $this->dingdanxiaService->jdHighCommission($materialUrl,$pid);
            // if (!empty($result)) {
            //     return app('json')->success($result);
            // }
            // 当前仍可“转链成功”的原因：直接回传前端 POST 的 materialUrl（列表/详情京粉物料链），
            // 并标记 fallback=true。这不是订单侠，也无高佣/跟单；正式释放订单侠账号前请关掉京东 Tab。
            $fallbackUrl = $this->normalizeJdMaterialUrl($materialUrl);
            if ($fallbackUrl === '') {
                return app('json')->fail('京东转链已停用订单侠，且无可用物料链接');
            }
            Log::warning('京东转链跳过订单侠，使用物料链接兜底', [
                'materialUrl' => $materialUrl,
                'fallbackUrl' => $fallbackUrl,
            ]);
            return app('json')->success([
                'clickURL' => $fallbackUrl,
                'clickUrl' => $fallbackUrl,
                'shortURL' => $fallbackUrl,
                'fallback' => true,
                '_note' => '非订单侠：仅回传 materialUrl 物料兜底；无高佣。释放账号前请关京东 Tab。',
            ]);

        } catch (\Exception $e) {
            Log::error('生成推广位失败', [
                'materialUrl' => $materialUrl,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败');
        }
        
    }

    /**
     * 规范化京东物料链接（列表常返回无协议的 jingfen.jd.com/...）
     */
    protected function normalizeJdMaterialUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) {
            return $url;
        }
        if (stripos($url, '//') === 0) {
            return 'https:' . $url;
        }
        return 'https://' . ltrim($url, '/');
    }
    
    
     
    /**
     * 唯品会商品
     * GET /api/taoke/goods/taobao
     */
    public function wphGoods()
    {
        $page = (int) $this->request->post('page_no', $this->request->post('page', 1));
        $limit = (int) $this->request->post('page_size', $this->request->post('limit', 20));
        $keyword = (string) $this->request->post('keyword', '');
        $cate = (int) $this->request->post('cate', 0);
        if ($keyword === '' && $cate <= 0) {
            $keyword = '热销';
        }
        try {
            $list = $this->serviceGoodsRepository->searchPlatform('wph', $keyword, $page, $limit, $cate);
            $payload = [
                'list' => $list,
                '_source' => $this->serviceGoodsRepository->getWphDataSource(),
            ];
            if ($this->goodsDriverChannel === 'official') {
                $payload['channel'] = 'official';
            }
            return app('json')->success($payload);
        } catch (\Exception $e) {
            Log::error('唯品商品列表获取失败', [
                'keyword' => $keyword,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }
    
     /**
     * 唯品会商品详情
     * GET /api/taoke/goods/taobao
     */
    public function vipGoodsDetail()
    {
        $goodsId = (string) $this->request->post('id', $this->request->post('goods_id', ''));
        try {
            $bundle = $this->serviceGoodsRepository->fetchVipDetailWithMeta($goodsId);
            $result = $bundle['detail'];
            if ($result === [] || !is_array($result)) {
                return app('json')->fail('商品详情获取失败');
            }
            return app('json')->success([
                'detail' => $result,
                '_source' => $this->serviceGoodsRepository->getWphDataSource(),
                '_meta' => $bundle['meta'],
            ]);
        } catch (\Exception $e) {
            Log::error('商品详情获取失败', [
                'itemIds' => $goodsId,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('搜索失败，请稍后重试');
        }
    }

    /**
     * 生成唯品会推广链接
     * POST /api/taoke/goods/create_vip_link
     * 注意：PHP 方法名大小写不敏感，禁止再写 createvipLink 别名壳。
     */
    public function createVipLink()
    {
        $goods_id = (string) $this->request->post('goods_id', $this->request->post('id', ''));

        if ($goods_id === '') {
            return app('json')->fail('商品ID不能为空');
        }
        try {
            $openId = $this->resolveVipOpenId();
            $result = $this->serviceGoodsRepository->createVipPromotion($goods_id, $openId, [
                'ad_code' => (string) $this->request->post('ad_code', ''),
                'dest_url' => (string) $this->request->post('url', ''),
                'goods_name' => (string) $this->request->post('goods_name', ''),
            ]);

            if (empty($result)) {
                return app('json')->fail('生成推广链接失败');
            }

            return app('json')->success($result);
        } catch (\Exception $e) {
            Log::error('生成推广链接失败', [
                'goods_id' => $goods_id,
                'error' => $e->getMessage()
            ]);
            return app('json')->fail('生成推广链接失败');
        }
    }

    protected function resolveVipOpenId(): string
    {
        if ($this->request->isLogin()) {
            $uid = (int) $this->request->uid();
            if ($uid > 0) {
                return (string) $uid;
            }
        }
        $openId = (string) $this->request->post('open_id', '');
        if ($openId !== '') {
            return preg_replace('/[^a-zA-Z0-9_]/', '', $openId) ?: 'default_open_id';
        }
        return 'default_open_id';
    }
}
