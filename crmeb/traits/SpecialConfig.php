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


namespace crmeb\traits;

/**
 *
 * Class BaseError
 * @package crmeb\basic
 */
trait SpecialConfig
{
    public  $specialConfigArray = [
        [
            'is_mer' => 0,
            'name' => '商户最低提现金额',
            'route' => '/admin/accounts/settings'
        ],
        [
            'is_mer' => 0,
            'name' => '商户每笔最小提现额度',
            'route' => '/admin/accounts/settings'
        ],
        [
            'is_mer' => 0,
            'name' => '商户每笔最高提现额度',
            'route' => '/admin/accounts/settings'
        ],
        [
            'is_mer' => 0,
            'name' => '商户余额冻结期',
            'route' => '/admin/accounts/settings'
        ],
        [
            'is_mer' => 0,
            'name' => '开启自动分账',
            'route' => '/admin/accounts/settings'
        ],
        [
            'is_mer' => 0,
            'name' => '开启子商户入驻',
            'route' => '/admin/accounts/settings'
        ],
        [
            'is_mer' => 0,
            'name' => '虚拟成团启用',
            'route' => '/admin/marketing/combination/combination_set'
        ],
        [
            'is_mer' => 0,
            'name' => '真实成团最小比例',
            'route' => '/admin/marketing/combination/combination_set'
        ],
        [
            'is_mer' => 0,
            'name' => '积分',
            'route' => '/admin/marketing/integral/config'
        ],
        [
            'is_mer' => 0,
            'name' => '积分抵用金额',
            'route' => '/admin/marketing/integral/config'
        ],
        [
            'is_mer' => 0,
            'name' => '下单赠送积分比例',
            'route' => '/admin/marketing/integral/config'
        ],
        [
            'is_mer' => 0,
            'name' => '下单赠送积分冻结期',
            'route' => '/admin/marketing/integral/config'
        ],
        [
            'is_mer' => 0,
            'name' => '积分清除时间设置',
            'route' => '/admin/marketing/integral/config'
        ],
        [
            'is_mer' => 0,
            'name' => '邀请好友赠送积分',
            'route' => '/admin/marketing/integral/config'
        ],
        [
            'is_mer' => 0,
            'name' => '积分说明',
            'route' => '/admin/marketing/integral/config'
        ],
        [
            'is_mer' => 1,
            'name' => '积分开启',
            'route' => '/merchant/marketing/integral/config'
        ]
    ];

    public $special = [
        '0' => [
            "商户最低提现金额" => "/accounts/settings",
            "商户每笔最小提现额度" => "/accounts/settings",
            "商户每笔最高提现额度" => "/accounts/settings",
            "商户余额冻结期" => "/accounts/settings",
            "开启自动分账" => "/accounts/settings",
            "开启子商户入驻" => "/accounts/settings",
            "虚拟成团启用" => "/marketing/combination/combination_set",
            "真实成团最小比例" => "/marketing/combination/combination_set",
            "积分" => "/marketing/integral/config",
            "积分抵用金额" => "/marketing/integral/config",
            "下单赠送积分比例" => "/marketing/integral/config",
            "下单赠送积分冻结期" => "/marketing/integral/config",
            "积分清除时间设置" => "/marketing/integral/config",
            "邀请好友赠送积分" => "/marketing/integral/config",
            "积分说明" => "/marketing/integral/config",
        ],
        '1' => [
            "积分开启" => "/marketing/integral/config",
        ],
    ];

    /**
     * 废弃配置项
     * @var array|string[]
     */
    public  $unsetConfigArray = [
        '/systemForm/Basics/message',
    ];

    public function getSpecialConfig()
    {
        return array_combine(array_column($this->specialConfigArray, 'name'), array_column($this->specialConfigArray, 'route'));
    }


    public function valSpecial($isMer, $title)
    {
        if (in_array($title, array_keys($this->special[$isMer]))) {
            return $this->special[$isMer][$title];
        }
        return false;
    }
}
