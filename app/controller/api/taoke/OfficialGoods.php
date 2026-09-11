<?php

namespace app\controller\api\taoke;

use app\common\repositories\taoke\ServiceTabConfigRepository;

/**
 * 各平台联盟官方直连（/api/taoke/official/goods/*）
 * 与 Goods（订单侠 legacy）分离，不做订单侠回退。
 */
class OfficialGoods extends Goods
{
    protected string $goodsDriverChannel = 'official';

    /**
     * 平台直连专用 Tab（channel=official，与现网 legacy Tab 无关）
     */
    public function serviceTabs()
    {
        return $this->buildServiceTabsPayload(ServiceTabConfigRepository::CHANNEL_OFFICIAL);
    }
}
