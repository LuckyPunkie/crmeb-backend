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
use app\common\model\store\product\StoreDiscounts;

class StoreDiscountDao extends BaseDao
{
    protected function getModel(): string
    {
        return StoreDiscounts::class;
    }

    /**
     * 增加商品库存
     * 该方法用于对指定商品的库存进行增加操作。如果商品设置了库存限制，会增加限制数量；如果商品销量大于1，会减少销量。
     * @param integer $id 商品ID，用于查询特定商品的信息。
     */
    public function incStock($id)
    {
        // 通过ID查询商品信息
        $res = $this->getModel()::getDb()->find($id);
        // 检查商品是否存在
        if ($res) {
            // 如果商品设置了库存限制，增加限制数量
            if ($res['is_limit']) $res->limit_num++;
            // 如果商品销量大于1，减少销量
            if ($res->sales > 1) $res->sales--;
            // 保存更新后的商品信息
            $res->save();
        }

    }


    /**
     * 减少指定商品的库存
     *
     * 此方法用于处理商品库存的减少操作。它首先尝试根据商品ID查询数据库中的商品记录。
     * 如果商品不存在，则返回false。如果商品存在，它将尝试减少商品的销售数量。
     * 如果该商品是有限制库存的，它还会减少商品的库存限制数量。如果库存限制数量小于或等于0，
     * 则表示商品库存不足，方法将返回false。否则，更新后的商品记录将被保存到数据库中，
     * 并返回true表示操作成功。
     *
     * @param int $id 商品ID
     * @return bool 操作成功返回true，否则返回false
     */
    public function decStock($id)
    {
        // 通过ID查询商品记录
        $res = $this->getModel()::getDb()->find($id);

        // 如果商品不存在，则返回false
        if (!$res) {
            return false;
        }

        // 增加商品销售数量
        $res->sales++;

        // 如果商品有限制库存
        if ($res['is_limit']) {
            // 如果当前库存限制数量大于0，则减少库存限制数量
            if ($res['limit_num'] > 0) {
                $res->limit_num--;
            } else {
                // 如果库存限制数量小于或等于0，表示库存不足，返回false
                return false;
            }
        }

        // 保存更新后的商品记录到数据库
        $res->save();

        // 返回true，表示操作成功
        return true;
    }

    /**
     * 清除特定字段值对应的数据记录
     *
     * 本函数用于根据指定的字段值和该值对应的ID，从数据库中删除相应的记录。
     * 这是个通用函数，可以通过传入不同的字段名和ID值来删除不同表中的数据。
     *
     * @param mixed $id 需要删除的数据记录的ID值，可以是数字、字符串等
     * @param string $field 指定的字段名，用于查询和删除数据
     */
    public function clear($id,$field)
    {
        // 使用模型获取数据库实例，并构造删除语句，根据字段和ID删除数据
        $this->getModel()::getDB()->where($field, $id)->delete();
    }



}

