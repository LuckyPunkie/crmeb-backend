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


namespace app\common\repositories\store;


use app\common\repositories\system\config\ConfigValueRepository;

class MerchantTakeRepository
{
    /**
     * 根据商家ID获取商家提现配置信息
     *
     * 本函数通过调用merchantConfig函数，为指定的商家ID获取一系列提现配置信息。
     * 这些配置信息包括商家的提现状态、名称、电话、地址、地理位置、提现周期和时间等。
     * 使用这些信息可以完善商家的提现设置，确保商家能够顺利进行提现操作。
     *
     * @param string $merId 商家ID，用于唯一标识商家。
     * @return array 返回包含商家提现配置信息的数组。
     */
    public function get($merId)
    {
        // 调用merchantConfig函数，传入商家ID和需要获取的配置项列表
        return merchantConfig($merId, [
            'mer_take_status', 'mer_take_name', 'mer_take_phone', 'mer_take_address', 'mer_take_location', 'mer_take_day', 'mer_take_time'
        ]);
    }

    /**
     * 设置商家配置信息
     *
     * 本函数用于存储商家的配置数据。通过依赖注入的方式，获取ConfigValueRepository实例，
     * 并调用其setFormData方法来存储数据。此函数不直接处理数据存储逻辑，而是委托给ConfigValueRepository，
     * 体现了依赖注入和职责分离的原则。
     *
     * @param string $merId 商家ID，用于标识数据所属的商家
     * @param array $data 商家的配置数据，以键值对形式提供
     */
    public function set($merId, array $data)
    {
        // 通过应用容器获取ConfigValueRepository实例
        $configValueRepository = app()->make(ConfigValueRepository::class);
        // 调用ConfigValueRepository的方法来存储商家的配置数据
        $configValueRepository->setFormData($data, $merId);
    }

    /**
     * 检查商家是否启用了特定功能
     *
     * 本函数通过查询商家配置，确定商家是否启用了某种特定功能。
     * 它通过比较配置项“mer_take_status”的值是否为“1”来判断。
     * 这种检查对于确保商家在进行特定操作前已正确配置其账户非常重要。
     *
     * @param string $merId 商家ID，用于查询商家的配置信息。
     * @return bool 如果商家启用了特定功能，则返回true；否则返回false。
     */
    public function has($merId)
    {
        // 查询商家配置，检查特定功能是否启用
        return merchantConfig($merId, 'mer_take_status') == '1';
    }
}
