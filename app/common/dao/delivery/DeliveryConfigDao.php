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
namespace app\common\dao\delivery;

use app\common\dao\BaseDao;
use think\exception\ValidateException;
use app\common\model\delivery\DeliveryConfig;

class DeliveryConfigDao extends BaseDao
{

    protected function getModel(): string
    {
        return DeliveryConfig::class;
    }

    public function getDeliveryConfig(int $merId)
    {
        return $this->getModel()::getModel()->with(['merchant'])->where('mer_id', $merId)->find() ?: [];
    }

    public function saveConfig(int $id, array $params)
    {
        if($id) {
            $info = $this->get($id);
            if (!$info) {
                throw new ValidateException('配送设置记录不存在!');
            }

            return $this->update($id, $params);
        }

        return $this->create($params);
    }
}
