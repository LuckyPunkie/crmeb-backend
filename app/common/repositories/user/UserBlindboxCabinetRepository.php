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

namespace app\common\repositories\user;

use app\common\dao\user\UserBlindboxCabinetDao;
use app\common\model\store\product\ProductAttrValue;
use app\common\repositories\BaseRepository;

/**
 * @mixin UserBlindboxCabinetDao
 */
class UserBlindboxCabinetRepository extends BaseRepository
{

    public function __construct(UserBlindboxCabinetDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 添加款式到盒柜（并发安全，依赖 uk_uid_product_attr 唯一索引）
     * @param array $data
     * @return mixed
     */
    public function addToCabinet(array $data)
    {
        $existing = $this->dao->getByUserProductSku($data['uid'], $data['product_id'], $data['attr_value_id']);
        if ($existing) {
            $existing->quantity = $existing->quantity + ($data['quantity'] ?? 1);
            $existing->save();
            return $existing;
        }
        try {
            return $this->dao->create($data);
        } catch (\Exception $e) {
            $isDuplicate = $e->getCode() == 1062
                || ($e->getPrevious() && $e->getPrevious()->getCode() == 1062)
                || stripos($e->getMessage(), 'Duplicate entry') !== false;

            if (!$isDuplicate) {
                throw $e;
            }

            $existing = $this->dao->getByUserProductSku($data['uid'], $data['product_id'], $data['attr_value_id']);
            if ($existing) {
                $existing->quantity = $existing->quantity + ($data['quantity'] ?? 1);
                $existing->save();
                return $existing;
            }
            throw $e;
        }
    }

    /**
     * 获取盒柜列表
     * @param int $uid
     * @param int $productId
     * @param string $type all|collected|uncollected|duplicate
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getCabinetList(int $uid, int $productId = 0, string $type = 'all', int $page = 1, int $limit = 20)
    {
        $where = ['uid' => $uid, 'status' => 1];
        if ($productId > 0) {
            $where['product_id'] = $productId;
        }

        $query = $this->dao->search($where);

        switch ($type) {
            case 'uncollected':
                if ($productId <= 0) {
                    return ['list' => [], 'count' => 0];
                }
                $allSkus = ProductAttrValue::where('product_id', $productId)->select()->toArray();
                $collectedSkuIds = $this->dao->search(['uid' => $uid, 'product_id' => $productId, 'status' => 1])
                    ->column('attr_value_id');
                $uncollectedSkus = [];
                $weightTotal = ProductAttrValue::where('product_id', $productId)->sum('probability_weight') ?: 1;
                foreach ($allSkus as $sku) {
                    if (!in_array($sku['value_id'], $collectedSkuIds)) {
                        $pct = round(($sku['probability_weight'] ?? 0) / $weightTotal * 100, 1);
                        $sku['rarity'] = $this->calcRarity($pct);
                        $sku['is_collected'] = false;
                        $sku['quantity'] = 0;
                        $uncollectedSkus[] = $sku;
                    }
                }
                $count = count($uncollectedSkus);
                $list = array_slice($uncollectedSkus, ($page - 1) * $limit, $limit);
                return compact('list', 'count');
            case 'duplicate':
                $query->where('quantity', '>', 1);
                break;
            case 'collected':
                break;
            default:
                break;
        }

        $count = $query->count();
        $list = $query->page($page, $limit)
            ->order('create_time DESC')
            ->with([
                'product' => function ($query) {
                    $query->field('product_id,mer_id,store_name,image,price,ot_price');
                },
                'attrValue' => function ($query) {
                    $query->field('value_id,product_id,suk,image,price,probability_weight');
                }
            ])
            ->select();

        $weightCache = [];
        foreach ($list as $item) {
            if ($item->attrValue && $item->attrValue->probability_weight > 0) {
                $pid = $item->product_id;
                if (!isset($weightCache[$pid])) {
                    $weightCache[$pid] = ProductAttrValue::where('product_id', $pid)->sum('probability_weight') ?: 1;
                }
                $totalWeight = $weightCache[$pid];
                $pct = round($item->attrValue->probability_weight / $totalWeight * 100, 1);
                $item->rarity = $this->calcRarity($pct);
            }
        }

        return compact('list', 'count');
    }

    /**
     * 获取盒柜统计
     * @param int $uid
     * @param int $productId
     * @return array
     */
    public function getCabinetStats(int $uid, int $productId = 0)
    {
        $where = ['uid' => $uid, 'status' => 1];
        if ($productId > 0) {
            $where['product_id'] = $productId;
        }

        $cabinetList = $this->dao->search($where)->select()->toArray();
        $totalDraws = 0;
        $skuSet = [];
        $productSkuTotal = [];

        foreach ($cabinetList as $item) {
            $totalDraws += $item['quantity'];
            $skuSet[$item['attr_value_id']] = ($skuSet[$item['attr_value_id']] ?? 0) + $item['quantity'];
            $pid = $item['product_id'];
            $productSkuTotal[$pid] = ($productSkuTotal[$pid] ?? 0) + $item['quantity'];
        }

        $collectedCount = count($skuSet);

        if ($productId > 0) {
            $totalCount = ProductAttrValue::where('product_id', $productId)->count();
        } else {
            $allProductIds = array_keys($productSkuTotal);
            $totalCount = $allProductIds ? ProductAttrValue::whereIn('product_id', $allProductIds)->count() : $collectedCount;
        }

        $duplicateCount = collect($cabinetList)->where('quantity', '>', 1)->count();

        $rate = $totalCount > 0 ? round($collectedCount / $totalCount * 100, 1) : 0;

        return compact('totalDraws', 'collectedCount', 'totalCount', 'duplicateCount', 'rate');
    }

    /**
     * 计算稀有度（根据实际概率百分比）
     */
    public function calcRarity(float $probabilityPercent)
    {
        if ($probabilityPercent <= 5) return ['code' => 'S', 'name' => '传说'];
        if ($probabilityPercent <= 15) return ['code' => 'A', 'name' => '稀有'];
        if ($probabilityPercent <= 30) return ['code' => 'B', 'name' => '精品'];
        return ['code' => 'C', 'name' => '普通'];
    }
}
