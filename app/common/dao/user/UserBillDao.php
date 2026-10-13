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
use app\common\model\user\UserBill;

/**
 * Class UserBillDao
 * @package app\common\dao\user
 * @author xaboy
 * @day 2020/6/22
 */
class UserBillDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return UserBill::class;
    }

    /**
     * 更新用户账单信息
     *
     * 本函数用于根据指定条件更新用户账单的数据。通过传入更新条件和数据，精确更新数据库中的一条账单记录。
     * 这是一个重要的数据维护功能，确保了用户账单数据的准确性和实时性。
     *
     * @param array $where 更新条件数组，用于指定需要更新的账单记录。数组的键值对形式，键为字段名，值为字段值。
     * @param array $data 更新的数据数组，用于指定更新后的值。数组的键值对形式，键为字段名，值为新的字段值。
     * @return int 返回影响的行数，如果更新成功，返回1；如果未找到符合条件的记录或者更新失败，则返回0。
     */
    public function updateBill(array $where, $data)
    {
        // 获取数据库操作对象
        $db = UserBill::getDB();
        // 设置更新条件
        $db->where($where);
        // 限制只更新一条记录
        $db->limit(1);
        // 执行更新操作，并返回影响的行数
        return $db->update($data);
    }

    /**
     * 获取超时未处理的经纪费账单
     *
     * 本函数用于查询指定时间之前，状态为未处理的经纪费账单。这些账单可以是订单一或订单二类型的经纪费。
     * 主要用于统计或管理需要跟进的经纪费账单情况。
     *
     * @param int $time 查询的时间点，通常为时间戳形式，表示查询小于等于该时间的记录。
     * @return Collection|array|\think\Collection|\think\db\BaseQuery[]
     */
    public function getTimeoutBrokerageBill($time)
    {
        // 通过UserBill的getDB方法获取数据库实例，并链式调用where方法设置查询条件
        // 本行注释解释了为何使用where('create_time', '<=', $time)和where('category', 'brokerage')
        // 这两个条件用于限定查询的时间范围和账单类型
        return UserBill::getDB()->where('create_time', '<=', $time)->where('category', 'brokerage')
            // 使用whereIn方法限定查询的账单类型为'order_one'或'order_two'
            // 这一行注释解释了为何使用whereIn('type', ['order_one', 'order_two'])
            ->whereIn('type', ['order_one', 'order_two'])->with('user')->where('status', 0)->select();
    }

    /**
     * 获取过期积分账单
     *
     * 本函数用于查询指定时间之前，类型为锁定积分的账单记录。
     * 这些账单处于未处理状态（即状态为0），并关联了相应的用户信息。
     * 主要用于处理过期积分的统计和管理。
     *
     * @param int $time 以时间戳形式表示的截止时间
     * @return array|\think\Collection|\think\db\BaseQuery[]
     */
    public function getTimeoutIntegralBill($time)
    {
        // 通过UserBill的getDB方法获取数据库实例，并链式调用where方法设置查询条件
        // 包括创建时间小于等于指定时间、分类为积分、类型为锁定、状态为0
        // 最后使用with方法加载关联的用户信息，并通过select方法执行查询
        return UserBill::getDB()->where('create_time', '<=', $time)->where('category', 'integral')
            ->where('type', 'lock')->with('user')->where('status', 0)->select();
    }

    /**
     * 获取超时未处理的商家结算账单
     *
     * 本函数用于查询指定时间之前，且状态为未处理的商家结算账单。
     * 这些账单可能是由于订单支付超时或其他原因尚未进行结算。
     * 通过筛选创建时间、类别、类型和状态，精确获取目标账单数据。
     *
     * @param int $time 查询的时间点，通常为时间戳形式。
     * @return array 返回符合条件的商家结算账单数据集合。
     */
    public function getTimeoutMerchantMoneyBill($time)
    {
        // 使用UserBill类的getDB方法获取数据库实例，并链式调用where方法设置查询条件。
        // 查询条件包括：创建时间不晚于指定时间、类别为mer_computed_money、类型为order、状态为0（未处理）。
        // 最后调用select方法执行查询并返回结果。
        return UserBill::getDB()
            ->where('create_time', '<=', $time)
            ->where('category', 'mer_computed_money')
            ->where('type', 'order')
            ->where('status', 0)->select();
    }


    /**
     * 计算商家退款金额
     *
     * 本函数用于查询指定订单ID和商家ID下的特定类型退款金额。
     * 它通过查询用户账单表，筛选出符合条件的退款记录，并计算它们的总数。
     * 这样可以方便地获取商家的退款总额，以便进行财务结算。
     *
     * @param string $order_id 订单ID，用于关联退款记录和订单。
     * @param string $type 退款类型，用于区分不同的退款情况。
     * @param string $mer_id 商家ID，用于限定查询商家的退款记录。
     * @return float 返回符合条件的退款金额总和。
     */
    public function refundMerchantMoney($order_id, $type, $mer_id)
    {
        // 使用where子句筛选出特定订单ID、商家ID、类别为'mer_refund_money'且类型为参数$type的记录，然后计算number列的总和。
        return UserBill::getDB()->where('link_id', $order_id)->where('mer_id', $mer_id)
            ->where('category', 'mer_refund_money')->where('type', $type)->sum('number');
    }


    /**
     * 计算商家锁定的资金总额。
     *
     * 本函数用于根据指定的商家ID，计算该商家目前被锁定的资金总额。
     * 锁定的资金包括：待处理的锁定资金、退款资金和订单计算资金。
     *
     * @param string|null $merId 商家ID，如果为null，则计算所有商家的锁定资金总额。
     * @return float 返回商家锁定的资金总额。
     */
    public function merchantLickMoney($merId = null)
    {
        // 查询待处理的商家锁定资金记录，根据商家ID进行过滤。
        $lst = UserBill::getDB()
            ->where('category', 'mer_lock_money')
            ->when($merId, function ($query, $val) {
            $query->where('mer_id', $val);
        })->where('status', 0)->select()->toArray();

        // 初始化锁定资金总额为0。
        $lockMoney = 0;
        // 如果有锁定资金记录，计算已退款金额并从中扣除。
        if (count($lst)) {
            $lockMoney = -1 * UserBill::getDB()->whereIn('link_id', array_column($lst, 'link_id'))
                    ->where('category', 'mer_refund_money')->sum('number');
        }

        // 累加所有的锁定资金到$lockMoney。
        foreach ($lst as $bill) {
            $lockMoney = bcadd($lockMoney, $bill['number'], 2);
        }

        // 加上订单计算产生的锁定资金。
        $lockMoney = bcadd($lockMoney, UserBill::getDB()
            ->where('category', 'mer_computed_money')->when($merId, function ($query, $val) {
                $query->where('mer_id', $val);
            })->where('status', 0)->where('type', 'order')->sum('number'), 2);

        // 返回计算得到的商家锁定资金总额。
        return $lockMoney;
    }

    /**
     * 锁定经纪佣金
     * 该方法用于计算并返回指定用户ID的待处理（未结算）订单所产生的经纪佣金总和。
     * 这里的“锁定”指的是，通过计算这些未结算订单的佣金总额，为后续的佣金结算提供一个预估值。
     *
     * @param int $uid 用户ID，指定哪个用户的佣金需要被锁定计算。
     * @return float 返回计算得到的待处理佣金总和，以浮点数形式表示。
     */
    public function lockBrokerage($uid)
    {
        // 查询用户未结算的订单佣金记录，包括一级和二级订单产生的佣金。
        $lst = UserBill::getDB()->where('category', 'brokerage')
            ->whereIn('type', ['order_one', 'order_two'])->where('uid', $uid)->where('status', 0)->field('link_id,number')->select()->toArray();

        // 初始化退款金额为0，用于后续计算已退款的佣金。
        $refundPrice = 0;
        // 如果存在未结算的佣金记录，则计算已退款的佣金总额。
        if (count($lst)) {
            // 根据之前的佣金记录，查询相同订单链接ID下的退款佣金记录，并计算其总和。
            $refundPrice = -1 * UserBill::getDB()->whereIn('link_id', array_column($lst, 'link_id'))->where('uid', $uid)
                    ->where('category', 'brokerage')->whereIn('type', ['refund_two', 'refund_one'])->sum('number');
        }

        // 遍历未结算佣金列表，累加所有未结算的佣金金额。
        foreach ($lst as $bill) {
            $refundPrice = bcadd($refundPrice, $bill['number'], 2);
        }

        // 返回计算得到的待处理佣金总和。
        return $refundPrice;
    }

    /**
     * 锁定积分的逻辑处理。
     * 该方法用于计算指定用户由于订单或其他操作而被锁定的积分总量。
     * 如果提供了订单ID，则只考虑与该订单相关的锁定积分；
     * 如果提供了用户ID，则返回该用户所有被锁定的积分，无论订单ID是否提供。
     *
     * @param integer $uid 用户ID。可选参数，用于限定查询的用户。
     * @param integer $order_id 订单ID。可选参数，用于限定查询与特定订单相关的锁定积分。
     * @return integer 返回锁定的积分总量。如果没有任何积分被锁定，返回0。
     */
    public function lockIntegral($uid = null, $order_id = null)
    {
        // 查询已经被锁定的积分记录，这些记录可能是由于不同的订单或其他操作导致的。
        $lst = UserBill::getDB()->where('category', 'integral')
            ->where('type', 'lock')->when($order_id, function ($query, $order_id) {
                // 如果提供了订单ID，则查询与该订单关联的锁定积分记录。
                $query->where('link_id', $order_id);
            })->when($uid, function ($query, $uid) {
                // 如果提供了用户ID，则查询该用户的所有锁定积分记录。
                $query->where('uid', $uid);
            })->where('status', 0)->field('link_id,number')->select()->toArray();

        // 初始化锁定的积分总量为0。
        $lockIntegral = 0;
        // 如果存在锁定的积分记录，则计算这些记录中的积分总量。
        if (count($lst)) {
            // 计算所有被退款锁定（即可能由于订单退款导致的积分锁定）的积分总量。
            $lockIntegral = -1 * UserBill::getDB()->whereIn('link_id', array_column($lst, 'link_id'))->where('uid', $uid)
                    ->where('category', 'integral')->where('type', 'refund_lock')->sum('number');
        }

        // 遍历所有锁定积分记录，累加它们的数值到$lockIntegral中。
        foreach ($lst as $bill) {
            $lockIntegral = bcadd($lockIntegral, $bill['number'], 0);
        }

        // 返回锁定的积分总量。
        return $lockIntegral;
    }


    /**
     * 计算用户的积分扣除总量
     *
     * 本函数用于查询指定用户的所有积分扣除记录，并计算其积分扣除的总量。
     * 这里的积分扣除包括了正常扣除、退款锁定、超时扣除以及系统扣除等情况。
     *
     * @param integer $uid 用户ID
     * @return integer 用户的积分扣除总量
     */
    public function deductionIntegral($uid)
    {
        // 使用UserBill的数据库实例，查询满足条件的积分扣除记录，并计算积分数量的总和
        return UserBill::getDB()->where('uid', $uid)
            ->where('category', 'integral')
            ->where('pm', 0)
            ->whereIn('type', ['deduction', 'refund_lock', 'timeout', 'sys_dec'])
            ->where('status', 1)
            ->sum('number');
    }


    /**
     * 计算用户的积分收益总和
     *
     * 本函数通过查询用户积分账单，统计指定用户的所有积分收益额。
     * 积分收益来自符合条件的账单记录，这些记录代表了用户的积极积分变动，
     * 不包括退款、取消、退款锁定等负向操作。
     *
     * @param int $uid 用户ID
     * @return int 用户的积分收益总和
     */
    public function totalGainIntegral($uid)
    {
        // 根据用户ID、账单类型、积分变动方向、操作类型和状态，查询用户的积分收益账单
        return UserBill::getDB()->where('uid', $uid)
            ->where('category', 'integral')
            ->where('pm', 1) // 仅选择积分增加的记录
            ->whereNotIn('type', ['refund', 'cancel', 'refund_lock']) // 排除退款、取消、退款锁定等类型的操作
            ->where('status', 1) // 选择有效的账单记录
            ->sum('number'); // 计算符合条件的记录的积分总和
    }

    /**
     * 计算用户的总佣金
     *
     * 该方法通过查询用户的佣金收入和退款，来计算用户净佣金总额。
     * 具体来说，它首先汇总了所有订单产生的佣金（包括一级和二级订单），然后从中减去所有退款产生的佣金损失（包括一级和二级退款）。
     * 这样做的目的是为了得到用户实际获得的佣金总额，以精确反映用户的收入情况。
     *
     * @param int $uid 用户ID
     * @return string 返回计算后的总佣金，以字符串形式保持高精度
     */
    public function totalBrokerage($uid)
    {
        // 计算用户的佣金收入，包括一级和二级订单的佣金
        $income = UserBill::getDB()->where('category', 'brokerage')
            ->whereIn('type', ['order_one', 'order_two'])->where('uid', $uid)->sum('number');

        // 计算用户的佣金退款，包括一级和二级退款
        $refund = UserBill::getDB()->where('uid', $uid)
            ->where('category', 'brokerage')->whereIn('type', ['refund_two', 'refund_one'])->sum('number');

        // 返回用户的净佣金总额，保留两位小数
        return bcsub($income, $refund, 2);
    }

    /**
     * 计算指定用户昨日的佣金总额
     *
     * 本函数通过查询用户佣金账单，筛选出昨日的订单一和订单二的佣金记录，
     * 并计算这些记录的佣金总额。
     *
     * @param int $uid 用户ID
     * @return float 昨日佣金总额
     */
    public function yesterdayBrokerage($uid)
    {
        // 使用UserBill的数据库实例，并构造查询条件：分类为佣金，类型为订单一或订单二，用户ID为$uid
        // 然后获取昨日的时间范围
        // 最后计算符合条件的记录的number列（佣金金额）的总和
        return getModelTime(UserBill::getDB()->where('category', 'brokerage')
            ->whereIn('type', ['order_one', 'order_two'])->where('uid', $uid), 'yesterday')->sum('number');
    }

    /**
     * 根据条件搜索用户账单信息。
     *
     * 该方法提供了丰富的查询条件，能够根据传入的数组参数灵活地查询用户账单数据。
     * 主要支持的查询条件包括：现在金额(now_money)、用户ID(uid)、支付方式(pm)、类别(category)、
     * 状态(status)、日期(date)、具体日期(day)、月份(month)、类型(type)、商户ID(mer_id)和关联ID(link_id)。
     *
     * @param array $where 查询条件数组，包含各种可能的查询条件。
     * @return \think\db\BaseQuery|\think\db\Query
     */
    public function search(array $where)
    {
        // 获取数据库操作对象
        return UserBill::getDB()
            // 根据(now_money)条件筛选类别和类型
            ->when(isset($where['now_money']) && in_array($where['now_money'], [0, 1, 2]), function ($query) use ($where) {
                // 现在金额为0时的查询条件
                if ($where['now_money'] == 0) {
                    $query->where('category', 'now_money')
                        ->whereIn('type', ['pay_product', 'recharge', 'sys_inc_money', 'sys_dec_money', 'brokerage', 'presell', 'refund','extract']);
                // 现在金额为1时的查询条件
                } else if ($where['now_money'] == 1) {
                    $query->where('category', 'now_money')
                        ->whereIn('type', ['pay_product', 'sys_dec_money', 'presell']);
                // 现在金额为2时的查询条件
                } else if ($where['now_money'] == 2) {
                    $query->where('category', 'now_money')
                        ->whereIn('type', ['recharge', 'sys_inc_money', 'brokerage', 'refund','extract']);
                }
            })
            // 根据用户ID(uid)和商户ID(mer_id)查询
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('uid', $where['uid'])->where('mer_id', 0);
            })
            // 根据支付方式(pm)查询
            ->when(isset($where['pm']) && $where['pm'] !== '', function ($query) use ($where) {
                $query->where('pm', $where['pm']);
            })
            // 根据类别(category)查询
            ->when(isset($where['category']) && $where['category'] !== '', function ($query) use ($where) {
                $query->where('category', $where['category']);
            })
            // 根据状态(status)查询
            ->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
                $query->where('status', $where['status']);
            })
            // 根据日期(date)查询
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'create_time');
            })
            // 根据具体日期(day)查询
            ->when(isset($where['day']) && $where['day'] !== '', function ($query) use ($where) {
                $query->whereDay('create_time', $where['day']);
            })
            // 根据月份(month)查询
            ->when(isset($where['month']) && $where['month'] !== '', function ($query) use ($where) {
                $query->whereMonth('create_time', $where['month']);
            })
            // 根据类型(type)查询，支持多级类别查询
            ->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
                $data = explode('/', $where['type'], 2);
                if (count($data) > 1) {
                    $query->where('category', $data[0])->where('type', $data[1]);
                } else {
                    $query->where('type', $where['type']);
                }
            })
            // 根据商户ID(mer_id)查询
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('mer_id', $where['mer_id']);
            })
            // 根据关联ID(link_id)查询
            ->when(isset($where['link_id']) && $where['link_id'] !== '', function ($query) use ($where) {
                $query->where('link_id', $where['link_id']);
            });
    }

    /**
     * 计算指定用户当前资金的总增加额
     *
     * 本函数用于查询数据库中指定用户的当前资金项（now_money）的增加总额。
     * 通过搜索满足条件的记录，其中条件包括用户ID（uid）和资金类型为2的记录，
     * 然后对这些记录的“number”字段进行求和操作，得到该用户的当前资金增加总额。
     *
     * @param int $uid 用户ID，用于指定要查询的用户。
     * @return float 返回查询结果，即指定用户当前资金的增加总额。
     */
    public function userNowMoneyIncTotal($uid)
    {
        // 根据用户ID和资金类型为2的条件查询，并对符合条件的记录的“number”字段进行求和
        return $this->search(['uid' => $uid, 'now_money' => 2])->sum('number');
    }


    /**
     * 根据条件搜索用户账单并联表查询
     *
     * 本函数用于根据提供的条件搜索用户的账单记录，并进行左连接操作以包含用户信息。
     * 支持的搜索条件包括：mer_id（商户ID）、type（账单类型）、date（日期范围）、keyword（关键词搜索）、category（账单分类）、uid（用户ID）。
     *
     * @param array $where 搜索条件数组，包含各种可能的搜索参数。
     * @return \think\Collection 返回搜索结果的集合。
     */
    public function searchJoin(array $where)
    {
        // 使用UserBill类的getDB方法获取数据库对象，并设置表别名为a
        return UserBill::getDB()->alias('a')
            // 左连接用户表b，根据a表的uid和b表的uid关联
            ->leftJoin('User b', 'a.uid = b.uid')
            // 指定查询的字段
            ->field('a.bill_id,a.pm,a.title,a.number,a.balance,a.mark,a.create_time,a.status,b.nickname,a.uid,a.category')
            // 当搜索条件中包含mer_id时，添加对应条件到查询
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                $query->where('a.mer_id', $where['mer_id']);
            })
            // 当搜索条件中包含type时，根据type的值进行分类查询
            ->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
                $data = explode('/', $where['type'], 2);
                if (count($data) > 1) {
                    // 如果type是分类/类型的格式，按分类和类型查询
                    $query->where('a.category', $data[0])->where('type', $data[1]);
                } else {
                    // 否则，按type查询
                    $query->where('a.type', $where['type']);
                }
            })
            // 当搜索条件中包含date时，添加日期范围条件到查询
            ->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
                getModelTime($query, $where['date'], 'a.create_time');
            })
            // 当搜索条件中包含keyword时，添加关键词搜索条件到查询
            ->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
                $query->whereLike('a.uid|b.nickname|a.title', "%{$where['keyword']}%");
            })
            ->when(isset($where['nickname']) && $where['nickname'] !== '', function ($query) use ($where) {
                $query->whereLike('b.nickname', "%{$where['nickname']}%");
            })
            ->when(isset($where['phone']) && $where['phone'] !== '', function ($query) use ($where) {
                $query->whereLike('b.phone', "%{$where['phone']}%");
            })
            ->when(isset($where['real_name']) && $where['real_name'] !== '', function ($query) use ($where) {
                $query->whereLike('b.real_name', "%{$where['real_name']}%");
            })
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('b.uid', $where['uid']);
            })
            // 当搜索条件中包含category时，添加分类条件到查询
            ->when(isset($where['category']) && $where['category'] !== '', function ($query) use ($where) {
                if (is_array($where['category'])){
                    // 如果category是数组，使用whereIn查询
                    $query->whereIn('a.category', $where['category']);
                } else {
                    // 否则，直接按category查询
                    $query->where('a.category', $where['category']);
                }
            })
            // 当搜索条件中包含uid时，添加用户ID条件到查询
            ->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
                $query->where('a.uid', $where['uid']);
            })
            // 排除分类为sys_brokerage的账单
            ->where('category', '<>', 'sys_brokerage');
    }

    /**
     * 计算并返回指定订单和用户ID的退款佣金总额。
     *
     * 本函数通过查询用户账单表，找出与指定订单ID和用户ID相关联的退款佣金记录，
     * 并计算这些记录的佣金总额。这有助于在处理退款时准确地调整用户的佣金余额。
     *
     * @param string $order_id 订单ID，用于查询与订单相关的佣金退款记录。
     * @param string $uid 用户ID，用于查询特定用户的佣金退款记录。
     * @return float 返回符合条件的佣金退款总额。
     */
    public function refundBrokerage($order_id, $uid)
    {
        // 使用UserBill的数据库实例，查询满足条件的佣金退款记录，并计算总数。
        return UserBill::getDB()->where('link_id', $order_id)->where('uid', $uid)
            ->where('category', 'brokerage')->whereIn('type', ['refund_two', 'refund_one'])->sum('number');
    }


    /**
     * 计算指定订单和用户退款的积分总数。
     *
     * 本函数通过查询用户账单表，找出与指定订单关联的、属于指定用户的、
     * 类别为积分、类型为退款锁定的记录，然后计算这些记录的积分总数。
     * 这样做的目的是为了在退款操作中准确地回滚用户的积分，确保用户积分账户的准确性。
     *
     * @param string $order_id 订单ID，用于关联订单和用户账单。
     * @param int $uid 用户ID，用于查询指定用户的账单记录。
     * @return int 返回符合条件的记录的积分总数。
     */
    public function refundIntegral($order_id, $uid)
    {
        // 使用UserBill的数据库工具方法进行查询，通过where子句指定订单ID、用户ID、类别和类型，
        // 最后使用sum方法计算符合条件的记录的number列的总和，即积分总数。
        return UserBill::getDB()->where('link_id', $order_id)->where('uid', $uid)
            ->where('category', 'integral')->where('type', 'refund_lock')->sum('number');
    }


    /**
     * 校验用户在指定时间范围内积分的锁定状态是否有效。
     * 该方法通过计算用户在指定时间内锁定的积分，以及相同时间内发生的积分返还和系统扣减情况，来确定用户的实际可用积分。
     *
     * @param integer $uid 用户ID。
     * @param string $start 查询的开始时间。
     * @param string $end 查询的结束时间。
     * @return integer 返回用户在指定时间内实际可用的积分值。
     */
    public function validIntegral($uid, $start, $end)
    {
        // 查询指定时间内被锁定的积分记录。
        $lst = UserBill::getDB()->where('category', 'integral')
            ->where('type', 'lock')->whereBetween('create_time', [$start, $end])->where('uid', $uid)->where('status', 1)->field('link_id,number')->select()->toArray();

        // 初始化积分计算变量。
        $integral = 0;
        // 如果有锁定的积分记录，则计算已返还的锁定积分。
        if (count($lst)) {
            $integral = -1 * UserBill::getDB()->whereIn('link_id', array_column($lst, 'link_id'))->where('uid', $uid)
                    ->where('category', 'integral')->where('type', 'refund_lock')->sum('number');
        }
        // 累加计算所有锁定的积分。
        foreach ($lst as $bill) {
            $integral = bcadd($integral, $bill['number'], 0);
        }

        // 计算指定时间内用户获得的积分（不包括锁定和系统扣减）。
        $integral2 = UserBill::getDB()->where('uid', $uid)->whereBetween('create_time', [$start, $end])
            ->where('category', 'integral')->where('pm', 1)->whereNotIn('type', ['lock', 'refund'])->sum('number');

        // 计算指定时间内用户因系统原因被扣减的积分。
        $integral3 = UserBill::getDB()->where('uid', $uid)->whereBetween('create_time', [$start, $end])
            ->where('category', 'integral')->where('type', 'sys_dec')->sum('number');

        // 返回用户在指定时间内实际可用的积分，确保积分值不为负。
        return (int)max(bcsub(bcadd($integral, $integral2, 0), $integral3, 0), 0);
    }



}
