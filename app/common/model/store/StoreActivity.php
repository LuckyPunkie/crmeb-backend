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
use app\common\repositories\store\StoreActivityRepository;
use app\common\repositories\system\RelevanceRepository;

class StoreActivity extends BaseModel
{

    public static function tablePk(): string
    {
        return 'activity_id';
    }

    public static function tableName(): string
    {
        return 'store_activity';
    }

    public function getFullAttr($val)
    {
        return $val ? (float)$val : $val;
    }

    public function getImagesAttr($val)
    {
        if (empty($val)) {
            return [];
        }
        return explode(',',$val);
    }

    public function spu()
    {
        return $this->hasMany(Spu::class, 'activity_id', 'activity_id');
    }

    public function socpeData()
    {
        return $this->hasMany(Relevance::class,'left_id','activity_id')->whereIn('type',[RelevanceRepository::SCOPE_TYPE_STORE,RelevanceRepository::SCOPE_TYPE_CATEGORY,RelevanceRepository::SCOPE_TYPE_PRODUCT,RelevanceRepository::SCOPE_TYPE_PRODUCT_LABEL]);
    }

    /**
     *  系统表单关联
     * @return \think\model\relation\HasOne
     * @author Qinii
     * @day 2023/11/16
     */
    public function systemForm()
    {
        return $this->hasOne(Form::class, 'form_id','link_id');
    }

    /**
     * 绑定系统表单一下显示字段
     * @return \think\model\relation\HasOne
     */
    public function systemFormKeys()
    {
        return $this->hasOne(Form::class, 'form_id','link_id')->bind(['form_name'=>'name']);
    }

    public function relevance()
    {
        return $this->hasMany(Relevance::class,'left_id','activity_id');
    }
    /**
     * 活动的进行状态
     * @author Qinii
     * @day 2023/11/22
     */
    public function getTimeStatusAttr()
    {
        $start_time = $this->start_time ? strtotime($this->start_time) : '';
        $end_time = $this->end_time ? strtotime($this->end_time) : '';
        $time = time();
        if ($this->status == 1) {
            if ($start_time && $end_time) {
                if ($start_time > $time) return 0; //未开始
                if ($end_time < $time) return -1; //已结束
            }
            return 1;
        } else {
            return $this->status;
        }
    }


    public function searchIsShowAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('is_show', $value);
        }
    }

    public function searchStatusAttr($query, $value)
    {
        $date_time = date('Y-m-d H:i:s');
        if ($value !== '') {
            switch ($value) {
                case 0:   // 查询未开始
                    $query->whereTime('start_time', '>', $date_time);
                    break;
                case 1:
                    $query->whereTime('start_time', '<', $date_time)->where('end_time', '>', $date_time);
                    break;
                case -1:
                    $query->whereTime('end_time', '<', $date_time);
                    break;
            }
        }
    }

    public function searchActivityTypeAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('activity_type', $value);
        }
    }

    public function searchIsStatusAttr($query, $value)
    {
        $query->whereIn('status',[0,1]);
    }

    public function searchActivityIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('activity_id', $value);
        }
    }


    public function searchDateAttr($query, $value)
    {
        if ($value !== '') {
            getModelTime($query, $value, 'create_time');
        }
    }

    public function searchKeywordAttr($query, $value)
    {
        if ($value !== '') {
            $query->whereLike('activity_id|activity_name', '%' . $value . '%');
        }
    }

    public function searchIsDelAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('is_del', $value);
        }
    }

    public function searchGtEndTimeAttr($query, $value)
    {
        if ($value !== '') {
            $query->whereTime('end_time','>', $value);
        }
    }

    public function searchFormIdAttr($query, $value)
    {
        if ($value !== '') {
            $query->where('link_id', $value);
        }
    }

}
