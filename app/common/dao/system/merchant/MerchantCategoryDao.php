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


namespace app\common\dao\system\merchant;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\system\merchant\MerchantCategory;
use think\db\BaseQuery;
use think\facade\Db;

/**
 * Class MerchantCategoryDao
 * @package app\common\dao\system\merchant
 * @author xaboy
 * @day 2020-05-06
 */
class MerchantCategoryDao extends BaseDao
{

    /**
     * @return BaseModel
     * @author xaboy
     * @day 2020-03-30
     */
    protected function getModel(): string
    {
        return MerchantCategory::class;
    }


    /**
     * @param array $where
     * @return BaseQuery
     * @author xaboy
     * @day 2020-05-06
     */
    public function search(array $where = [])
    {
        return MerchantCategory::getDB();
    }

    /**
     * 获取所有商家分类选项
     *
     * 本函数旨在获取所有商家分类的名称和ID，以数组形式返回这些信息，
     * 以便在前端界面中作为下拉选项或其他形式的列表使用。
     *
     * @return array 返回一个包含所有商家分类ID和名称的数组，数组的每个元素是一个关联数组，
     * 关联数组中包含 'value' 和 'label' 两个键，分别对应商家分类的ID和名称。
     */
    public function allOptions()
    {
        // 从数据库中查询所有商家分类的名称和ID
        $data = MerchantCategory::getDB()->column('category_name', 'merchant_category_id');

        // 初始化用于存储选项的数组
        $options = [];
        // 遍历查询结果，构建每个分类的选项数组
        foreach ($data as $value => $label) {
            $options[] = compact('value', 'label');
        }
        // 返回构建好的选项数组
        return $options;
    }


    /**
     * 根据日期查询商家类别下的订单金额分组情况
     *
     * 本函数用于获取指定日期内，每个商家类别的订单总支付金额，并按支付金额降序排列。
     * 主要用于展示或统计特定日期内各商家类别的订单收入情况。
     *
     * @param string $date 查询的日期，格式为YYYY-MM-DD。如果未指定日期，则查询所有记录。
     * @param int $limit 返回结果的限制数量，默认为4，用于控制返回的商家类别数量。
     * @return array 返回一个包含商家类别名称和对应订单总支付金额的数组，数组项按支付金额降序排列。
     */
    public function dateMerchantPriceGroup($date, $limit = 4)
    {
        // 从MerchantCategory模型的数据库连接中获取查询对象
        return MerchantCategory::getDB()->alias('A')->leftJoin('Merchant B', 'A.merchant_category_id = B.category_id')
            ->leftJoin('StoreOrder C', 'C.mer_id = B.mer_id')->field(Db::raw('sum(C.pay_price) as pay_price,A.category_name'))
            // 根据$date参数条件，动态添加查询时间范围的条件
            ->when($date, function ($query, $date) {
                getModelTime($query, $date, 'C.pay_time');
            })
            // 按商家类别ID分组，筛选支付金额大于0的记录，按支付金额降序排列
            ->group('A.merchant_category_id')->where('pay_price', '>', 0)->order('pay_price DESC')->limit($limit)->select();
    }

    /**
     * 根据分类ID数组获取商戶分类名称列表
     *
     * 本函数通过查询数据库，根据提供的商戶分类ID数组，返回对应的分类名称列表。
     * 这种方法的使用场景可能是需要在前端展示商戶分类名称，而只有分类ID的情况下，
     * 通过本函数可以批量获取对应的分类名称，提高查询效率和代码的可读性。
     *
     * @param array $ids 商戶分类ID数组
     * @return array 分类名称列表
     */
    public function names(array $ids)
    {
        // 使用whereIn查询满足条件的商戶分类名称，并通过column方法只返回category_name列
        return MerchantCategory::getDB()->whereIn('merchant_category_id', $ids)->column('category_name');
    }

}
