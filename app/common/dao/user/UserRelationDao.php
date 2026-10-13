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
use app\common\model\user\UserRelation;
use app\common\model\user\UserRelation as model;

/**
 * Class UserVisitDao
 * @package app\common\dao\user
 * @author xaboy
 * @day 2020/5/27
 */
class UserRelationDao extends BaseDao
{

    /**
     * @return string
     * @author xaboy
     * @day 2020/5/27
     */
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 检查指定字段是否存在
     *
     * 该方法通过查询数据库来确定给定字段的值是否在指定的条件下存在。
     * 它支持通过可选参数来进一步限定查询的条件，如类型(type)和用户ID(uid)。
     * 主要用于API中对数据存在性的验证。
     *
     * @param string $field 要查询的字段名
     * @param mixed $value 字段对应的值
     * @param string|null $type 可选参数，限定查询的数据类型
     * @param int|null $uid 可选参数，限定查询的用户ID
     * @return bool 查询结果，存在返回true，不存在返回false
     */
    public function apiFieldExists($field, $value, $type = null, $uid = null)
    {
        // 调用getModel方法获取模型实例，并链式调用getDB方法获取数据库实例
        return $this->getModel()::getDB()
            // 当$uid不为空时，添加where条件查询指定用户ID的数据
            ->when($uid, function ($query) use ($uid) {
                $query->where('uid', $uid);
            })
            // 当$type不为空时，添加where条件查询指定类型的数据
            ->when(!is_null($type), function ($query) use ($type) {
                $query->where('type', $type);
            })
            // 最终添加查询字段和值的条件
            ->where($field, $value);
    }


    /**
     * 根据条件搜索数据。
     *
     * 本函数用于根据提供的$where参数条件来搜索数据库中的记录。它支持两种条件：'type' 和 'uid'。
     * 'type' 条件的处理逻辑是，如果类型值是在预定义的数组[1,2,3,4]中，则使用whereIn查询；
     * 否则，直接使用where查询。'uid' 条件直接使用where查询。
     *
     * @param array $where 搜索条件数组，包含'type'和'uid'等键值对。
     * @return \Illuminate\Database\Query\Builder|static 返回一个查询构建器实例，已应用搜索条件和排序。
     */
    public function search($where)
    {
        // 从模型中获取数据库实例，并应用条件查询
        $query = ($this->getModel()::getDB())
            // 当'type'条件存在且不为空时，应用相应的查询条件
            ->when((isset($where['type']) && $where['type'] !== ''), function ($query) use ($where) {
                // 如果类型值在预定义数组中，使用whereIn查询；否则，直接使用where查询
                if(in_array($where['type'],[1,2,3,4])){
                    $query->whereIn('type',[1,2,3,4]);
                }else{
                    $query->where('type',$where['type']);
                }
            })
            // 当'uid'条件存在时，应用相应的查询条件
            ->when((isset($where['uid']) && $where['uid']), function ($query) use ($where) {
                $query->where('uid', $where['uid']);
            });

        // 返回应用了排序的查询构建器
        return $query->order('create_time DESC');
    }


    /**
     * 删除数据
     *
     * 本函数用于根据指定的条件删除数据库中的记录。
     * 通过传入一个包含条件的数组，利用这些条件来定位特定的记录，并将它们从数据库中删除。
     *
     * @param array $where 删除条件数组，包含一个或多个用于定位记录的条件。
     */
    public function destory(array $where)
    {
        // 获取模型对应的数据库实例，并构建删除语句
        ($this->getModel()::getDB())->where($where)->delete();
    }


