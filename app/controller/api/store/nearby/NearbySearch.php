<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016-2026 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\controller\api\store\nearby;

use think\App;
use crmeb\basic\BaseController;
use app\common\repositories\store\nearby\NearbyShopRepository;

/**
 * 附近好店搜索 - C端API控制器
 */
class NearbySearch extends BaseController
{
    protected $repository;

    public function __construct(App $app, NearbyShopRepository $repository)
    {
        parent::__construct($app);
        $this->repository = $repository;
    }

    /**
     * 搜索商家列表
     * GET /api/nearby/search/lst
     */
    public function lst()
    {
        [$page, $limit] = $this->getPage();
        $where = $this->request->params([
            'keyword',
            'latitude',
            'longitude',
        ]);

        if (empty($where['keyword'])) {
            return app('json')->success(['count' => 0, 'list' => []]);
        }

        return app('json')->success($this->repository->getList($where, $page, $limit));
    }

    /**
     * 热门搜索关键词
     * GET /api/nearby/search/hot
     */
    public function hot()
    {
        // 尝试从系统配置读取热门搜索关键词
        try {
            $hotWordsStr = \app\common\model\system\config\SystemConfigValue::getDB()
                ->where('config_key', 'nearby_hot_keywords')
                ->where('mer_id', 0)
                ->value('value');
            if (!empty($hotWordsStr)) {
                $hotWords = json_decode($hotWordsStr, true);
                if (is_array($hotWords) && !empty($hotWords)) {
                    return app('json')->success($hotWords);
                }
            }
        } catch (\Exception $e) {
            // 配置读取失败时 fallback 硬编码兜底
        }

        // 返回默认热门搜索关键词（fallback）
        $hotWords = [
            ['keyword' => '火锅', 'search_count' => 5234],
            ['keyword' => '烤肉', 'search_count' => 4102],
            ['keyword' => '奶茶', 'search_count' => 3891],
            ['keyword' => '健身房', 'search_count' => 2765],
            ['keyword' => '日料', 'search_count' => 2450],
            ['keyword' => 'KTV', 'search_count' => 2341],
            ['keyword' => '密室逃脱', 'search_count' => 1987],
            ['keyword' => '烧烤', 'search_count' => 1876],
        ];

        return app('json')->success($hotWords);
    }
}
