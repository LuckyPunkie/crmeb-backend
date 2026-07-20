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

namespace app\common\model\store;

use app\common\model\BaseModel;
use app\common\model\store\product\Spu;
use app\common\model\system\form\Form;
use app\common\model\system\Relevance;
use app\common\model\user\User;
use app\common\repositories\store\StoreActivityRepository;
use app\common\repositories\system\RelevanceRepository;

class StoreActivityRelated extends BaseModel
{

    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'store_activity_related';
    }

    public function getValueAttr($value)
    {
        return json_decode($value,true);
    }

    public function getKeysAttr($value)
    {
        return json_decode($value,true);
    }

    public function getFormValueAttr($value)
    {
        return json_decode($value,true);
    }


    public function activity()
    {
        return $this->hasOne(StoreActivity::class, 'activity_id','activity_id');
    }

    public function systemForm()
    {
        return $this->hasOne(Form::class, 'form_id','link_id');
    }

    public function user()
    {
        return $this->hasOne(User::class,'uid','uid');
    }

    public function searchUidAttr($query,$value)
    {
       $query->where('uid',$value);
    }

    public function searchActivityIdAttr($query,$value)
    {
        $query->where('activity_id',$value);
    }

    public function searchIdAttr($query,$value)
    {
        $query->where('id',$value);
    }

    public function searchActivityTypeAttr($query,$value)
    {
        $query->where('activity_type',$value);
    }

    public function searchLinkIdAttr($query,$value)
    {
        $query->where('link_id',$value);
    }

    public function searchCreateTimeAttr($query, $value)
    {
        if(!empty($value)){
            return getModelTime($query, $value);
        }
    }

    public function searchKeywordAttr($query, $value)
    {
        if ($value !== '') {
            $query->whereLike('uid|nickname|phone', "%{$value}%");
        }
    }
}
