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

namespace app\common\dao\store\parameter;

use app\common\dao\BaseDao;
use app\common\model\store\parameter\ParameterTemplate;

class ParameterTemplateDao extends BaseDao
{

    protected function getModel(): string
    {
        return ParameterTemplate::class;
    }

    /**
     * 清除特定字段中具有指定ID的记录。
     *
     * 此方法通过提供的ID和字段名称，从数据库中删除符合条件的记录。
     * 它首先获取模型对应的数据库实例，然后使用提供的字段和ID构建删除条件，
     * 最后执行删除操作。
     *
     * @param int $id 主键ID，用于指定要删除的记录。
     * @param string $field 要用于删除条件的字段名称。
     */
    public function clear(int $id, string $field)
    {
        $this->getModel()::getDB()->where($field, $id)->delete();
    }

}
