<?php

namespace app\controller\api\taoke;

use app\common\repositories\taoke\ServiceTabConfigRepository;

/**
 * 平台官方直连（/api/taoke/official/goods/*）
 * 与 Goods(legacy) 共用实现，仅锁定 driverChannel=official
 */
class OfficialGoods extends Goods
{
    protected string $goodsDriverChannel = 'official';

    /**
     * 官方联调 Tab 配置（eb_service_tab_config.channel=official）
     * legacy 的 service_tabs 仍走 Goods::serviceTabs（含「碳中和」等旧 Tab）
     */
    public function serviceTabs()
    {
        return $this->buildServiceTabsPayload(ServiceTabConfigRepository::CHANNEL_OFFICIAL);
    }
}
