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
namespace app\common\dao\store\broadcast;

use app\common\dao\BaseDao;
use app\common\model\store\broadcast\BroadcastAssistant;
use think\exception\ValidateException;

/**
 * 直播间主播
 * @author wuhaotian
 * @email 442384644@qq.com
 * @date 2024/7/13
 */
class BroadcastAssistantDao extends BaseDao
{

    protected function getModel(): string
    {
        return BroadcastAssistant::class;
    }

    /**
     * 查询是否存在
     * @param int $id
     * @param int $merId
     * @return bool
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/13
     */
    public function merExists(int $id, int $merId)
    {
        return $this->existsWhere([$this->getPk() => $id, 'is_del' => 0, 'mer_id' => $merId]);
    }

    /**
     * 查询所有的主播id
     * @param string|null $ids
     * @param int $merId
     * @return int[]|mixed
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/13
     */
    public function intersection(?string $ids, int $merId)
    {
        if (!$ids)  return [0];
        return $this->getModel()::getDb()->whereIn('assistant_id',$ids)->where('mer_id', $merId)->column('assistant_id');
    }

    /**
     * 查询主播是否存在
     * @param $ids
     * @param $merId
     * @return bool
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/13
     */
    public function existsAll($ids, $merId)
    {
        foreach ($ids as $id) {
            $has = $this->getModel()::getDb()->where('assistant_id',$id)->where('mer_id',$merId)->count();
            if (!$has) throw new ValidateException('ID:'.$id.' 不存在');
        }

        return true;
    }
}
