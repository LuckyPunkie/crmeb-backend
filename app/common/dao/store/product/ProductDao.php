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
use app\common\model\store\product\Product as model;
use app\common\repositories\store\product\SpuRepository;
use think\db\BaseQuery;
use think\db\exception\DbException;
use think\Exception;
use think\facade\Db;
use app\common\repositories\store\StoreCategoryRepository;
class ProductDao extends BaseDao
{
    protected function getModel(): string
    {
        return model::class;
    }

    /**
     * 创建或更新模型的属性。
     *
     * 本函数通过给定的模型ID，和一组属性数据，来创建或更新该模型的属性信息。
     * 它首先检索指定ID的模型实例，即使该模型已被删除（使用withTrashed()确保）。
     * 然后，它使用给定的数据批保存更新模型的属性。
     *
     * @param int $id 模型的唯一标识ID。
     * @param array $data 包含属性数据的数组，每个属性作为一个子数组。
     */
    public function createAttr(int $id, array $data)
    {
        ($this->getModel()::withTrashed()->find($id))->attr()->saveAll($data);
    }

    /**
     * 创建属性值
     *
     * 本函数用于根据提供的ID和数据数组创建属性值。它首先检索具有给定ID的模型实体，
     * 然后通过关联方法保存传入的数据数组。这个函数特别处理了软删除的情况，
     * 可以恢复和保存被软删除的模型的属性值。
     *
     * @param int $id 属性值关联模型的ID。这个ID用于定位特定的模型实例。
     * @param array $data 包含要保存的属性值数据的数组。每个元素都应该符合属性值的保存要求。
     */
    public function createAttrValue(int $id, array $data)
    {
        // 使用withTrashed()来包含软删除的记录, find($id)找回指定ID的记录，然后通过attrValue()方法关联属性值，最后使用saveAll()保存数据数组
        ($this->getModel()::withTrashed()->find($id))->attrValue()->saveAll($data);
    }

    /**
     * 创建或更新内容信息。
     *
     * 本函数通过给定的ID检索特定的模型实体，即使该实体已被删除（使用withTrashed()确保能检索到软删除的记录）。
     * 然后，它使用关联的方法(content())来保存或更新给定的数据数组到这个实体的内容字段。
     * 这种方法的设计允许在不直接触碰实体本身的情况下，灵活地处理与实体相关的内容数据。
     *
     * @param int $id 模型实体的唯一标识ID，用于检索特定的实体。
     * @param array $data 包含要保存或更新的内容数据的数组。
     */
    public function createContent(int $id, array $data)
    {
        ($this->getModel()::withTrashed()->find($id))->content()->save($data);
    }

    /**
     * 创建或更新预约商品信息
     *
     * @param integer $id
     * @param array $data
     * @return void
     */
    public function createReservation(int $id, array $data)
    {
        ($this->getModel()::withTrashed()->find($id))->reservation()->save($data);
    }

    /**
     * 检查指定字段的值在数据库中是否存在。
     *
     * 此函数用于确定给定字段的特定值是否在数据库中出现。它支持排除特定值的检查，
     * 以及针对特定商户ID的检查。这在处理数据唯一性或验证数据是否存在时非常有用。
     *
     * @param int|null $merId 商户ID，用于限定查询范围。如果为null，则不进行商户ID的筛选。
     * @param string $field 要检查的字段名。
     * @param string $value 要检查的字段值。
     * @param string|null $except 排除的特定值，如果不为null，则查询时会排除这个值。
     * @return bool 如果存在返回true，否则返回false。
     * @throws DbException
     */
    public function merFieldExists(?int $merId, $field, $value, $except = null)
    {
        // 使用withTrashed()确保查询时包括已删除的数据
        return model::withTrashed()->when($except, function ($query, $except) use ($field) {
                // 如果有需要排除的值，则添加不等于条件
                $query->where($field, '<>', $except);
            })->when($merId, function ($query, $merId) {
                // 如果提供了商户ID，则添加条件筛选特定商户的数据
                $query->where('mer_id', $merId);
            })->where($field, $value)->count() > 0;
    }

    /**
     * 检查指定字段的值在数据库中是否存在。
     *
     * 此函数用于通过字段值查询数据库记录，判断是否存在满足条件的记录。
     * 它支持排除特定的值和特定的商户ID，同时确保查询的记录状态为有效。
     *
     * @param int $merId 商户ID，可选参数，用于限定查询的商户范围。
     * @param string $field 要查询的字段名。
     * @param mixed $value 字段的值，用于查询条件。
     * @param mixed $except 排除的值，可选参数，用于不在指定值范围内的查询。
     * @return bool 如果存在满足条件的记录，则返回true；否则返回false。
     */
    public function apiFieldExists(int $merId, $field, $value, $except = null)
    {
        // 获取数据库实例
        $db = ($this->getModel())::getDB();

        // 如果有指定的排除值，添加不等于排除值的查询条件
        $db->when($except, function ($query, $except) use ($field) {
            $query->where($field, '<>', $except);
        });

        // 如果指定了商户ID，添加商户ID的查询条件
        $db->when($merId, function ($query, $merId) {
            $query->where('mer_id', $merId);
        });

        // 确保查询的状态为有效
        $db->where(['status' => 1]);

        // 添加字段值的查询条件
        $db->where($field, $value);

        // 判断满足所有条件的记录数是否大于0，存在则返回true，否则返回false
        return $db->count() > 0;
    }

    /**
     * 检查是否存在已被删除的商品
     *
     * 本函数用于确定给定商家ID和产品ID对应的商品是否曾经被删除过。
     * 这是通过查询数据库中被软删除（即使用了`onlyTrashed`方法）的商品记录来实现的。
     * 如果找到了匹配的被删除的商品记录，则说明该商品曾经被删除过，返回true；
     * 如果没有找到匹配的记录，则说明该商品从未被删除过，返回false。
     *
     * @param int $merId 商家ID，用于限定查询的商家范围
     * @param int $productId 产品ID，用于查询特定的产品
     * @return bool 如果存在被删除的商品记录则返回true，否则返回false
     */
    public function getDeleteExists(int $merId, int $productId)
    {
        // 使用onlyTrashed方法查询被删除的商品记录，限定查询条件为商家ID和产品ID
        // 然后通过count方法统计匹配记录的数量，判断是否存在被删除的商品
        return ($this->getModel())::onlyTrashed()->where('mer_id', $merId)->where($this->getPk(), $productId)->count() > 0;
    }

