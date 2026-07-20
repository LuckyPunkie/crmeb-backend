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
use app\common\model\user\UserHistory;

class UserHistoryDao extends BaseDao
{
    protected function getModel(): string
    {
        return UserHistory::class;
    }


    /**
     * 根据给定的数据创建或更新记录。
     * 此方法首先尝试根据数据中的条件查询现有记录，如果存在，则更新记录的更新时间并保存；
     * 如果不存在，则将更新时间添加到数据中并创建新记录。
     * @param array $data 包含要操作的数据的数组，其中的条件用于查询或插入。
     */
    public function createOrUpdate(array $data)
    {
        // 尝试根据$data中的条件查询现有记录
        $ret = $this->getModel()::getDB()->where($data)->find();
        if ($ret) {
            // 如果记录存在，更新更新时间并保存
            $ret->update_time = time();
            $ret->save();
        } else {
            // 如果记录不存在，将更新时间添加到数据数组中并创建新记录
            $data['update_time'] = time();
            $this->create($data);
        }
    }

    /**
     * 根据用户ID和资源类型搜索资源。
     *
     * 此方法提供了一个灵活的方式来查询资源，可以根据用户ID和资源类型进行过滤。
     * 查询构建利用了when方法来条件性地添加where子句，确保了只有在相应的参数非空时才应用过滤条件。
     * 最后，通过order方法指定了查询结果的排序方式，按照更新时间降序排列。
     *
     * @param int|null $uid 用户ID。如果提供，将根据用户ID过滤结果。
     * @param int $type 资源类型。如果提供，将根据资源类型过滤结果。
     * @return 查询对象，已经应用了过滤条件和排序，可用于进一步的操作，如获取结果集。
     */
    public function search(?int $uid, int $type)
    {
        // 获取数据库实例，并根据$uid和$type条件性地添加where子句
        $query = ($this->getModel()::getDB())->when($uid, function ($query) use ($uid) {
            $query->where('uid', $uid);
        })->when($type, function ($query) use ($type) {
            $query->where('res_type', $type);
        });

        // 对查询结果按更新时间降序排序
        return $query->order('update_time DESC');
    }



    /**
     * 批量删除记录。
     * 根据传入的参数，本函数支持两种方式批量删除数据：
     * 1. 当$data为数组时，删除主键在$data数组内的所有记录。
     * 2. 当$data为1时，删除所有uid为$uid的记录。
     *
     * @param int $uid 用户ID，当$data为1时，用于指定删除哪个用户的记录。
     * @param array|int $data 要删除的记录的主键数组，或者当值为1时，表示删除指定用户的所有记录。
     */
    public function deleteBatch($uid,$data)
    {
        // 检查$data是否为数组，如果是，删除主键在数组内的记录。
        if(is_array($data)){
            $this->getModel()::getDB()->where($this->getPk(),'in',$data)->delete();
        }else if($data == 1){
            // 如果$data为1，删除所有uid为$uid的记录。
            $this->getModel()::getDB()->where('uid',$uid)->delete();
        }
    }


    /**
     * 计算用户的历史记录总数
     *
     * 本函数用于查询指定用户（通过$uid标识）在系统中的历史记录总数。特别地，它只计算
     * 类型为1的资源的历史记录，这通常表示用户的购买、浏览或其他有意义的操作次数。
     *
     * @param int $uid 用户ID，用于指定要查询历史记录的用户。
     * @return int 返回用户的历史记录总数。这个数字只包括与资源类型为1相关的记录。
     */
    public function userTotalHistory($uid)
    {
        // 使用别名H来引用UserHistory模型，以便在查询中使用
        // 加入store_spu表的关联查询，通过S.spu_id = H.res_id条件关联
        // 筛选条件：uid为指定用户ID且res_type为1
        // 返回满足条件的记录总数
        return UserHistory::alias('H')
            ->join('store_spu S','S.spu_id = H.res_id')
            ->where('uid',$uid)
            ->where('res_type', 1)
            ->count();
    }

    /**
     * 根据条件查询关联的SPU
     *
     * 本函数用于构建一个查询用户历史记录中特定SPU的查询语句。它允许通过关键词和其他条件来过滤结果。
     * 主要用于在用户历史记录中查找与特定条件匹配的SPU实体。
     *
     * @param array $where 查询条件数组，包含关键词、用户ID和资源类型。
     * @return \Illuminate\Database\Eloquent\Builder 返回构建好的查询构建器对象，用于进一步的查询操作或数据获取。
     */
    public function joinSpu($where)
    {
        // 初始化查询，基于是否有关键词来动态构建WHERE子句
        $query = UserHistory::hasWhere('spu',function($query) use($where){
            // 如果关键词存在且不为空，则添加LIKE条件来筛选store_name
            $query->when(isset($where['keyword']) && $where['keyword'] !== '', function($query) use ($where){
                // 使用LIKE操作符来匹配关键词
                $query->whereLike('store_name',"%{$where['keyword']}%");
            });
            // 添加一个总是为真的条件，用于确保WHERE子句的存在，以便后续的AND连接
            $query->where(true);
        });

        // 添加用户ID和资源类型作为查询条件
        $query->where('uid', $where['uid']);
        $query->where('res_type', $where['type']);

        // 返回构建好的查询构建器对象
        return $query;
    }

}
