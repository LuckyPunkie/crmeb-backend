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

namespace app\common\repositories\community;

use app\common\dao\community\CommunityPaidDao;
use app\common\dao\community\CommunityPaidOrderDao;
use app\common\repositories\BaseRepository;
use think\exception\ValidateException;
use think\facade\Db;

/**
 * 社区付费内容
 */
class CommunityPaidContentRepository extends BaseRepository
{
    /**
     * @var CommunityPaidDao
     */
    protected $dao;

    public function __construct(CommunityPaidDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取付费内容详情（根据权限分级返回）
     */
    public function getDetail(int $communityId, $uid = null)
    {
        $data = $this->dao->search(['community_id' => $communityId])->find();
        if (!$data) throw new ValidateException('付费内容不存在');

        $isUnlocked = false;
        if ($uid) {
            // 发布者自己始终可见
            if ($data['uid'] == $uid) {
                $isUnlocked = true;
            } else {
                // 检查是否已购买
                $orderDao = app()->make(CommunityPaidOrderDao::class);
                $isUnlocked = $orderDao->search([
                    'community_id' => $communityId,
                    'buyer_uid' => $uid,
                    'pay_status' => 1,
                ])->count() > 0;
            }
        }

        $result = $data->toArray();
        if (!$isUnlocked) {
            // 未购买：仅返回免费预览内容和付费元数据
            unset($result['paid_content']);
            $result['is_unlocked'] = false;
            $result['paid_meta'] = [
                'char_count' => mb_strlen($data['paid_content']),
                'image_count' => substr_count($data['paid_content'], '<img'),
            ];
        } else {
            $result['is_unlocked'] = true;
        }
        return $result;
    }

    /**
     * 检查是否已解锁
     */
    public function checkUnlocked(int $communityId, int $uid): bool
    {
        $data = $this->dao->search(['community_id' => $communityId])->find();
        if (!$data) return false;
        if ($data['uid'] == $uid) return true;

        $orderDao = app()->make(CommunityPaidOrderDao::class);
        return $orderDao->search([
            'community_id' => $communityId,
            'buyer_uid' => $uid,
            'pay_status' => 1,
        ])->count() > 0;
    }

    /**
     * 解锁付费内容（创建订单）
     */
    public function unlock(int $communityId, int $buyerUid, string $payType = 'balance')
    {
        $data = $this->dao->search(['community_id' => $communityId])->find();
        if (!$data) throw new ValidateException('付费内容不存在');
        if ($data['uid'] == $buyerUid) throw new ValidateException('自己的内容无需购买');

        $orderDao = app()->make(CommunityPaidOrderDao::class);
        if ($orderDao->search([
            'community_id' => $communityId,
            'buyer_uid' => $buyerUid,
            'pay_status' => 1,
        ])->count() > 0) {
            throw new ValidateException('付费内容已购买', 10008);
        }

        $orderNo = 'PO' . date('YmdHis') . rand(1000, 9999);
        $platformRatio = $this->getCommissionRatio('paid');

        $order = $orderDao->create([
            'order_no' => $orderNo,
            'paid_content_id' => $data['id'],
            'community_id' => $communityId,
            'buyer_uid' => $buyerUid,
            'seller_uid' => $data['uid'],
            'amount' => $data['price'],
            'pay_type' => $payType,
            'pay_status' => 0,
            'platform_ratio' => $platformRatio,
        ]);

        return $order;
    }

    /**
     * 支付成功回调
     */
    public function paySuccess(string $orderNo)
    {
        $orderDao = app()->make(CommunityPaidOrderDao::class);
        $order = $orderDao->search(['order_no' => $orderNo])->find();
        if (!$order) throw new ValidateException('订单不存在');
        if ($order['pay_status'] == 1) return $order; // 幂等

        return Db::transaction(function () use ($orderDao, $order) {
            $sellerIncome = round($order['amount'] * (1 - $order['platform_ratio']), 2);
            $orderDao->update($order['id'], [
                'pay_status' => 1,
                'pay_time' => date('Y-m-d H:i:s'),
                'seller_income' => $sellerIncome,
            ]);
            $this->dao->update($order['paid_content_id'], [
                'buy_count' => Db::raw('buy_count + 1'),
                'total_income' => Db::raw('total_income + ' . $order['amount']),
            ]);
            return $orderDao->get($order['id']);
        });
    }

    /**
     * 我的付费收益
     */
    public function getIncome(int $uid, array $dateRange = [], int $page = 1, int $limit = 10)
    {
        $query = $this->dao->search(['uid' => $uid]);
        if (!empty($dateRange[0])) {
            $query->where('create_time', '>=', $dateRange[0]);
        }
        if (!empty($dateRange[1])) {
            $query->where('create_time', '<=', $dateRange[1] . ' 23:59:59');
        }
        $query->with(['community' => function ($q) {
            $q->field('community_id,title');
        }]);
        $count = $query->count();
        $list = $query->page($page, $limit)->order('id DESC')->select();

        $totalIncome = $this->dao->search(['uid' => $uid])->sum('total_income');

        return [
            'total_income' => (float)$totalIncome,
            'count' => $count,
            'list' => $list,
        ];
    }

    /**
     * 付费订单列表
     */
    public function getOrders(int $uid, $communityId = null, int $page = 1, int $limit = 10)
    {
        $orderDao = app()->make(CommunityPaidOrderDao::class);
        $where = ['seller_uid' => $uid, 'pay_status' => 1];
        if ($communityId) {
            $where['community_id'] = $communityId;
        }
        $query = $orderDao->search($where)->order('pay_time DESC');
        $count = $query->count();
        $list = $query->page($page, $limit)->select();
        return compact('count', 'list');
    }

    /**
     * 获取平台抽成比例
     */
    public function getCommissionRatio(string $type): float
    {
        $config = Db::name('system_commission_config')->where('type', $type)->where('status', 1)->find();
        return $config ? (float)$config['ratio'] : 0.20;
    }
}
