<?php
namespace app\validate\merchant\nearby;

use think\Validate;

class NearbyRecommendValidate extends Validate
{
    protected $failException = true;

    protected $rule = [
        'name|菜品名称' => 'require|max:50',
        'image|菜品图片' => 'require|url',
        'mention_count|提及数' => 'integer|>=:0',
        'like_count|点赞数' => 'integer|>=:0',
        'tag|标签' => 'max:50',
        'sort|排序' => 'integer|>=:0',
        'is_show|是否展示' => 'integer|in:0,1',
    ];
}