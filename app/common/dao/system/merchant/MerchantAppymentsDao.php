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


namespace app\common\dao\system\merchant;

use app\common\dao\BaseDao;
use app\common\model\system\merchant\MerchantApplyments;

class MerchantAppymentsDao extends BaseDao
{
    protected function getModel(): string
    {
        return MerchantApplyments::class;
    }

    /**
     * 根据条件搜索数据。
     *
     * 本函数用于根据提供的条件数组搜索数据库中的记录。它支持多个条件，
     * 条件之间是逻辑与（AND）关系。每个条件只有在数组中存在且不为空时才会被应用。
     *
     * @param array $where 包含搜索条件的数组。数组的键是条件的字段名，值是条件的值。
     *                    条件支持的字段包括：mer_id, uid, status, mer_applyments_id, date。
     *                    其中date字段需要是一个特定格式的字符串，会被内部转换为对应的时间范围条件。
     * @return \Illuminate\Database\Query\Builder|static 返回构建好的查询构建器对象，可以进一步链式调用或执行查询。
     */
    public function search(array $where)
    {
        // 获取模型对应的数据库连接实例
        $query = $this->getModel()::getDB();

        // 如果条件数组中包含mer_id，且值不为空，则添加where条件筛选mer_id
        $query = $query->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->where('mer_id', $where['mer_id']);
        });

        // 如果条件数组中包含uid，且值不为空，则添加where条件筛选uid
        $query = $query->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('uid', $where['uid']);
        });

        // 如果条件数组中包含status，且值不为空，则添加where条件筛选status，并将值转换为整型
        $query = $query->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status', (int)$where['status']);
        });

        // 如果条件数组中包含mer_applyments_id，且值不为空，则添加where条件筛选mer_applyments_id
        $query = $query->when(isset($where['mer_applyments_id']) && $where['mer_applyments_id'] !== '', function ($query) use ($where) {
            $query->where('mer_applyments_id', $where['mer_applyments_id']);
        });

        // 如果条件数组中包含date，且值不为空，则调用getModelTime函数添加对应的时间范围条件
        $query = $query->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date']);
        });

        $query = $query->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            $query->where('type', $where['type']);
        });

        // 永久排除已删除的数据
        $query = $query->where('is_del', 0);

        // 返回构建好的查询对象
        return $query;
    }



}
