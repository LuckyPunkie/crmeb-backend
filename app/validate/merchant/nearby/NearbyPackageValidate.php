<?php
namespace app\validate\merchant\nearby;

use think\Validate;

class NearbyPackageValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'name|套餐名称' => 'require|max:100',
        'image|套餐图片' => 'require|url',
        'price|套餐价' => 'require|float|>=:0',
        'original_price|原价' => 'require|float|>=:0',
        'discount|折扣' => 'float|>=:0|<=:10',
        'tags|标签' => 'max:500',
        'content|套餐内容' => 'max:2000',
        'sort|排序' => 'integer|>=:0',
        'is_show|是否展示' => 'integer|in:0,1',
    ];
}