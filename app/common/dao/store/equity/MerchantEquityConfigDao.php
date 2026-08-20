<?php

namespace app\common\dao\store\equity;

use app\common\dao\BaseDao;
use app\common\model\store\equity\MerchantEquityConfig;

class MerchantEquityConfigDao extends BaseDao
{
    protected function getModel(): string
    {
        return MerchantEquityConfig::class;
    }
}
