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

namespace app\common\dao\store\product;

use app\common\dao\BaseDao;
use app\common\model\store\product\ProductAttr as model;

class ProductAttrDao extends BaseDao
{
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 根据条件搜索数据库。
     *
     * 本函数旨在根据传入的条件参数，构建并返回一个数据库查询查询对象。
     * 它允许条件参数中的`product_id`来限定查询结果，只有当`product_id`被明确设置且非空时，才会添加相应的查询条件。
     *
     * @param array $where 查询条件数组，可能包含多个条件，本函数处理其中的`product_id`条件。
     * @return \yii\db\Query 查询对象，可用于进一步的查询操作或直接执行查询。
     */
    public function search($where)
    {
        // 获取模型对应的数据库查询对象
        $query = $this->getModel()::getDb();

        // 当`product_id`存在于条件数组中且非空时，添加相应的查询条件
        $query->when(isset($where['product_id']) && $where['product_id'] != '', function($query) use($where) {
            $query->where('product_id',$where['product_id']);
        });

        // 返回构建好的查询对象
        return $query;
    }

    /**
     * 清除指定产品的属性
     *
     * 本函数用于删除数据库中与指定产品ID相关联的所有属性记录。
     * 通过调用关联模型的where方法来筛选出特定产品ID的属性记录，然后调用delete方法进行删除。
     *
     * @param int $productId 产品的ID，用于指定要清除属性的产品。
     * @return int 返回受影响的行数，即被删除的属性记录数。
     */
    public function clearAttr(int $productId)
    {
        // 使用动态模型删除与指定产品ID相关的所有属性记录
        return ($this->getModel())::where('product_id',$productId)->delete();
    }

    /**
     * 插入数据到数据库。
     *
     * 本函数用于将给定的数据数组批量插入到数据库。它首先通过getModel方法获取模型对象，
     * 然后调用该对象的getDB方法来获取数据库连接对象，最后通过调用insertAll方法来执行数据插入操作。
     *
     * @param array $data 包含多条待插入数据的数组，每条数据是一个子数组。
     * @return mixed 返回数据库操作的结果。具体类型取决于数据库库的实现。
     */
    public function insert(array $data)
    {
        // 通过模型获取数据库对象，并执行批量插入操作
        return ($this->getModel()::getDB())->insertAll($data);
    }

}
