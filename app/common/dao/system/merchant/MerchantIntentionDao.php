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
use app\common\model\system\merchant\MerchantIntention;

class MerchantIntentionDao extends BaseDao
{
    protected function getModel(): string
    {
        return MerchantIntention::class;
    }

    public function search(array $where)
    {
        $query = $this->getModel()::getDB()->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            $query->where('mer_id', $where['mer_id']);
        })->when(isset($where['uid']) && $where['uid'] !== '', function ($query) use ($where) {
            $query->where('uid', $where['uid']);
        })->when(isset($where['status']) && $where['status'] !== '', function ($query) use ($where) {
            $query->where('status', $where['status']);
        })->when(isset($where['mer_intention_id']) && $where['mer_intention_id'] !== '', function ($query) use ($where) {
            $query->where('mer_intention_id', $where['mer_intention_id']);
        })->when(isset($where['category_id']) && $where['category_id'] !== '', function ($query) use ($where) {
            $query->where('merchant_category_id', $where['category_id']);
        })->when(isset($where['type_id']) && $where['type_id'] !== '', function ($query) use ($where) {
            $query->where('mer_type_id', $where['type_id']);
        })->when(isset($where['keyword']) && $where['keyword'] !== '', function ($query) use ($where) {
            $query->where('mer_name|phone|mark', 'like', '%' . $where['keyword'] . '%');
        })->when(isset($where['date']) && $where['date'] !== '', function ($query) use ($where) {
            getModelTime($query, $where['date']);
        })->where('is_del', 0);

        return $query;
    }

    /**
     * 更新表单数据的状态和标记
     *
     * 本函数用于根据给定的ID更新表单记录的状态和标记。它首先通过ID从数据库中定位到特定的记录，
     * 然后将状态和标记字段更新为传入的数据值。此功能对于处理表单数据的更新操作非常有用，例如，
     * 在一个后台管理系统中，管理员可能需要更改某个表单记录的状态或添加标记以进行进一步处理。
     *
     * @param int $id 表单记录的主键ID，用于在数据库中定位到特定记录
     * @param array $data 包含状态和标记数据的数组，用于更新表单记录的相应字段
     */
    public function form($id, $data)
    {
        // 通过模型获取数据库实例，并使用给定的ID定位到记录，然后更新记录的状态和标记字段
        $this->getModel()::getDB()->where($this->getPk(), $id)->update(['status' => $data['status'], 'mark' => $data['mark']]);
    }

}
