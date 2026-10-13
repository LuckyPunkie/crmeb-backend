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


namespace app\common\dao\community;


use app\common\dao\BaseDao;
use app\common\model\community\CommunityTopic;

class CommunityTopicDao extends BaseDao
{

    protected function getModel(): string
    {
        return CommunityTopic::class;
    }

    /**
     * 数量增加
     * @param int $id
     * @param string $filed
     * @param int $inc
     * @return mixed
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/13
     */
    public function countInc(int $id, string $filed, int $inc = 1)
    {
        return $this->getModel()::getDb()->where($this->getPk(), $id)->inc($filed, $inc)->update();
    }

    /**
     * 数量减少
     * @param int $id
     * @param string $filed
     * @param int $dec
     * @return mixed|void
     * @author wuhaotian
     * @email 442384644@qq.com
     * @date 2024/7/13
     */
    public function countDec(int $id, string $filed, int $dec = 1)
    {
        try{
            return $this->getModel()::getDb()->where($this->getPk(), $id)->dec($filed, $dec)->update();
        }catch (\Exception $exception) {

        }
    }
}
