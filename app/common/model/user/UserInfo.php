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

class UserInfo extends BaseModel
{

    public static function tablePk(): string
    {
        return 'id';
    }


    public static function tableName(): string
    {
        return 'user_info';
    }

    public function searchTypeAttr($query, $value)
    {
        $query->where('type', $value);
    }

    public function searchFieldAttr($query, $value)
    {
        $query->where('field', $value);
    }

    public function searchIsDefaultAttr($query, $value)
    {
        $query->where('is_default', $value);
    }

    public function searchIsUsedAttr($query, $value)
    {
        $query->where('is_used', $value);
    }

    public function searchIsRequireAttr($query, $value)
    {
        $query->where('is_require', $value);
    }

    public function searchIsShowAttr($query, $value)
    {
        $query->where('is_show', $value);
    }

    public function searchDateAttr($query, $value)
    {
        return getModelTime($query, $value);
    }

    public function getContentAttr($value, $data)
    {
//        if ($data['type'] == 'radio' && !empty($value)) {
//            $content = [];
//            $content_arr = explode(',', $value);
//            if (is_array($content_arr)) {
//                foreach ($content_arr as $item) {
//                    $item_arr = explode(':', $item);
//                    $content[] = ['label' => $item_arr[1] ?? '', 'value' => $item_arr[0] ?? ''];
//                }
//            } else {
//                $content = [$content_arr];
//            }
//            return $content;
//        }
//        return $value;
        if (!empty($value)) {
            return json_decode($value);
        }
    }
}
