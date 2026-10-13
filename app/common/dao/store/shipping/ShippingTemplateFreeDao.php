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

namespace app\common\dao\store\shipping;

use think\facade\Db;
use app\common\dao\BaseDao;
use app\common\model\store\shipping\ShippingTemplateFree as model;

class ShippingTemplateFreeDao  extends BaseDao
{
    /**
     * @Author:Qinii
     * @Date: 2020/5/8
     * @return string
     */
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 检查指定字段是否存在特定值。
     *
     * 该方法用于查询数据库中指定字段的值是否与给定值匹配，
     * 并可以根据需要排除特定的值。
     *
     * @param string $field 要检查的字段名。
     * @param mixed $value 要匹配的值。
     * @param mixed $except 可选参数，用于指定需要排除的值。
     * @return bool 如果找到匹配的记录则返回true，否则返回false。
     */
    public function merFieldExists($field, $value, $except = null)
    {
        // 获取模型对应的数据库实例。
        $db = ($this->getModel())::getDB();

        // 如果指定了需要排除的值，则添加相应的where条件。
        $db->when($except, function ($query, $except) use ($field) {
            $query->where($field, '<>', $except);
        });

        // 添加字段等于给定值的where条件。
        $db->where($field, $value);

        // 统计符合条件的记录数，如果大于0则表示存在匹配的记录。
        return $db->count() > 0;
    }


    /**
     * 批量删除
     * @Author:Qinii
     * @Date: 2020/5/8
     * @param array $id
     * @param array $temp_id
     */
    public function batchRemove(array $id,array $temp_id)
    {
        if($id)
            ($this->getModel())::getDB()->where($this->getPk(),'in',$id)->delete();
        if($temp_id)
            ($this->getModel())::getDB()->where('temp_id','in',$temp_id)->delete();
    }

    /**
     * 批量插入数据到数据库。
     *
     * 本方法通过调用getModel方法获取模型实例，进而获取数据库连接对象，并执行批量插入操作。
     * 它接受一个数组作为参数，数组中的每个元素代表一条待插入的数据。
     *
     * @param array $data 包含多条待插入数据的数组，每条数据以键值对形式表示。
     * @return mixed 返回批量插入操作的结果，具体类型取决于数据库操作的返回值。
     */
    public function insertAll(array $data)
    {
        // 通过模型获取数据库实例，并执行批量插入操作
        return ($this->getModel()::getDB())->insertAll($data);
    }
}
