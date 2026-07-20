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
use app\common\model\system\merchant\MerchantType;

class MerchantTypeDao extends BaseDao
{

    protected function getModel(): string
    {
        return MerchantType::class;
    }

    /**
     * 根据条件搜索商家类型
     *
     * 本函数用于查询商家类型的数据库记录。它接受一个条件数组作为参数，并根据这些条件来过滤查询结果。
     * 主要用于在后台管理界面中，根据用户输入的条件进行商家类型的搜索。
     *
     * @param array $where 查询条件数组。该数组可以包含任何用于筛选商家类型记录的条件。
     *                     其中，如果指定了 'mer_type_id' 字段，则会根据该字段的值进行精确查询。
     * @return \think\Collection 返回查询结果。如果没有指定查询条件，则返回所有商家类型的记录。
     */
    public function search(array $where = [])
    {
        // 调用 MerchantType 类的静态方法 getDB 来获取数据库查询对象
        return MerchantType::getDB()
            // 使用 when 方法来条件性地添加 where 条件
            ->when(isset($where['mer_type_id']) && $where['mer_type_id'] !== '', function ($query) use ($where) {
                // 如果查询条件中包含了 'mer_type_id'，则添加该条件到查询中
                $query->where('mer_type_id', $where['mer_type_id']);
            });
    }

    /**
     * 获取商家类型的选项列表
     *
     * 本函数用于查询并返回所有商家类型的名称和ID，以数组形式提供，
     * 适用于需要展示商家类型选择列表的场景，如表单中使用。
     *
     * @return array 返回一个包含商家类型选项的数组，每个选项包含'value'和'label'两个元素，
     *               'value'代表商家类型的ID，'label'代表商家类型的名称。
     */
    public function getOptions()
    {
        // 从数据库查询所有商家类型的名称和ID
        $data = MerchantType::getDB()->column('type_name', 'mer_type_id');

        $options = [];
        // 遍历查询结果，构建选项数组
        foreach ($data as $value => $label) {
            $options[] = compact('value', 'label');
        }

        return $options;
    }

    /**
     * 获取带有保证金规则的 商户类型选项
     *
     * 本函数用于查询并构造那些需要缴纳保证金的商户类型的选项列表。
     * 列表中的每个选项包含一个规则，规则描述了保证金的具体金额。
     *
     * @return array 返回一个数组，其中每个元素代表一个需要缴纳保证金的商户类型，
     *               包含该商户类型的值和保证金规则。
     */
    public function getMargin()
    {
        // 从数据库查询所有商户类型及其保证金信息
        $data = MerchantType::getDB()->column('margin,is_margin', 'mer_type_id');

        // 初始化选项数组
        $options = [];
        foreach ($data as $value => $item) {
            // 判断当前商户类型是否需要缴纳保证金
            if ($item['is_margin'] == 1) {
                // 如果需要，构造该商户类型的选项规则，并添加到选项数组中
                $options[] = [
                    'value' => $value,
                    'rule' => [
                        [
                            'type' => 'div',
                            'children' => [
                                '保证金：' . $item['margin']. ' 元'
                            ],
                            'style' => [
                                'paddingTop' => '100px',
                            ],
                        ]
                    ]
                ];
            }
        }
        return $options;
    }



}
