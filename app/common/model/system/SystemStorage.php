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

namespace app\common\model\system;

use app\common\model\BaseModel;

class SystemStorage extends BaseModel
{

    public static function tablePk(): string
    {
        return 'id';
    }

    public static function tableName(): string
    {
        return 'system_storage';
    }

    public function searchAccessKeyAttr($query, $value)
    {
        $query->where('access_key',$value);
    }
    public function searchStatusAttr($query, $value)
    {
        $query->where('status',$value);
    }
    public function searchIsDelAttr($query, $value)
    {
        $query->where('is_del',$value);
    }
    public function searchIdAttr($query, $value)
    {
        $query->where('id',$value);
    }
    public function searchTypeAttr($query, $value)
    {
        $query->where('type',$value);
    }
}