    /**
     * 计算指定日期内喜欢商家的用户数量
     *
     * 本函数用于查询在指定日期内，对特定商家喜欢的用户数量。
     * 如果未指定商家ID，则返回所有商家的喜欢用户数量。
     *
     * @param string $day 指定的日期，用于查询该日期内的喜欢情况。
     * @param int|null $merId 商家ID，可选参数，用于指定查询特定商家的喜欢用户数量。
     * @return int 返回指定日期内喜欢商家的用户数量。
     */
    public function dayLikeStore($day, $merId = null)
    {
        // 使用UserRelation模型查询数据库，条件为类型为10（表示喜欢操作），
        // 如果提供了商家ID，则进一步筛选出喜欢该商家的用户。
        // 最后，通过getModelTime处理查询结果，聚焦在指定日期，并计算用户数量。
        return getModelTime(UserRelation::getDB()->where('type', 10)->when($merId, function ($query, $merId) {
            $query->where('type_id', $merId);
        }), $day)->count();
    }


    /**
     * 统计指定日期内，指定商户的访问次数。
     *
     * 本函数用于查询在特定日期内，特定商户的用户访问次数。
     * 如果未指定日期，则返回所有时间的访问次数；如果未指定商户ID，则返回所有商户的访问次数。
     *
     * @param string $date 查询的日期，格式为YYYY-MM-DD。
     * @param int $merId 商户ID，可选参数，用于指定查询特定商户的访问次数。
     * @return int 返回符合条件的访问次数。
     */
    public function dateVisitStore($date, $merId = null)
    {
        // 从UserRelation类的getDB方法获取数据库查询对象，并开始构建查询条件
        return UserRelation::getDB()->where('type', 11)->when($merId, function ($query, $merId) {
            // 如果指定了商户ID，则添加where条件，查询特定商户的访问次数
            $query->where('type_id', $merId);
        })->when($date, function ($query, $date) {
            // 如果指定了日期，则调用getModelTime函数，添加where条件，查询特定日期的访问次数
            getModelTime($query, $date, 'create_time');
        })->count();
        // 计算符合条件的记录数，即访问次数，并返回结果
    }


    /**
     * 获取用户ID与指定类型ID交集的函数
     *
     * 本函数用于查询与给定用户ID相关的特定类型ID集合，
     * 其中类型ID是在预定义范围内的一组特定值。
     * 通过比较数据库中记录的类型ID，确定与用户相关联的ID集合。
     *
     * @param int $uid 用户的唯一标识ID
     * @param array $ids 指定的类型ID集合
     * @return array 返回与用户ID相关的指定类型ID的交集
     */
    public function intersectionPayer($uid, array $ids): array
    {
        // 通过UserRelation类的getDB方法获取数据库操作对象
        // 然后根据$uid查询满足条件的记录，其中"type"为12且"type_id"在$ids数组内的记录
        // 最后返回这些记录的"type_id"字段组成的数组
        return UserRelation::getDB()->where('uid', $uid)->whereIn('type', 12)->whereIn('type_id', $ids)->column('type_id');
    }


    /**
     * 获取用户关联的商品到社区的信息
     *
     * 本函数用于构建查询用户关联的商品（SPU）到社区的条件。支持通过关键词过滤，并限定关联状态为有效。
     * 主要用于在用户社区功能中展示用户关联的商品。
     *
     * @param string|null $keyword 关键词，用于过滤商品名称。如果为null，则不进行关键词过滤。
     * @param int $uid 用户ID，指定查询哪个用户的关联商品信息。
     * @return \Illuminate\Database\Eloquent\Builder 返回构建好的查询构建器对象，用于进一步的查询操作或数据获取。
     */
    public function getUserProductToCommunity(?string  $keyword, int $uid)
    {
        // 根据$keyword条件构建商品（SPU）的查询条件，支持模糊搜索，并限定关联状态为1（有效）
        $query = UserRelation::hasWhere('spu', function ($query) use($keyword) {
            $query->when($keyword, function ($query) use($keyword) {
                // 当关键词存在时，进行模糊搜索
                $query->whereLike('store_name',"%{$keyword}%");
            });
            // 限定关联状态为有效
            $query->where('status',1);
        });
        // 指定查询用户ID为$uid的关联信息
        $query->where('uid',$uid);
        return $query;
    }

}
