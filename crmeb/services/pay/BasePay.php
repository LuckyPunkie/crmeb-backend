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

// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2023 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------
namespace crmeb\services\pay;

use crmeb\basic\BaseStorage;


/**
 * Class BasePay
 * @package crmeb\services\pay
 */
abstract class BasePay extends BaseStorage
{


    /**
     * @var string
     */
    protected $payType;

    protected function initialize($config){}

        /**
     * 设置支付类型
     * @param string $type
     * @return $this
     */
    public function setPayType(string $type)
    {
        $this->payType = $type;
        return $this;
    }

    /**
     * 将业务支付方式转换为微信支付配置端。
     *
     * 兼容历史退款调用传入的数字 pay_type，其中 2 表示小程序。
     */
    protected function resolvePaymentAccessEnd($channel): string
    {
        $channel = (string)$channel;
        if (in_array($channel, ['2', 'routine', 'mini'], true)) {
            return 'mini';
        }
        if (in_array($channel, ['weixinApp', 'app'], true)) {
            return 'app';
        }
        return 'web';
    }

}
