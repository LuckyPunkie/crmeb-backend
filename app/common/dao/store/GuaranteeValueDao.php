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
namespace app\common\dao\store;

use app\common\dao\BaseDao;
use app\common\model\store\GuaranteeValue;

class GuaranteeValueDao extends BaseDao
{


    protected function getModel(): string
    {
        return GuaranteeValue::class;
    }

    /**
     * 更改保证状态
     *
     * 本函数用于根据给定的保证ID更新保证的状态。它通过查询数据库找到对应ID的保证记录，
     * 然后将其状态更新为新提供的状态值。
     *
     * @param int $id 保证的唯一标识ID。此ID用于在数据库中定位特定的保证记录。
     * @param int $status 新的状态值。此值将被设置为指定保证记录的状态，以反映其新的状态。
     */
    public function chageStatus(int $id,int $status)
    {
        // 通过getModel方法获取数据库操作对象，并使用where语句定位到特定ID的保证记录，然后更新其状态为$status
        $this->getModel()::getDB()->where('guarantee_id',$id)->update(['status' => $status]);
    }

    /**
     * 清除关联的保证模板数据
     *
     * 本函数用于根据给定的模板ID，从数据库中删除与该模板相关的所有数据。
     * 这是数据维护操作的一部分，用于在模板更新或废弃时清理旧数据。
     *
     * @param int $id 保证模板的唯一标识ID
     */
    public function clear($id)
    {
        // 通过模型获取数据库实例，并基于guarantee_template_id删除相关数据
        $this->getModel()::getDB()->where('guarantee_template_id',$id)->delete();
    }

}
