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


namespace app\common\dao\user;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\user\UserLabel;
use app\common\model\user\UserMerchant;
use think\db\BaseQuery;

/**
 * Class UserMerchantDao
 * @package app\common\dao\user
 * @author xaboy
 * @day 2020/10/20
 */
class UserMerchantDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/10/20
     */
    protected function getModel(): string
    {
        return UserMerchant::class;
    }

    /**
     * 检查用户是否为特定商户的用户
     *
     * 本函数通过比较用户ID和商户ID来确定用户是否属于特定的商户。
     * 这对于在多商户平台上验证用户权限或进行特定的业务逻辑非常有用。
     *
     * @param int $uid 用户ID
     * @param int $mer_id 商户ID
     * @return bool 如果用户是该商户的用户返回true，否则返回false
     */
    public function isMerUser($uid, $mer_id)
    {
        // 使用compact函数创建包含uid和mer_id的数组，并通过existsWhere方法检查是否存在匹配的用户
        return $this->existsWhere(compact('uid', 'mer_id'));
    }

    /**
     * 更新用户在商户最后一次活动的时间
     *
     * 本函数用于记录用户在特定商户的最后活动时间，通过更新数据库中的相应记录来实现。
     * 参数$uid代表用户的唯一标识，$mer_id代表商户的唯一标识。函数通过查询匹配$uid和$mer_id的记录，
     * 并将最后活动时间更新为当前时间（格式为Y-m-d H:i:s）。
     *
     * @param int $uid 用户ID，用于定位特定用户的记录
     * @param int $mer_id 商户ID，用于定位特定商户的记录
     * @return int 返回更新操作的影响行数，用于确认更新是否成功
     */
    public function updateLastTime($uid, $mer_id)
    {
        // 构造查询条件，并更新'last_time'字段为当前时间
        return UserMerchant::getDB()->where(compact('uid', 'mer_id'))->update([
            'last_time' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * @param array $where
     * @return mixed
     * @author xaboy
     * @day 2020/10/20
     */
    public function search(array $where)
    {
        return UserMerchant::getDB()->alias('A')->leftJoin('User B', 'A.uid = B.uid AND A.status = 1')
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('A.mer_id', $where['mer_id']);
            })->when(isset($where['keyword']) && $where['keyword'], function (BaseQuery $query) use ($where) {
                return $query->where(function($query) use ($where) {
                    return $query
                        ->where('B.nickname|B.uid', 'like', '%' . $where['keyword'] . '%')
                        ->whereOr('B.phone', $where['keyword']);
                });
            })->when(isset($where['nickname']) && $where['nickname'], function (BaseQuery $query) use ($where) {
                return $query->where('B.nickname', 'like', '%' . $where['nickname'] . '%');
            })->when(isset($where['phone']) && $where['phone'], function (BaseQuery $query) use ($where) {
                return $query->where('B.phone', 'like', '%' . $where['phone'] . '%');
            })->when(isset($where['sex']) && $where['sex'] !== '', function (BaseQuery $query) use ($where) {
                return $query->where('B.sex', intval($where['sex']));
            })->when(isset($where['is_promoter']) && $where['is_promoter'] !== '', function (BaseQuery $query) use ($where) {
                return $query->where('B.is_promoter', $where['is_promoter']);
            })->when(isset($where['uid']) && $where['uid'] !== '', function (BaseQuery $query) use ($where) {
                return $query->where('A.uid', $where['uid']);
            })->when(isset($where['uids']), function (BaseQuery $query) use ($where) {
                return $query->whereIn('A.uid', $where['uids']);
            })->when(isset($where['user_time_type']) && $where['user_time_type'] !== '' && $where['user_time'] != '', function ($query) use ($where) {
                if ($where['user_time_type'] == 'visit') {
                    getModelTime($query, $where['user_time'], 'A.last_time');
                }
                if ($where['user_time_type'] == 'add_time') {
                    getModelTime($query, $where['user_time'], 'A.create_time');
                }
            })->when(isset($where['pay_count']) && $where['pay_count'] !== '', function ($query) use ($where) {
                if ($where['pay_count'] == -1) {
                    $query->where('A.pay_num', 0);
                } else {
                    $query->where('A.pay_num', '>', $where['pay_count']);
                }
            })->when(isset($where['label_id']) && $where['label_id'] !== '', function (BaseQuery $query) use ($where) {
                return $query->whereRaw('CONCAT(\',\',A.label_id,\',\') LIKE \'%,' . $where['label_id'] . ',%\'');
            })->when(isset($where['user_type']) && $where['user_type'] !== '', function (BaseQuery $query) use ($where) {
                return $query->where('B.user_type', $where['user_type']);
            })
            ;
    }

    /**
     * 获取指定商家ID下的用户ID列表，这些用户的支付数目在指定的范围内。
     *
     * 本函数通过查询用户支付表，筛选出特定商家ID且支付数目在最小值和最大值（可选）之间的用户ID。
     * 使用了Laravel的查询构建器来构造数据库查询，并通过分组和列操作来优化结果集的返回形式。
     *
     * @param string $mer_id 商家ID，用于查询指定商家的用户。
     * @param int $min 最小支付数目，用于筛选支付数目不小于该值的用户。
     * @param int|null $max 最大支付数目，可选参数，用于筛选支付数目不大于该值的用户。
     * @return array 返回符合条件的用户ID列表。
     */
    public function numUserIds($mer_id, $min, $max = null)
    {
        // 构建查询条件，首先指定商家ID和支付数目最小值
        return UserMerchant::getDB()->where('mer_id', $mer_id)->where('pay_num', '>=', $min)->when(!is_null($max), function ($query) use ($max) {
            // 如果指定了最大支付数目，则添加该条件进行筛选
            $query->where('pay_num', '<=', $max);
        })->group('uid')->column('uid');
    }


    /**
     * 获取指定商家ID和价格范围内的用户ID列表
     *
     * 此方法用于查询与特定商家相关联的，并且交易价格在指定范围内的用户的唯一ID列表。
     * 查询条件包括商家ID和支付价格，支付价格支持设定最小值和最大值。
     * 如果只指定了最小值而没有最大值，则查询大于等于最小值的所有记录。
     * 如果同时指定了最小值和最大值，则查询大于等于最小值且小于等于最大值的所有记录。
     *
     * @param int $mer_id 商家ID，用于限定查询的商家范围
     * @param int $min 最小支付价格，用于限定查询的最低价格范围
     * @param int|null $max 最大支付价格，可选参数，用于限定查询的最高价格范围
     * @return array 返回符合条件的用户ID列表
     */
    public function priceUserIds($mer_id, $min, $max = null)
    {
        // 使用UserMerchant类的数据库连接，并构造查询条件
        return UserMerchant::getDB()->where('mer_id', $mer_id)->where('pay_price', '>=', $min)->when(!is_null($max), function ($query) use ($max, $min) {
            // 如果指定了最大值，则进一步限定支付价格小于等于最大值
            $query->where('pay_price', $min == $max ? '<=' : '<', $max);
        })->group('uid')->column('uid');
    }

}
