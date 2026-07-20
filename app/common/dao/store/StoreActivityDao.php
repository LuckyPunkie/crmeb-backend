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


namespace app\common\dao\store;


use app\common\dao\BaseDao;
use app\common\model\BaseModel;
use app\common\model\store\StoreActivity;
use app\common\repositories\system\RelevanceRepository;
use think\exception\ValidateException;

/**
 *
 * Class StoreActivityDao
 * @package app\common\dao\system\merchant
 */
class StoreActivityDao extends BaseDao
{
    protected function getModel(): string
    {
        return StoreActivity::class;
    }

    public function search(array $where = [], array $with = [])
    {
        $where['is_del'] = 0;
        return $this->getSearch($where)->when(!empty($with), function ($query) use ($with) {
            $query->with($with);
        });
    }

    /**
     * 增加指定ID的数据的total字段值。
     * 此函数主要用于对数据库中的某些记录进行总量控制和更新，确保数据的正确性和一致性。
     *
     * @param int $id 需要更新的记录的ID。
     * @param int $inc 需要增加的值，默认为1，表示增加1。
     * @throws ValidateException 如果找不到相关数据或更新后的总量超过了预设的限制，则抛出异常。
     */
    public function incTotal($id, $inc = 1)
    {
        // 根据ID查询数据库记录。
        $res = $this->getModel()::getDb()->where($this->getPk(), $id)->find();

        // 如果查询结果为空，则抛出异常，表示数据异常。
        if (empty($res)) {
            throw new ValidateException('活动数据异常');
        }

        // 计算更新后的total值。
        $total = $res['total'] + $inc;

        // 如果当前记录的count值存在且小于更新后的total值，则抛出异常，表示超出总数限制。
        if ($res['count'] && $res['count'] < $total) {
            throw new ValidateException('超出总数限制');
        }

        // 更新记录的total值，并保存到数据库。
        $res->total = $total;
        $res->save();
    }
}
