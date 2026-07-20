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
use app\common\model\user\User;
use app\common\repositories\user\UserInfoRepository;
use think\Collection;
use think\db\BaseQuery;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbException;
use think\db\exception\ModelNotFoundException;
use think\facade\Db;
use think\Model;

/**
 * Class UserDao
 * @package app\common\dao\user
 * @author xaboy
 * @day 2020-04-28
 */
class UserDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return User::class;
    }

    /**
     * 默认密码
     * @return string
     * @author xaboy
     * @day 2020/6/22
     */
    public function defaultPwd()
    {
        return substr(md5(time() . random_int(10, 99)), 0, 8);
    }

    /**
     * 用户搜索
     * @param array $where
     * @return BaseQuery
     * @author xaboy
     * @day 2020-05-07
     */
    public function search(array $where, $viewSearch = [])
    {
        if (isset($where['province']) && $where['province'] !== '') {
            $query = User::hasWhere('wechat', function ($query) use ($where) {
                $query->where('province', $where['province']);
                if ($where['city'] !== '') $query->where('city', $where['city']);
            });
        } else {
            $query = User::getDB()->alias('User');
        }

        //扩展信息查询
        if (isset($where['fields_value']) && $where['fields_value'] !== '' && isset($where['fields_type']) && $where['fields_type'] !== '') {
            if (app()->make(UserInfoRepository::class)->getFieldsIsItDefault($where['fields_type'])) {
                $query->whereLike($where['fields_type'], "%{$where['fields_value']}%");
            } else {
                $query->hasWhere('fields', function ($query) use ($where) {
                    $query->whereLike($where['fields_type'], "%{$where['fields_value']}%");
                });
            }
        }

        $query->whereNull('User.cancel_time')
            ->when(isset($where['keyword']) && $where['keyword'], function (BaseQuery $query) use ($where) {
            return $query->where('User.uid|User.real_name|User.nickname|User.phone', 'like', '%' . $where['keyword'] . '%');
        })->when(isset($where['user_type']) && $where['user_type'] !== '', function (BaseQuery $query) use ($where) {
            return $query->where('User.user_type', $where['user_type']);
        })->when(isset($where['uid']) && $where['uid'] !== '', function (BaseQuery $query) use ($where) {
            return $query->where('User.uid', $where['uid']);
        })->when(isset($where['status']) && $where['status'] !== '', function (BaseQuery $query) use ($where) {
            return $query->where('User.status', intval($where['status']));
        })->when(isset($where['group_id']) && $where['group_id'], function (BaseQuery $query) use ($where) {
            return $query->where('User.group_id', intval($where['group_id']));
        })->when(isset($where['brokerage_level']) && $where['brokerage_level'], function (BaseQuery $query) use ($where) {
            return $query->where('User.brokerage_level', intval($where['brokerage_level']));
        })->when(isset($where['label_id']) && $where['label_id'] !== '', function (BaseQuery $query) use ($where) {
            return $query->whereRaw('CONCAT(\',\',User.label_id,\',\') LIKE \'%,' . $where['label_id'] . ',%\'');
        })->when(isset($where['sex']) && $where['sex'] !== '', function (BaseQuery $query) use ($where) {
            return $query->where('User.sex', intval($where['sex']));
        })->when(isset($where['is_promoter']) && $where['is_promoter'] !== '', function (BaseQuery $query) use ($where) {
            if (is_array($where['is_promoter'])) {
                $query->whereIn('User.is_promoter', $where['is_promoter']);
            } else {
                $query->where('User.is_promoter', $where['is_promoter']);
            }
        })->when(isset($where['phone']) && $where['phone'] !== '', function (BaseQuery $query) use ($where) {
            return $query->whereLike('User.phone', "%{$where['phone']}%");
        })->when(isset($where['nickname']) && $where['nickname'] !== '', function (BaseQuery $query) use ($where) {
            return $query->whereLike('User.nickname', "%{$where['nickname']}%");
        })->when(isset($where['real_name']) && $where['real_name'] !== '', function (BaseQuery $query) use ($where) {
            return $query->whereLike('User.real_name', "%{$where['real_name']}%");
        })->when(isset($where['spread_time']) && $where['spread_time'] !== '', function (BaseQuery $query) use ($where) {
            getModelTime($query, $where['spread_time'], 'User.spread_time');
        })->when(isset($where['birthday']) && $where['birthday'] !== '', function (BaseQuery $query) use ($where) {
            getModelTime($query, $where['birthday'], 'User.birthday');
        })->when(isset($where['date']) && $where['date'] !== '', function (BaseQuery $query) use ($where) {
            getModelTime($query, $where['date'], 'User.create_time');
        })->when(isset($where['mom_date']) && $where['mom_date'] !== '', function (BaseQuery $query) use ($where) {
            momTime($query, $where['mom_date'], 'User.create_time');
        })->when(isset($where['promoter_date']) && $where['promoter_date'] !== '', function (BaseQuery $query) use ($where) {
            getModelTime($query, $where['promoter_date'], 'User.promoter_time');
        })->when(isset($where['spread_uid']) && $where['spread_uid'] !== '', function (BaseQuery $query) use ($where) {
            return $query->where('User.spread_uid', intval($where['spread_uid']));
        })->when(isset($where['spread_uids']), function (BaseQuery $query) use ($where) {
            return $query->whereIn('User.spread_uid', $where['spread_uids']);
        })->when(isset($where['uids']), function (BaseQuery $query) use ($where) {
            return $query->whereIn('User.uid', $where['uids']);
        })->when(isset($where['pay_count']) && $where['pay_count'] !== '', function ($query) use ($where) {
            if ($where['pay_count'] == -1) {
                $query->where('User.pay_count', 0);
            } else {
                $query->where('User.pay_count', '>', $where['pay_count']);
            }
        })->when(isset($where['user_time_type']) && $where['user_time_type'] !== '' && $where['user_time'] != '', function ($query) use ($where) {
            if ($where['user_time_type'] == 'visit') {
                getModelTime($query, $where['user_time'], 'User.last_time');
            }
            if ($where['user_time_type'] == 'add_time') {
                getModelTime($query, $where['user_time'], 'User.create_time');
            }
        })->when(isset($where['sort']) && in_array($where['sort'], ['pay_count ASC', 'pay_count DESC', 'pay_price DESC', 'pay_price ASC', 'spread_count ASC', 'spread_count DESC']), function (BaseQuery $query) use ($where) {
            $query->order('User.' . $where['sort']);
        }, function ($query) {
            $query->order('User.uid DESC');
        })->when(isset($where['is_svip']) && $where['is_svip'] !== '', function (BaseQuery $query) use ($where) {
            if ($where['is_svip']) {
                $query->where('User.is_svip','>',0);
            } else {
                $query->where('User.is_svip','<=',0);
            }
        })->when(isset($where['svip_type']) && $where['svip_type'] !== '', function (BaseQuery $query) use ($where) {
            $query->where('User.is_svip',$where['svip_type']);
        })->when(isset($where['member_level']) && $where['member_level'] !== '', function (BaseQuery $query) use ($where) {
            $query->where('User.member_level',$where['member_level']);
        })->when(isset($where['promoter_switch']), function (BaseQuery $query) use ($where) {
            return $query->whereIn('User.promoter_switch', $where['promoter_switch']);
        });
        if (!empty($viewSearch)) {
            $query = $this->viewSearch($viewSearch,$query,'User');
        }
        return $query;
    }

    /**
     * 根据关键词搜索商家用户
     *
     * 本函数旨在通过关键词搜索商家用户数据库，找出满足条件的用户。搜索依据是用户的昵称，
     * 仅返回未被取消且状态为激活的用户。
     *
     * @param string $keyword 搜索关键词。函数将根据此关键词对商家用户的昵称进行模糊搜索。
     * @return \think\db\Query 返回一个数据库查询对象，该对象包含了根据关键词搜索出的商家用户。
     *         查询结果尚未执行，可以通过调用对象的方法进一步筛选或获取数据。
     */
    public function searchMerUser($keyword)
    {
        // 使用静态方法获取数据库操作对象，并设置条件：昵称含有关键词、取消时间为空、状态为1
        return User::getDB()->whereLike('nickname', "%$keyword%")->whereNull('cancel_time')->where('status', 1);
    }

    /**
     * 批量更改用户组ID
     *
     * 本函数用于根据提供的用户ID数组，批量将这些用户所在的用户组ID更新为指定的新用户组ID。
     * 这是一个高效的操作，通过一次性更新符合条件的所有用户，避免了逐个更新用户的性能损耗。
     *
     * @param array $ids 用户ID数组，这些用户将被批量更改用户组ID
     * @param int $group_id 新的用户组ID，所有指定的用户将被更新为这个用户组ID
     * @return int 返回影响的行数，即被成功更新的用户数量
     */
    public function batchChangeGroupId(array $ids, int $group_id)
    {
        // 使用whereIn查询符合条件的所有用户，并更新他们的用户组ID为指定的新ID
        return User::getDB()->whereIn($this->getPk(), $ids)->update(compact('group_id'));
    }

    /**
     * 批量更改用户标签ID
     *
     * 本函数用于将一批用户的数据中的标签ID批量更新为新的标签ID。它通过接收一个用户ID数组和一个新的标签ID数组来实现。
     * 更新操作是通过查询数据库中与传入ID数组匹配的用户记录，并将这些记录的标签ID字段更新为新的标签ID组合。
     *
     * @param array $ids 用户ID数组，这些用户的标签ID将被更新
     * @param array $label_id 新的标签ID数组，将用于更新指定用户的标签ID
     * @return int 返回更新操作影响的行数，用于确认更新操作的成功程度
     */
    public function batchChangeLabelId(array $ids, array $label_id)
    {
        // 将标签ID数组转换为以逗号分隔的字符串，这是SQL查询中IN操作符的需要
        $label_id = implode(',', $label_id);

        // 更新用户表中与传入ID数组匹配的记录的标签ID字段，返回更新操作影响的行数
        return User::getDB()->whereIn($this->getPk(), $ids)->update(compact('label_id'));
    }

    /**
     * 根据微信用户ID获取用户信息
     *
     * 本函数通过查询数据库，根据传入的微信用户ID，返回对应用户的详细信息。
     * 这对于需要关联微信用户和网站用户信息的场景非常有用，比如在用户登录或注册时。
     *
     * @param int $id 微信用户ID，用于查询数据库中的对应用户信息。
     * @return object 返回查询结果，是一个包含用户信息的对象。如果未找到对应用户，则返回null。
     */
    public function wechatUserIdBytUser(int $id)
    {
        // 使用User类的getDB方法获取数据库连接，并构造查询语句，查询wechat_user_id为$id$的用户信息
        return User::getDB()->where('wechat_user_id', $id)->find();
    }

    /**
     * 根据微信用户ID获取平台用户ID
     *
     * 本函数旨在通过微信用户ID查询平台用户数据库，返回对应平台用户ID（uid）。
     * 这对于需要将微信用户与平台用户关联的操作是非常重要的。
     *
     * @param string $id 微信用户ID
     * @return string|false 平台用户ID（uid）如果找到，则返回uid；如果未找到，则返回false。
     */
    public function wechatUserIdByUid($id)
    {
        // 使用User模型的getDB方法获取数据库对象，并根据wechat_user_id查询uid值
        return User::getDB()->where('wechat_user_id', $id)->value('uid');
    }

    /**
     * 根据微信用户ID获取用户UID。
     *
     * 本函数旨在通过微信用户ID查询数据库，获取对应用户的UID。这在需要将微信用户信息与
     * 自有用户系统对接时非常有用。函数直接返回查询结果，如果没有找到对应微信用户ID的
     * 用户UID，则返回null。
     *
     * @param string $id 微信用户ID。
     * @return string|null 返回查询到的用户UID或者null（如果未找到匹配的用户）。
     */
    public function uidByWechatUserId($id)
    {
        // 使用User模型的getDB方法获取数据库连接，并根据uid查询wechat_user_id字段的值
        return User::getDB()->where('uid', $id)->value('wechat_user_id');
    }


    /**
     * 根据账户名获取用户信息
     *
     * 本函数旨在通过账户名从数据库中检索对应的用户信息。它使用了User类的getDB方法来获取数据库连接，
     * 并基于这个连接执行一个查询，查询条件为账户名等于传入的参数值。
     *
     * @param string $account 用户的账户名
     * @return object 返回查询结果，如果找不到匹配的用户，则返回空
     */
    public function accountByUser($account)
    {
        // 使用User类的getDB方法获取数据库连接，并基于此连接查询符合条件的用户信息
        return User::getDB()->where('account', $account)->find();
    }

    /**
     * 获取用户的下级用户ID列表
     *
     * 本函数通过查询数据库，找出所有传播UID为指定UID的用户的UID列，
     * 主要用于统计或管理上下级关系。
     *
     * @param int $uid 指定的用户ID，用于查询该用户的下级用户。
     * @return array 返回一个包含下级用户ID的数组。
     */
    public function getSubIds($uid)
    {
        // 使用User类的getDB方法获取数据库对象，并根据$uid查询符合条件的用户uid列
        return User::getDB()->where('spread_uid', $uid)->column('uid');
    }


    /**
     * 获取直接下线用户数量
     *
     * 本函数用于统计指定用户ID的直接下线用户数量。通过查询数据库中spread_uid字段等于指定uid的记录数来实现。
     * 这里的直接下线指的是直接被uid推广的用户，不包括间接推广的用户。
     *
     * @param int $uid 指定的用户ID，用于查询该用户的直接下线用户数量。
     * @return int 返回直接下线用户的数量。
     */
    public function getOneLevelCount($uid)
    {
        // 使用User类的getDB方法获取数据库对象，并通过where方法指定查询条件为spread_uid等于$uid，最后调用count方法统计记录数
        return User::getDB()->where('spread_uid', $uid)->count();
    }


    /**
     * 获取指定用户的二级下线数量
     *
     * 本函数通过查询数据库，统计指定用户$uid的二级下线总数。
     * 首先，获取指定用户的所有直接下线的ID，然后查询这些直接下线用户的下线用户数量，
     * 从而得到指定用户的二级下线总数。
     *
     * @param int $uid 用户ID，指定查询哪个用户的二级下线数量
     * @return int 返回二级下线的数量，如果没有二级下线则返回0
     */
    public function getTwoLevelCount($uid)
    {
        // 获取指定用户的所有直接下线的ID
        $ids = $this->getSubIds($uid);

        // 如果直接下线ID数量不为0，则查询二级下线总数，否则返回0
        return count($ids) ? User::getDB()->whereIn('spread_uid', $ids)->count() : 0;
    }


    /**
     * 获取所有子用户的ID
     *
     * 本函数用于查询并返回所有传播者UID对应的用户UID列表。
     * 主要用于需要批量获取子用户ID的场景，例如统计、分发等业务逻辑中。
     *
     * @param array $ids 传播者UID列表
     * @return array 所有子用户的UID列表
     */
    public function getSubAllIds(array $ids)
    {
        // 使用whereIn查询条件，查询所有传入传播者UID对应的用户UID
        // 并通过column方法只返回uid列，以数组形式组织结果
        return User::getDB()->whereIn('spread_uid', $ids)->column('uid');
    }


    /**
     * 根据用户ID数组获取用户信息
     *
     * 本函数通过调用User类的静态方法getDB，进而使用whereIn方法筛选出ID在给定数组中的用户，
     * 最后通过field方法指定返回的字段，并执行select查询。此方法适用于批量获取用户信息，
     * 特别是在需要根据一组ID进行查询的场景下，能有效提高查询效率。
     *
     * @param array $ids 用户ID数组，用于查询的条件
     * @param string $field 需要返回的字段，可以是单个字段名或多个字段名的数组，，默认为'*'，表示返回所有字段
     * @return array 返回符合条件的用户信息数组
     */
    public function users(array $ids, $field = '*')
    {
        // 执行查询，根据$ids获取用户信息，指定返回的字段为$field
        return User::getDB()->whereIn('uid', $ids)->field($field)->select();
    }

    /**
     * 获取指定日期内的新用户数量
     *
     * 此方法通过查询数据库来统计指定日期内新增用户的数量。
     * 它使用了Laravel的查询构建器来条件性地添加一个时间范围查询，
     * 仅当传入了$date参数时，才会添加这个查询条件。
     *
     * @param string|null $date 指定的日期，格式为Y-m-d。如果为null，则不添加日期条件。
     * @return int 指定日期内新用户的数量。
     */
    public function newUserNum($date)
    {
        // 使用User模型的数据库连接。
        return User::getDB()->where('cancel_time',null)->when($date, function ($query, $date) {
            // 当$date不为空时，添加创建时间的查询条件。
            getModelTime($query, $date, 'create_time');
        })->count();
    }


    /**
     * 获取用户的订单详情信息
     *
     * 本函数通过查询用户表和订单表的数据，获取指定用户($uid)的订单总额、订单数量以及相关的会员信息。
     * 查询条件限定为已支付的订单，并且只统计当天的订单数据。同时，如果存在预售订单，也会一并统计其支付金额。
     *
     * @param int $uid 用户ID
     * @return object|null 返回用户订单详情的对象，包含订单总额、订单数量等信息；如果未找到相关数据，则返回null。
     */
    public function userOrderDetail($uid)
    {
        // 构建查询语句，从用户表和订单表中获取所需数据
        $info = User::getDB()->alias('A')->with(['group','spread','memberIcon'])
            ->leftJoin('StoreOrder B', 'A.uid = B.uid and B.paid = 1 and B.pay_time between \'' . date('Y/m/d', strtotime('first day of')) . ' 00:00:00\' and \'' . date('Y/m/d H:i:s') . '\'')
            ->leftJoin('PresellOrder C', 'C.order_id = B.order_id and C.paid = 1')
            ->field('A.*, sum(B.pay_price + IFNULL(C.pay_price,0)) as total_pay_price, count(B.order_id) as total_pay_count,is_svip,svip_endtime,svip_save_money')
            ->where('A.uid', $uid)
            ->find()->append(['userLabel']);

        // 如果查询结果存在，则进行额外的信息处理
        if(!empty($info)){
            // 处理会员等级和团ID的空值问题
            $info->member_level = $info->member_level ?: '';
            $info->group_id = $info->group_id ?: '';
            // 删除用户敏感信息字段，这些信息不应该在订单详情中展示
            // 删除扩展信息默认字段，影响用户编辑
            unset($info['real_name']);
            unset($info['sex']);
            unset($info['birthday']);
            unset($info['addres']);
        }

        // 返回处理后的用户订单详情信息
        return $info;
    }

    /**
     * 根据指定日期分组统计用户数量
     *
     * 本函数用于查询在指定日期范围内每天新增的用户数量。
     * 通过数据库查询语句实现，根据用户创建时间进行分组，统计每天的新增用户数。
     * 如果指定了日期，则查询该日期范围内的数据；如果未指定日期，则查询所有数据。
     *
     * @param mixed $date 查询的日期范围。如果为NULL，则查询所有数据；否则，查询指定日期范围内的数据。
     * @return array 返回一个数组，其中每个元素包含日期和该日期新增的用户数量。
     */
    public function userNumGroup($date)
    {
        // 根据$date的值决定是否添加时间范围的查询条件
        return User::getDB()->when($date, function ($query, $date) {
            // 如果$date不为空，则添加查询条件，根据创建时间查询
            getModelTime($query, $date, 'create_time');
        })->field(Db::raw('from_unixtime(unix_timestamp(create_time),\'%m-%d\') as time, count(uid) as new'))
            ->group('time')->order('time ASC')->select();
    }

    /**
     * 根据用户ID数组获取用户的支付次数
     *
     * 此方法通过查询数据库，返回给定用户ID列表中每个用户的支付次数。
     * 它使用了whereIn查询语句来筛选指定ID的用户，并通过column方法以用户ID为键，支付次数为值返回结果。
     * 这样做的目的是为了高效地获取一批用户的支付次数，而不是逐个查询或获取整个用户对象。
     *
     * @param array $ids 用户ID数组
     * @return array 以用户ID为键，支付次数为值的数组
     */
    public function idsByPayCount(array $ids)
    {
        // 根据用户ID数组查询用户的支付次数，并以uid为键返回pay_count列
        return User::getDB()->whereIn('uid', $ids)->column('pay_count', 'uid');
    }


    /**
     * 计算在给定日期之前注册的用户数量。
     *
     * 此方法通过查询数据库中创建时间早于指定日期的用户记录数量，来统计在某个日期之前注册的用户数。
     * 主要用于数据分析和用户增长统计。
     *
     * @param string $date 以字符串格式指定的日期，应符合数据库中存储日期的格式。
     * @return int 返回在指定日期之前注册的用户数量。
     */
    public function beforeUserNum($date)
    {
        // 使用User模型的getDB方法获取数据库连接，并根据create_time字段小于指定日期的条件，统计用户数量。
        return User::getDB()->where('create_time', '<', $date)->count();
    }


    /**
     * 获取指定电话号码的用户列表
     *
     * 本函数用于查询数据库中与指定电话号码相关的用户信息。它返回一个包含用户ID、昵称、头像和用户类型的数组。
     * 这样做的目的是允许应用程序根据电话号码快速检索用户的详细资料，以便进行进一步的操作或展示。
     *
     * @param string $phone 用户的电话号码。这是查询的依据，用于精确匹配数据库中的记录。
     * @return array 返回一个包含用户ID、昵称、头像和用户类型的数组。如果找不到匹配的用户，则返回空数组。
     */
    public function selfUserList($phone)
    {
        // 使用User类的getDB方法获取数据库连接，并基于电话号码查询用户信息。
        // 通过where方法指定查询条件，field方法限制返回的字段，select方法执行查询并返回结果。
        return User::getDB()->where('phone', $phone)->field('uid,nickname,avatar,user_type')->select();
    }

    /**
     * 初始化用户每日传播限制
     *
     * 本函数用于更新用户数据库中指定用户的传播限制日期。它通过计算当前日期之后的指定天数，
     * 设置用户的传播限制时间为未来某一天的特定时间点。这有助于管理用户的传播活动，确保他们
     * 在指定的时间范围内进行传播行为。
     *
     * @param int $day 传播限制的生效天数。从当前日期开始，多少天后传播限制将生效。
     * @return int 返回更新操作影响的行数。这可以用来确认更新操作是否成功。
     */
    public function initSpreadLimitDay(int $day)
    {
        // 使用User类的getDB方法获取数据库连接，并构造更新语句。
        // 更新条件是spread_uid大于0，这意味着只更新具有有效传播标识符的用户。
        // 更新的字段是spread_limit，其值为当前时间向后推$day天后的日期和时间。
        return User::getDB()->where('spread_uid', '>', 0)->update(['spread_limit' => date('Y-m-d H:i:s', strtotime("+ $day day"))]);
    }

    /**
     * 清除用户的每日传播限制
     *
     * 本函数用于更新数据库中满足条件的用户记录，将他们的传播限制设置为null。
     * 这样做可以解除这些用户在当天的传播次数限制，允许他们继续进行传播操作。
     *
     * @return int 返回影响的行数，即被更新的用户记录数。
     */
    public function clearSpreadLimitDay()
    {
        // 使用User类的getDB方法获取数据库连接，并构造更新语句，更新满足条件的用户的spread_limit字段为null。
        return User::getDB()->where('spread_uid', '>', 0)->update(['spread_limit' => null]);
    }


    /**
     * 更新用户的传播限制日期
     *
     * 本函数用于根据指定的天数更新用户的传播限制日期。它首先更新所有传播用户ID大于0且传播限制日期为空的用户的传播限制日期为当前日期加上指定天数。
     * 然后，它更新所有传播用户ID大于0且传播限制日期不为空的用户的传播限制日期为当前传播限制日期加上指定天数。
     * 这样做的目的是确保所有用户的传播限制日期都被正确地根据业务需求进行更新。
     *
     * @param int $day 要添加的天数，用于更新传播限制日期。
     * @return int 返回影响的行数，表示成功更新的用户数量。
     */
    public function updateSpreadLimitDay(int $day)
    {
        // 更新所有传播用户ID大于0且传播限制日期为空的用户的传播限制日期为当前日期加上指定天数
        User::getDB()->where('spread_uid', '>', 0)->whereNull('spread_limit')->update(['spread_limit' => date('Y-m-d H:i:s', strtotime("+ $day day"))]);

        // 更新所有传播用户ID大于0且传播限制日期不为空的用户的传播限制日期为当前传播限制日期加上指定天数
        return User::getDB()->where('spread_uid', '>', 0)->whereNotNull('spread_limit')->update(['spread_limit' => Db::raw('TIMESTAMPADD(DAY, ' . $day . ', `spread_limit`)')]);
    }


    /**
     * 同步传播状态
     *
     * 本函数用于更新用户数据库中满足特定条件的记录的传播相关字段。
     * 它针对的是那些传播者ID大于0，传播限制日期不为空且小于等于当前日期的用户记录。
     * 对于这些记录，它将传播时间、传播者ID和传播限制日期字段重置为null。
     * 这个函数的存在是为了处理传播活动的结束或无效化，确保数据库的准确性和一致性。
     *
     * @return int 返回更新的记录数。这可以让调用者知道有多少条记录受到了影响。
     */
    public function syncSpreadStatus()
    {
        // 使用where子句构建查询条件，筛选出需要更新的记录。
        // 这里解释了为什么会有这些特定的查询条件，即为了找出那些传播活动已结束或无效的用户。
        return User::getDB()->where('spread_uid', '>', 0)->whereNotNull('spread_limit')->where('spread_limit', '<=', date('Y-m-d H:i:s'))->update(['spread_time' => null, 'spread_uid' => 0, 'spread_limit' => null]);
    }


    /**
     * 增加用户的传播次数
     *
     * 此方法用于更新指定用户的传播次数。它通过查询数据库找到对应用户，
     * 然后将该用户的传播次数增加1。这种方法适用于需要记录用户传播行为的场景，
     * 比如计算用户的分享、推荐或邀请次数。
     *
     * @param int $uid 用户ID
     *          传入用户的唯一标识符，用于在数据库中定位到该用户。
     */
    public function incSpreadCount($uid)
    {
        // 使用User类的静态方法getDB来获取数据库实例，并根据$uid更新用户的传播次数
        User::getDB()->where('uid', $uid)->update([
            // 使用数据库原生表达式来增加spread_count列的值，避免直接的字符串拼接
            'spread_count' => Db::raw('spread_count + 1')
        ]);
    }


    /**
     * 减少用户的传播数。
     *
     * 本函数用于更新指定用户的传播数，将其传播数减少1。传播数可能是用户分享、邀请他人等行为的统计，
     * 通过减少传播数可以反映用户行为的回溯或撤销。
     *
     * @param int $uid 用户ID。此参数用于指定哪个用户的传播数需要减少。
     */
    public function decSpreadCount($uid)
    {
        // 使用User类的getDB方法获取数据库实例，并通过where语句构建条件，更新指定用户的传播数
        User::getDB()->where('uid', $uid)->where('spread_count','>',0)->update([
            'spread_count' => Db::raw('spread_count - 1')
        ]);
    }
}
