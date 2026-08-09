<?php

namespace app\common\repositories\store;

use app\common\dao\store\BlindBoxFreeOpenDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\store\product\ProductAttrValueRepository;
use app\common\repositories\user\UserBlindboxCabinetRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 普通商家账号：每天首次进入盲盒广场免费开盒一次
 */
class BlindBoxFreeOpenRepository extends BaseRepository
{
    public function __construct(BlindBoxFreeOpenDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 查询今日是否可免费开（不消耗次数）
     */
    public function check(int $uid): array
    {
        if ($uid <= 0) {
            return [
                'is_merchant' => false,
                'eligible' => false,
                'already_opened' => false,
                'mer_id' => 0,
                'win_rate' => app()->make(BlindBoxShareRepository::class)->getFreeWinRate(),
                'message' => '请先登录',
            ];
        }

        $shareRepo = app()->make(BlindBoxShareRepository::class);
        $merId = $shareRepo->resolveOrdinaryMerchantIdByUid($uid);
        $winRate = $shareRepo->getFreeWinRate();

        if ($merId <= 0) {
            return [
                'is_merchant' => false,
                'eligible' => false,
                'already_opened' => false,
                'mer_id' => 0,
                'win_rate' => $winRate,
                'message' => '仅普通商家账号可免费开盒',
            ];
        }

        $today = date('Y-m-d');
        $exist = $this->dao->getWhere([
            'share_mer_id' => $merId,
            'open_date' => $today,
        ]);
        if ($exist) {
            return [
                'is_merchant' => true,
                'eligible' => false,
                'already_opened' => true,
                'mer_id' => $merId,
                'win_rate' => $winRate,
                'is_win' => (int)$exist['is_win'] === 1,
                'message' => '今日已领取过免费开盒',
            ];
        }

        return [
            'is_merchant' => true,
            'eligible' => true,
            'already_opened' => false,
            'mer_id' => $merId,
            'win_rate' => $winRate,
            'message' => '可免费开盒一次',
        ];
    }

    /**
     * 商家账号免费开盒：每个商家每天仅一次
     */
    public function tryOpen(int $uid): array
    {
        if ($uid <= 0) {
            throw new ValidateException('请先登录');
        }

        $shareRepo = app()->make(BlindBoxShareRepository::class);
        $merId = $shareRepo->resolveOrdinaryMerchantIdByUid($uid);
        if ($merId <= 0) {
            throw new ValidateException('仅普通商家账号可免费开盒');
        }

        $today = date('Y-m-d');
        $exist = $this->dao->getWhere([
            'share_mer_id' => $merId,
            'open_date' => $today,
        ]);
        if ($exist) {
            return [
                'eligible' => false,
                'already_opened' => true,
                'is_merchant' => true,
                'mer_id' => $merId,
                'is_win' => (int)$exist['is_win'] === 1,
                'product' => null,
                'attr' => null,
                'message' => '今日已领取过免费开盒',
            ];
        }

        $winRate = $shareRepo->getFreeWinRate();
        $isWin = $winRate > 0 && mt_rand(1, 100) <= $winRate;

        $productId = 0;
        $attrValueId = 0;
        $skuUnique = '';
        $cabinetId = 0;
        $productInfo = null;
        $attrInfo = null;

        if ($isWin) {
            $drawn = $this->drawPlatformBlindbox($uid);
            $productId = (int)$drawn['product_id'];
            $attrValueId = (int)$drawn['attr_value_id'];
            $skuUnique = (string)$drawn['sku_unique'];
            $cabinetId = (int)$drawn['cabinet_id'];
            $productInfo = $drawn['product'];
            $attrInfo = $drawn['attr'];
        }

        try {
            $row = $this->dao->create([
                'uid' => $uid,
                'share_mer_id' => $merId,
                'open_date' => $today,
                'is_win' => $isWin ? 1 : 0,
                'product_id' => $productId,
                'attr_value_id' => $attrValueId,
                'sku_unique' => $skuUnique,
                'cabinet_id' => $cabinetId,
            ]);
        } catch (\Throwable $e) {
            $dup = stripos($e->getMessage(), 'Duplicate') !== false || (int)$e->getCode() === 1062;
            if (!$dup) {
                throw $e;
            }
            $exist = $this->dao->getWhere([
                'share_mer_id' => $merId,
                'open_date' => $today,
            ]);
            return [
                'eligible' => false,
                'already_opened' => true,
                'is_merchant' => true,
                'mer_id' => $merId,
                'is_win' => $exist ? (int)$exist['is_win'] === 1 : false,
                'product' => null,
                'attr' => null,
                'message' => '今日已领取过免费开盒',
            ];
        }

        return [
            'eligible' => true,
            'already_opened' => false,
            'is_merchant' => true,
            'mer_id' => $merId,
            'is_win' => $isWin,
            'win_rate' => $winRate,
            'product' => $productInfo,
            'attr' => $attrInfo,
            'cabinet_id' => $cabinetId,
            'record_id' => (int)($row['id'] ?? 0),
            'message' => $isWin ? '恭喜中奖，奖品已放入盒柜' : '未中奖，谢谢参与',
        ];
    }

    /**
     * 从平台盲盒店在售商品随机选一个并按权重开款入柜
     */
    protected function drawPlatformBlindbox(int $uid): array
    {
        $platform = app()->make(BlindBoxShareRepository::class)->getPlatformBlindboxMerchant();
        if (!$platform) {
            throw new ValidateException('暂无平台盲盒商品');
        }
        $merId = (int)$platform['mer_id'];

        $products = Db::name('store_product')
            ->where('mer_id', $merId)
            ->where('is_del', 0)
            ->where('is_show', 1)
            ->where('status', 1)
            ->where('is_used', 1)
            ->where('stock', '>', 0)
            ->field('product_id,mer_id,store_name,image,price,stock')
            ->select()
            ->toArray();
        if (!$products) {
            throw new ValidateException('暂无在售盲盒商品');
        }

        $product = $products[array_rand($products)];
        $productId = (int)$product['product_id'];

        $attrValues = app()->make(ProductAttrValueRepository::class)
            ->search(['product_id' => $productId])
            ->where('stock', '>', 0)
            ->select();
        if (!$attrValues || $attrValues->isEmpty()) {
            throw new ValidateException('盲盒款式已售罄');
        }

        $selected = $this->weightedRandom($attrValues->toArray());
        if (!$selected) {
            throw new ValidateException('开盒失败');
        }

        $cabinet = app()->make(UserBlindboxCabinetRepository::class)->addToCabinet([
            'uid' => $uid,
            'product_id' => $productId,
            'attr_value_id' => (int)$selected['value_id'],
            'sku_unique' => (string)($selected['unique'] ?? ''),
            'order_id' => 0,
            'quantity' => 1,
            'random_seed' => uniqid('bb_free_', true),
        ]);

        Db::name('store_product_attr_value')
            ->where('value_id', (int)$selected['value_id'])
            ->where('stock', '>', 0)
            ->dec('stock', 1)
            ->update([]);
        Db::name('store_product')->where('product_id', $productId)->where('stock', '>', 0)->dec('stock', 1)->update([]);

        return [
            'product_id' => $productId,
            'attr_value_id' => (int)$selected['value_id'],
            'sku_unique' => (string)($selected['unique'] ?? ''),
            'cabinet_id' => (int)(is_array($cabinet) ? ($cabinet['id'] ?? 0) : ($cabinet->id ?? 0)),
            'product' => [
                'product_id' => $productId,
                'store_name' => $product['store_name'] ?? '',
                'image' => $product['image'] ?? '',
                'price' => $product['price'] ?? 0,
            ],
            'attr' => [
                'value_id' => (int)$selected['value_id'],
                'sku' => $selected['sku'] ?? ($selected['suk'] ?? ''),
                'image' => $selected['image'] ?? '',
            ],
        ];
    }

    protected function weightedRandom(array $items): ?array
    {
        $total = 0;
        foreach ($items as $item) {
            $total += max(0, (int)($item['probability_weight'] ?? 0));
        }
        if ($total <= 0) {
            return $items[array_rand($items)] ?? null;
        }
        $rand = mt_rand(1, $total);
        $cursor = 0;
        foreach ($items as $item) {
            $cursor += max(0, (int)($item['probability_weight'] ?? 0));
            if ($rand <= $cursor) {
                return $item;
            }
        }
        return $items[count($items) - 1] ?? null;
    }
}
