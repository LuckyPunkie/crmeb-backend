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

namespace app\common\dao\store\order;

use app\common\dao\BaseDao;
use app\common\model\store\order\MerchantReconciliation as model;
use app\common\repositories\system\admin\AdminRepository;
use app\common\repositories\system\merchant\MerchantRepository;

class MerchantReconciliationDao extends BaseDao
{
   public function getModel(): string
   {
       return model::class;
   }

   /**
    * 根据条件搜索数据。
    *
    * 该方法用于根据传入的条件数组来构建数据库查询条件，灵活地支持多种搜索逻辑，
    * 包括对特定字段的条件查询以及关键词的全文搜索。搜索条件的构建使用了Laravel的查询构建器，
    * 通过链式调用where和when方法来动态添加查询条件。
    *
    * @param array $where 搜索条件数组，包含各种可能的查询条件。
    * @return \Illuminate\Database\Eloquent\Builder|static 返回构建好的查询构建器实例，可用于进一步的操作或直接执行查询。
    */
   public function search(array $where)
   {
       // 获取模型对应的数据库实例
       $query = ($this->getModel()::getDB())
           // 当'mer_id'字段存在且不为空时，添加对'mer_id'字段的查询条件
           ->when(isset($where['mer_id']) && $where['mer_id'] != '' ,function($query)use($where){
               $query->where('mer_id',$where['mer_id']);
           })
           // 当'status'字段存在且不为空时，添加对'status'字段的查询条件
           ->when(isset($where['status']) && $where['status'] != '' ,function($query)use($where){
               $query->where('status',$where['status']);
           })
           // 当'is_accounts'字段存在且不为空时，添加对'is_accounts'字段的查询条件
           ->when(isset($where['is_accounts']) && $where['is_accounts'] != '' ,function($query)use($where){
               $query->where('is_accounts',$where['is_accounts']);
           })
           // 当'date'字段存在且不为空时，调用getModelTime函数添加对日期的查询条件
           ->when(isset($where['date']) && $where['date'] != '' ,function($query)use($where){
               getModelTime($query,$where['date']);
           })
           // 当'reconciliation_id'字段存在且不为空时，添加对'reconciliation_id'字段的查询条件
           ->when(isset($where['reconciliation_id']) && $where['reconciliation_id'] != '' ,function($query)use($where){
               $query->where('reconciliation_id',$where['reconciliation_id']);
           })
           // 当'keyword'字段存在且不为空时，执行关键词搜索逻辑
           ->when(isset($where['keyword']) && $where['keyword'] !== '',function($query)use($where){
               // 实例化管理员仓库，用于获取管理员ID列表
               $make = app()->make(AdminRepository::class);
               $admin_id = $make->getSearch(['real_name' => $where['keyword']],null,false)->column('admin_id');
               // 根据是否存在'mer_id'字段，构建不同的查询条件
               $query->where(function($query) use($admin_id,$where){
                   if(isset($where['mer_id'])){
                       $query->where('admin_id','in',$admin_id);
                   }else {
                       // 实例化商家仓库，用于获取商家ID列表
                       $mer_make = app()->make(MerchantRepository::class);
                       $mer_id = $mer_make->getSearch(['keyword' => $where['keyword']])->column('mer_id');
                       // 添加对管理员ID或商家ID的查询条件
                       $query->where('admin_id','in',$admin_id)->whereOr('mer_id','in',$mer_id);
                   }
               });
           });
       // 设置查询结果的排序方式
       return $query->order('create_time DESC,status DESC');
   }

}
