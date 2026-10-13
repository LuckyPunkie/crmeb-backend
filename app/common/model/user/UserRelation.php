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

namespace app\common\model\user;


use app\common\model\BaseModel;
use app\common\model\store\product\Product;
use app\common\model\store\product\Spu;
use app\common\model\system\merchant\Merchant;

class UserRelation extends BaseModel
{

    public static function tablePk(): ?string
    {
        return 'uid';
    }

    public static function tableName(): string
    {
        return 'user_relation';
    }

    public function merchant()
    {
        return $this->hasOne(Merchant::class,'mer_id','type_id');
    }

    public function product()
    {
        return $this->hasOne(Product::class,'product_id','type_id');
    }

    public function spu()
    {
        return $this->hasOne(Spu::class,'spu_id','type_id');
    }

    public function searchTypeAttr($query, $value)
    {
        $query->where('type',$value);
    }

    public function searchUidAttr($query, $value)
    {
        $query->where('uid',$value);
    }

    public function searchDateAttr($query, $value)
    {
        getModelTime($query, $value, 'create_time');
    }

    public function searchMomDateAttr($query, $value)
    {
        momTime($query, $value, 'create_time');
    }
}
