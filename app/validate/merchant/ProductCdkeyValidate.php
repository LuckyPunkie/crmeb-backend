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


use app\common\repositories\store\product\ProductCdkeyRepository;
use think\Validate;

class ProductCdkeyValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'csList|卡密信息' => 'require|array|isUnique'
    ];

    public function isUnique($value,$rule,$data)
    {
        foreach ($value as $datum) {
            if (!$datum) return '卡密信息不能为空';
        }
        $keys = array_column($value,'key');
        if (count($keys) != count(array_unique($keys))) return '卡密信息不能重复';
        $has = app()->make(ProductCdkeyRepository::class)->checkKey($data['library_id'],$keys);
        if ($has) return '卡密[ '.$has['key'].' ]已存在';
        return true;
    }

}
