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


namespace app\common\dao\system\form;

use app\common\dao\BaseDao;
use app\common\model\system\form\Form;

class FormDao extends BaseDao
{
    protected function getModel(): string
    {
        return Form::class;
    }

    public function search(array $where)
    {
        return Form::getDB()
            ->when(isset($where['form_id']) && $where['form_id'] !== '', function($query) use ($where) {
                $query->where('form_id',$where['form_id']);
            })
            ->when(isset($where['keyword']) && $where['keyword'] !== '', function($query) use ($where) {
                $query->whereLike('name',"%{$where['keyword']}%");
            })
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function($query) use ($where) {
                $query->where('mer_id',$where['mer_id']);
            })
            ->when(isset($where['date']) && $where['date'] !== '', function($query) use ($where) {
                getModelTime($query,$where['date']);
            })
            ->when(isset($where['is_del']) && $where['is_del'] !== '', function($query) use ($where) {
                $query->where('is_del',$where['is_del']);
            })
            ->when(isset($where['status']) && $where['status'] !== '', function($query) use ($where) {
                $query->where('status',$where['status']);
            })
            ->order('form_id DESC,update_time DESC,create_time DESC');
    }
}
