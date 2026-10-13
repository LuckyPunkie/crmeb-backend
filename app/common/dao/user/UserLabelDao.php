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


namespace app\common\dao\user;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\user\UserLabel;
use think\db\BaseQuery;

/**
 * Class UserLabelDao
 * @package app\common\dao\user
 * @author xaboy
 * @day 2020-05-07
 */
class UserLabelDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return UserLabel::class;
    }

    /**
     * 根据条件搜索用户标签。
     *
     * 该方法提供了搜索用户标签的灵活性，根据传入的条件决定搜索的范围和条件。
     * 主要处理两种情况：1) 是否搜索所有标签；2) 是否指定商家ID进行搜索。
     *
     * @param array $where 搜索条件数组，可能包含 'all' 和 'mer_id' 键。
     *                     'all' 表示是否搜索所有标签，'mer_id' 表示商家ID。
     * @return \think\db\Query 用户标签查询结果。
     */
    public function search(array $where = [])
    {
        // 获取用户标签的数据库查询对象
        $db = UserLabel::getDB();

        // 如果没有指定搜索所有标签或指定的值为空，则添加类型为0的条件
        $db->when(!isset($where['all']) || $where['all'] === '', function ($query) use ($where) {
            // 当 'all' 不设置或为空时，查询类型为0的标签
            $query->where('type', 0);
        });

        // 如果指定了商家ID且不为空，则添加商家ID的条件；否则，添加商家ID为0的条件
        $db->when(isset($where['mer_id']) && $where['mer_id'] !== '', function ($query) use ($where) {
            // 当 'mer_id' 设定且不为空时，查询指定商家ID的标签
            $query->where('mer_id', $where['mer_id']);
        }, function ($query) {
            // 当 'mer_id' 未设定或为空时，查询商家ID为0的标签
            $query->where('mer_id', 0);
        });

        // 返回处理后的查询对象
        return $db;
    }

    /**
     * 获取所有标签选项
     *
     * 本函数用于查询并返回所有类型为0的用户标签的名称和ID。这些标签属于特定的商家（mer_id）。
     * 主要用于在前端展示或供其他功能使用这些标签选项。
     *
     * @param int $merId 商家ID，默认为0，表示查询所有商家的标签。
     * @return array 返回一个数组，数组的键为标签ID，值为标签名称。
     */
    public function allOptions($merId = 0)
    {
        // 使用UserLabel模型的getDB方法获取数据库操作对象，并设置查询条件为商家ID和类型为0，然后返回标签名称和ID的列。
        return UserLabel::getDB()->where('mer_id', $merId)->where('type', 0)->column('label_name', 'label_id');
    }

    /**
     * 根据标签ID数组和商家ID获取标签名称列表
     *
     * 本函数通过查询用户标签数据库表，筛选出指定商家ID和标签ID数组内的标签名称。
     * 主要用于在系统中根据用户或商品等的标签ID，获取对应的标签名称，以便于展示或进一步处理。
     *
     * @param array $ids 标签ID数组，指定需要查询的标签ID列表
     * @param int $merId 商家ID，用于指定查询哪个商家的标签，默认为0，表示查询所有商家的标签
     * @return array 返回一个包含标签名称的数组，每个标签名称对应输入的标签ID
     */
    public function labels(array $ids, $merId = 0)
    {
        // 通过UserLabel类的静态方法getDB获取数据库操作对象，并构造查询条件，筛选出指定商家ID和标签ID内的标签名称
        return UserLabel::getDB()->where('mer_id', $merId)->whereIn('label_id', $ids)->column('label_name');
    }

    /**
     * 检查特定标签ID是否存在
     *
     * 此方法用于确定数据库中是否存在具有指定标签ID、类型为0且可选的商户ID的记录。
     * 主要用于标签管理中对标签存在性的验证，以避免重复创建或操作已存在的标签。
     *
     * @param int $id 标签的ID，用于唯一标识一个标签。
     * @param int $mer_id 商户的ID，可选参数，用于指定特定商户下的标签。默认为0，表示系统标签。
     * @return bool 返回布尔值，表示标签是否存在。存在返回true，不存在返回false。
     */
    public function exists(int $id, $mer_id = 0)
    {
        // 使用existsWhere方法查询是否存在满足条件的标签记录。
        return $this->existsWhere(['label_id' => $id, 'type' => 0, 'mer_id' => $mer_id]);
    }

    /**
     * 检查指定标签名称是否存在
     *
     * 该方法用于查询指定条件下的标签名称是否存在，主要考虑了商家ID、标签类型和排除特定ID的情况。
     *
     * @param string $name 标签名称
     * @param int $mer_id 商家ID，用于限定查询范围
     * @param int $type 标签类型，用于进一步筛选标签
     * @param int|null $except 排除的标签ID，用于确保查询结果不包括特定标签
     * @return bool 如果存在符合条件的标签名称则返回true，否则返回false
     */
    public function existsName($name, $mer_id = 0, $type = 0, $except = null)
    {
        // 根据参数条件查询标签数据库记录，包括标签名称、商家ID和标签类型
        return UserLabel::where('label_name', $name)->where('mer_id', $mer_id)->where('type', $type)
                // 当$except不为空时，添加额外的条件以排除特定ID的标签
                ->when($except, function ($query, $except) {
                    $query->where($this->getPk(), '<>', $except);
                })->count() > 0;
    }


    /**
     * 获取用户标签交集
     * 该方法用于根据给定的标签ID数组，商家ID和可选的标签类型，从数据库中查询并返回符合条件的标签ID集合。
     *
     * @param array $ids 标签ID数组，表示需要查询的标签范围。
     * @param int $merId 商家ID，表示查询指定商家的标签。
     * @param string|null $type 标签类型，可选参数，用于进一步筛选标签类型。
     * @return array 返回符合条件的标签ID集合。
     */
    public function intersection(array $ids, $merId, $type)
    {
        // 使用UserLabel的数据库连接，并构造查询条件
        return UserLabel::getDB()->whereIn('label_id', $ids)->where('mer_id', $merId)->when(!is_null($type), function ($query) use ($type) {
            // 如果指定了标签类型，则添加类型查询条件
            $query->where('type', $type);
        })->column('label_id');
    }

}