    /**
     * 根据条件搜索产品信息。
     *
     * 该方法用于构建并执行产品搜索查询。它根据传入的条件数组来筛选产品，
     * 并支持多种条件组合以满足不同的搜索需求。搜索条件包括但不限于产品属性、
     * 商家标识、状态、标签等。此外，方法还处理了不同条件下的查询逻辑，如软删除、
     * 商家关联、产品类型等。
     *
     * @param string $merId 商家ID，用于限制搜索范围。
     * @param array $where 搜索条件数组，包含各种过滤条件。
     * @return \think\db\Query 查询对象，可用于进一步的查询操作或获取结果。
     */
    public function search($merId, array $where, $search = '')
    {
        // 初始化用于构建查询条件的数组
        $keyArray = $whereArr = [];
        // 定义需要排除的搜索字段
        $out = ['soft', 'us_status', 'mer_labels', 'sys_labels', 'order', 'hot_type','cate_ids', 'is_action', 'seckill_active_id','spu_ids','activity_label_ids','is_trader'];

        // 遍历搜索条件，构建查询字段和值的映射
        foreach ($where as $key => $item) {
            if ($item !== '' && !in_array($key, $out)) {
                $keyArray[] = $key;
                $whereArr[$key] = $item;
            }
        }
        // 根据是否有软删除条件，构建查询对象
        $query = isset($where['soft']) ? model::onlyTrashed()->alias('Product')->where('delete', 0) : model::alias('Product');
        $query->where('Product.mer_status', 1);
        if($search) {
            $query->hasWhere('attrValue');
            $query->where(function ($query) use($search) {
                $query->hasWhere('attrValue', ['bar_code_number' => $search])->whereOr("Product.store_name|Product.product_id", "like", "%{$search}%");
            });
        }

        // 分组权限
        if (app('request')->hasMacro('regionAuthority') && $region = app('request')->regionAuthority()) {
            $query->whereIn('Product.mer_id', $region);
        }
        // 处理商家类型条件
        if (isset($where['is_trader']) && $where['is_trader'] !== '') {
            $query->hasWhere('merchant', function ($query) use ($where) {
                $query->where('Merchant.is_trader', $where['is_trader']);
            });
        }

        // 处理搜索关键字和关联查询
        $query->withSearch($keyArray, $whereArr)->Join('StoreSpu U', 'Product.product_id = U.product_id')->where('U.product_type', $where['product_type'] ?? 0);

        // 处理类别ID条件
        $query->when((isset($where['spu_ids']) && !empty($where['spu_ids'])), function ($query) use ($where) {
            $query->whereIn('spu_id',$where['spu_ids']);
        });

        // 处理类别ID条件
        $query->when((isset($where['cate_id']) && !empty($where['cate_id'])), function ($query) use ($where) {
            $query->where(['cate_id' => $where['cate_id']]);
        });
        $query->when((isset($where['cate_ids']) && !empty($where['cate_ids'])), function ($query) use ($where) {
            $cateIds = app()->make(StoreCategoryRepository::class)->allChildren($where['cate_ids']);
            $query->whereIn('cate_id', $cateIds);
        });

        // 处理秒杀活动ID条件
        $query->when((isset($where['seckill_active_id']) && is_array($where['seckill_active_id']) && !empty($where['seckill_active_id'])), function ($query) use ($where) {
            $query->whereIn('Product.active_id', $where['seckill_active_id']);
        });
        $query->when((isset($where['seckill_active_id']) && !is_array($where['seckill_active_id']) && $where['seckill_active_id']), function ($query) use ($where) {
            $query->where('Product.active_id', $where['seckill_active_id']);
        });

        // 处理商家ID条件
        $query->when($merId, function ($query) use ($merId) {
            if (is_array($merId)) {
                $query->whereIn('Product.mer_id', $merId);
            } else if (!is_array($merId)) {
                $query->where('Product.mer_id', $merId);
            }
        })
            // 处理热门类型条件
            ->when(isset($where['hot_type']) && $where['hot_type'] !== '', function ($query) use ($where) {
                if ($where['hot_type'] == 'new')
                    $query->where('is_new', 1);
                else if ($where['hot_type'] == 'hot')
                    $query->where('is_hot', 1);
                else if ($where['hot_type'] == 'best')
                    $query->where('is_best', 1);
                else if ($where['hot_type'] == 'good')
                    $query->where('is_benefit', 1);
            })
            // 处理使用状态条件
            ->when(isset($where['us_status']) && $where['us_status'] !== '', function ($query) use ($where) {
                if ($where['us_status'] == 0) {
                    $query->where('Product.is_show', 0)->where('Product.is_used', 1)->where('Product.status', 1);
                }
                if ($where['us_status'] == 1) {
                    $query->where('Product.is_show', 1)->where('Product.is_used', 1)->where('Product.status', 1);
                }
                if ($where['us_status'] == -1) {
                    $query->where(function ($query) {
                        $query->where('Product.is_used', 0)->whereOr('Product.status', '<>', 1);
                    });
                }
            })
            // 处理标签条件
            ->when(isset($where['mer_labels']) && $where['mer_labels'] !== '', function ($query) use ($where) {
                $query->whereLike('U.mer_labels', "%,{$where['mer_labels']},%");
            })
            // 处理活动商品条件
            ->when(isset($where['is_action']) && $where['is_action'] !== '', function ($query) use ($where) {
                $query->where('type', '<>', 2);
            })
            // 处理系统标签条件
            ->when(isset($where['sys_labels']) && $where['sys_labels'] !== '', function ($query) use ($where) {
                $query->whereLike('U.sys_labels', "%,{$where['sys_labels']},%");
            })
            // 处理VIP价格类型条件
            ->when(isset($where['svip_price_type']) && $where['svip_price_type'] !== '', function ($query) use ($where) {
                $query->where('Product.svip_price_type', $where['svip_price_type']);
            })
            // 处理产品类型条件
            ->when(isset($where['product_type']) && $where['product_type'] == 1, function ($query) use ($where) {
                $query->where('active_id', '>', 0);
            })
            // 处理排序条件
            ->when(isset($where['order']), function ($query) use ($where) {
                $order = (string)$where['order'];
                $orderMap = [
                    'price_asc' => 'price ASC',
                    'price_desc' => 'price DESC',
                    'is_new' => 'is_new DESC',
                    'rate' => 'rate DESC',
                    'sales' => 'sales DESC',
                ];

                if ($order === '') {
                    $query->orderRaw('U.create_time DESC');
                    return;
                }

                if (isset($orderMap[$order])) {
                    $query->orderRaw($orderMap[$order] . ',rank DESC ,create_time DESC ');
                    return;
                }

                $query->orderRaw('U.' . $order . ' DESC,U.create_time DESC');
            })
            // 处理星级条件
            ->when(isset($where['star']), function ($query) use ($where) {
                if ($where['star'] !== '') {
                    $query->where('U.star', $where['star']);
                }
                $query->orderRaw('U.star DESC,U.rank DESC,Product.create_time DESC');
            })
            // 处理排序条件
            ->when(isset($where['sort']), function ($query) use ($where) {
                if ($where['sort'] !== '') {
                    $query->where('U.sort', $where['sort']);
                }
                $query->orderRaw('U.sort DESC,Product.create_time DESC');
            })
            // 处理优质产品条件
            ->when(isset($where['is_good']) && $where['is_good'] !== '', function ($query) use ($where) {
                $query->where('Product.is_good', $where['is_good']);
            })
            // 处理活动标签条件
            ->when(isset($where['activity_label_ids']) && $where['activity_label_ids'] !== '', function ($query) use ($where) {
                $activityLabelIds = is_string($where['activity_label_ids']) ? explode(',',$where['activity_label_ids']) : $where['activity_label_ids'];
                foreach($activityLabelIds as $item) {
                    $query->whereFindInSet('Product.activity_label_ids', $item);
                }
            });

        return $query;
    }

