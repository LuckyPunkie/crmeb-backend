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
use app\common\model\store\product\ProductAttrValue as model;
use app\common\repositories\store\product\CdkeyLibraryRepository;
use app\common\repositories\store\product\ProductCdkeyRepository;
use app\common\repositories\store\product\ProductRepository;
use think\db\exception\DbException;
use think\facade\Db;

/**
 * Class ProductAttrValueDao
 * @package app\common\dao\store\product
 * @author xaboy
 * @day 2020/6/9
 */
class ProductAttrValueDao extends BaseDao
{
    /**
     * @return string
     * @author xaboy
     * @day 2020/6/9
     */
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     *  商品规格添加操作
     * @param $data
     * @param $isType
     * @return void
     * @author Qinii
     */
    public function add($data,$isType)
    {
        switch ($isType) {
            case 2: //网盘信息
                $cdkey = [];
                foreach ($data as $datum) {
                    $sku_cdkey = $datum['cdkey'][0];
                    unset($datum['cdkey']);
                    $sku = $this->create($datum);
                    $sku_cdkey['value_id'] = $sku['value_id'];
                    $cdkey[] = $sku_cdkey;
                }
                if ($cdkey) {
                    app()->make(ProductCdkeyRepository::class)->insertAll($cdkey);
                }
                break;
            case 3: // 一次性卡密关联
                $cdkeyLibraryRepository = app()->make(CdkeyLibraryRepository::class);
                foreach ($data as $datum) {
                    $sku = $this->create($datum);
                    if ($datum['library_id']) {
                        $cdkeyLibraryRepository->update($datum['library_id'], [
                            'product_id' => $datum['product_id'],
                            'product_attr_value_id' => $sku['value_id']
                        ]);
                    }
                }
                break;
            case 4: // 预约商品
                $reservation = [];
                foreach ($data['reservation'] as $val) {
                    $item = [];
                    $item['start_time'] = $val['start_time'];
                    $item['end_time'] = $val['end_time'];
                    $item['stock'] = $val['stock'];

                    $reservation[] = $item;
                }
                unset($data['reservation']);

                $sku = $this->create($data);
                $this->createReservation($sku, $reservation);
                break;
            default:
                $this->insertAll($data);
                break;
        }
    }

    /**
     *  清楚所有规格
     * @Author:Qinii
     * @Date: 2020/5/9
     * @param int $productId
     * @return mixed
     */
    public function clearAttr(int $productId)
    {
        ($this->getModel())::where('product_id', $productId)->delete();
    }

    /**
     *  根据条件获取某字段的值合集
     * @Author:Qinii
     * @Date: 2020/5/9
     * @param int $merId
     * @param $field
     * @param $value
     * @param null $except
     * @return mixed
     */
    public function getFieldColumnt($key, $value, $field, $except = null)
    {
        return ($this->getModel()::getDB())->when($except, function ($query, $except) use ($field) {
            $query->where($field, '<>', $except);
        })->where($key, $value)->column($field);
    }

