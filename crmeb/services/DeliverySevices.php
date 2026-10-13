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

namespace crmeb\services;

use crmeb\interfaces\DeliveryInterface;
use crmeb\services\delivery\Delivery;
use crmeb\services\delivery\storage\Dada;
use crmeb\services\delivery\store\Uupt;

/**
 * Class BaseExpress
 * @package crmeb\basic
 */
class DeliverySevices
{
    const DELIVERY_TYPE_UU   = 2;
    const DELIVERY_TYPE_DADA = 1;

    public static function create($gateway = self::DELIVERY_TYPE_DADA, $merId = 0)
    {
        $gateway = (int)$gateway;
        switch ($gateway) {
            case 1:
                $config = [
                    'app_key'    => merchantConfig($merId, 'dada_app_key'),
                    'app_secret' => merchantConfig($merId, 'dada_app_sercret'),
                    'source_id'  => merchantConfig($merId, 'dada_source_id'),
                ];
                break;
            case 2:
                $config = [
                    'app_key' => merchantConfig($merId, 'uupt_appkey'),
                    'app_id'  => merchantConfig($merId, 'uupt_app_id'),
                    'open_id' => merchantConfig($merId, 'uupt_open_id'),
                ];
                break;
        }
        return new Delivery($gateway, $config);
    }
}
