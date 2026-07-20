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
use app\common\model\store\order\MerchantReconciliationOrder as model;

class MerchantReconciliationOrderDao extends BaseDao
{
   public function getModel(): string
   {
       return model::class;
   }


   /**
    * 根据条件搜索数据。
    *
    * 本函数用于根据提供的条件搜索数据库中的记录。它支持两种条件：'reconciliation_id' 和 'type'。
    * 只有当条件存在且不为空时，才会在查询中添加相应的 WHERE 子句。
    *
    * @param array $where 包含搜索条件的数组。可能的条件键包括 'reconciliation_id' 和 'type'。
    * @return \Illuminate\Database\Eloquent\Builder|static 返回构建器对象，可用于进一步的查询操作或数据检索。
    */
   public function search($where)
   {
       // 获取模型对应的数据库实例
       return ($this->getModel()::getDB())->when(
           // 检查是否有 'reconciliation_id' 条件，并且它不是空的
           isset($where['reconciliation_id']) && $where['reconciliation_id'] !== '',
           function ($query) use ($where) {
               // 如果存在 'reconciliation_id'，则在查询中添加对应的 WHERE 条件
               $query->where('reconciliation_id', $where['reconciliation_id']);
           }
       )->when(
           // 检查是否有 'type' 条件，并且它不是空的
           isset($where['type']) && $where['type'] !== '',
           function ($query) use ($where) {
               // 如果存在 'type'，则在查询中添加对应的 WHERE 条件
               $query->where('type', $where['type']);
           }
       );
   }
}