    /**
     * 计算指定字段的总和，排除特定值。
     *
     * 此函数用于根据给定的条件计算数据表中某个字段的总和，
     * 支持排除特定的值以获得更精确的计算结果。
     *
     * @param string $key 查询条件的关键字。
     * @param mixed $value 查询条件的关键字对应的值。
     * @param string $field 需要计算总和的字段名。
     * @param mixed $except 排除的特定值，可选参数。
     * @return int 返回计算得到的字段总和。
     */
    public function getFieldSum($key, $value, $field, $except = null)
    {
        // 使用模型的数据库实例，并根据$except参数应用排除条件
        return ($this->getModel()::getDB())->when($except, function ($query, $except) use ($field) {
            // 如果$except有值，则添加不等于($field, '<>', $except)的条件
            $query->where($field, '<>', $except);
        })->where($key, $value)->sum($field);
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

    /**
     * 检查指定字段的值在数据库中是否存在。
     *
     * 此函数用于确定给定字段的特定值是否在数据库中出现。它支持条件筛选，可以通过`merId`来限定查询范围，也可以通过`except`参数排除特定值。
     * 主要用于在进行数据操作前验证数据的唯一性或存在性，以避免重复数据或错误的数据操作。
     *
     * @param int|null $merId 商户ID，用于限定查询的范围。如果为null，则不进行商户ID的筛选。
     * @param string $field 要检查的字段名。
     * @param mixed $value 要检查的字段值。
     * @param mixed|null $except 排除的值，如果不为null，则查询时会排除掉这个值。
     * @return bool 如果存在返回true，否则返回false。
     */
    public function merFieldExists(?int $merId, $field, $value, $except = null)
    {
        // 获取数据库实例，并根据条件构建查询。
        return ($this->getModel())::getDB()->when($except, function ($query, $except) use ($field) {
                // 如果有排除值，则添加不等于条件。
                $query->where($field, '<>', $except);
            })->when($merId, function ($query, $merId) {
                // 如果有商户ID，则添加等于条件。
                $query->where('mer_id', $merId);
            })->where($field, $value)->count() > 0;
    }

    /**
     * 根据产品ID获取SKU
     *
     * 本函数旨在通过产品ID检索与之相关联的SKU。SKU (Stock Keeping Unit) 是一种产品库存管理单位，通常用于唯一标识一个产品变体。
     * 使用本函数需要提供一个产品ID，然后它将返回一个查询构建器实例，该实例已配置为查询与指定产品ID相关联的SKU。
     *
     * @param int $id 产品的唯一标识ID。这个ID用于在数据库查询中定位特定的产品。
     * @return \Illuminate\Database\Eloquent\Builder|static 返回一个Eloquent查询构建器实例，该实例已准备好根据产品ID查询SKU。
     */
    public function getSku($id)
    {
        // 通过调用$this->getModel()获取模型实例，并立即使用where子句过滤查询结果，只包括product_id为$id的记录。
        return ($this->getModel())::where('product_id', $id);
    }

    /**
     * 检查指定字段的值是否存在，可选地排除特定值或限制商户ID。
     *
     * 此方法用于查询数据库中是否存在指定字段的值，可以根据需要排除特定值或限制查询的商户。
     * 主要用于数据验证或数据存在性检查场景。
     *
     * @param int|null $merId 商户ID，用于限制查询的范围。
     * @param string $field 要检查的字段名。
     * @param string $value 要检查的字段值。
     * @param string|null $except 要排除的特定值，如果不为空，则查询时不包括此值。
     * @return bool 查询结果，如果存在则返回true，否则返回false。
     */
    public function getFieldExists(?int $merId, $field, $value, $except = null)
    {
        // 获取模型对应的数据库实例
        return ($this->getModel())::getDB()->when($except, function ($query, $except) use ($field) {
            // 如果有需要排除的值，则添加不等于条件
            $query->where($field, '<>', $except);
        })->when($merId, function ($query, $merId) {
            // 如果提供了商户ID，则添加条件限制查询结果只包含指定商户
            $query->where('mer_id', $merId);
        })->where($field, $value);
        // 添加字段等于条件，完成查询条件构建
    }

    /**
     * 减少商品库存并增加销售数量
     *
     * 该方法用于更新数据库中指定商品的库存和销售数量。它通过传入的产品ID和唯一标识符
     * 来定位特定的记录，并对库存进行减少，同时对销售数量进行增加。这种方法适用于
     * 实时更新库存系统，例如在处理订单时减少可用库存并记录已销售数量。
     *
     * @param int $productId 商品ID，用于在数据库中定位商品记录。
     * @param string $unique 商品的唯一标识符，用于确保操作的准确性。
     * @param int $desc 库存减少的数量，同时也是销售数量增加的数量。
     * @return mixed 返回数据库更新操作的结果，可能是布尔值或影响的行数。
     * @throws DbException
     */
    public function descStock(int $productId, string $unique, int $desc)
    {
        // 使用模型获取数据库实例，并通过WHERE子句定位到特定的商品记录。
        // 然后更新该记录的'stock'和'sales'字段，分别进行库存减少和销售数量增加的操作。
        return model::getDB()->where('product_id', $productId)->where('unique', $unique)->update([
            'stock' => Db::raw('stock-' . $desc), // 直接使用数据库原生语法减少库存
            'sales' => Db::raw('sales+' . $desc)  // 直接使用数据库原生语法增加销售数量
        ]);
    }

    /**
     * 更新SKU库存和销量
     *
     * 该方法用于减少指定SKU的库存并增加其销售量。通过传入产品ID、SKU和销售数量，
     * 直接对数据库进行更新操作，避免了不必要的数据检索和处理步骤，提高了数据库操作的效率。
     *
     * @param int $productId 产品ID，用于定位特定产品的SKU信息。
     * @param string $sku 特定产品的SKU，用于唯一标识产品的一个变种。
     * @param int $desc 销售描述数量，表示此次销售的数量，将从库存中扣除，并加到销售量中。
     * @return mixed 返回数据库更新操作的结果，可能是布尔值或影响行数。
     * @throws DbException
     */
    public function descSkuStock(int $productId, string $sku, int $desc)
    {
        // 使用模型的数据库访问方法，直接构造并执行更新语句
        // 通过where子句定位到特定的产品ID和SKU，然后更新库存和销量字段
        return model::getDB()->where('product_id', $productId)->where('sku', $sku)->update([
            'stock' => Db::raw('stock-' . $desc), // 直接从库存字段减去销售数量
            'sales' => Db::raw('sales+' . $desc)  // 直接将销售数量加到销量字段
        ]);
    }

    /**
     * 增加商品销售量
     *
     * 该方法用于更新数据库中指定产品的销售量。它通过传入的产品ID和SKU来定位特定的产品行，
     * 然后将销售量字段的值增加指定的数量。
     *
     * @param int $productId 产品ID，用于定位特定产品
     * @param string $sku 产品的SKU（库存单位），进一步唯一标识产品
     * @param int $desc 销售量增加的数量，表示要将当前销售量增加的值
     *
     * @return int 返回更新操作的影响行数，用于确认更新是否成功
     * @throws DbException
     */
    public function incSales(int $productId, string $sku, int $desc)
    {
        // 使用Db::raw()来构建SQL的增量更新语句，确保销售量字段正确增加
        return model::getDB()->where('product_id', $productId)->where('sku', $sku)->update([
            'sales' => Db::raw('sales+' . $desc)
        ]);
    }

    /**
     * 增加商品库存并减少对应销量
     *
     * 本函数用于在数据库中增加指定商品的库存量，并同时减少相同数量的已销售量。
     * 这样做的目的是为了准确跟踪商品的库存和销售情况，确保数据的准确性。
     *
     * @param int $productId 商品ID，用于定位特定商品
     * @param string $unique 商品的唯一标识，用于进一步确认特定商品
     * @param int $inc 需要增加的库存数量，同时也是需要减少的销售数量
     */
    public function incStock(int $productId, string $unique, int $inc)
    {
        // 增加商品库存
        model::getDB()->where('product_id', $productId)->where('unique', $unique)->inc('stock', $inc)->update();
        // 减少商品销量，但只针对销量大于等于增加的库存数量的部分进行减少
        model::getDB()->where('product_id', $productId)->where('unique', $unique)->where('sales', '>=', $inc)->dec('sales', $inc)->update();
    }

    /**
     * 增加SKU库存并调整销售数量
     *
     * 该方法用于在数据库中增加指定产品的指定SKU的库存数量，并且如果该SKU的销售数量大于增加的库存数量，
     * 则相应地减少销售数量。这种方法适用于处理库存和销售数据的同步更新，确保数据的一致性。
     *
     * @param int $productId 产品ID，用于定位特定产品的SKU
     * @param string $sku 指定产品的SKU，用于唯一标识一个产品的SKU
     * @param int $inc 需要增加的库存数量，这个数量将被加到SKU的库存上
     */
    public function incSkuStock(int $productId, string $sku, int $inc)
    {
        // 增加SKU的库存数量
        model::getDB()->where('product_id', $productId)->where('sku', $sku)->inc('stock', $inc)->update();

        // 如果SKU的销售数量大于增加的库存数量，则减少销售数量
        // 这里假设销售数量不应减少到小于增加的库存数量，确保数据的合理性
        model::getDB()->where('product_id', $productId)->where('sku', $sku)->where('sales', '>', $inc)->dec('sales', $inc)->update();
    }

    /**
     * 检查产品属性是否存在
     *
     * 本函数用于查询数据库中是否存在指定产品ID和唯一标识符对应的属性记录。
     * 主要用于验证某个产品的特定属性是否已经被定义。
     *
     * @param int $productId 产品ID，用于查询产品属性时的条件过滤。
     * @param string $unique 唯一标识符，用于查询产品属性时的条件过滤。
     * @return bool 如果找到匹配的属性记录，则返回true，否则返回false。
     * @throws DbException
     */
    public function attrExists(int $productId, string $unique): bool
    {
        // 通过模型获取数据库实例，并构造查询条件，检查是否存在指定产品ID和唯一标识符的属性记录。
        return model::getDB()->where('product_id', $productId)->where('unique', $unique)->count() > 0;
    }

    /**
     * 检查SKU是否存在于数据库中。
     *
     * 本函数用于确定给定的产品ID和SKU组合是否在数据库中存在记录。
     * 通过查询产品ID和SKU匹配的记录数量，如果数量大于0，则表示SKU存在。
     *
     * @param int $productId 产品ID，用于查询特定产品的SKU记录。
     * @param string $sku 商品SKU，用于唯一标识一个商品。
     * @return bool 如果SKU存在则返回true，否则返回false。
     * @throws DbException
     */
    public function skuExists(int $productId, string $sku): bool
    {
        // 使用模型获取数据库实例，并构造查询条件，检查是否存在指定产品ID和SKU的记录。
        return model::getDB()->where('product_id', $productId)->where('sku', $sku)->count() > 0;
    }

    /**
     *  商品佣金是否大于设置佣金比例
     * @param $productId
     * @return bool
     * @author Qinii
     * @day 2020-06-25
     */
    public function checkExtensionById($productId)
    {
        $extension_one_rate = systemConfig('extension_one_rate');
        $extension_two_rate = systemConfig('extension_two_rate');

        $count = ($this->getModel()::getDb())->where(function($query)use($productId,$extension_one_rate){
            $query->where('product_id',$productId)->whereRaw('price * '.$extension_one_rate.' > extension_one');
        })->whereOr(function($query)use($productId,$extension_two_rate){
            $query->where('product_id',$productId)->whereRaw('price * '.$extension_two_rate.' > extension_two');
        })->count();
        return $count ? false : true;
    }

    /**
     * 根据条件搜索记录。
     *
     * 该方法用于构建查询条件，根据传入的数组$where动态添加查询条件到查询语句中。
     * 支持根据产品ID、SKU和唯一标识符进行搜索。只有当相应字段的值被设置且不为空时，才会添加对应的查询条件。
     *
     * @param array $where 搜索条件数组，可能包含产品ID、SKU和唯一标识符。
     * @return \yii\db\Query 查询对象，带有应用了搜索条件的查询语句。
     */
    public function search(array $where)
    {
        // 获取数据库查询对象，并根据$where数组中的条件动态构建查询语句
        $query = ($this->getModel()::getDb())
            // 当产品ID存在且不为空时，添加到查询条件中
            ->when(isset($where['product_id']) && $where['product_id'] !== '',function($query)use($where){
                $query->where('product_id',$where['product_id']);
            })
            // 当SKU存在且不为空时，添加到查询条件中
            ->when(isset($where['sku']) && $where['sku'] !== '',function($query)use($where){
                $query->where('sku',$where['sku']);
            })
            // 当唯一标识符存在且不为空时，添加到查询条件中
            ->when(isset($where['unique']) && $where['unique'] !== '',function($query)use($where){
                $query->where('unique',$where['unique']);
            });

        return $query;
    }


    /**
     * 批量更新指定产品ID的数据
     *
     * 本函数通过传入的产品ID数组和数据数组，更新数据库中对应产品ID的所有记录。
     * 它使用了whereIn查询语句来筛选需要更新的记录，然后应用update方法进行更新。
     * 这种方法适用于需要一次性更新多个记录的场景，可以减少数据库操作的次数，提高效率。
     *
     * @param array $ids 产品ID数组，指定需要更新的产品范围
     * @param array $data 数据数组，包含需要更新的字段及其值
     */
    public function updates(array $ids, array $data)
    {
        // 获取模型实例并调用其数据库连接方法，然后使用whereIn和update进行批量更新
        $this->getModel()::getDb()->whereIn('product_id',$ids)->update($data);
    }

    /**
     * 批量更新产品的扩展字段
     *
     * 此方法用于根据提供的产品ID数组和更新数据数组，批量更新指定产品ID的扩展字段。
     * 首先，它将所有指定产品的扩展类型设置为1，然后根据产品ID分批获取产品列表，
     * 并对每个产品计算并更新其两个扩展字段的值。
     *
     * @param array $ids 产品ID数组，指定需要更新扩展字段的产品
     * @param array $data 更新数据数组，包含扩展字段'extension_one'和'extension_two'的倍数值
     */
    public function updatesExtension(array $ids, array $data)
    {
        // 设置指定产品ID的扩展类型为1
        app()->make(ProductRepository::class)->updates($ids,['extension_type' => 1]);

        // 构建查询，准备分批更新产品扩展字段
        $query = $this->getModel()::getDb()->where('product_id','in',$ids);

        // 分批处理产品列表，每次处理100条，用于更新扩展字段
        $query->chunk(100, function($list) use($data){
            foreach ($list as $item) {
                // 计算扩展字段的新值，并更新到数据库
                $arr['extension_one'] = bcmul($item->price,$data['extension_one'],2);
                $arr['extension_two'] = bcmul($item->price,$data['extension_two'],2);
                $this->getModel()::getDb()->where('unique',$item->unique)->update($arr);
            }
        },'product_id');
    }

    public function createReservation($value, array $reservationData)
    {
        foreach ($reservationData as &$item) {
            $item['attr_value_id'] = $value['value_id'];
            $item['product_id'] = $value['product_id'];
            $item['unique'] = $value['unique'];
        }
        ($this->getModel()::find($value['value_id']))->reservation()->saveAll($reservationData);
    }

    public function deleteReservation(int $productId)
    {
        $res = $this->getModel()::where('product_id', $productId)->with('reservation')->select();
        foreach ($res as $item) {
            $item->reservation()->delete();
        }
    }
}
