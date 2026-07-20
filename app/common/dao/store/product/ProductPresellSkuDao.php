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
use app\common\model\store\product\ProductPresellSku;
use think\facade\Db;

class ProductPresellSkuDao extends BaseDao
{
    protected function getModel(): string
    {
        return ProductPresellSku::class;
    }

    /**
     * 清除预售产品的相关数据
     *
     * 本函数用于根据给定的预售产品ID，从数据库中删除与该预售产品相关的所有数据。
     * 这里的相关数据指的是通过预售产品ID可以唯一标识的数据记录。
     *
     * @param int $id 预售产品的唯一标识ID
     */
    public function clear($id)
    {
        // 通过模型获取数据库实例，并基于给定的预售产品ID删除相关数据
        $this->getModel()::getDB()->where('product_presell_id', $id)->delete();
    }


    /**
     * 减少指定预售产品的库存并增加销量
     *
     * 本函数用于处理预售产品的库存逻辑。通过传入预售产品ID和唯一标识符，
     * 对对应产品的库存进行减少，同时增加该产品的销量。
     * 这种设计适用于预售后期对已预订产品的库存和销量进行精确调整的场景。
     *
     * @param int $product_presell_id 预售产品的ID，用于定位特定的预售产品。
     * @param string $unique 唯一标识符，用于确保操作的准确性，防止重复操作。
     * @param int $desc 需要减少的库存数量，同时也是增加的销量数量。
     * @return bool 返回操作结果，成功为true，失败为false。
     */
    public function descStock(int $product_presell_id, string $unique, int $desc)
    {
        // 使用模型的数据库操作方法，根据预售产品ID和唯一标识符定位到特定的数据行。
        // 然后更新该行的库存和销量数据，库存减少，销量增加。
        return $this->getModel()::getDB()->where('product_presell_id', $product_presell_id)->where('unique', $unique)->update([
            'stock' => Db::raw('stock-' . $desc), // 直接通过数据库原生表达式减少库存
            'seles' => Db::raw('seles+' . $desc), // 直接通过数据库原生表达式增加销量
        ]);
    }

    /**
     * 增加预售产品的库存并减少销量
     *
     * 本函数用于在数据库中更新指定预售产品的库存和销量。它通过增加库存量和减少销售量来调整产品的状态。
     * 主要用于处理预售结束后的库存和销量结算。
     *
     * @param int $product_presell_id 预售产品的ID，用于定位特定的预售产品
     * @param string $unique 预售产品的唯一标识，用于确保操作的准确性
     * @param int $desc 库存增加的数量，同时也是销量减少的数量，表示此次操作的规模
     * @return bool 更新操作的结果，成功返回true，失败返回false
     */
    public function incStock(int $product_presell_id, string $unique, int $desc)
    {
        // 使用模型的数据库操作方法，根据预售产品ID和唯一标识定位到特定的预售产品记录
        // 然后更新该记录的库存和销量字段，库存增加，销量减少
        $info = $this->getModel()::getDB()->where('product_presell_id', $product_presell_id)->where('unique',$unique)->find();

        $info->stock = bcadd($info['stock'], $desc);
        $info->seles = max(bcsub($info['seles'], $desc), 0);
        return $info->save();
    }

    /**
     *  增加 参与或支付成功 人数
     * @param int $product_presell_id
     * @param string $unique
     * @param string $field
     * @return mixed
     * @author Qinii
     * @day 2020-11-27
     */
    public function incCount(int $product_presell_id,string $unique,string $field,$inc = 1)
    {
        return $this->getModel()::getDB()->where('product_presell_id', $product_presell_id)->where('unique', $unique)
            ->update([
                $field => Db::raw($field.'+' . $inc)
            ]);
    }

    /**
     *  减少 参与或支付成功 人数
     * @param int $product_presell_id
     * @param string $unique
     * @param string $field
     * @return mixed
     * @author Qinii
     * @day 2020-11-27
     */
    public function desCount(int $product_presell_id,string $unique,$inc = 1)
    {
        $res = $this->getModel()::getDB()->where('product_presell_id', $product_presell_id)->where('unique',$unique)->find();
        if($res->presell->presell_type == 1 ){
            $res->one_pay = ($res->one_pay > 0) ? $res->one_pay - $inc : 0;
        }else{
            $res->two_pay = ($res->two_pay > 0) ? $res->two_pay - $inc : 0;
        }
        return $res->save();
    }
}