    /**
     * 搜索秒杀活动商品
     *
     * 本函数用于构建查询秒杀活动商品的条件，根据传入的$where数组来筛选符合条件的秒杀活动及商品。
     * 这其中包括对秒杀活动状态的检查，商品的各种状态过滤，以及针对商家和活动ID的条件查询。
     * 最后返回构建好的查询对象，可用于进一步的查询操作或数据获取。
     *
     * @param array $where 查询条件数组，包含各种过滤条件如日期、时间、商家ID、活动ID等。
     * @return \think\db\Query 返回构建好的查询对象，包含所有设定的查询条件。
     */
    public function seckillSearch(array $where)
    {
        // 根据$where数组中的条件构建秒杀活动（seckillActive）的查询条件
        $query = model::hasWhere('seckillActive', function ($query) use ($where) {
            // 仅查询状态为1（有效）的秒杀活动
            $query->where('status', 1);
            // 注释掉的代码是原本用于查询日期范围的条件，根据具体业务需求可能启用或禁用
        });

        // 加入关联查询，关联商品表中的SPU信息，过滤出产品类型为1的商品
        $query->join('StoreSpu U', 'Product.product_id = U.product_id')->where('U.product_type', 1);

        // 设置一系列固定的查询条件，包括商品的状态、是否展示、是否可用、商家状态等
        $query->where([
            'Product.is_show' => 1,
            'Product.status' => 1,
            'Product.is_used' => 1,
            'Product.mer_status' => 1,
            'Product.product_type' => 1,
            'Product.is_gift_bag' => 0,
        ])
            // 当$where数组中包含mer_id时，添加商家ID的查询条件
            ->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
                if(is_array($where['mer_id'])) {
                    $query->whereIn('Product.mer_id', $where['mer_id']);
                } else {
                    $query->where('Product.mer_id', $where['mer_id']);
                }
            })
            // 当$where数组中包含active_id且不为空（可能是数组）时，添加活动ID的查询条件
            ->when(isset($where['active_id']) && !empty($where['active_id']) && is_array($where['active_id']), function ($query) use ($where) {
                $query->whereIn('Product.active_id', $where['active_id']);
            })
            // 当$where数组中包含star时，添加商品星级的查询条件，并按星级和排名排序
            ->when(isset($where['star']) && $where['star'] !== '', function ($query) use ($where) {
                $query->where('U.star', $where['star']);
                $query->order('U.star DESC,Product.rank DESC');
            })
            ;

        // 返回构建好的查询对象
        return $query;
    }

    /**
     * 删除指定ID的记录。
     *
     * 此方法提供了软删除和硬删除两种方式，软删除会将记录移动到回收站，硬删除则会直接从数据库中删除。
     * 同时，此方法还会更新关联的SPU信息，并触发一个产品删除事件。
     *
     * @param int $id 需要删除的记录的ID。
     * @param bool $soft 是否执行软删除，默认为false表示硬删除。
     */
    public function delete(int $id, $soft = false)
    {
        // 根据$soft参数决定使用软删除还是硬删除
        if ($soft) {
            // 执行软删除，找到指定ID的回收站中的记录，并强制删除
            (($this->getModel())::onlyTrashed()->find($id))->force()->delete();
        } else {
            // 执行硬删除，通过更新is_del字段为1来标记删除
            $this->getModel()::where($this->getPk(), $id)->update(['is_del' => 1]);
        }
        // 更新关联的SPU信息，将其标记为删除状态并禁用
        app()->make(SpuRepository::class)
            ->getSearch(['product_id' => $id])
            ->update(['is_del' => 1, 'status' => 0]);
        // 触发产品删除事件，传递删除的ID作为参数
        event('product.delete', compact('id'));
    }


    /**
     * 删除产品及相关信息。
     *
     * 本函数用于彻底删除指定ID的产品及其在搜索索引中的信息，并触发相应的删除事件。
     * 具体操作包括：
     * 1. 更新数据库中该产品的删除状态为1，实现逻辑删除。
     * 2. 删除搜索索引中该产品的信息，确保搜索结果不再包含该产品。
     * 3. 触发产品删除事件，允许其他监听器对此操作做出响应。
     *
     * @param int $id 产品的ID。
     */
    public function destory(int $id)
    {
        try {
            // 更新数据库中指定产品ID的删除状态为1，同时处理已删除的记录。
            $this->getModel()::withTrashed()->where('product_id', $id)->update(['delete' => 1]);

            // 删除搜索索引中与指定产品ID相关的信息。
            app()->make(SpuRepository::class)->getSearch(['product_id' => $id])->delete();

            // 触发产品删除事件，传递删除的产品ID作为参数。
            event('product.delete', compact('id'));
        } catch (Exception $e) {
            // 捕获并处理任何异常，避免删除操作影响到整个程序的执行。
        }

    }

    /**
     * 恢复已删除的资源。
     *
     * 此方法用于恢复数据库中被软删除的记录。它首先通过给定的ID找到被删除的记录，
     * 然后将其恢复到原始状态。这个过程涉及到两个主要步骤：
     * 1. 使用onlyTrashed()方法定位到被删除的记录，并通过find()方法找到特定ID的记录。
     * 2. 调用restore()方法来恢复该记录到原来的表中。
     *
     * @param int $id 要恢复的资源的ID。
     * @return \Illuminate\Database\Eloquent\Model 恢复的资源模型。
     */
    public function restore($id)
    {
        // 通过ID找到被删除的资源。
        $res = ($this->getModel())::onlyTrashed()->find($id);

        // 从产品仓库中删除该产品，将其状态重置为未删除。
        app()->make(SpuRepository::class)->delProduct($id, 0);

        // 恢复该资源到原始状态。
        return $res->restore();
    }

    /**
     * 批量恢复商品
     * @param int $merId 商户ID
     * @param array $ids 商品ID数组
     */
    public function batchRestore(int $merId, array $ids)
    {
        // Query 没有 restore 方法，需取出模型实例后逐条恢复
        $list = ($this->getModel())::onlyTrashed()
            ->where('mer_id', $merId)
            ->whereIn('product_id', $ids)
            ->select();

        foreach ($list as $item) {
            $item->restore();
        }

        // 批量更新 SPU is_del = 0
        app()->make(SpuRepository::class)->batchDelProduct($ids, 0);
    }

    /**
     * 获取只被软删除的数据
     *
     * 本函数用于查询特定条件下的只有被软删除的数据。它利用了Laravel框架的Eloquent ORM提供的onlyTrashed方法，
     * 该方法用于获取仅被软删除的数据。通过结合where方法和order方法，可以对查询条件和结果排序进行进一步的定制。
     *
     * @param array|string $where 查询条件，可以是数组或字符串形式的SQL WHERE子句。
     * @return \Illuminate\Database\Eloquent\Builder|static 只被软删除的数据的查询构建器。
     */
    public function getOnlyTranshed($where)
    {
        // 调用getModel方法获取模型实例，并立即使用onlyTrashed方法查询只被软删除的数据
        // 然后使用where方法添加额外的查询条件，最后使用order方法按照product_id降序排序
        return ($this->getModel())::onlyTrashed()->where($where)->order('product_id DESC');
    }

    /**
     * 切换实体的状态
     *
     * 本函数用于根据给定的ID和新的状态数组，更新数据库中相应实体的状态。
     * 它首先通过getModel方法获取当前实体的模型，然后利用模型的getDB方法获取数据库连接。
     * 最后，使用where方法指定ID条件，并通过update方法更新实体的状态。
     *
     * @param int $id 实体的唯一标识符，用于在数据库中定位特定的实体。
     * @param array $status 包含新状态值的数组，这些值将被更新到数据库中。
     * @return int 返回更新操作影响的行数，用于确认更新是否成功。
     */
    public function switchStatus(int $id, array $status)
    {
        // 通过模型和数据库操作符更新实体的状态
        return ($this->getModel()::getDB())->where($this->getPk(), $id)->update($status);
    }

    /**
     * 根据商家ID和产品ID数组，获取对应的产品图片信息
     *
     * 此函数用于查询数据库，获取指定商家ID下，指定产品ID列表中每个产品的ID和图片信息。
     * 主要用于在前端展示产品图片，或者进行与产品图片相关的操作。
     *
     * @param int $merId 商家ID，用于限定查询的商家范围。
     * @param array $productIds 产品ID数组，指定需要查询的产品。
     * @return array 返回一个包含产品ID和图片信息的数组。
     */
    public function productIdByImage(int $merId, array $productIds)
    {
        // 使用模型的数据库访问方法，查询满足条件的产品ID和图片信息
        return model::getDB()->where('mer_id', $merId)->whereIn('product_id', $productIds)->column('product_id,image');
    }

    /**
     * 获取与给定ID数组交集的产品ID数组
     *
     * 本函数用于查询数据库中，产品表中产品ID存在于给定ID数组中的所有产品ID。
     * 通过使用whereIn查询条件，筛选出产品ID在给定数组中的记录，并只返回product_id列的值。
     *
     * @param array $ids 给定的产品ID数组，用于查询的条件
     * @return array 返回查询结果中product_id列的值组成的数组，即与给定ID数组的交集
     */
    public function intersectionKey(array $ids): array
    {
        // 使用model的getDB方法获取数据库对象，然后通过whereIn方法查询产品表中product_id在$ids数组中的记录，最后使用column方法仅返回product_id列的值
        return model::getDB()->whereIn('product_id', $ids)->column('product_id');
    }

    /**
     * 根据产品ID获取商家ID
     *
     * 本函数旨在通过产品ID查询数据库，获取对应产品的商家ID。
     * 使用了模型类的数据库操作方法，通过指定的条件查询数据库，并返回查询结果中特定列的值。
     *
     * @param int $id 产品ID，作为查询条件
     * @return int 商家ID，如果找不到对应产品则返回null
     */
    public function productIdByMerId($id)
    {
        // 使用模型的数据库操作方法，根据产品ID查询商家ID
        return model::getDB()->where('product_id', $id)->value('mer_id');
    }

    /**
     * 减少产品库存并增加销售数量
     *
     * 该方法用于更新数据库中指定产品的库存和销售数量。
     * 它通过减少库存量和增加销售量来反映产品的销售情况。
     *
     * @param int $productId 产品的ID，用于定位特定的产品记录。
     * @param int $desc 库存减少的数量，同时也是销售数量增加的数量。
     * @return mixed 返回数据库更新操作的结果，可能是布尔值或影响的行数。
     * @throws DbException
     */
    public function descStock(int $productId, int $desc)
    {
        // 使用Db::raw来执行原生的SQL片段，这里用于更新库存和销售数量。
        // 这种方法允许直接操作数据库字段的值，而不是通过变量绑定。
        return model::getDB()->where('product_id', $productId)->update([
            'stock' => Db::raw('stock-' . $desc),
            'sales' => Db::raw('sales+' . $desc)
        ]);
    }

    /**
     * 增加商品库存并减少对应销售量
     * 此函数用于在数据库中增加指定商品的库存量，并同时减少该商品的销售量。
     * 这种操作常见于处理商品退货或库存调整的情况，需要在库存和销售数据中做出相应的调整。
     *
     * @param int $productId 商品ID，用于指定需要调整库存的商品。
     * @param int $inc 库存增加的数量。此参数同时用于增加库存和减少销售量。
     */
    public function incStock(int $productId, int $inc)
    {
        // 增加商品库存
        model::getDB()->where('product_id', $productId)->inc('stock', $inc)->update();
        // 减少商品销售量，仅针对之前已销售的数量大于等于当前增加的库存量的部分进行调整
        model::getDB()->where('product_id', $productId)->where('sales', '>=', $inc)->dec('sales', $inc)->update();
    }


    /**
     * 减少商品销量
     *
     * 该方法用于更新数据库中指定商品的销量，将其减少指定的数量。
     * 主要用于处理商品销售时的库存更新或其他需要减少销量的场景。
     *
     * @param int $productId 商品ID，用于定位要更新销量的商品。
     * @param int $desc 销量减少的数量，一个正整数，表示要从当前销量中减去的数值。
     * @return mixed 返回数据库操作的结果，可能是布尔值或影响行数。
     * @throws DbException
     */
    public function descSales(int $productId, int $desc)
    {
        // 使用Db::raw处理SQL中的减法操作，确保操作的安全性和正确性。
        return model::getDB()->where('product_id', $productId)->update([
            'sales' => Db::raw('sales-' . $desc)
        ]);
    }


    /**
     * 增加商品销量
     *
     * 该方法用于更新指定商品的销量，销量增加的数值由参数$inc指定。
     * 通过传入商品ID($productId)来定位到特定商品，并对销量进行增加操作。
     * 使用Db::raw()来构建SQL的计算表达式，确保销量是原销量基础上增加$inc。
     *
     * @param int $productId 商品ID，用于确定要更新销量的具体商品。
     * @param int $inc 销量增加的数值，表示要将商品的销量增加多少。
     * @return bool 更新操作的结果，成功返回true，失败返回false。
     * @throws DbException
     */
    public function incSales(int $productId, int $inc)
    {
        // 根据商品ID更新数据库中对应商品的销量，销量增加$inc
        return model::getDB()->where('product_id', $productId)->update([
            'sales' => Db::raw('sales+' . $inc)
        ]);
    }

    /**
     * 减少商品的积分总量和积分价格总量
     *
     * 本函数用于更新数据库中指定商品的积分总量和积分价格总量，实现减少操作。
     * 主要用于处理商品积分的扣减逻辑，例如在商品退货、积分抵扣减少等场景下。
     *
     * @param int $productId 商品ID，用于定位要更新的商品记录。
     * @param int $integral_total 需要减少的积分总量，表示本次操作将从商品的积分总量中减去的数值。
     * @param int $integral_price_total 需要减少的积分价格总量，表示本次操作将从商品的积分价格总量中减去的数值。
     * @return int 返回更新操作的影响行数，用于判断操作是否成功。
     * @throws DbException
     */
    public function descIntegral(int $productId, $integral_total, $integral_price_total)
    {
        // 使用Db::raw包裹更新表达式，直接在SQL中进行减法操作，避免因类型转换等问题造成的错误。
        // 通过where条件定位到指定ID的商品记录，然后更新其积分总量和积分价格总量。
        return model::getDB()->where('product_id', $productId)->update([
            'integral_total' => Db::raw('integral_total-' . $integral_total),
            'integral_price_total' => Db::raw('integral_price_total-' . $integral_price_total),
        ]);
    }

    /**
     * 增加产品的积分和积分金额
     *
     * 该方法用于更新数据库中指定产品的积分总数和积分金额总数。
     * 它通过传入的产品ID定位到特定的产品记录，然后分别增加积分总数和积分金额总数。
     * 这种设计用于在进行相关交易或操作时，自动更新产品的积分相关数据，保持数据的实时性。
     *
     * @param int $productId 产品的ID，用于在数据库中定位到特定产品记录。
     * @param int $integral_total 需要增加的积分总数，表示产品积分的增量。
     * @param int $integral_price_total 需要增加的积分金额总数，表示产品积分金额的增量。
     */
    public function incIntegral(int $productId, $integral_total, $integral_price_total)
    {
        // 使用模型的数据库访问方法，定位到指定产品ID的记录，然后分别增加积分总数和积分金额总数，并执行更新操作。
        model::getDB()->where('product_id', $productId)->inc('integral_total', $integral_total)->inc('integral_price_total', $integral_price_total)->update();
    }

    /**
     * 访问产品组的方法
     * 该方法用于查询指定日期内，每个产品组的访问量和相关信息。
     * 可以根据日期和商家ID进行过滤，返回最近访问量最高的产品组列表。
     *
     * @param string $date 查询的日期，格式为YYYY-MM-DD。如果不提供，则查询最近7天的数据。
     * @param int $merId 商家ID，可选参数。如果提供了商家ID，则只查询该商家的产品组。
     * @param int $limit 返回结果的数量限制，默认为7。
     * @return array 返回一个包含产品组信息的数组，每个元素包含产品ID、商店名称、图片和访问量。
     */
    public function visitProductGroup($date, $merId = null, $limit = 7)
    {
        // 从数据库中获取数据
        return model::getDB()->alias('A')->leftJoin('UserRelation B', 'A.product_id = B.type_id')
            ->field(Db::raw('count(B.type_id) as total,A.product_id,A.store_name,A.image'))
            ->when($date, function ($query, $date) {
                // 如果提供了日期，则根据日期筛选数据
                getModelTime($query, $date, 'B.create_time');
            })->when($merId, function ($query, $merId) {
                // 如果提供了商家ID，则根据商家ID筛选数据
                $query->where('A.mer_id', $merId);
            })->where('B.type', 1)->group('A.product_id')->limit($limit)->order('total DESC')->select();
    }

    /**
     * 获取购物车产品分组信息
     * 该方法用于查询指定日期内，每个产品的购物车数量总和，可选地根据商家ID进行筛选。
     * 结果将按照购物车数量降序排列，并限制返回结果的数量。
     *
     * @param string $date 查询的日期，格式为YYYY-MM-DD。用于筛选在指定日期内添加到购物车的产品。
     * @param int|null $merId 商家ID，可选参数。用于筛选属于特定商家的产品。
     * @param int $limit 返回结果的数量限制，默认为7。用于限制返回的产品分组数量。
     * @return array 返回一个包含产品分组信息的数组，每个分组包含产品ID、商家名称、产品图片和购物车总数。
     */
    public function cartProductGroup($date, $merId = null, $limit = 7)
    {
        // 从数据库中获取数据
        return model::getDB()->alias('A')->leftJoin('StoreCart B', 'A.product_id = B.product_id')
            ->field(Db::raw('sum(B.cart_num) as total,A.product_id,A.store_name,A.image'))
            // 当传入日期时，根据日期筛选数据
            ->when($date, function ($query, $date) {
                getModelTime($query, $date, 'B.create_time');
            })
            // 当传入商家ID时，根据商家ID筛选数据
            ->when($merId, function ($query, $merId) {
                $query->where('A.mer_id', $merId);
            })
            // 筛选条件：产品类型为0，未支付，未删除，非新购，未失败
            ->where('B.product_type', 0)->where('B.is_pay', 0)->where('B.is_del', 0)
            ->where('B.is_new', 0)->where('B.is_fail', 0)
            // 按产品ID分组，限制返回结果的数量，并按购物车总数降序排列
            ->group('A.product_id')->limit($limit)->order('total DESC')->select();
    }

    /**
     * 更新商家产品的信息
     *
     * 本函数用于根据给定的商家ID和数据集，更新对应商家产品的信息。
     * 它通过查询数据库中与给定商家ID匹配的记录，并应用提供的数据更新。
     *
     * @param string $merId 商家的唯一标识符。用于确定要更新哪个商家的产品信息。
     * @param array $data 包含要更新的产品信息的数据数组。数组的键值对表示要更新的字段及其新值。
     */
    public function changeMerchantProduct($merId, $data)
    {
        // 通过模型获取数据库实例，并使用where子句指定mer_id，然后执行更新操作。
        ($this->getModel()::getDB())->where('mer_id', $merId)->update($data);
    }

    /**
     * 增加产品关注数
     *
     * 该方法用于指定产品的关注数增加1。它通过调用getModel方法获取模型实例，
     * 进而连接数据库，并根据产品的主键($productId)定位到相应记录，
     * 然后将该记录的care_count字段值增加1。
     *
     * @param int $productId 产品的唯一标识符，用于在数据库中定位到具体产品记录。
     */
    public function incCareCount(int $productId)
    {
        // 通过模型实例的getDB方法获取数据库连接，然后使用where方法指定条件，
        // 使用inc方法增加care_count字段的值，并通过update方法更新数据库记录。
        ($this->getModel()::getDB())->where($this->getPk(), $productId)->inc('care_count', 1)->update();
    }

    /**
     * 减少指定产品关注数
     *
     * 此方法用于减少数据库中指定产品ID的关注数。它首先通过产品ID数组筛选出相关记录，
     * 然后针对这些记录将关注数减少1。这种方法适用于批量处理，例如在用户取消关注多个产品时。
     *
     * @param array $productId 产品ID数组，表示需要减少关注数的产品集合。
     */
    public function decCareCount(array $productId)
    {
        // 检查$productId是否为空，避免不必要的数据库操作
        if (empty($productId)) {
            return; // 或者记录日志、抛出异常等，根据实际需求决定
        }
        // 通过模型获取数据库实例，并使用whereIn和where条件构建查询，然后减少care_count列的值并更新记录。
        ($this->getModel()::getDB())->whereIn($this->getPk(), $productId)->where('care_count', '>', 0)->dec('care_count', 1)->update();
    }
    /**
     * 获取商品展示配置
     *
     * 该方法用于返回一个配置数组，定义了商品在前端的展示行为和状态。
     * 返回的配置包括：
     * - is_show: 商品是否上架，值为1表示上架。
     * - status: 商品审核状态，值为1表示审核通过。
     * - is_used: 商品是否启用，值为1表示启用。
     * - product_type: 商品类型，值为0表示普通商品。
     * - mer_status: 商铺状态，值为1表示商铺正常运营。
     * - is_gift_bag: 商品是否为礼盒，值为0表示不是礼盒。
     *
     * @return array 商品展示配置数组
     */
    public function productShow()
    {
        return [
            'is_show' => 1,   // 上架
            'status' => 1,   // 审核通过
            'is_used' => 1,  // 显示
            'product_type' => 0, // 普通商品
            'mer_status' => 1,  //商铺状态正常
            'is_gift_bag' => 0,  //不是礼包
        ];
    }

    /**
     *  api展示的礼包商品条件
     * @return array
     * @author Qinii
     * @day 2020-08-18
     */
    public function bagShow()
    {
        return [
            'is_show' => 1,
            'status' => 1,
            'is_used' => 1,
            'mer_status' => 1,
            'product_type' => 0,
            'is_gift_bag' => 1,
        ];
    }

    /**
     *  api展示的秒杀商品条件
     * @return array
     * @author Qinii
     * @day 2020-08-18
     */
    public function seckillShow()
    {
        return [
            'is_show' => 1,
            'status' => 1,
            'is_used' => 1,
            'mer_status' => 1,
            'product_type' => 1,
            'is_gift_bag' => 0,
        ];
    }

    /**
     * 根据产品ID获取产品类型，并检查是否与传入的现有类型匹配。
     *
     * 此方法主要用于查询数据库中指定产品ID对应的产品类型，并根据需求判断是否与预设的存在类型相匹配。
     * 如果传入的存在类型不为空，则会在查询时添加一个额外的条件来筛选产品类型。
     * 方法返回一个布尔值，表示查询到的产品类型是否为0（即true表示是，false表示不是）。
     *
     * @param int $productId 产品的唯一标识ID。
     * @param int|null $exsistType 存在的产品类型ID，用于查询时的条件筛选。
     * @return bool 如果查询到的产品类型为0，则返回true，否则返回false。
     */
    public function getProductTypeById(int $productId, ?int $exsistType)
    {
        // 通过模型获取数据库实例，并根据条件构造查询语句。
        $product_type = $this->getModel()::getDB()
            ->when($exsistType, function ($query) use ($exsistType) {
                // 如果存在类型ID，则在查询时添加对应的产品类型条件。
                $query->where('product_type', $exsistType);
            })
            ->where($this->getPk(), $productId) // 根据产品ID进行查询。
            ->where('is_del', 0) // 排除已删除的产品。
            ->value('product_type'); // 只返回产品类型这一列的值。

        // 判断查询到的产品类型是否为0，返回相应的布尔值。
        return $product_type == 0 ? true : false;
    }


    /**
     * 根据产品ID获取失败的产品信息
     *
     * 本函数旨在通过产品ID检索特定产品的详细信息。特别地，它包括了产品是否被删除的状态，
     * 这是通过使用`withTrashed`方法来实现的，意味着即使产品已被标记为删除，也能被检索到。
     * 它返回的产品信息精简到了最相关和必要的字段，以提高查询效率和数据使用的针对性。
     *
     * @param int $productId 产品ID，用于精确查找特定产品。
     * @return \think\Model|null 返回匹配给定产品ID的模型对象，如果找不到则返回null。
     */
    public function getFailProduct(int $productId)
    {
        // 使用withTrashed确保可以查询到已删除的数据
        // 精选查询字段，只获取必要信息，提高查询效率
        return $this->getModel()::withTrashed()->field('product_type,product_id,image,store_name,is_show,status,is_del,unit_name,price,mer_status,is_used,mer_form_id')->find($productId);
    }

    /**
     * 获取已删除的产品信息
     *
     * 本方法用于查询一个特定ID的产品，包括已经被软删除的产品。通过使用`withTrashed`方法，可以包含已被删除的数据在查询结果中。
     * 这对于需要恢复已删除数据或者查看删除状态下的数据是非常有用的。
     *
     * @param int $id 产品的唯一标识ID
     * @return \Illuminate\Database\Eloquent\Model 返回查询到的产品模型，可能包括已被删除的产品。
     */
    public function geTrashedtProduct(int $id)
    {
        // 使用withTrashed方法包含软删除的记录，并根据主键ID查询产品
        return model::withTrashed()->where($this->getPk(), $id);
    }


    /**
     *  获取各种有效时间内的活动
     * @param int $productType
     * @return BaseQuery
     *
     * @date 2023/09/22
     * @author yyw
     */
    public function activitSearch(int $productType)
    {
        $query = model::getDB()->alias('P')
            ->where('P.is_del', 0)
            ->where('P.mer_status', 1)
            ->where('P.product_type', $productType);
        switch ($productType) {
            case 0:
                // $query->where('P.is_show',1)
                //     ->where('P.is_used',1)
                //     ->field('product_id,product_type,mer_id,store_name,keyword,price,rank,sort,image,status,temp_id');
                break;
            case 1:
                $query->join('StoreSeckillActive S', 'S.seckill_active_id = P.active_id')
                    ->field('P.*,S.status,S.seckill_active_id,S.end_time');
                break;
            case 2:
                $query->join('StoreProductPresell R', 'R.product_id = P.product_id')
                    ->where('R.is_del', 0)
                    ->field('P.*,R.product_presell_id,R.store_name,R.price,R.status,R.is_show,R.product_status,R.action_status');
                break;
            case 3:
                $query->join('StoreProductAssist A', 'A.product_id = P.product_id')
                    ->where('A.is_del', 0)
                    ->field('P.*,A.product_assist_id,A.store_name,A.status,A.is_show,A.product_status,A.action_status');
                break;
            case 4:
                $query->join('StoreProductGroup G', 'G.product_id = P.product_id')
                    ->where('G.is_del', 0)
                    ->field('P.*,G.product_group_id,G.price,G.status,G.is_show,G.product_status,G.action_status');
                break;
            default:
                break;
        }
        return $query;
    }


    public function commandChangeProductStatus($data)
    {
        $ret = [];

        foreach ($data as $item) {
            $status = 0;
            switch ($item['product_type']) {
                case 0:
                    if ($item['is_show'] && $item['is_used']) $status = 1;
                    $ret[] = [
                        'activity_id' => 0,
                        'product_id' => $item['product_id'],
                        'mer_id' => $item['mer_id'],
                        'keyword' => $item['keyword'],
                        'price' => $item['price'],
                        'rank' => $item['rank'],
                        'sort' => $item['sort'],
                        'image' => $item['image'],
                        'status' => $status,
                        'temp_id' => $item['temp_id'],
                        'store_name' => $item['store_name'],
                        'product_type' => $item['product_type'],
                    ];
                    break;
                case 1:
                    if ($item['is_show'] && $item['is_used'] && $item['status'] && ($item['end_time'] > time())) $status = 1;
                    $ret[] = [
                        'activity_id' => $item['seckill_active_id'],
                        'product_id' => $item['product_id'],
                        'mer_id' => $item['mer_id'],
                        'keyword' => $item['keyword'],
                        'price' => $item['price'],
                        'rank' => $item['rank'],
                        'sort' => $item['sort'],
                        'image' => $item['image'],
                        'status' => $status,
                        'temp_id' => $item['temp_id'],
                        'store_name' => $item['store_name'],
                        'product_type' => $item['product_type'],
                    ];
                    break;
                case 2:
                    if ($item['is_show'] && $item['action_status'] && $item['status'] && $item['product_status']) $status = 1;
                    $ret[] = [
                        'activity_id' => $item['product_presell_id'],
                        'product_id' => $item['product_id'],
                        'mer_id' => $item['mer_id'],
                        'keyword' => $item['keyword'],
                        'price' => $item['price'],
                        'rank' => $item['rank'],
                        'sort' => $item['sort'],
                        'image' => $item['image'],
                        'status' => $status,
                        'temp_id' => $item['temp_id'],
                        'store_name' => $item['store_name'],
                        'product_type' => $item['product_type'],
                    ];
                    break;
                case 3:
                    if ($item['is_show'] && $item['action_status'] && $item['status'] && $item['product_status']) $status = 1;
                    $ret[] = [
                        'activity_id' => $item['product_assist_id'],
                        'product_id' => $item['product_id'],
                        'mer_id' => $item['mer_id'],
                        'keyword' => $item['keyword'],
                        'price' => $item['price'],
                        'rank' => $item['rank'],
                        'sort' => $item['sort'],
                        'image' => $item['image'],
                        'status' => $status,
                        'temp_id' => $item['temp_id'],
                        'store_name' => $item['store_name'],
                        'product_type' => $item['product_type'],
                    ];
                    break;
                case 4:
                    if ($item['is_show'] && $item['action_status'] && $item['status'] && $item['product_status']) $status = 1;
                    $ret[] = [
                        'activity_id' => $item['product_group_id'],
                        'product_id' => $item['product_id'],
                        'mer_id' => $item['mer_id'],
                        'keyword' => $item['keyword'],
                        'price' => $item['price'],
                        'rank' => $item['rank'],
                        'sort' => $item['sort'],
                        'image' => $item['image'],
                        'status' => $status,
                        'temp_id' => $item['temp_id'],
                        'store_name' => $item['store_name'],
                        'product_type' => $item['product_type'],
                    ];
                    break;
                default:
                    if ($item['is_show'] && $item['is_used']) $status = 1;
                    $ret[] = [
                        'activity_id' => 0,
                        'product_id' => $item['product_id'],
                        'mer_id' => $item['mer_id'],
                        'keyword' => $item['keyword'],
                        'price' => $item['price'],
                        'rank' => $item['rank'],
                        'sort' => $item['sort'],
                        'image' => $item['image'],
                        'status' => $status,
                        'temp_id' => $item['temp_id'],
                        'store_name' => $item['store_name'],
                        'product_type' => $item['product_type'],
                    ];
                    break;
            }
        }
        return $ret;
    }

    /**
     *  软删除商户的所有商品
     * @param $merId
     * @author Qinii
     * @day 5/15/21
     */
    public function clearProduct($merId)
    {
        $this->getModel()::withTrashed()->where('mer_id', $merId)->delete();
    }

    /**
     * 获取好物推荐列表
     * @param array|null $good_ids
     * @param $is_mer
     * @return array|mixed
     *
     * @date 2023/10/30
     * @author yyw
     */
    public function getGoodList(array $good_ids, int $merId, $is_show = true)
    {
        if (empty($good_ids) && !$is_show) return [];
        $filed = 'product_id,image,store_name,price,create_time,is_gift_bag,is_good,is_show,mer_id,sales,status,activity_label_ids,delivery_free,give_coupon_ids,delivery_way,type,product_type';
        $where = [];
        $limit = 30;
        if ($is_show) {
            $where = $this->productShow();
            $limit = 18;
        }
        $query = $this->getModel()::getDB()->where('mer_id', $merId)->where($where)
            ->when(!empty($good_ids), function ($query) use ($good_ids) {
                $query->whereIn($this->getPk(), $good_ids);
            })->field($filed);
        $list = $query->limit($limit)->select()->toArray();
        return $list;
    }

    public function deleteProductFormByFormId(int $form_id, int $mer_id)
    {
        return $this->getModel()::getDB()->where('mer_form_id', $form_id)->where('mer_id', $mer_id)->update(['mer_form_id' => 0]);
    }
    /**
     * 商品详情推荐商品列表
     *
     * @param array $goodIds
     * @param integer $merId
     * @param integer $recommendNum
     * @return void
     */
    public function recommendProduct(array $goodIds, int $merId, int $recommendNum, int $productId)
    {
        $where = $this->productShow();
        $where['mer_id'] = $merId;
        $filed = 'product_id, image, store_name, price, create_time, is_gift_bag, is_good, is_show, mer_id, sales, status,activity_label_ids,delivery_free,give_coupon_ids,delivery_way,type,product_type';
        $query = $this->getModel()::getDB()->field($filed)->where($where);
        if (!empty($goodIds)) {
            $query->whereIn($this->getPk(), $goodIds);
            $diff = bcsub($recommendNum, count($goodIds));
        }
        $list = $query->limit($recommendNum)->where('product_id <> ' . $productId)->order('is_good DESC')->order('product_id DESC')->select()->toArray();
        if (isset($diff) && $diff > 0) {
            $diffList = $query->removeWhereField($this->getPk())->whereNotIn($this->getPk(), $goodIds)->limit($diff)->select()->toArray();
        }

        return array_merge($list, $diffList ?? []);
    }
}
