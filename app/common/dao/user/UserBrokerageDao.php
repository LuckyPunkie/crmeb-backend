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
use app\common\model\user\UserBrokerage;

class UserBrokerageDao extends BaseDao
{

    protected function getModel(): string
    {
        return UserBrokerage::class;
    }

    public function search(array $where)
    {
        return UserBrokerage::getDB()->when(isset($where['brokerage_name']) && $where['brokerage_name'] !== '', function ($query) use ($where) {
            $query->whereLike('brokerage_name', "%{$where['brokerage_name']}%");
        })->when(isset($where['brokerage_level']) && $where['brokerage_level'] !== '', function ($query) use ($where) {
            $query->where('brokerage_level', $where['brokerage_level']);
        })->when(isset($where['next_level']) && $where['next_level'] !== '', function ($query) use ($where) {
            $query->where('brokerage_level', '>', $where['next_level']);
        })->when(isset($where['type']) && $where['type'] !== '', function ($query) use ($where) {
            $query->where('type', $where['type']);
        });
    }

    /**
     * 检查字段是否存在
     *
     * 本函数用于查询指定字段在数据库中是否已存在特定的值。可以通过指定排除的ID，来避免查询到特定的记录。
     * 主要用于场景如：验证数据的唯一性，或在插入数据前做重复性检查。
     *
     * @param string $field 要查询的字段名
     * @param mixed $value 字段对应的值
     * @param int|null $except 排除的记录ID，如果为null，则不进行排除
     * @param int $type 查询的数据类型，用于进一步筛选数据
     * @return bool 如果字段存在则返回true，否则返回false
     */
    public function fieldExists($field, $value, ?int $except = null, int $type = 0): bool
    {
        // 构建查询条件，查询指定类型且字段值符合的记录
        $query = ($this->getModel())::getDB()->where('type',$type)->where($field, $value);

        // 如果指定了排除的ID，则添加到查询条件中，排除该记录
        if (!is_null($except)) $query->where($this->getPk(), '<>', $except);

        // 返回查询结果是否存在，存在则返回true，否则返回false
        return $query->count() > 0;
    }
}
