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
namespace app\validate\merchant;

use think\Validate;
use app\common\repositories\store\CityAreaRepository;

class UserValidate extends Validate
{
    protected $rule = [
        'phone|手机号' => 'require|mobile',
        'nickname|用户昵称' => 'require|max:16',
        'uid|用户id' => 'require',
        'real_name|姓名' => 'require|max:32',
        'province|省' => 'require',
        'province_id|省id' => 'require|number',
        'city|市' => 'require',
        'city_id|市id' => 'require|number',
        'district|区' => 'require',
        'district_id|区id' => 'require|number',
        'street|街道' => 'require',
        'street_id|街道id' => 'require|number',
        'detail|详细地址' => 'require|max:256',
        'cart_ids|购物车ids' => 'array',
    ];

    protected $message = [
        'phone.require' => '手机号不能为空',
        'phone.mobile' => '手机号格式错误',
        'nickname.require' => '用户昵称不能为空',
        'nickname.max' => '用户昵称长度不能超过16个字符',
        'uid.require' => '用户id不能为空',
        'real_name.require' => '姓名不能为空',
        'province.require' => '省不能为空',
        'province_id.require' => '省id不能为空',
        'city.require' => '市不能为空',
        'city_id.require' => '市id不能为空',
        'district.require' => '区不能为空',
        'district_id.require' => '区id不能为空',
        'street.require' => '街道不能为空',
        'street_id.require' => '街道id不能为空',
        'detail.require' => '详细地址不能为空',
    ];

    protected $scene = [
        'user'  =>  ['phone'],
        'userAddress'  =>  ['uid', 'real_name', 'phone', 'province', 'province_id', 'city', 'city_id', 'district', 'district_id', 'street', 'street_id', 'detail'],
        'coupon'  =>  ['uid', 'cart_ids'],
    ];
    /**
     * 验证商户注册信息是否合法
     *
     * @param array $data
     * @return boolean
     */
    public function userCreateCheck(array $data): bool
    {
        if (!$this->scene('user')->check($data)) {
            return false;
        }

        return true;
    }
    /**
     * 验证用户地址信息是否合法
     *
     * @param array $data
     * @return boolean
     */
    public function userAddressCreateCheck(array $data): bool
    {
        if (!$this->scene('userAddress')->check($data)) {
            return false;
        }

        if($data['uid'] == 0 && empty($data['tourist_unique_key'])) {
            $this->error = '请传入游客唯一标识';
            return false;
        }
        // 验证最后行政区是否存在
        $make = app()->make(CityAreaRepository::class);
        if (!$make->existsWhere(['id' => $data['street_id'], 'snum' => 0])) {
            $this->error = '请选择正确的所在地区';
            return false;
        }

        return true;
    }
    public function couponCheck(array $data): bool
    {
        if (!$this->scene('coupon')->check($data)) {
            return false;
        }

        return true;
    }
}
