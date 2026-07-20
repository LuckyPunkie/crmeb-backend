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

namespace app\controller\merchant\store;

use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\store\product\ProductRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserBlindboxRecycleRepository;
use crmeb\basic\BaseController;
use think\App;

/**
 * 商户端盲盒管理控制器
 * 菜单位置：商品 > 盲盒管理
 */
class BlindBox extends BaseController
{

    public function __construct(App $app)
    {
        parent::__construct($app);
    }

    /**
     * 获取盲盒设置
     */
    public function settings(MerchantRepository $repo)
    {
        $merId = $this->request->merId();
        $merchant = $repo->get($merId);

        $data = [
            'is_blindbox' => (int)($merchant['is_blindbox'] ?? 0),
            'blindbox_recycle_type' => (int)($merchant['blindbox_recycle_type'] ?? 1),
            'blindbox_recycle_point' => (int)($merchant['blindbox_recycle_point'] ?? 100),
            'blindbox_recycle_coupon_id' => (int)($merchant['blindbox_recycle_coupon_id'] ?? 0),
            'blindbox_recycle_coupon_num' => (int)($merchant['blindbox_recycle_coupon_num'] ?? 2),
        ];

        return app('json')->success($data);
    }

    /**
     * 保存盲盒设置
     */
    public function saveSettings(MerchantRepository $repo)
    {
        $merId = $this->request->merId();
        $data = $this->request->params([
            'is_blindbox',
            'blindbox_recycle_type',
            'blindbox_recycle_point',
            'blindbox_recycle_coupon_id',
            'blindbox_recycle_coupon_num',
        ]);

        $recycleType = (int)($data['blindbox_recycle_type'] ?? 1);
        $recyclePoint = max(1, (int)($data['blindbox_recycle_point'] ?? 100));
        $couponNum = max(1, (int)($data['blindbox_recycle_coupon_num'] ?? 2));

        $repo->update($merId, [
            'is_blindbox' => (int)($data['is_blindbox'] ?? 0),
            'blindbox_recycle_type' => in_array($recycleType, [1, 2]) ? $recycleType : 1,
            'blindbox_recycle_point' => min($recyclePoint, 10000),
            'blindbox_recycle_coupon_id' => max(0, (int)($data['blindbox_recycle_coupon_id'] ?? 0)),
            'blindbox_recycle_coupon_num' => $couponNum,
        ]);

        return app('json')->success(null, '盲盒设置已保存');
    }

    /**
     * 更新商品SKU概率权重
     */
    public function updateAttrWeight(ProductAttrValueRepository $repo)
    {
        $merId = $this->request->merId();
        $productId = (int)$this->request->param('product_id');
        $weights = $this->request->param('weights', []);

        if (empty($weights)) {
            return app('json')->fail('请传入概率权重数据');
        }

        $product = app()->make(ProductRepository::class)->getWhere([
            'product_id' => $productId,
            'mer_id' => $merId,
        ]);
        if (!$product) {
            return app('json')->fail('商品不存在');
        }

        $updateCount = 0;
        foreach ($weights as $item) {
            if (!isset($item['value_id']) || !isset($item['probability_weight'])) {
                continue;
            }
            $attrValue = $repo->getWhere([
                'value_id' => (int)$item['value_id'],
                'product_id' => $productId,
            ]);
            if ($attrValue) {
                $attrValue->probability_weight = max(0, (int)$item['probability_weight']);
                $attrValue->save();
                $updateCount++;
            }
        }

        return app('json')->success(['updated_count' => $updateCount], '已更新 ' . $updateCount . ' 个款式权重');
    }

    /**
     * 获取商品SKU列表（含概率权重）
     */
    public function attrList(ProductAttrValueRepository $repo)
    {
        $merId = $this->request->merId();
        $productId = (int)$this->request->param('product_id');

        $product = app()->make(ProductRepository::class)->getWhere([
            'product_id' => $productId,
            'mer_id' => $merId,
        ]);
        if (!$product) {
            return app('json')->fail('商品不存在');
        }

        $list = $repo->search(['product_id' => $productId])
            ->field('value_id,product_id,suk,image,price,stock,probability_weight,unique')
            ->select();

        $totalWeight = $list->sum('probability_weight');
        $totalWeight = max($totalWeight, 1);

        $formatted = [];
        foreach ($list as $item) {
            $formatted[] = [
                'value_id' => $item['value_id'],
                'suk' => $item['suk'],
                'image' => $item['image'],
                'price' => $item['price'],
                'stock' => $item['stock'],
                'probability_weight' => (int)$item['probability_weight'],
                'probability' => round($item['probability_weight'] / $totalWeight * 100, 1) . '%',
            ];
        }

        return app('json')->success([
            'list' => $formatted,
            'total_weight' => (int)$totalWeight,
        ]);
    }

    /**
     * 商户端回收统计
     */
    public function recycleStats(UserBlindboxRecycleRepository $repo)
    {
        $merId = $this->request->merId();
        $where = $this->request->params(['uid', 'product_id', 'reward_type', 'date_range']);
        $where['mer_id'] = $merId;
        $stats = $repo->getRecycleStats($where);
        return app('json')->success($stats);
    }

    /**
     * 商户端回收记录列表
     */
    public function recycleLst(UserBlindboxRecycleRepository $repo)
    {
        $merId = $this->request->merId();
        [$page, $limit] = $this->getPage();
        $where = $this->request->params(['uid', 'product_id', 'reward_type', 'date_range']);

        $productIds = app()->make(ProductRepository::class)->search($merId, [
            'is_del' => 0,
        ])->column('product_id');

        $query = $repo->search($where);
        if ($productIds) {
            $query->whereIn('product_id', $productIds);
        }

        $count = $query->count();
        $list = $query->page($page, $limit)
            ->order('create_time DESC')
            ->with([
                'user' => function ($query) {
                    $query->field('uid,nickname');
                },
                'cabinet.attrValue' => function ($query) {
                    $query->field('value_id,suk,image');
                },
                'product' => function ($query) {
                    $query->field('product_id,store_name');
                },
            ])
            ->select();

        return app('json')->success(compact('list', 'count'));
    }
}
