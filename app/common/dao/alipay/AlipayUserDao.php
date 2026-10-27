<?php

namespace app\common\dao\alipay;

use app\common\dao\BaseDao;
use app\common\model\alipay\AlipayUser;

class AlipayUserDao extends BaseDao
{
    protected function getModel(): string
    {
        return AlipayUser::class;
    }

    public function getByOpenId(string $appId, string $openId)
    {
        return AlipayUser::getDB()->where('app_id', $appId)->where('open_id', $openId)->find();
    }
}
