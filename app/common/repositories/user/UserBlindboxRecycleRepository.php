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

use app\common\dao\user\UserBlindboxRecycleDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\store\coupon\StoreCouponRepository;
use app\common\repositories\store\coupon\StoreCouponUserRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * @mixin UserBlindboxRecycleDao
 */
class UserBlindboxRecycleRepository extends BaseRepository
{

    const RARITY_MULTIPLIER = [
        'S' => 5.0,
        'A' => 3.0,
        'B' => 1.5,
        'C' => 1.0,
    ];

    public function __construct(UserBlindboxRecycleDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 回收款式
     * @param int $uid
     * @param int $cabinetId
     * @param int $quantity
     * @param int $rewardType 1积分 2优惠券
     * @return mixed
     */
    public function recycle(int $uid, int $cabinetId, int $quantity, int $rewardType)
    {
        $cabinetRepo = app()->make(UserBlindboxCabinetRepository::class);
        $cabinet = $cabinetRepo->getWhere(['id' => $cabinetId, 'uid' => $uid, 'status' => 1]);
        if (!$cabinet) {
            throw new ValidateException('盒柜记录不存在');
        }
        if ($cabinet['quantity'] < $quantity) {
            throw new ValidateException('可回收数量不足');
        }

        $product = $cabinet->product;
        $merchantRepository = app()->make(MerchantRepository::class);
        $merchant = $merchantRepository->get($product ? $product['mer_id'] : 0);
        if (!$merchant || !$merchant['is_blindbox']) {
            throw new ValidateException('该商户不是盲盒店铺');
        }

        $rarityCode = 'C';
        if ($cabinet->attrValue && $cabinet->attrValue->probability_weight > 0) {
            $totalWeight = \app\common\model\store\product\ProductAttrValue::where('product_id', $cabinet->product_id)->sum('probability_weight') ?: 1;
            $pct = round($cabinet->attrValue->probability_weight / $totalWeight * 100, 1);
            $rarityInfo = $cabinetRepo->calcRarity($pct);
            $rarityCode = $rarityInfo['code'];
        }

        return Db::transaction(function () use ($uid, $cabinetId, $quantity, $rewardType, $cabinet, $merchant, $rarityCode, $cabinetRepo) {
            $cabinet->quantity = $cabinet->quantity - $quantity;
            if ($cabinet->quantity <= 0) {
                $cabinet->status = 0;
                $cabinet->quantity = 0;
            }
            $cabinet->save();

            $rewardValue = 0;
            $rewardTotal = 0;

            if ($rewardType == 1) {
                $basePoints = intval($merchant['blindbox_recycle_point']);
                $multiplier = self::RARITY_MULTIPLIER[$rarityCode] ?? 1.0;
                $points = intval($basePoints * $multiplier * $quantity);
                $rewardValue = $points;
                $rewardTotal = $points;

                $userRepository = app()->make(UserRepository::class);
                $user = $userRepository->get($uid);
                $balance = ($user['integral'] ?? 0) + $points;
                $userRepository->incIntegral($uid, $points, '盲盒回收获得积分', 'blindbox_recycle', [
                    'status' => 1,
                    'mark' => '盲盒回收获得积分' . $points,
                    'number' => $points,
                    'balance' => $balance,
                ]);
            } elseif ($rewardType == 2) {
                $couponId = intval($merchant['blindbox_recycle_coupon_id']);
                $couponNum = intval($merchant['blindbox_recycle_coupon_num']);
                if ($couponId <= 0 || $couponNum <= 0) {
                    throw new ValidateException('商户未配置优惠券回收规则');
                }
                $couponCount = intval(floor($quantity / $couponNum));
                if ($couponCount <= 0) {
                    throw new ValidateException('回收数量不足以兑换优惠券（每' . $couponNum . '件换1张）');
                }

                $rewardValue = $couponId;
                $rewardTotal = $couponCount;

                $couponRepository = app()->make(StoreCouponRepository::class);
                $coupon = $couponRepository->getWhere(['coupon_id' => $couponId, 'is_del' => 0]);
                if (!$coupon) {
                    throw new ValidateException('回收奖励优惠券不存在或已删除');
                }

                for ($i = 0; $i < $couponCount; $i++) {
                    $couponRepository->sendCoupon($coupon, $uid, StoreCouponUserRepository::SEND_TYPE_SEND);
                }
            }

            $recycleData = [
                'uid' => $uid,
                'cabinet_id' => $cabinetId,
                'product_id' => $cabinet['product_id'],
                'attr_value_id' => $cabinet['attr_value_id'],
                'quantity' => $quantity,
                'mer_id' => $cabinet->product->mer_id ?? 0,
                'reward_type' => $rewardType,
                'reward_value' => $rewardValue,
                'reward_total' => $rewardTotal,
            ];

            return $this->dao->create($recycleData);
        });
    }

    /**
     * 获取用户回收记录
     * @param int $uid
     * @param int $page
     * @param int $limit
     * @return array
     */
    public function getUserRecords(int $uid, int $page, int $limit)
    {
        $query = $this->dao->search(['uid' => $uid])
            ->order('create_time DESC')
            ->with([
                'cabinet.attrValue' => function ($query) {
                    $query->field('value_id,sku,image');
                },
                'product' => function ($query) {
                    $query->field('product_id,store_name');
                }
            ]);

        $count = $query->count();
        $list = $query->page($page, $limit)->select();

        return compact('list', 'count');
    }

    /**
     * 获取回收统计（管理后台用）
     * @param array $where
     * @return array
     */
    public function getRecycleStats(array $where = [])
    {
        $query = $this->dao->search($where);

        $data['total_count'] = $query->count();
        $data['total_point'] = $query->where('reward_type', 1)->sum('reward_total');
        $data['total_coupon'] = $query->where('reward_type', 2)->sum('reward_total');

        return $data;
    }
}
