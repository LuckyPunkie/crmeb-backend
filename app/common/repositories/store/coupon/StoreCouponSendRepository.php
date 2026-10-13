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


namespace app\common\repositories\store\coupon;


use app\common\dao\store\coupon\StoreCouponSendDao;
use app\common\repositories\BaseRepository;
use app\common\repositories\system\merchant\MerchantRepository;
use app\common\repositories\user\UserMerchantRepository;
use app\common\repositories\user\UserRepository;
use crmeb\jobs\MerchantSendCouponJob;
use think\exception\ValidateException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Queue;

/**
 * @mixin StoreCouponSendDao
 */
class StoreCouponSendRepository extends BaseRepository
{
    public function __construct(StoreCouponSendDao $dao)
    {
        $this->dao = $dao;
    }

    /**
     * 获取优惠券发送列表
     *
     * 根据给定的条件和分页信息，查询优惠券发送记录及其相关信息。
     * 该方法首先构造查询条件，然后计算符合条件的记录总数，最后分页获取记录列表。
     * 列表中包括优惠券发送的基本信息、创建时间、状态等，并附带相关的优惠券使用统计信息。
     *
     * @param array $where 查询条件数组
     * @param int $page 当前页码
     * @param int $limit 每页记录数
     * @return array 包含记录总数和记录列表的数组
     */
    public function getList(array $where, $page, $limit)
    {
        // 构造查询条件，同时加载关联的coupon信息，只获取coupon_id和type字段
        $query = $this->dao->search($where)->with([
            'coupon' => function($query) {
                $query->field('coupon_id,type');
            }
        ]);

        // 计算符合条件的记录总数
        $count = $query->count();

        // 设置查询字段，获取优惠券发送的详细信息，包括使用次数和已使用数量等附加信息
        // 并按照coupon_send_id降序分页查询记录
        $list = $query->setOption('field', [])->field('B.*,A.coupon_num,A.create_time,A.status as send_status, A.mark,A.coupon_send_id')
            ->append(['useCount', 'used_num'], true)->page($page, $limit)->order('coupon_send_id DESC')->select();

        // 返回记录总数和记录列表的数组
        return compact('count', 'list');
    }


    /**
     * 创建优惠券发送任务
     *
     * 本函数用于处理优惠券的批量发送任务。它首先验证优惠券是否存在并检查是否可以发送给指定的用户。
     * 如果优惠券有限制并且剩余数量不足，则会抛出异常。最后，它在数据库事务中执行发送操作，
     * 更新优惠券的剩余数量，缓存发送的优惠券ID和用户ID列表，并将发送任务推入队列中。
     *
     * @param array $data 发送任务的数据，包括优惠券ID、用户ID列表、是否发送给所有用户等信息。
     * @param int $merId 商家ID，用于确定优惠券所属的商家。
     * @return object 返回发送任务的信息。
     * @throws ValidateException 如果优惠券不存在、用户未选择或优惠券数量不足，则抛出验证异常。
     */
    public function create($data, $merId)
    {
        // 初始化查询变量
        $query = null;

        // 根据传入的商家ID和优惠券ID验证优惠券是否存在
        $coupon = app()->make(StoreCouponRepository::class)->getWhere(['mer_id' => $merId, 'coupon_id' => $data['coupon_id'], 'is_del' => 0]);
        if (!$coupon) {
            throw new ValidateException('优惠券不存在');
        }

        // 根据商家ID确定用户仓库类型，并构建查询条件
        if ($merId) {
            $userMerchantRepository = app()->make(UserMerchantRepository::class);
            $where = ['mer_id' => $merId];
            $field = 'A.uid';
        } else {
            $where = [];
            $userMerchantRepository = app()->make(UserRepository::class);
            $field = 'uid';
        }

        // 根据是否发送给所有用户，构建查询条件并执行查询
        if ($data['is_all']) {
            $query = $userMerchantRepository->search($where + $data['search']);
        } else {
            $query = $userMerchantRepository->search($where + ['uids' => $data['uid']]);
        }

        // 获取查询结果中的用户ID列表
        $uid = $query->column($field);
        $uTotal = count($uid);
        // 如果没有选择用户，则抛出异常
        if (!$uTotal) {
            throw new ValidateException('请选择用户');
        }

        // 如果优惠券有限制且剩余数量不足，则抛出异常
        if ($coupon['is_limited'] && $coupon->remain_count < $uTotal) {
            throw new ValidateException('该优惠券可领取数不足' . $uTotal);
        }

        // 在数据库事务中执行发送操作
        return Db::transaction(function () use ($uid, $merId, $data, $coupon, $uTotal) {
            $search = $data['mark'];
            // 如果优惠券有限制，则更新优惠券的剩余数量
            if($coupon['is_limited']){
                $coupon->remain_count -= $uTotal;
            }
            // 创建优惠券发送记录
            $send = $this->dao->create([
                'mer_id' => $merId,
                'coupon_id' => $coupon->coupon_id,
                'coupon_num' => $uTotal,
                'status' => 0,
                'mark' => [
                    'type' => $data['is_all'],
                    'search' => count($search) ? $search : null
                ]
            ]);
            // 保存更新后的优惠券信息
            $coupon->save();
            // 缓存发送的优惠券ID和用户ID列表
            Cache::store('file')->set('_send_coupon' . $send->coupon_send_id, $uid);
            // 将发送任务推入队列
            Queue::push(MerchantSendCouponJob::class, $send->coupon_send_id);
            // 返回发送任务的信息
            return $send;
        });
    }

}
