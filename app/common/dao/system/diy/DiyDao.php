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


namespace app\common\dao\system\diy;

use app\common\dao\BaseDao;
use app\common\model\system\diy\Diy;
use app\common\model\system\Relevance;
use app\common\repositories\system\RelevanceRepository;
use think\facade\Db;

class DiyDao extends BaseDao
{

    protected function getModel(): string
    {
        return Diy::class;
    }

    /**
     * 设置指定ID的记录为使用中
     * 此方法主要用于更新数据库中与商家（merId）相关的特定记录的状态。
     * 它首先检查指定的ID是否为默认记录，如果不是，则将其状态设置为使用中（1）。
     * 如果指定的ID是默认记录，则不更新其状态。
     * @param int $id 需要设置为使用中的记录的ID
     * @param int $merId 商家的ID，用于确定记录所属的商家
     */
    public function setUsed($id, $merId)
    {
        // 根据ID查找记录
        $res  = $this->getModel()::getDb()->find($id);

        // 更新所有不属于当前商家的默认记录状态为未使用（0）
        $this->getModel()::getDb()->where('mer_id', $merId)->where('is_default' ,0)->update(['status'=>0]);

        // 如果当前记录不是默认记录，则将其状态设置为使用中（1）
        if (!$res['is_default']) {
            $this->getModel()::getDb()->where('mer_id', $merId)->where('id',$id)->update(['status'=> 1]);
        }
    }

    /**
     * 检查指定商户ID和ID组合是否存在对应的记录。
     *
     * 本函数通过查询数据库来确定是否存在一个满足特定条件的记录。
     * 条件包括指定的商户ID（merId）和指定的ID（$id）。
     * 如果存在满足条件的记录，则返回true，否则返回false。
     *
     * @param int $merId 商户ID，用于限定查询的范围。
     * @param int $id 需要检查的ID，用于进一步限定查询的条件。
     * @return bool 如果找到满足条件的记录，则返回true，否则返回false。
     */
    public function merExists(int $merId, int $id)
    {
        // 通过模型获取数据库实例，并构造查询条件，查询满足mer_id和主键$id的记录数量。
        // 如果记录数量大于0，则表示存在对应的记录，返回true；否则，返回false。
        return ($this->getModel()::getDb()->where('mer_id', $merId)->where($this->getPk(), $id)->count() > 0 );
    }

    public function search($where)
    {
        $query = $this->getModel()::getDb()
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '' ,function($query) use($where) {
                $query->where('mer_id',$where['mer_id']);
            })
            ->when(isset($where['ids']) && $where['ids'] !== '', function($query) use($where){
                $query->whereIn('id',$where['ids']);
            })
            ->when(isset($where['type']) && $where['type'] !== '', function($query) use($where){
                $query->where('type',$where['type']);
            })
            ->when(isset($where['status']) && $where['status'] !== '', function($query) use($where){
                $query->where('status',$where['status']);
            })
            ->when(isset($where['is_diy']) && $where['is_diy'] !== '', function($query) use($where){
                $query->where('is_diy',$where['is_diy']);
            })
            ->when(isset($where['is_default']) && $where['is_default'] !== '', function($query) use($where){
                $query->where('is_default',$where['is_default']);
            })
            ->when(isset($where['name']) && $where['name'] !== '', function($query) use($where){
                $query->whereLike('name',"%{$where['name']}%");
            })
            ->when(isset($where['default_ids']), function($query) use($where){
                $query->whereOr(function($query) use($where) {
                    $query->whereIn('id', $where['default_ids']);
                });
            })
        ;
        return $query;
    }


    /**
     * 符合条件的默认模板ID
     * @param array $where
     * @return string
     * @author Qinii
     * @day 2023/7/14
     */
    public function withMerSearch(array $where)
    {
        $ids =Diy::hasWhere('relevance',function($query) use($where){
             $query->where(function($query) use($where) {
                 $query->where(function($query) use($where) {
                     $query->where('Relevance.type',RelevanceRepository::MER_DIY_SCOPE[0])->where('right_id',$where['mer_id']);
                 })->whereOr(function($query) use($where){
                     $query->where('Relevance.type',RelevanceRepository::MER_DIY_SCOPE[1])->where('right_id',$where['category_id']);
                 })->whereOr(function($query) use($where){
                     $query->where('Relevance.type',RelevanceRepository::MER_DIY_SCOPE[2])->where('right_id',$where['type_id']);
                 })->whereOr(function($query) use($where){
                     $query->where('Relevance.type',RelevanceRepository::MER_DIY_SCOPE[3])->where('right_id',$where['is_trader']);
                 });
             });
        })->where('Diy.type',2)->where('is_default',1)->column('id');
        $_ids = Diy::where(function($query){
            $query->where(function($query){
                $query->where('type',2)->where('is_default',1);
            })->whereOr(function($query){
                $query->where('type',1)->where('is_default',2);
            });
        })->where('is_diy',1)->where('scope_type',4)->column('id');
        return array_merge($ids,$_ids);
    }
}
